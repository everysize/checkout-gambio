<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrderExtender.inc.php

   The "everysize" box in the order detail (admin/orders.php), added
   below the order info like any OrderExtenderComponent overload.
   Three sections in the same layout as the other everysize plugins but
   with Gambio's own admin look (own CSS classes, no pixel copying):

   1. "everysize-Bestellung" — one two-column info table
   2. "Erstattung über everysize" — paid/refunded/open tiles, refunds
      table, refund form (fresh idempotency key per page view) and a
      notice box; only with a PayPal capture id
   3. "Rückmeldungen an everysize" — the report table

   Only rendered for orders that came from everysize. Times are shown
   in the shop time zone (DB values are already shop-local; API ISO
   times are converted). All text is from the TextPhrases section
   "everysizecheckout" (everysizecheckout_-prefixed keys).
   --------------------------------------------------------------*/

require_once __DIR__ . '/../../../src/autoload.php';

use Everysize\Checkout\Api\ApiException;
use Everysize\Checkout\OrderLinks;

class EverysizeCheckoutOrderExtender extends EverysizeCheckoutOrderExtender_parent
{
    /** Refund statuses that count as "erstattet" (the money is on its way). */
    private const REFUNDED_STATES = ['COMPLETED', 'PENDING'];

    private ?EverysizeCheckoutSettings $settings = null;

    private ?EverysizeCheckoutDb $db = null;

    private ?OrderLinks $links = null;

    private ?\Everysize\Checkout\Api\Client $client = null;

    private ?object $texts = null;


    public function proceed()
    {
        $orderId = (string) ($_GET['oID'] ?? '');
        if ($orderId !== '' && ctype_digit($orderId)) {
            $html = $this->boxHtml($orderId);
            if ($html !== '') {
                $this->v_output_buffer['below_order_info'] = $html;
                $this->addContent();
            }
        }

        parent::proceed();
    }


