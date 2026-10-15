<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrdersOverviewAjaxController.inc.php

   Feeds the "everysize" column of the admin order overview table
   (admin.php?do=OrdersOverviewAjax/DataTable). Gambio documents
   _getTableData() as "It can be overloaded in order to contain extra
   data e.g. for a new column." (OrdersOverviewAjaxController.inc.php)
   The rows are matched to orders via DT_RowId, so one extra query per
   page is enough.
   --------------------------------------------------------------*/

class EverysizeCheckoutOrdersOverviewAjaxController extends EverysizeCheckoutOrdersOverviewAjaxController_parent
{
    protected function _getTableData()
    {
        $tableData = parent::_getTableData();

        $orderIds = [];
        foreach ($tableData as $row) {
            $orderIds[] = (int) $row['DT_RowId'];
        }

        $numbers = [];
        if ($orderIds !== []) {
            $query = $this->db->select('orders_id, everysize_order_id')
                              ->from('everysize_checkout_order')
                              ->where_in('orders_id', $orderIds)
                              ->get();
            foreach ($query->result_array() as $row) {
                $numbers[(int) $row['orders_id']] = $row['everysize_order_id'];
            }
        }

        foreach ($tableData as &$row) {
            $row['everysize'] = $numbers[(int) $row['DT_RowId']] ?? '';
        }

        return $tableData;
    }
}
