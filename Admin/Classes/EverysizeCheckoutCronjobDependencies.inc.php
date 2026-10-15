<?php
/* --------------------------------------------------------------
   EverysizeCheckoutCronjobDependencies.inc.php

   The services the cronjob task runs: the sync built over one shared
   mysqli connection. Settings come from the module's configuration
   storage; the API URL from the environment (never a silent fallback).
   --------------------------------------------------------------
*/

declare(strict_types=1);

// NOTE: the module's Admin/Classes live in the GLOBAL namespace (loaded by the
// shop's class loader, no GXModules namespace). A `use GXModules\...` import
// here made the task fail with "Class not found" and the sync never ran.

class EverysizeCheckoutCronjobDependencies extends AbstractCronjobDependencies
{
    /**
     * @return array
     */
    public function getDependencies()
    {
        $db = EverysizeCheckoutFactory::db();

        return [
            'EverysizeCheckoutSettings' => new EverysizeCheckoutSettings(),
            'EverysizeCheckoutSync'     => EverysizeCheckoutFactory::sync($db),
        ];
    }
}