    private function boxHtml(string $orderId): string
    {
        $link = $this->orderLinks()->getLink($orderId);
        if ($link === null) {
            return '';
        }

        $html = '<style>'
            . '.everysize-box{margin:10px 0 24px}.everysize-box h3{font-size:14px;margin:20px 0 8px}'
            . '.everysize-box h3:first-child{margin-top:0}'
            . '.everysize-box table.everysize-info{border-collapse:collapse;width:100%}'
            . '.everysize-box table.everysize-info th{width:260px;text-align:left;font-weight:normal;color:#666;padding:6px 10px;vertical-align:top}'
            . '.everysize-box table.everysize-info td{text-align:left;padding:6px 10px;vertical-align:top}'
            . '.everysize-box table.everysize-info tr:nth-child(odd){background:#f4f4f4}'
            . '.everysize-box .everysize-hint{color:#8a8a8a;font-size:11px;display:block;margin-top:2px}'
            . '.everysize-box .everysize-tiles{display:flex;gap:14px;margin:8px 0 14px;flex-wrap:wrap}'
            . '.everysize-box .everysize-tile{border:1px solid #d5d5d5;background:#f8f8f8;padding:8px 14px;min-width:130px}'
            . '.everysize-box .everysize-tile span{display:block;color:#666;font-size:11px}'
            . '.everysize-box .everysize-tile strong{font-size:20px;font-weight:600}'
            . '.everysize-box .everysize-tile.is-open strong{color:#d0021b}'
            . '.everysize-box table.everysize-grid{border-collapse:collapse;width:100%;margin-bottom:10px}'
            . '.everysize-box table.everysize-grid th{background:#575757;color:#fff;text-align:left;padding:6px 10px;font-size:11px}'
            . '.everysize-box table.everysize-grid td{border:1px solid #e0e0e0;text-align:left;padding:5px 10px;vertical-align:top}'
            . '.everysize-box td.everysize-mono{font-family:monospace;color:#666}'
            . '.everysize-box .everysize-refund-form{margin:10px 0;overflow:hidden}'
            . '.everysize-box .everysize-refund-form label{display:block;color:#666;font-size:11px;margin-bottom:2px}'
            . '.everysize-box .everysize-refund-form input[type=text]{border:1px solid #bbb;padding:4px 6px;width:120px;margin-right:8px}'
            . '.everysize-box .everysize-refund-form button{background:#2e6da4;color:#fff;border:0;padding:6px 14px;cursor:pointer}'
            . '.everysize-box .everysize-notice{background:#fdf5d8;border:1px solid #e3cf7f;padding:8px 10px;font-size:11px;margin-top:8px}'
            . '</style>'
            . '<div class="everysize-box">';

        // --- 1. everysize-Bestellung --------------------------------------------
        $html .= '<h3>' . $this->t('everysizecheckout_section_order') . '</h3>'
            . '<table class="everysize-info"><tbody>'
            . $this->infoRow($this->t('everysizecheckout_es_order_number'), '<strong>' . $this->e((string) $link['EVERYSIZE_ORDER_ID']) . '</strong>')
            . $this->infoRow($this->t('everysizecheckout_imported'), $this->e($this->dbTime((string) $link['CREATED'])))
            . $this->infoRow($this->t('everysizecheckout_number_reported'), $this->e($link['NUMBER_REPORTED'] !== null ? $this->dbTime((string) $link['NUMBER_REPORTED']) : $this->t('everysizecheckout_not_yet')))
            . $this->infoRow($this->t('everysizecheckout_paypal_capture'), $this->e($link['PAYPAL_CAPTURE_ID'] !== '' ? $link['PAYPAL_CAPTURE_ID'] : '–'))
            . $this->infoRow(
                $this->t('everysizecheckout_original_email'),
                $this->e($link['ORIGINAL_EMAIL'] !== '' ? $link['ORIGINAL_EMAIL'] : '–')
                . '<span class="everysize-hint">' . $this->e($this->t('everysizecheckout_original_email_hint')) . '</span>'
            )
            . '</tbody></table>';

        // --- 2. Erstattung über everysize (nur mit Capture-ID) -------------------
        if ($link['PAYPAL_CAPTURE_ID'] !== '') {
            $html .= $this->refundSection($orderId, (string) $link['EVERYSIZE_ORDER_ID'], (string) $link['PAYPAL_CAPTURE_ID']);
        }

        // --- 3. Rückmeldungen an everysize ---------------------------------------
        $html .= '<h3>' . $this->t('everysizecheckout_section_reports') . '</h3>' . $this->reportsTable($orderId);

        return $html . '</div>';
    }


