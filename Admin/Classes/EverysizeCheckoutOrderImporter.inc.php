<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrderImporter.inc.php

   Creates a Gambio order from an everysize order: validates before
   writing anything, then in one transaction resolves the customer
   (existing account or guest), inserts orders (with the same name
   columns and orders_hash the OrderRepositoryWriter writes),
   orders_products with the everysize gross prices, the variant's
   properties row (orders_products_properties, like the checkout's
   _addProperties — that is what "rebook" and the PDFs read),
   orders_total (titles and sort orders like the ot_* modules),
   an orders_status_history entry and reduces stock following the
   Gambio stock matrix (STOCK_LIMITED + products.use_properties_
   combis_quantity, symmetric to xtc_remove_order). Never sends
   customer mail; a REVOKED order is created and immediately put
   into the cancellation status without stock reduction. The
   merchant order number is reported by the sync AFTER the commit.
   --------------------------------------------------------------*/

require_once __DIR__ . '/../../src/autoload.php';

use Everysize\Checkout\Api\Amount;
use Everysize\Checkout\Api\Client;
use Everysize\Checkout\Import\AddressSplitter;
use Everysize\Checkout\Import\ImportException;
use Everysize\Checkout\Import\PriceCalculator;
use Everysize\Checkout\OrderLinks;
use Everysize\Checkout\Settings;

class EverysizeCheckoutOrderImporter implements \Everysize\Checkout\Import\OrderImporter
{
    private EverysizeCheckoutDb $db;
    private Settings $settings;
    private OrderLinks $links;
    private ?EverysizeCheckoutOrderMailer $mailer;


    public function __construct(EverysizeCheckoutDb $db, Settings $settings, OrderLinks $links, ?EverysizeCheckoutOrderMailer $mailer = null)
    {
        $this->db = $db;
        $this->settings = $settings;
        $this->links = $links;
        $this->mailer = $mailer;
    }


