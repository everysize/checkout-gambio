<?php
/* --------------------------------------------------------------
   EverysizeCheckoutSettings.inc.php

   Module settings stored in Gambios configuration_storage (namespace
   modules/everysize/checkout) and read through the core's Settings
   interface, so Sync/StatusExporter/OrderImporter never touch the shop
   database directly.

   Stored values: clientId, clientSecret, carrier, autoRefund,
   autoRefundSince, shippingStatuses, cancellationStatuses,
   returnStatuses, importStatus, sendOrderConfirmation, syncEnabled.
   --------------------------------------------------------------
*/

require_once __DIR__ . '/../../src/autoload.php';

class EverysizeCheckoutSettings extends ConfigurationStorage implements \Everysize\Checkout\Settings
{
    public const CONFIG_STORAGE_NAMESPACE = 'modules/everysize/checkout';

    public const DEFAULT_CARRIER          = 'DHL';
    public const DEFAULT_IMPORT_STATUS    = '2';          // Gambio "In Bearbeitung" (paid)
    public const DEFAULT_SHIPPING_STATUS  = '3';          // Gambio "Versendet"
    public const DEFAULT_CANCEL_STATUS    = '99';         // Gambio "Storniert"


    public function __construct()
    {
        parent::__construct(self::CONFIG_STORAGE_NAMESPACE);
    }


    // --- Everysize\Checkout\Settings -------------------------------------

    public function getClientId(): string
    {
        return (string) $this->get('clientId');
    }


    public function getClientSecret(): string
    {
        return (string) $this->get('clientSecret');
    }


    public function isConfigured(): bool
    {
        return $this->getClientId() !== '' && $this->getClientSecret() !== '';
    }


    public function getCarrier(): string
    {
        $carrier = trim((string) $this->get('carrier'));

        return $carrier !== '' ? $carrier : self::DEFAULT_CARRIER;
    }


    public function isAutoRefundDecline(): bool
    {
        return (string) $this->get('autoRefund') !== '0';
    }


    public function getAutoRefundSince(): string
    {
        return (string) ($this->get('autoRefundSince') ?? '');
    }


    public function getShippingStatuses(): array
    {
        return \Everysize\Checkout\StatusList::parse($this->get('shippingStatuses')) ?: [self::DEFAULT_SHIPPING_STATUS];
    }


    public function getCancellationStatuses(): array
    {
        return \Everysize\Checkout\StatusList::parse($this->get('cancellationStatuses')) ?: [self::DEFAULT_CANCEL_STATUS];
    }


    public function getReturnStatuses(): array
    {
        return \Everysize\Checkout\StatusList::parse($this->get('returnStatuses'));
    }


    // --- Values the admin UI needs ---------------------------------------

    public function getImportStatus(): string
    {
        return (string) ($this->get('importStatus') ?? self::DEFAULT_IMPORT_STATUS);
    }


    public function sendOrderConfirmation(): bool
    {
        return (string) $this->get('sendOrderConfirmation') === '1';
    }


    public function isSyncEnabled(): bool
    {
        return (string) $this->get('syncEnabled') !== '0';
    }


    /** Marks the point in time auto refunds start from (admin toggled it on). */
    public function setAutoRefundSinceNow(): void
    {
        $this->set('autoRefundSince', date('Y-m-d H:i:s'));
    }

}