    private function refundSection(string $orderId, string $everysizeOrderId, string $captureId): string
    {
        $html = '<h3>' . $this->t('everysizecheckout_section_refund') . '</h3>';
        $info = $this->refundInfo($orderId, $everysizeOrderId);

        if ($info['error'] !== '') {
            return $html . '<div class="everysize-notice">' . $this->e($this->t('everysizecheckout_refunds_error')) . ' '
                . $this->e($info['error']) . '</div>';
        }

        $html .= '<div class="everysize-tiles">'
            . $this->tile($this->t('everysizecheckout_paid'), $this->money($info['paid']), false)
            . $this->tile($this->t('everysizecheckout_refunded'), $this->money($info['refunded']), false)
            . $this->tile($this->t('everysizecheckout_open'), $this->money($info['open']), $info['open'] > 0.004)
            . '</div>';

        if ($info['refunds'] !== []) {
            $html .= '<table class="everysize-grid"><thead><tr>'
                . '<th>' . $this->e($this->t('everysizecheckout_date')) . '</th>'
                . '<th>' . $this->e($this->t('everysizecheckout_refund_amount')) . '</th>'
                . '<th>' . $this->e($this->t('everysizecheckout_refund_status')) . '</th>'
                . '<th>' . $this->e($this->t('everysizecheckout_refund_id')) . '</th>'
                . '</tr></thead><tbody>';
            foreach ($info['refunds'] as $refund) {
                $html .= '<tr><td>' . $this->e($refund['created']) . '</td><td>' . $this->e($refund['amount'])
                    . '</td><td>' . $this->e($refund['status']) . '</td><td class="everysize-mono">' . $this->e($refund['refundId']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($info['open'] > 0.004) {
            $confirm = str_replace('%s', "' + this.refund_amount.value + '", $this->t('everysizecheckout_refund_confirm'));
            $html .= '<form class="everysize-refund-form" method="post" action="' . $this->e(xtc_href_link('admin.php', 'do=EverysizeCheckoutOrderBox/Refund'))
                . '" onsubmit="return confirm(\'' . $this->e($confirm) . '\');">'
                . '<input type="hidden" name="pageToken" value="' . $this->e($_SESSION['coo_page_token']->generate_token()) . '">'
                . '<input type="hidden" name="order_id" value="' . (int) $orderId . '">'
                . '<input type="hidden" name="refund_key" value="' . $this->e(bin2hex(random_bytes(16))) . '">'
                . '<div><label for="everysize_refund_amount">' . $this->e($this->t('everysizecheckout_amount')) . '</label>'
                . '<input type="text" id="everysize_refund_amount" name="refund_amount" value="' . $this->e(number_format($info['open'], 2, ',', '')) . '"></div>'
                . '<div style="margin-top:6px"><button type="submit">' . $this->e($this->t('everysizecheckout_refund_button')) . '</button></div>'
                . '</form>';
        }

        $html .= '<div class="everysize-notice">' . $this->e($this->t('everysizecheckout_hint_refund'));
        if ($this->orderSettings()->isAutoRefundDecline()) {
            $html .= ' ' . $this->e($this->t('everysizecheckout_hint_autorefund'));
        }
        $html .= '</div>';

        return $html;
    }


    private function reportsTable(string $orderId): string
    {
        $reports = $this->orderLinks()->getReports($orderId);
        if ($reports === []) {
            return '<p style="color:#666">' . $this->e($this->t('everysizecheckout_report_none')) . '</p>';
        }

        $html = '<table class="everysize-grid"><thead><tr>'
            . '<th>' . $this->e($this->t('everysizecheckout_report_time')) . '</th>'
            . '<th>' . $this->e($this->t('everysizecheckout_report_kind')) . '</th>'
            . '<th>' . $this->e($this->t('everysizecheckout_report_item')) . '</th>'
            . '<th>' . $this->e($this->t('everysizecheckout_report_reason')) . '</th>'
            . '<th>' . $this->e($this->t('everysizecheckout_report_result')) . '</th>'
            . '</tr></thead><tbody>';
        foreach ($reports as $report) {
            $kind = (string) $report['KIND'];
            $sku = (string) $report['SKU'];
            $reason = (string) $report['REASON'];
            $item = '–';
            if ($kind === OrderLinks::KIND_REFUND) {
                $item = $sku === OrderLinks::AUTO_REFUND ? $this->t('everysizecheckout_auto_refund_sku') : $sku;
            } elseif ($sku !== '') {
                $item = $sku;
            }
            if ($kind === OrderLinks::KIND_REFUND && is_numeric($reason)) {
                $reason = $this->money((float) $reason);
            } elseif ($reason === '') {
                $reason = '–';
            }
            $html .= '<tr><td>' . $this->e($this->dbTime((string) $report['CREATED'])) . '</td>'
                . '<td>' . $this->e($this->kindLabel($kind)) . '</td>'
                . '<td class="' . ($kind === OrderLinks::KIND_REFUND && $sku !== OrderLinks::AUTO_REFUND ? 'everysize-mono' : '') . '">' . $this->e($item) . '</td>'
                . '<td>' . $this->e($reason) . '</td>'
                . '<td>' . $this->e($this->resultLabel((string) $report['RESULT'])) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }


    /**
     * Paid/refunded/open from everysize (the live refund state) plus the
     * order total as "paid". An API failure shows the reason and keeps the
     * form usable — the order data is local.
     *
     * @return array{refunds: array<int, array{created: string, amount: string, status: string, refundId: string}>, error: string, paid: float, refunded: float, open: float}
     */
    private function refundInfo(string $orderId, string $everysizeOrderId): array
    {
        $info = ['refunds' => [], 'error' => '', 'paid' => 0.0, 'refunded' => 0.0, 'open' => 0.0];
        $paid = $this->db->fetchValue(
            "SELECT value FROM orders_total WHERE orders_id = :id AND class = 'ot_total' ORDER BY sort_order LIMIT 1",
            [':id' => $orderId]
        );
        $info['paid'] = $paid !== null ? (float) $paid : 0.0;

        try {
            $refunds = $this->orderClient()->getRefunds($everysizeOrderId);
        } catch (ApiException $e) {
            $info['error'] = $e->getMessage();

            return $info;
        }

        foreach ($refunds as $refund) {
            $amount = (float) str_replace(',', '', (string) ($refund['amount'] ?? '0'));
            $status = strtoupper((string) ($refund['status'] ?? ''));
            if (\in_array($status, self::REFUNDED_STATES, true)) {
                $info['refunded'] += $amount;
            }
            $info['refunds'][] = [
                'created' => $this->apiTime((string) ($refund['created'] ?? '')),
                'amount' => $this->money($amount),
                'status' => $status,
                'refundId' => (string) ($refund['refundId'] ?? ''),
            ];
        }
        $info['open'] = max(0.0, round($info['paid'] - $info['refunded'], 2));

        return $info;
    }


    private function tile(string $label, string $value, bool $highlight): string
    {
        return '<div class="everysize-tile' . ($highlight ? ' is-open' : '') . '"><span>' . $this->e($label)
            . '</span><strong>' . $this->e($value) . '</strong></div>';
    }


    private function infoRow(string $label, string $value): string
    {
        return '<tr><th>' . $this->e($label) . '</th><td>' . $value . '</td></tr>';
    }


    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            OrderLinks::KIND_FULFILLMENT => $this->t('everysizecheckout_kind_fulfillment'),
            OrderLinks::KIND_TRACKING => $this->t('everysizecheckout_kind_tracking'),
            OrderLinks::KIND_REVOCATION => $this->t('everysizecheckout_kind_revocation'),
            OrderLinks::KIND_REFUND => $this->t('everysizecheckout_kind_refund'),
            default => $kind,
        };
    }


    private function resultLabel(string $result): string
    {
        return match ($result) {
            OrderLinks::RESULT_SENT => $this->t('everysizecheckout_result_sent'),
            OrderLinks::RESULT_KNOWN => $this->t('everysizecheckout_result_known'),
            OrderLinks::RESULT_SKIPPED => $this->t('everysizecheckout_result_skipped'),
            default => $result,
        };
    }


    /** DB timestamps are already shop-local (the sync writes shop time). */
    private function dbTime(string $dbValue): string
    {
        if ($dbValue === '') {
            return '';
        }

        return $this->formatDbTime($dbValue);
    }


    private function formatDbTime(string $dbValue): string
    {
        try {
            return (new DateTimeImmutable($dbValue))->format('d.m.Y H:i');
        } catch (Exception $e) {
            return $dbValue;
        }
    }


    /** PayPal's ISO 8601 time as shop time. */
    private function apiTime(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($iso))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('d.m.Y H:i');
        } catch (Exception $e) {
            return $iso;
        }
    }


    /** "1.234,56 €" — the shop's currency is always EUR (import rejects the rest). */
    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '') . ' €';
    }


    private function t(string $key): string
    {
        if ($this->texts === null) {
            $languageId = (int) ($_SESSION['languages_id'] ?? 0);
            $this->texts = MainFactory::create('LanguageTextManager', 'everysizecheckout', $languageId);
        }

        return (string) $this->texts->get_text($key);
    }


    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }


    private function orderSettings(): EverysizeCheckoutSettings
    {
        return $this->settings ??= MainFactory::create('EverysizeCheckoutSettings');
    }


    private function orderDb(): EverysizeCheckoutDb
    {
        return $this->db ??= EverysizeCheckoutFactory::db();
    }


    private function orderLinks(): OrderLinks
    {
        return $this->links ??= new OrderLinks($this->orderDb(), $this->orderSettings());
    }


    private function orderClient(): \Everysize\Checkout\Api\Client
    {
        return $this->client ??= EverysizeCheckoutFactory::client($this->orderSettings(), $this->orderDb());
    }
}
