<?php
/* --------------------------------------------------------------
   EverysizeCheckoutCronjobTask.inc.php

   The Gambio cronjob (Admin → Toolbox → Cronjobs): runs the module
   sync — imports open everysize orders and reports shipping,
   cancellation, returns and refunds. Inactive (checkbox in the
   cronjob configuration) or not installed → no-op, and the settings'
   "automatic sync" switch overrides the cronjob as well.
   --------------------------------------------------------------
*/

declare(strict_types=1);

class EverysizeCheckoutCronjobTask extends AbstractCronjobTask
{
    /**
     * @param float $cronjobStartAsMicrotime
     *
     * @return Closure
     */
    public function getCallback($cronjobStartAsMicrotime)
    {
        return function () {
            $this->logger->lastRun();

            try {
                // Resolve the services once: building the sync opens the DB
                // connection and reads the configuration.
                $dependencies = $this->dependencies->getDependencies();

                // The module's "automatic sync" switch: off means the cronjob idles
                // (its own checkbox is the second layer, evaluated by the kernel).
                /** @var EverysizeCheckoutSettings $settings */
                $settings = $dependencies['EverysizeCheckoutSettings'];
                if (!$settings->isSyncEnabled()) {
                    $this->logger->log(['sync' => 'disabled in the module settings, nothing to do']);

                    return true;
                }

                /** @var Sync $sync */
                $sync = $dependencies['EverysizeCheckoutSync'];
                $result = $sync->run();

                $this->logger->log($result['messages']);
                if ($result['errors'] !== []) {
                    $this->logger->logError($result['errors']);
                }
                $this->logger->lastSuccess();

                // Order confirmations that stayed pending (e.g. CLI imports) go
                // out here — the full shop context is required for the legacy
                // mail layer, which the cronjob provides.
                EverysizeCheckoutFactory::mailer()->sendPending();
            } catch (Throwable $e) {
                $this->logger->logError(['exception' => get_class($e), 'message' => $e->getMessage()]);
            }

            return true;
        };
    }
}
