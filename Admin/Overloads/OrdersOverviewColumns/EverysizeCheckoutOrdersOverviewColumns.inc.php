<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrdersOverviewColumns.inc.php

   Adds the "everysize-Nummer" column to the admin order overview
   (admin.php?do=OrdersOverview — the overview the GambioAdmin menu
   links "Bestellungen" to, see GambioAdmin/Layout/Menu/data/
   GambioAdminMenu.json). Gambio documents this class as overloadable:
   "This class can be overloaded and more table columns can be added."
   (GXMainComponents/Extensions/Orders/OrdersOverviewColumns.inc.php)

   The column name "everysize" is the key used by the matching
   OrdersOverviewAjaxController overload (row values) and
   OrderListGenerator overload (filtering/sorting on the joined
   everysize_checkout_order table).
   --------------------------------------------------------------*/

class EverysizeCheckoutOrdersOverviewColumns extends EverysizeCheckoutOrdersOverviewColumns_parent
{
    public function __construct()
    {
        parent::__construct();

        $languageTextManager = MainFactory::create('LanguageTextManager',
                                                   'everysizecheckout',
                                                   $_SESSION['languages_id']);

        $this->columns[] = MainFactory::create('DataTableColumn')
            ->setTitle(new StringType($languageTextManager->get_text('everysizecheckout_overview_column')))
            ->setName(new StringType('everysize'))
            ->setField(new StringType('everysize_checkout_order.everysize_order_id'))
            ->setType(new DataTableColumnType(DataTableColumnType::STRING));
    }
}