    /**
     * @param array<string, mixed> $esOrder order as returned by the everysize API
     *
     * @return array{orderId: string, orderNumber: string, warnings: string[]}
     */
    public function import(array $esOrder): array
    {
        // --- Validation, before writing anything ---------------------------------
        $everysizeOrderId = $this->requireString($esOrder, 'everysizeOrderId');
        $customer = \is_array($esOrder['customer'] ?? null) ? $esOrder['customer'] : [];
        $email = $this->requireString($customer, 'email', 'customer.email');
        // Information only: the customer keeps the forwarding address above.
        $originalEmail = filter_var(trim((string) ($customer['originalEmail'] ?? '')), \FILTER_VALIDATE_EMAIL) ?: '';
        $currency = strtoupper((string) ($esOrder['currency'] ?? 'EUR'));
        if ($currency !== 'EUR') {
            throw new ImportException(sprintf('%s: order currency %s is not the shop currency EUR', $everysizeOrderId, $currency));
        }

        $billing = $this->address($esOrder['billingAddress'] ?? null, 'billingAddress');
        $shipping = \is_array($esOrder['shippingAddress'] ?? null) && $esOrder['shippingAddress'] !== []
            ? $this->address($esOrder['shippingAddress'], 'shippingAddress')
            : $billing;
        $revoked = strtoupper((string) ($esOrder['status'] ?? '')) === Client::STATUS_REVOKED;
        $warnings = [];

        $language = $this->defaultLanguage();
        $lines = $this->resolveLines($everysizeOrderId, $esOrder['lineItems'] ?? null, $shipping['countryId'], $language, $warnings);
        $prices = PriceCalculator::calculate(
            array_map(static function (array $line): array {
                return ['gross' => $line['gross'], 'quantity' => $line['quantity'], 'vat' => $line['vat']];
            }, $lines),
            Amount::parse($esOrder['shippingCosts'] ?? 0, 'shippingCosts')
        );
        if (isset($esOrder['grossPrice'])) {
            $warning = PriceCalculator::checkTotal($esOrder['grossPrice'], $prices['totalOrderSum'], $everysizeOrderId);
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        $payment = \is_array($esOrder['payment'] ?? null) ? $esOrder['payment'] : [];
        $payPalCaptureId = (string) ($payment['resourceId'] ?? $payment['transactionId'] ?? '');
        $orderDate = $this->localDate($esOrder['created'] ?? null, 'created');

        $importStatus = $this->settings->getImportStatus();
        $cancelStatus = $this->settings->getCancellationStatuses()[0] ?? '99';
        $orderStatus = $revoked ? $cancelStatus : $importStatus;

        // --- Writing -------------------------------------------------------------
        $this->db->beginTransaction();
        try {
            [$customerId] = $this->resolveCustomer($email);
            $ordersId = $this->insertOrder(
                $everysizeOrderId, $email, $customerId,
                $billing, $shipping, $orderDate, $orderStatus, $revoked, $prices, $language
            );

            foreach ($lines as $key => $line) {
                $ordersProductsId = $this->insertOrderProduct($ordersId, $line, $prices['items'][$key]);
                if (!$revoked) {
                    $warnings = array_merge($warnings, $this->reduceStock($line, $everysizeOrderId));
                }
                $this->links->addOrderItem($ordersProductsId, $ordersId, $line['sku']);
            }

            $this->insertOrderTotals($ordersId, $prices, $language);
            $this->insertStatusHistory($ordersId, $orderStatus, $everysizeOrderId);
            $this->links->addOrder($ordersId, $everysizeOrderId, $payPalCaptureId, $originalEmail);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // Only after the commit: the optional order confirmation. Sent only in the
        // full shop context (cronjob/admin) — the CLI boot cannot run the legacy
        // order/mail classes, so the confirmation stays pending there and the
        // cronjob sends it with the next run. A mail failure is a warning; the
        // order exists and is linked.
        if (!$revoked && $this->mailer !== null && $this->settings->sendOrderConfirmation()) {
            if (\PHP_SAPI === 'cli') {
                $warnings[] = sprintf('%s: order confirmation pending, will be sent with the next cronjob run', $everysizeOrderId);
            } else {
                try {
                    $this->mailer->sendConfirmation($ordersId);
                } catch (\Throwable $e) {
                    $warnings[] = sprintf('%s: order confirmation mail failed: %s', $everysizeOrderId, $e->getMessage());
                }
            }
        }

        return ['orderId' => $ordersId, 'orderNumber' => $ordersId, 'warnings' => $warnings];
    }


    // --- Validation helpers --------------------------------------------------

    private function requireString(array $data, string $key, ?string $label = null): string
    {
        $value = $data[$key] ?? null;
        if (!\is_scalar($value) || trim((string) $value) === '') {
            throw new ImportException(sprintf('everysize order has no %s', $label ?? $key));
        }

        return trim((string) $value);
    }


    /**
     * @return array{firstName: string, lastName: string, street: string, streetNr: string, addInfo: string, zip: string, city: string, countryId: string, country: string, addressFormatId: string}
     */
    private function address($address, string $field): array
    {
        if (!\is_array($address)) {
            throw new ImportException(sprintf('everysize order has no %s', $field));
        }

        [$street, $streetNr] = AddressSplitter::split($this->requireString($address, 'addressLine1', $field.'.addressLine1'));
        $countryCode = strtoupper($this->requireString($address, 'countryCode', $field.'.countryCode'));
        $country = $this->db->fetchRows(
            'SELECT countries_id, countries_name, address_format_id FROM countries
             WHERE countries_iso_code_2 = :iso AND status = 1 LIMIT 1',
            [':iso' => $countryCode]
        );
        if ($country === []) {
            throw new ImportException(sprintf('Unknown or inactive country %s in %s', $countryCode, $field));
        }

        return [
            'firstName' => trim((string) ($address['firstName'] ?? '')),
            'lastName' => $this->requireString($address, 'lastName', $field.'.lastName'),
            'street' => $street,
            'streetNr' => $streetNr,
            'addInfo' => trim((string) ($address['addressLine2'] ?? '')),
            'zip' => $this->requireString($address, 'postalCode', $field.'.postalCode'),
            'city' => $this->requireString($address, 'city', $field.'.city'),
            'countryId' => (string) $country[0]['countries_id'],
            'country' => (string) $country[0]['countries_name'],
            'addressFormatId' => (string) ($country[0]['address_format_id'] ?? '5'),
        ];
    }


    /**
     * SKU → products row: Gambio article number products_model for masters,
     * the combi's combi_model for variants. A combi match wins: everysize
     * SKUs are the purchasable variant. Case-sensitive match beats a
     * case-insensitive one, active article beats inactive; two equally good
     * candidates are an error ("not unique"). The combi id comes with the
     * row — no second lookup.
     *
     * @return array{productsId: string, combiId: ?string, taxClassId: string, name: string, model: string}
     */
    private function resolveArticle(string $sku): array
    {
        $rows = $this->db->fetchRows(
            'SELECT p.products_id AS PRODUCTS_ID, p.products_status AS PRODUCTS_STATUS,
                    p.products_tax_class_id AS TAX_CLASS_ID, p.products_model AS PRODUCTS_MODEL,
                    NULL AS COMBI_ID,
                    (BINARY p.products_model = BINARY :sku) AS EXACT_MATCH, 0 AS IS_COMBI
             FROM products p
             WHERE p.products_model = :sku
             UNION ALL
             SELECT p.products_id, p.products_status, p.products_tax_class_id, p.products_model,
                    c.products_properties_combis_id,
                    (BINARY c.combi_model = BINARY :sku) AS EXACT_MATCH, 1 AS IS_COMBI
             FROM products_properties_combis c
             JOIN products p ON p.products_id = c.products_id
             WHERE c.combi_model = :sku',
            [':sku' => $sku]
        );
        if ($rows === []) {
            throw new ImportException(sprintf('SKU %s not found in the shop', $sku));
        }
        // Exact case first, then the active article, then the variant.
        usort($rows, static function (array $a, array $b): int {
            return [(int) $b['EXACT_MATCH'], (int) $b['PRODUCTS_STATUS'], (int) $b['IS_COMBI']]
                <=> [(int) $a['EXACT_MATCH'], (int) $a['PRODUCTS_STATUS'], (int) $a['IS_COMBI']];
        });
        if (\count($rows) > 1
            && (int) $rows[0]['EXACT_MATCH'] === (int) $rows[1]['EXACT_MATCH']
            && (int) $rows[0]['PRODUCTS_STATUS'] === (int) $rows[1]['PRODUCTS_STATUS']
            && (int) $rows[0]['IS_COMBI'] === (int) $rows[1]['IS_COMBI']) {
            throw new ImportException(sprintf('SKU %s is not unique in the shop', $sku));
        }

        $combiId = $rows[0]['IS_COMBI'] === '1' ? (string) $rows[0]['COMBI_ID'] : null;

        return [
            'productsId' => (string) $rows[0]['PRODUCTS_ID'],
            'combiId' => $combiId,
            'taxClassId' => (string) $rows[0]['TAX_CLASS_ID'],
            'name' => $this->productName($rows[0]['PRODUCTS_ID']),
            'model' => (string) $rows[0]['PRODUCTS_MODEL'],
        ];
    }


    /**
     * @return list<array{sku: string, title: string, quantity: int, gross: float, vat: float, productsId: string, combiId: ?string, name: string, model: string, combiModel: string}>
     */
    private function resolveLines(string $everysizeOrderId, $lineItems, string $countryId, array $language, array &$warnings): array
    {
        if (!\is_array($lineItems) || $lineItems === []) {
            throw new ImportException('everysize order has no line items');
        }

        $lines = [];
        foreach (array_values($lineItems) as $item) {
            if (!\is_array($item)) {
                throw new ImportException('invalid line item in everysize order');
            }
            $sku = $this->requireString($item, 'sku', 'lineItems.sku');
            $article = $this->resolveArticle($sku);

            // Gambio's _updateProductsModel: with APPEND_PROPERTIES_MODEL the
            // article model is kept and the combi model appended with "-".
            $model = $article['model'];
            $combiModelValues = '';
            if ($article['combiId'] !== null) {
                $combiModel = $this->combiModel($article['combiId']);
                if ($combiModel !== '') {
                    $model = $this->shopConfig('APPEND_PROPERTIES_MODEL') === 'true' && $article['model'] !== ''
                        ? $article['model'] . '-' . $combiModel
                        : $combiModel;
                }
                $combiModelValues = $this->combiValuesName($article['combiId'], $language);
            }

            $lines[] = [
                'sku' => $sku,
                'title' => trim((string) ($item['title'] ?? '')),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'gross' => Amount::parse($item['price'] ?? null, 'lineItems.price'),
                'vat' => $this->taxRate($article['taxClassId'], $countryId, $everysizeOrderId, $sku, $warnings),
                'productsId' => $article['productsId'],
                'combiId' => $article['combiId'],
                'name' => $article['name'],
                'model' => $model,
                'combiModel' => $combiModelValues,
            ];
        }

        return $lines;
    }


    /** combi_model of a properties combi (the variant's own article number). */
    private function combiModel(string $combiId): string
    {
        return (string) ($this->db->fetchValue(
            'SELECT combi_model FROM products_properties_combis WHERE products_properties_combis_id = :id',
            [':id' => $combiId]
        ) ?? '');
    }


    /**
     * The variant's value names ("EU: 35,5"), lowercase, dash-joined — the
     * same string Gambio writes to orders_products.properties_combi_model.
     */
    private function combiValuesName(string $combiId, array $language): string
    {
        $values = $this->db->fetchRows(
            'SELECT pcv.properties_values_id, pcv.options_id
             FROM products_properties_combis_values pcv
             WHERE pcv.products_properties_combis_id = :combiId
             ORDER BY pcv.products_properties_combis_values_id',
            [':combiId' => $combiId]
        );
        $names = [];
        foreach ($values as $value) {
            $names[] = mb_strtolower((string) ($this->db->fetchValue(
                'SELECT values_name FROM properties_values_description
                 WHERE properties_values_id = :id AND language_id = :lang LIMIT 1',
                [':id' => $value['properties_values_id'], ':lang' => $language['id']]
            ) ?? ''));
        }

        return rtrim(implode('-', $names), '-');
    }


    /** VAT rate of the article's tax class for the delivery country's tax zone. */
    private function taxRate(string $taxClassId, string $countryId, string $everysizeOrderId, string $sku, array &$warnings): float
    {
        $rate = $this->db->fetchValue(
            'SELECT tr.tax_rate FROM tax_rates tr
             JOIN zones_to_geo_zones ztz ON ztz.geo_zone_id = tr.tax_zone_id
             WHERE tr.tax_class_id = :taxClassId AND ztz.zone_country_id = :countryId
             ORDER BY tr.tax_priority, tr.tax_rates_id LIMIT 1',
            [':taxClassId' => $taxClassId, ':countryId' => $countryId]
        );
        if ($rate === null) {
            $warnings[] = sprintf('%s: no tax rate for tax class %s in delivery country, item %s imported with 0%%',
                $everysizeOrderId, $taxClassId, $sku);

            return 0.0;
        }

        return (float) $rate;
    }


    private function productName(string $productsId): string
    {
        return (string) ($this->db->fetchValue(
            'SELECT products_name FROM products_description
             WHERE products_id = :id AND language_id = :lang LIMIT 1',
            [':id' => $productsId, ':lang' => $this->defaultLanguage()['id']]
        ) ?? '');
    }


    private function localDate($value, string $field): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new ImportException(sprintf('everysize order has an invalid %s date: %s', $field, $value));
        }
    }


