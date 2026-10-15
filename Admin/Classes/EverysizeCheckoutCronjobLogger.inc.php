<?php
/* --------------------------------------------------------------
   EverysizeCheckoutCronjobLogger.inc.php

   Logs the cronjob run into logfiles/ (Monolog, weekly file) — the
   module protocol (everysize_checkout_protocol) holds the user-visible
   entries, this is the technical trail.
   --------------------------------------------------------------
*/

declare(strict_types=1);

class EverysizeCheckoutCronjobLogger extends AbstractCronjobLogger
{
    /**
     * @param array $context
     */
    public function log(array $context = [])
    {
        $this->logger->info('everysize checkout sync', $context);
    }


    /**
     * @param array $context
     */
    public function logError(array $context = [])
    {
        $this->logger->error('everysize checkout sync error', $context);
    }
}
