<?php
/* --------------------------------------------------------------
   EverysizeCheckoutModuleCenterModuleController.inc.php

   Admin settings page (Module Center → everysize Checkout): credentials
   (secret never rendered to the browser), import/export behaviour, auto
   refund, connection test and credential removal.
   --------------------------------------------------------------
*/

require_once __DIR__ . '/../../../src/autoload.php';

use Everysize\Checkout\Api\ApiUrl;
use Everysize\Checkout\Api\Client;
use Everysize\Checkout\Api\ApiException;
use Everysize\Checkout\Credentials;

class EverysizeCheckoutModuleCenterModuleController extends AbstractModuleCenterModuleController
{
    private EverysizeCheckoutSettings $settings;

    private CI_DB_query_builder $db;


    protected function _init()
    {
        // The admin's default text manager does not know the module section —
        // load it explicitly so pageTitle and the settings labels resolve.
        $texts           = MainFactory::create('LanguageTextManager', 'everysizecheckout', (int) ($_SESSION['languages_id'] ?? 0));
        $this->pageTitle = $texts->get_text('everysizecheckout_title');
        $this->settings  = MainFactory::create('EverysizeCheckoutSettings');
        $this->db        = StaticGXCoreLoader::getDatabaseQueryBuilder();
    }


    public function actionDefault()
    {
        $title    = new NonEmptyStringType($this->languageTextManager->get_text('everysizecheckout_title'));
        $template = $this->getTemplateFile('Everysize/Checkout/Admin/Html/everysize_checkout_settings.html');

        $data = MainFactory::create('KeyValueCollection', [
            'pageToken'              => $_SESSION['coo_page_token']->generate_token(),
            'configuration'          => $this->settings->get_all(),
            'has_credentials'        => $this->settings->isConfigured(),
            'environment'            => $this->environment(),
            'order_statuses'         => $this->orderStatuses(),
            'shipping_statuses'      => $this->settings->getShippingStatuses(),
            'cancellation_statuses'  => $this->settings->getCancellationStatuses(),
            'return_statuses'        => $this->settings->getReturnStatuses(),
            'carriers'               => \Everysize\Checkout\CarrierMapper::CARRIERS,
            'api_url'                => $this->apiUrl(),
            'shop_version'           => $this->shopVersion(),
            'action_save'            => xtc_href_link('admin.php', 'do=EverysizeCheckoutModuleCenterModule/Save'),
            'action_test'            => xtc_href_link('admin.php', 'do=EverysizeCheckoutModuleCenterModule/TestConnection'),
            'action_remove'          => xtc_href_link('admin.php', 'do=EverysizeCheckoutModuleCenterModule/RemoveCredentials'),
        ]);

        return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data);
    }


    /**
     * Saves the form. An empty secret (or one that only contains masking
     * placeholder characters, e.g. filled in by a password manager) keeps
     * the stored one (the secret is never rendered to the browser);
     * installing or saving never deletes credentials. Toggling auto refund
     * on (or submitting it empty) marks the start of auto-refundable
     * cancellations.
     */
    public function actionSave()
    {
        $this->_validatePageToken();

        $clientId = (string) ($this->_getPostData('client_id') ?? '');
        $secret   = (string) ($this->_getPostData('client_secret') ?? '');
        if ($clientId !== '') {
            $this->settings->set('clientId', $clientId);
        }
        if (!Credentials::isMaskedPlaceholder($secret)) {
            $this->settings->set('clientSecret', $secret);
        }

        $carrier = trim((string) ($this->_getPostData('carrier') ?? ''));
        $this->settings->set('carrier', $carrier !== '' ? mb_substr($carrier, 0, 31) : EverysizeCheckoutSettings::DEFAULT_CARRIER);

        $autoRefund = (string) ($this->_getPostData('auto_refund') ?? '1');
        $wasAutoRefund = $this->settings->isAutoRefundDecline();
        $this->settings->set('autoRefund', $autoRefund === '1' ? '1' : '0');
        if ($autoRefund === '1' && !$wasAutoRefund) {
            $this->settings->setAutoRefundSinceNow();
        } elseif ($this->settings->getAutoRefundSince() === '') {
            $this->settings->setAutoRefundSinceNow();
        }

        foreach (['shippingStatuses' => 'shipping_statuses', 'cancellationStatuses' => 'cancellation_statuses',
                  'returnStatuses' => 'return_statuses'] as $key => $post) {
            $this->settings->set($key, implode(',', array_map('intval',
                (array) ($this->_getPostData($post) ?? []))));
        }

        $this->settings->set('importStatus',
            (string) ($this->_getPostData('import_status') ?? EverysizeCheckoutSettings::DEFAULT_IMPORT_STATUS));
        $this->settings->set('sendOrderConfirmation',
            (string) ($this->_getPostData('send_order_confirmation') ?? '0') === '1' ? '1' : '0');
        $this->settings->set('syncEnabled',
            (string) ($this->_getPostData('sync_enabled') ?? '1') === '1' ? '1' : '0');

        // One switch for both layers: the cronjob's own "active" flag in the
        // cronjob configuration storage follows the module's "automatic sync"
        // setting, so there is never a second place to think about.
        MainFactory::create('ConfigurationStorage', 'cronjobs/EverysizeCheckout')
            ->set('active', $this->settings->isSyncEnabled() ? 'true' : 'false');

        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('everysizecheckout_saved'), 'info');

        return MainFactory::create('RedirectHttpControllerResponse',
            xtc_href_link('admin.php', 'do=EverysizeCheckoutModuleCenterModule'));
    }


    /**
     * AJAX: tests the connection with the posted values or, when not given,
     * the stored ones. Reports shop_id and API URL on success.
     */
    public function actionTestConnection(): JsonHttpControllerResponse
    {
        $clientId = (string) ($this->_getPostData('client_id') ?? '');
        $secret   = (string) ($this->_getPostData('client_secret') ?? '');
        if (Credentials::isMaskedPlaceholder($secret)) {
            $secret = '';
        }
        if ($clientId === '' || $secret === '') {
            $clientId = $this->settings->getClientId();
            $secret   = $this->settings->getClientSecret();
        }
        if ($clientId === '' || $secret === '') {
            return new JsonHttpControllerResponse([
                'ok'     => false,
                'error'  => $this->languageTextManager->get_text('everysizecheckout_no_credentials'),
            ]);
        }

        try {
            $client = new Client($clientId, $secret, $this->apiUrl(), 'Gambio ' . $this->shopVersion());
            $token  = $client->requestToken($clientId, $secret);

            return new JsonHttpControllerResponse([
                'ok'      => true,
                'shop_id' => (int) ($token['shop_id'] ?? 0),
                'api_url' => $client->getApiUrl(),
            ]);
        } catch (ApiException $e) {
            return new JsonHttpControllerResponse([
                'ok'     => false,
                'error'  => $e->getMessage(),
                'api_url' => $this->apiUrl(),
            ]);
        } catch (Throwable $e) {
            return new JsonHttpControllerResponse([
                'ok'     => false,
                'error'  => $e->getMessage(),
            ]);
        }
    }


    /**
     * AJAX/POST: removes stored credentials after confirmation. Orders and
     * reports stay (they reference them independently).
     */
    public function actionRemoveCredentials()
    {
        $this->_validatePageToken();

        $this->settings->delete('clientId');
        $this->settings->delete('clientSecret');
        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('everysizecheckout_credentials_removed'), 'info');

        return MainFactory::create('RedirectHttpControllerResponse',
            xtc_href_link('admin.php', 'do=EverysizeCheckoutModuleCenterModule'));
    }


    private function environment(): array
    {
        try {
            return ApiUrl::describe(null);
        } catch (Throwable $e) {
            return ['environment' => 'invalid', 'url' => '', 'error' => $e->getMessage()];
        }
    }


    private function apiUrl(): string
    {
        try {
            return ApiUrl::resolve(null);
        } catch (Throwable $e) {
            return '';
        }
    }


    private function shopVersion(): string
    {
        $row = $this->db->select('value')->from('gx_configurations')
            ->where('`key`', 'gm_configuration/INSTALLED_VERSION')->get()->row();

        return $row !== null ? (string) $row->value : 'unknown';
    }


    /**
     * Gambio order statuses for the select fields (id + name per language).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function orderStatuses(): array
    {
        $rows = $this->db->select('orders_status_id, orders_status_name, language_id')
            ->from('orders_status')->where('language_id', (int) $_SESSION['languages_id'])
            ->order_by('orders_status_id')->get()->result_array();

        $list = [];
        foreach ($rows as $row) {
            $list[] = ['id' => (int) $row['orders_status_id'], 'name' => $row['orders_status_name']];
        }

        return $list;
    }
}