    /** The shop's default language (first by sort order): directory name, id. */
    private function defaultLanguage(): array
    {
        $row = $this->db->fetchRows(
            'SELECT languages_id, directory, code FROM languages WHERE status = 1 ORDER BY sort_order, languages_id LIMIT 1'
        );
        if ($row === []) {
            return ['id' => '2', 'directory' => 'german', 'code' => 'de'];
        }

        return ['id' => (string) $row[0]['languages_id'], 'directory' => (string) $row[0]['directory'], 'code' => (string) $row[0]['code']];
    }


    // --- Writing helpers -----------------------------------------------------

    /**
     * The everysize customer either matches an existing shop account
     * (kept untouched, only referenced) or the order is a guest order
     * (no account is created, no registration mail).
     *
     * @return array{0: string} customers_id
     */
    private function resolveCustomer(string $email): array
    {
        $customerId = $this->db->fetchValue(
            'SELECT customers_id FROM customers WHERE customers_email_address = :email ORDER BY customers_id LIMIT 1',
            [':email' => $email]
        );

        return [$customerId ?? '0'];
    }


    /**
     * @param array{firstName: string, lastName: string, street: string, streetNr: string, addInfo: string, zip: string, city: string, countryId: string, country: string, addressFormatId: string} $billing
     * @param array{firstName: string, lastName: string, street: string, streetNr: string, addInfo: string, zip: string, city: string, countryId: string, country: string, addressFormatId: string} $shipping
     * @param array{totalGross: float, totalNet: float, vatGroups: array<string, float>, deliveryCost: float, deliveryVat: float, totalOrderSum: float} $prices
     */
    private function insertOrder(
        string $everysizeOrderId, string $email, string $customerId,
        array $billing, array $shipping, string $orderDate, string $orderStatus, bool $revoked,
        array $prices, array $language
    ): string {
        [$customerStatusName, $customerStatusImage, $customerStatusId] = $this->customerStatusFields($customerId);
        $guest = $customerId === '0';
        $billingName = $this->name($billing['firstName'], $billing['lastName']);
        $shippingName = $this->name($shipping['firstName'], $shipping['lastName']);

        $this->db->execute(
            'INSERT INTO orders (customers_id, customers_cid, customers_vat_id, customers_status,
                customers_status_name, customers_status_image, customers_status_discount,
                customers_name, customers_firstname, customers_lastname, customers_gender,
                customers_company, customers_street_address, customers_house_number,
                customers_additional_info, customers_suburb, customers_city, customers_postcode,
                customers_state, customers_country, customers_telephone, customers_email_address,
                customers_address_format_id,
                delivery_name, delivery_firstname, delivery_lastname, delivery_company, delivery_street_address,
                delivery_house_number, delivery_additional_info, delivery_suburb, delivery_city,
                delivery_postcode, delivery_state, delivery_country, delivery_country_iso_code_2,
                delivery_address_format_id,
                billing_name, billing_firstname, billing_lastname, billing_company, billing_street_address,
                billing_house_number, billing_additional_info, billing_suburb, billing_city,
                billing_postcode, billing_state, billing_country, billing_country_iso_code_2,
                billing_address_format_id,
                payment_method, payment_class, shipping_method, shipping_class,
                comments, date_purchased, orders_status, currency, currency_value,
                account_type, customers_ip, language, orders_hash, gm_order_send_date, gm_cancel_date)
             VALUES (:customerId, :customerCid, :vatId, :customerStatus,
                :customerStatusName, :customerStatusImage, 0.00,
                :customersName, :billingFirstName, :billingLastName, :gender,
                :company, :street, :streetNr, :addInfo, :suburb, :city, :zip,
                :state, :country, :telephone, :email, :formatId,
                :deliveryName, :deliveryFirstName, :deliveryLastName, :deliveryCompany, :deliveryStreet, :deliveryStreetNr,
                :deliveryAddInfo, :deliverySuburb, :deliveryCity, :deliveryZip, :deliveryState,
                :deliveryCountry, :deliveryCountryIso, :deliveryFormatId,
                :billingName, :billingFirstName, :billingLastName, :company, :street, :streetNr, :addInfo, :suburb,
                :city, :zip, :state, :country, :countryIso, :formatId,
                :paymentMethod, :paymentClass, :shippingMethod, :shippingClass,
                :comments, :datePurchased, :orderStatus, :currency, 1.00000000,
                :accountType, :ip, :language, :ordersHash, :epochDate, :cancelDate)',
            [
                ':customerId' => $customerId,
                ':customerCid' => $this->cut('', 32),
                ':vatId' => $this->cut('', 20),
                ':customerStatus' => $customerStatusId,
                ':customerStatusName' => $this->cut($customerStatusName, 32),
                ':customerStatusImage' => $this->cut($customerStatusImage, 255),
                ':customersName' => $billingName,
                ':billingFirstName' => $this->cut($billing['firstName'], 32),
                ':billingLastName' => $this->cut($billing['lastName'], 32),
                ':gender' => '',
                ':company' => $this->cut('', 255),
                ':street' => $this->cut($billing['street'], 64),
                ':streetNr' => $this->cut($billing['streetNr'], 64),
                ':addInfo' => $this->cut($billing['addInfo'], 255),
                ':suburb' => $this->cut('', 32),
                ':city' => $this->cut($billing['city'], 64),
                ':zip' => $this->cut($billing['zip'], 10),
                ':state' => $this->cut('', 32),
                ':country' => $this->cut($billing['country'], 64),
                ':telephone' => $this->cut('', 32),
                ':email' => $this->cut($email, 96),
                ':formatId' => $billing['addressFormatId'],
                ':deliveryName' => $shippingName,
                ':deliveryFirstName' => $this->cut($shipping['firstName'], 32),
                ':deliveryLastName' => $this->cut($shipping['lastName'], 32),
                ':deliveryCompany' => $this->cut('', 255),
                ':deliveryStreet' => $this->cut($shipping['street'], 64),
                ':deliveryStreetNr' => $this->cut($shipping['streetNr'], 64),
                ':deliveryAddInfo' => $this->cut($shipping['addInfo'], 255),
                ':deliverySuburb' => $this->cut('', 32),
                ':deliveryCity' => $this->cut($shipping['city'], 64),
                ':deliveryZip' => $this->cut($shipping['zip'], 10),
                ':deliveryState' => $this->cut('', 32),
                ':deliveryCountry' => $this->cut($shipping['country'], 64),
                ':deliveryCountryIso' => $this->cut($this->countryIso($shipping['countryId']), 2),
                ':deliveryFormatId' => $shipping['addressFormatId'],
                ':billingName' => $billingName,
                ':countryIso' => $this->cut($this->countryIso($billing['countryId']), 2),
                ':paymentMethod' => 'everysize (PayPal)',
                ':paymentClass' => 'everysize',
                ':shippingMethod' => $this->cut('everysize Versand', 64),
                ':shippingClass' => $this->cut('everysize', 64),
                ':comments' => sprintf('Importiert aus everysize (%s)', $everysizeOrderId),
                ':datePurchased' => $orderDate,
                ':orderStatus' => $orderStatus,
                ':currency' => 'EUR',
                ':accountType' => $guest ? '1' : '0',
                ':ip' => $this->cut('', 32),
                ':language' => $this->cut($language['directory'], 32),
                ':ordersHash' => (string) (time() + \mt_rand()),
                ':epochDate' => '1000-01-01 00:00:00',
                ':cancelDate' => $revoked ? date('Y-m-d H:i:s') : '1000-01-01 00:00:00',
            ]
        );

