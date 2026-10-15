<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrderListGenerator.inc.php

   Joins everysize_checkout_order into the admin order overview list
   query so that the "everysize" column can be filtered and sorted.
   Gambio: "The filtering of custom columns must be also done
   manually." (OrderListGenerator.inc.php) — hence the explicit LIKE
   filter in _setFilterArguments, matching the style of the built-in
   string column filters (customer, payment method, ...).
   --------------------------------------------------------------*/

class EverysizeCheckoutOrderListGenerator extends EverysizeCheckoutOrderListGenerator_parent
{
    protected function _select($filterParameters = null, $sorters = [])
    {
        parent::_select($filterParameters, $sorters);

        // LEFT JOIN so orders without an everysize number stay listed;
        // the join also backs sorting on the everysize column field.
        $this->db->join('everysize_checkout_order',
                        'everysize_checkout_order.orders_id = orders.orders_id',
                        'left');

        return $this;
    }


    protected function _setFilterArguments(array $filterParameters, ?\Pager $pager = null, array $sorters = [])
    {
        parent::_setFilterArguments($filterParameters, $pager, $sorters);

        if (!empty($filterParameters['everysize'])) {
            $this->db->like('everysize_checkout_order.everysize_order_id', $filterParameters['everysize']);
        }

        return $this;
    }
}
