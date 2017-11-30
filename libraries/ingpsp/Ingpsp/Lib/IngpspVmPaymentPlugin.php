<?php

namespace Ingpsp\Lib;

use Ingpsp\Lib\PaymentParametersFactory;

/**
 *   ╲          ╱
 * ╭──────────────╮  COPYRIGHT (C) 2017 GINGER PAYMENTS B.V.
 * │╭──╮      ╭──╮│
 * ││//│      │//││  This software is released under the terms of the
 * │╰──╯      ╰──╯│  MIT License.
 * ╰──────────────╯
 *   ╭──────────╮    https://www.gingerpayments.com/
 *   │ () () () │
 *
 * @category    Ginger
 * @package     Ginger Virtuemart
 * @author      Ginger Payments B.V. (plugins@gingerpayments.com)
 * @version     v1.0.0
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V.
 * @license     The MIT License (MIT)
 * @since       v1.0.0
 **/

abstract class IngpspVmPaymentPlugin extends \vmPSPlugin 
{

    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array  $config  An array that holds the plugin configuration
     */
    public function __construct(& $subject, $config) 
    {
        parent::__construct($subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->setConfigParameterable($this->_configTableFieldName, parent::getVarsToPush());
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     
     * @since v1.0.0 
     */
    public function getVmPluginCreateTableSQL() 
    {
        return $this->createTableSQL('Payment Standard Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return string SQL Fileds
     * @since v1.0.0 
     */
    function getTableSQLFields() 
    {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'ginger_order_id' => 'varchar(64)',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_min_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $SQLfields;
    }
    
    /**
     * 
     * @param type $method
     * @param type $selectedUserCurrency
     * @return type
     * @since v1.0.0
     */
    static function getPaymentCurrency(&$method, $selectedUserCurrency = false) 
    {
        if (empty($method->payment_currency)) {
            $vendor_model = \VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $method->payment_currency = $vendor->vendor_currency;
            return $method->payment_currency;
        } else {

            $vendor_model = \VmModel::getModel('vendor');
            $vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies($method->virtuemart_vendor_id);

            if (!$selectedUserCurrency) {
                if ($method->payment_currency == -1) {
                    $mainframe = \JFactory::getApplication();
                    $selectedUserCurrency = $mainframe->getUserStateFromRequest("virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt('virtuemart_currency_id', $vendor_currencies['vendor_currency']));
                } else {
                    $selectedUserCurrency = $method->payment_currency;
                }
            }

            $vendor_currencies['all_currencies'] = explode(',', $vendor_currencies['all_currencies']);
            if (in_array($selectedUserCurrency, $vendor_currencies['all_currencies'])) {
                $method->payment_currency = $selectedUserCurrency;
            } else {
                $method->payment_currency = $vendor_currencies['vendor_currency'];
            }

            return $method->payment_currency;
        }
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) 
    {
        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR ( $method->min_amount <= $amount AND ( $method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return TRUE;
        }

        return FALSE;
    }
    
    /**
     * update vm order status
     * 
     * @param string $gingerOrderStatus
     * @param int $virtuemart_order_id
     * @return boolean
     * @since v1.0.0
     */
    protected function updateOrder($gingerOrderStatus, $virtuemart_order_id) 
    {
        switch ($gingerOrderStatus) {
            case 'completed':
                $this->updateOrderStatus($this->methodParametersFactory()->statusCompleted(), $virtuemart_order_id);
                return true;
            case 'accepted':
                $this->updateOrderStatus($this->methodParametersFactory()->statusAccepted(), $virtuemart_order_id);
                return false;
            case 'processing':
                $this->updateOrderStatus($this->methodParametersFactory()->statusProcessing(), $virtuemart_order_id);
                return true;
            case 'pending':
                $this->updateOrderStatus($this->methodParametersFactory()->statusPending(), $virtuemart_order_id);
                return false;
            case 'new':
                $this->updateOrderStatus($this->methodParametersFactory()->statusNew(), $virtuemart_order_id);
                return true;
            case 'error':
                $this->updateOrderStatus($this->methodParametersFactory()->statusError(), $virtuemart_order_id);
                return false;
            case 'cancelled':
                $this->updateOrderStatus($this->methodParametersFactory()->statusCanceled(), $virtuemart_order_id);
                return false;
            case 'expired':
                $this->updateOrderStatus($this->methodParametersFactory()->statusExpired(), $virtuemart_order_id);
                return false;
            default:
                die("Should not happen");
        }
    }

    /**
     * 
     * @param string $newStatus
     * @param int $virtuemart_order_id 
     * @since v1.0.0
     */
    protected function updateOrderStatus($newStatus, $virtuemart_order_id) 
    {
        $modelOrder = \VmModel::getModel('orders');
        $order['order_status'] = $newStatus;
        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);
    }
    
    /**
     * fetch vm order id form hte payment table
     * 
     * @param type $gingerOrderId
     * @return int
     * @since v1.0.0
     */
    protected function getOrderIdByGingerOrder($gingerOrderId) 
    {
        $query = "SELECT `virtuemart_order_id` FROM " . $this->_tablename . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
        $db = \JFactory::getDBO();
        $db->setQuery($query);
        $r = $db->loadObject();
        if (is_object($r)) {
            return (int) $r->virtuemart_order_id;
        }
        return 0;
    }
    
    
    /**
     * Create a client instance
     * 
     * @return \GingerPayments\Payment\Client
     * @since v1.0.0
     */
    protected function getGingerClient() 
    {
        $params = $this->methodParametersFactory();
        $ginger = \GingerPayments\Payment\Ginger::createClient(
                        $params->apiKey(), 
                        $params->ingPspProduct()
        );
       
        if ($params->bundleCaCert() == true) { 
            $ginger->useBundledCA();
        }
       
        return $ginger;
    }
    
    /**
     * 
     * @param string $html
     * @since v1.0.0
     */
    protected function processFalseOrderStatusResponse($html) 
    {
        $mainframe = \JFactory::getApplication ();
        $mainframe->enqueueMessage ($html, 'error');
        $mainframe->redirect (\JRoute::_ ('index.php?option=com_virtuemart&view=cart',FALSE));
    }
    
    /**
     * Create a PaymentParameter instance
     * 
     * @return PaymentParameters
     * @since v1.0.0
     */
    protected function methodParametersFactory() 
    {
        return PaymentParametersFactory::getConfig($this->_name);
    }
    
    /**
     * Get webhook url
     * 
     * @param int $methodId
     * @return string|null
     * @since v1.0.0
     */
    protected function getWebhookUrl($methodId) 
    {
        $useWebhook = $this->methodParametersFactory()->allowNotification();
        return $useWebhook ? 
                    sprintf('%s?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=%d', \JURI::base(), $methodId)
                    : null;
    }
    
     /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    protected function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) 
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
        return TRUE;
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderPrintPayment($order_number, $method_id) 
    {
        return parent::onShowOrderPrint($order_number, $method_id);
    }

   
    /**
     * 
     * @param type $data
     * @return type
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data) 
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) 
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
    
     /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) 
    {
        return parent::onStoreInstallPluginTable($jplugin_id);
    }    
    
    public function plgVmOnCheckAutomaticSelectedPayment (\VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) 
    {
        return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
    }
}
