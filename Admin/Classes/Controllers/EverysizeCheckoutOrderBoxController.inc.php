<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrderBoxController.inc.php

   Admin controller behind the everysize box in the order detail
   (order form refund via everysize) and the module protocol page
   (do=EverysizeCheckoutOrderBox/Refund, do=EverysizeCheckoutProtocol).
   The refund goes through the everysize External API exactly like the
   sync does — no PayPal credentials in the shop. A refund is noted in
   the order history (no status change) and the module protocol; a
   failure changes nothing in the order, the reason is shown as a
   message and logged in the protocol.
   --------------------------------------------------------------*/

require_once __DIR__ . '/../../../src/autoload.php';

use Everysize\Checkout\Api\ApiException;
use Everysize\Checkout\ModuleInfo;
use Everysize\Checkout\OrderLinks;

class EverysizeCheckoutOrderBoxController extends AdminHttpViewController
{
    private EverysizeCheckoutSettings $settings;

    private EverysizeCheckoutDb $db;

    private OrderLinks $links;


    protected function init()
    {
        $this->db = EverysizeCheckoutFactory::db();
        $this->settings = MainFactory::create('EverysizeCheckoutSettings');
        $this->links = new OrderLinks($this->db, $this->settings);
    }


    /** The order detail box itself is rendered by the OrderExtenderComponent overload. */
    public function actionDefault(): RedirectHttpControllerResponse
    {
        return $this->redirectToOrders();
    }


    /**
     * POST from the box's refund form: amount → everysize refund (the
     * form's fresh idempotency key makes a double submit refund once).
     */
    public function actionRefund()
    {
        $this->_validatePageToken();

        $orderId = (string) ($this->_getPostData('order_id') ?? '');
        $amount = str_replace([',', ' '], ['.', ''], (string) ($this->_getPostData('refund_amount') ?? ''));
        $refundKey = (string) ($this->_getPostData('refund_key') ?? '');
        $text = MainFactory::create('LanguageTextManager', 'everysizecheckout', [(int) ($_SESSION['languages_id'] ?? 0)]);

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount) || (float) $amount <= 0.0) {
            $GLOBALS['messageStack']->add_session($text->get_text('everysizecheckout_refund_invalid'), 'error');

            return $this->redirectToOrders($orderId);
        }

        $link = $this->links->getLink($orderId);
        if ($link === null || $link['PAYPAL_CAPTURE_ID'] === '') {
            $GLOBALS['messageStack']->add_session($text->get_text('everysizecheckout_refund_invalid'), 'error');

            return $this->redirectToOrders($orderId);
        }

        try {
            $client = EverysizeCheckoutFactory::client($this->settings, $this->db);
            $refund = $client->refund(
                (string) $link['EVERYSIZE_ORDER_ID'],
                $amount,
                $refundKey !== '' ? $refundKey : 'manual-' . bin2hex(random_bytes(8))
            );
            $status = strtoupper((string) ($refund['status'] ?? ''));
            $refundId = (string) ($refund['refundId'] ?? '');
            $refundAmount = (string) ($refund['amount'] ?? $amount);
            $currency = (string) ($refund['currency'] ?? '');
            $this->links->addReport(
                $orderId,
                OrderLinks::KIND_REFUND,
                $refundId !== '' ? $refundId : $status,
                number_format((float) $amount, 2, '.', ''),
                $status === 'ALREADY_REFUNDED' ? OrderLinks::RESULT_KNOWN : OrderLinks::RESULT_SENT
            );
            if ($status === 'ALREADY_REFUNDED') {
                $this->protocol()->add(
                    'Refund in the order box: already refunded',
                    sprintf('Order %s (%s): everysize reports the payment as already refunded, nothing was refunded again.', $orderId, $link['EVERYSIZE_ORDER_ID']),
                    true
                );
            } else {
                $this->links->addHistoryComment($orderId, OrderLinks::refundComment($refundAmount, $currency, $refundId, false));
                $this->protocol()->add(
                    'Refunded in the order box',
                    sprintf('Order %s (%s): %s %s refunded, PayPal refund %s.', $orderId, $link['EVERYSIZE_ORDER_ID'], $refundAmount, $currency !== '' ? $currency : 'EUR', $refundId !== '' ? $refundId : $status),
                    true
                );
            }
            $GLOBALS['messageStack']->add_session($text->get_text('everysizecheckout_refund_ok'), 'info');
        } catch (ApiException $e) {
            $this->protocol()->add(
                'Refund in the order box failed',
                sprintf('Order %s (%s), %s EUR: %s', $orderId, $link['EVERYSIZE_ORDER_ID'], $amount, $e->getMessage()),
                false
            );
            $GLOBALS['messageStack']->add_session(
                $text->get_text('everysizecheckout_refund_failed') . ' ' . $e->getMessage(),
                'error'
            );
        }

        return $this->redirectToOrders($orderId);
    }


    private function protocol(): EverysizeCheckoutProtocol
    {
        return new EverysizeCheckoutProtocol($this->db);
    }


    private function redirectToOrders(string $orderId = ''): RedirectHttpControllerResponse
    {
        $params = $orderId !== '' ? 'oID=' . (int) $orderId . '&action=edit' : '';

        return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('orders.php', $params));
    }
}