        $ordersId = $this->db->lastInsertId();
        if ($ordersId === null) {
            throw new ImportException(sprintf('%s: order could not be inserted', $everysizeOrderId));
        }

        return $ordersId;
    }


    /** "Firstname Lastname" like OrderRepositoryWriter's _serializeAddressBlock. */
    private function name(string $firstName, string $lastName): string
    {
        return $this->cut(trim($firstName . ' ' . $lastName), 64);
    }


    /**
     * Guest orders get Gambio's guest status; existing accounts keep their own
     * status (like the checkout, resolved for the order language).
     *
     * @return array{0: string, 1: string, 2: string} name, image, status id
     */
    private function customerStatusFields(string $customerId): array
    {
        $langId = $this->defaultLanguage()['id'];
        if ($customerId !== '0') {
            $statusId = $this->db->fetchValue(
                'SELECT customers_status FROM customers WHERE customers_id = :id',
                [':id' => $customerId]
            ) ?? '2';
            $rows = $this->db->fetchRows(
                'SELECT customers_status_name, customers_status_image FROM customers_status
                 WHERE customers_status_id = :id AND language_id = :lang LIMIT 1',
                [':id' => $statusId, ':lang' => $langId]
            );
            if ($rows !== []) {
                return [(string) $rows[0]['customers_status_name'], (string) $rows[0]['customers_status_image'], $statusId];
            }

            return ['Gast', 'user_status5.gif', $statusId];
        }

        // Guest: Gambio's guest status (1) without creating a row.
        $rows = $this->db->fetchRows(
            'SELECT customers_status_name, customers_status_image FROM customers_status
             WHERE customers_status_id = 1 AND language_id = :lang LIMIT 1',
            [':lang' => $langId]
        );
        if ($rows !== []) {
            return [(string) $rows[0]['customers_status_name'], (string) $rows[0]['customers_status_image'], '1'];
        }

        return ['Gast', 'user_status5.gif', '1'];
    }


    private function countryIso(string $countryId): string
    {
        return (string) ($this->db->fetchValue(
            'SELECT countries_iso_code_2 FROM countries WHERE countries_id = :id',
            [':id' => $countryId]
        ) ?? '');
    }


    /**
     * @param array{sku: string, name: string, model: string, combiModel: string, combiId: ?string, quantity: int} $line
     * @param array{unitGross: float, totalGross: float, totalNet: float, totalVat: float, vat: float} $price
     */
    private function insertOrderProduct(string $ordersId, array $line, array $price): string
    {
        // The shop's article name first (like the other everysize plugins),
        // everysize's title only as fallback.
        $name = $line['name'] !== '' ? $line['name'] : $line['title'];

        $this->db->execute(
            'INSERT INTO orders_products (orders_id, products_id, products_model, products_name,
                products_price, products_discount_made, products_shipping_time, final_price,
                products_tax, products_quantity, allow_tax, product_type, properties_combi_price,
                properties_combi_model, checkout_information)
             VALUES (:ordersId, :productsId, :model, :name,
                :unitGross, 0.00, :shippingTime, :totalGross,
                :tax, :quantity, 1, 1, 0.0000,
                :combiModel, NULL)',
            [
                ':ordersId' => $ordersId,
                ':productsId' => $line['productsId'],
                ':model' => $this->cut($line['model'], 64),
                ':name' => $this->cut($name, 255),
                ':unitGross' => number_format($price['unitGross'], 4, '.', ''),
                ':shippingTime' => '',
                ':totalGross' => number_format($price['totalGross'], 4, '.', ''),
                ':tax' => number_format($price['vat'], 4, '.', ''),
                ':quantity' => number_format($line['quantity'], 4, '.', ''),
                ':combiModel' => $this->cut($line['combiModel'], 64),
            ]
        );
        $ordersProductsId = (string) $this->db->lastInsertId();

        if ($line['combiId'] !== null) {
            $this->insertVariantProperties($ordersId, $ordersProductsId, $line['combiId']);
        }

        return $ordersProductsId;
    }


    /**
     * The variant's properties as orders_products_properties rows — that is
     * what the checkout's _addProperties writes, what the "rebook" on a
     * cancellation reads and what order handling / PDFs show. There is no
     * orders_products_attributes row: properties are not attributes.
     */
    private function insertVariantProperties(string $ordersId, string $ordersProductsId, string $combiId): void
    {
        $languageId = $this->defaultLanguage()['id'];
        $values = $this->db->fetchRows(
            'SELECT pcv.properties_values_id, pcv.options_id, pd.properties_name, pvd.values_name
             FROM products_properties_combis_values pcv
             LEFT JOIN properties_description pd ON pd.properties_id = pcv.options_id AND pd.language_id = :lang
             LEFT JOIN properties_values_description pvd
                 ON pvd.properties_values_id = pcv.properties_values_id AND pvd.language_id = :lang
             WHERE pcv.products_properties_combis_id = :combiId',
            [':combiId' => $combiId, ':lang' => $languageId]
        );
        foreach ($values as $value) {
            $this->db->execute(
                'INSERT INTO orders_products_properties (orders_products_id, properties_name, values_name,
                    properties_price_type, properties_price, products_properties_combis_id)
                 VALUES (:ordersProductsId, :propertiesName, :valuesName, \'\', 0.0000, :combiId)',
                [
                    ':ordersProductsId' => $ordersProductsId,
                    ':propertiesName' => $this->cut((string) ($value['properties_name'] ?? ''), 255),
                    ':valuesName' => $this->cut((string) ($value['values_name'] ?? ''), 255),
                    ':combiId' => $combiId,
                ]
            );
        }
    }


    /**
     * Stock like the checkout's update_stock, symmetric to xtc_remove_order:
     * nothing unless STOCK_LIMITED; with a combi the products.use_properties_
     * combis_quantity decides (0 = article, its combi only with attribute
     * stock check; 1 = article only; 2 = combi only; 3 = nothing).
     * Stock may go negative (paid order!), that is a warning not an error —
     * only for the stock that is actually kept.
     *
     * @param array{sku: string, quantity: int, productsId: string, combiId: ?string} $line
     *
     * @return list<string>
     */
    private function reduceStock(array $line, string $everysizeOrderId): array
    {
        $warnings = [];
        // Sales statistics always (like the checkout, independent of STOCK_LIMITED).
        $this->db->execute(
            'UPDATE products SET products_ordered = products_ordered + :qty WHERE products_id = :id',
            [':qty' => (string) $line['quantity'], ':id' => $line['productsId']]
        );

        if ($this->shopConfig('STOCK_LIMITED') !== 'true') {
            return $warnings;
        }

        $stockCheck = $this->shopConfig('STOCK_CHECK') === 'true';
        $attributeStockCheck = $this->shopConfig('ATTRIBUTE_STOCK_CHECK') === 'true';
        $hasCombi = $line['combiId'] !== null;
        $useCombis = 0;
        if ($hasCombi) {
            $useCombis = (int) ($this->db->fetchValue(
                'SELECT use_properties_combis_quantity FROM products WHERE products_id = :id',
                [':id' => $line['productsId']]
            ) ?? '0');
        }

        // ProductStockService::isCombinationConfigChangeProductStock
        $changeProductStock = false;
        $changeCombiStock = false;
        switch ($useCombis) {
            case 0: // global default: article stock, the combi's only with attribute stock check
                $changeProductStock = $stockCheck && (!$hasCombi || !$attributeStockCheck);
                $changeCombiStock = $hasCombi && $stockCheck && $attributeStockCheck;
                break;
            case 1: // article stock only
                $changeProductStock = $stockCheck;
                break;
            case 2: // combi stock only
                $changeCombiStock = $hasCombi;
                break;
            // 3: no stock bookkeeping at all
        }

        if ($changeProductStock) {
            $this->db->execute(
                'UPDATE products SET products_quantity = products_quantity - :qty WHERE products_id = :id',
                [':qty' => (string) $line['quantity'], ':id' => $line['productsId']]
            );
            $remaining = $this->db->fetchValue(
                'SELECT products_quantity FROM products WHERE products_id = :id',
                [':id' => $line['productsId']]
            );
            if ($remaining !== null && (float) $remaining < 0) {
                $warnings[] = sprintf('%s: stock of %s went negative (%.0f)', $everysizeOrderId, $line['sku'], (float) $remaining);
            }
        }
        if ($changeCombiStock) {
            $this->db->execute(
                'UPDATE products_properties_combis SET combi_quantity = combi_quantity - :qty
                 WHERE products_properties_combis_id = :id AND products_id = :productsId',
                [':qty' => (string) $line['quantity'], ':id' => $line['combiId'], ':productsId' => $line['productsId']]
            );
            $remaining = $this->db->fetchValue(
                'SELECT combi_quantity FROM products_properties_combis WHERE products_properties_combis_id = :id',
                [':id' => $line['combiId']]
            );
            if ($remaining !== null && (float) $remaining < 0) {
                $warnings[] = sprintf('%s: stock of %s went negative (%.0f)', $everysizeOrderId, $line['sku'], (float) $remaining);
            }
        }

        return $warnings;
    }


    /**
     * orders_total with the titles and sort orders the ot_* modules write:
     * sort from MODULE_ORDER_TOTAL_*_SORT_ORDER, the tax title like the
     * tax group key of the order (TAX_ADD_TAX + the rate's description),
     * the total in bold. The tax lines are included in the amounts.
     *
     * @param array{totalGross: float, totalNet: float, vatGroups: array<string, float>, deliveryCost: float, deliveryVat: float, totalOrderSum: float} $prices
     */
    private function insertOrderTotals(string $ordersId, array $prices, array $language): void
    {
        $texts = $this->languageTexts($language, [
            'TAX_ADD_TAX',
            'MODULE_ORDER_TOTAL_SUBTOTAL_TITLE',
            'MODULE_ORDER_TOTAL_TOTAL_TITLE',
        ]);

        // Shipping carries the VAT rate of the items (PriceCalculator) — its
        // VAT joins the highest item rate, like Gambio shows one tax line.
        $deliveryVat = $prices['deliveryCost'] > 0 && $prices['deliveryVat'] > 0
            ? round($prices['deliveryCost'] - $prices['deliveryCost'] / (1 + $prices['deliveryVat'] / 100), 2)
            : 0.0;

        $this->insertTotal(
            $ordersId, $this->cut(($texts['MODULE_ORDER_TOTAL_SUBTOTAL_TITLE'] ?: 'Zwischensumme') . ':', 255),
            round($prices['totalGross'], 2), 'ot_subtotal', $this->sortOrder('SUBTOTAL', 10)
        );
        $this->insertTotal(
            $ordersId, $this->cut('everysize Versand:', 255), $prices['deliveryCost'], 'ot_shipping', $this->sortOrder('SHIPPING', 30)
        );

        $descriptions = $this->taxDescriptions();
        $top = null;
        foreach ($prices['vatGroups'] as $rate => $amount) {
            if ($top === null) {
                $top = $rate;
            }
            $description = $descriptions[$rate] ?? sprintf('%s%%', rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.'));
            $title = $this->cut(($texts['TAX_ADD_TAX'] ?: 'inkl. ') . $description . ':', 255);
            $this->insertTotal($ordersId, $title, $amount + ($rate == $top ? $deliveryVat : 0.0), 'ot_tax', $this->sortOrder('TAX', 97));
        }
        $this->insertTotal(
            $ordersId, $this->cut(($texts['MODULE_ORDER_TOTAL_TOTAL_TITLE'] ?: '<b>Summe</b>') . ':', 255),
            $prices['totalOrderSum'], 'ot_total', $this->sortOrder('TOTAL', 99)
        );
    }


    /** The tax description per rate of the delivery-country zones ("MwSt. 19%"). */
    private function taxDescriptions(): array
    {
        $descriptions = [];
        foreach ($this->db->fetchRows('SELECT tax_rate, tax_description FROM tax_rates') as $row) {
            $descriptions[(float) $row['tax_rate']] = (string) $row['tax_description'];
        }

        return $descriptions;
    }


    private function sortOrder(string $module, int $fallback): string
    {
        return (string) ($this->shopConfig('MODULE_ORDER_TOTAL_' . $module . '_SORT_ORDER') ?: (string) $fallback);
    }


    private function insertTotal(string $ordersId, string $title, float $value, string $class, string $sortOrder): void
    {
        $this->db->execute(
            'INSERT INTO orders_total (orders_id, title, text, value, class, sort_order)
             VALUES (:ordersId, :title, :text, :value, :class, :sortOrder)',
            [
                ':ordersId' => $ordersId,
                ':title' => $title,
                ':text' => $this->money($value),
                ':value' => number_format($value, 4, '.', ''),
                ':class' => $class,
                ':sortOrder' => $sortOrder,
            ]
        );
    }


    /** German money text as Gambio shows it ("124,89 EUR"). */
    private function money(float $value): string
    {
        return number_format($value, 2, ',', '').' EUR';
    }


    private function insertStatusHistory(string $ordersId, string $statusId, string $everysizeOrderId): void
    {
        $this->db->execute(
            'INSERT INTO orders_status_history (orders_id, orders_status_id, date_added, customer_notified, comments, customer_id)
             VALUES (:ordersId, :statusId, :now, 0, :comments, 0)',
            [
                ':ordersId' => $ordersId,
                ':statusId' => $statusId,
                ':now' => date('Y-m-d H:i:s'),
                ':comments' => sprintf('Importiert aus everysize (%s)', $everysizeOrderId),
            ]
        );
    }


    // --- Small helpers -------------------------------------------------------

    /** Trims to the column length (all text columns are utf8mb3-safe after that). */
    private function cut(string $value, int $length): string
    {
        return mb_substr($this->stripFourByte($value), 0, $length);
    }


    /** Removes 4-byte UTF-8 characters (emoji etc.): the order tables are utf8mb3. */
    private function stripFourByte(string $value): string
    {
        return (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $value);
    }


    /** A shop configuration value from the configuration storage ("configuration/<key>"). */
    private function shopConfig(string $key): string
    {
        return (string) ($this->db->fetchValue(
            'SELECT `value` FROM gx_configurations WHERE `key` = :key',
            [':key' => 'configuration/' . $key]
        ) ?? '');
    }


    /**
     * Language texts of the order language, read directly from the
     * original_sections lang files (general.lang for TAX_ADD_TAX, the
     * order_total module files for the titles). Unknown keys stay empty;
     * the callers have fallbacks.
     *
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function languageTexts(array $language, array $keys): array
    {
        $values = [];
        $directory = $language['directory'] !== '' ? $language['directory'] : 'german';
        $sections = [
            DIR_FS_CATALOG . 'lang/' . $directory . '/original_sections/general.lang.inc.php',
            DIR_FS_CATALOG . 'lang/' . $directory . '/original_sections/modules/order_total/ot_subtotal.lang.inc.php',
            DIR_FS_CATALOG . 'lang/' . $directory . '/original_sections/modules/order_total/ot_total.lang.inc.php',
        ];
        $all = [];
        foreach ($sections as $section) {
            if (is_file($section)) {
                /** @var array<string, string> $t_language_text_section_content_array */
                $t_language_text_section_content_array = [];
                include $section;
                $all += $t_language_text_section_content_array;
            }
        }
        foreach ($keys as $key) {
            $values[$key] = \is_string($all[$key] ?? null) ? $all[$key] : '';
        }

        return $values;
    }
}
