<?php

defined('_JEXEC') or die('Restricted access');

use Ingpsp\Lib\IngpspVmPaymentPlugin;

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

ini_set('display_errors', 'Off');
if (!class_exists('vmPSPlugin')) {
    require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

JLoader::registerNamespace('Ingpsp', JPATH_LIBRARIES . '/ingpsp');
JImport('ingpsp.ing-php.vendor.autoload');
JImport('ingpsp.ingpsphelper');

class plgVmPaymentIngpsphomepay extends IngpspVmPaymentPlugin
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
    }

    /**
     * @param $cart
     * @param $order
     * @return bool|null
     * @since v1.0.0
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $this->getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
        $currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
        $email_currency = $this->getEmailCurrency($method);

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        if (!empty($method->payment_info)) {
            $lang = JFactory::getLanguage();
            if ($lang->hasKey($method->payment_info)) {
                $method->payment_info = vmText::_($method->payment_info);
            }
        }

        $totalInCents = IngpspHelper::getAmountInCents($totalInPaymentCurrency['value']);
        $orderId = $order['details']['BT']->virtuemart_order_id;
        $description = IngpspHelper::getOrderDescription($orderId);
        $returnUrl = IngpspHelper::getReturnUrl(intval($order['details']['BT']->virtuemart_paymentmethod_id));
        $customer = \Ingpsp\Lib\CommonCustomerFactory::create(
                        $order['details']['BT'],
                        \IngpspHelper::getLocale(),
                        \JFactory::getApplication()->input->server->get('REMOTE_ADDR')
        );
        $plugin = ['plugin' => IngpspHelper::getPluginVersion($this->_name)];
        $webhook =$this->getWebhookUrl(intval($order['details']['BT']->virtuemart_paymentmethod_id));
        
        try {
            $response = $this->getGingerClient()->createHomepayOrder(
                    $totalInCents, // Amount in cents
                    $currency_code_3, // Currency
                    [],
                $description, // Description
                    $orderId, // Merchant Order Id
                    $returnUrl, // Return URL
                    null, // Expiration Period
                    $customer->toArray(), // Customer Information
                    $plugin, // Extra Information
                    $webhook     // WebHook URL
            );
        } catch (\Exception $exception) {
            $html = "<p>" . JText::_("INGPSP_LIB_ERROR_TRANSACTION") . "</p><p>Error: ".$exception->getMessage()."</p>";
            $this->processFalseOrderStatusResponse($html);
        }

        if ($response->status()->isError()) {
            $html = "<p>" . JText::_("INGPSP_LIB_ERROR_TRANSACTION") . "</p><p>Error: ".$response->transactions()->current()->reason()->toString()."</p>";
            $this->processFalseOrderStatusResponse($html);
        }

        if (!$response->getId()) {
            $html = "<p>" . JText::_("INGPSP_LIB_ERROR_TRANSACTION") . "</p><p>Error: Response did not include id!</p>";
            $this->processFalseOrderStatusResponse($html);
        }

        if (!$response->firstTransactionPaymentUrl()) {
            $html = "<p>" . JText::_("INGPSP_LIB_ERROR_TRANSACTION") . "</p><p>Error: Response did not include payment url!</p>";
            $this->processFalseOrderStatusResponse($html);
        }

        $dbValues['payment_name'] = $this->renderPluginName($method) . '<br />' . $method->payment_info;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_min_transaction'] = $method->cost_min_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['email_currency'] = $email_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
        $dbValues['tax_id'] = $method->tax_id;
        $dbValues['ginger_order_id'] = $response->id()->toString();

        $this->storePSPluginInternalData($dbValues);

        JFactory::getApplication()->redirect($response->firstTransactionPaymentUrl()->toString());
    }

    /**
     * Handle payment response
     *
     * @param int $virtuemart_order_id
     * @param string $html
     * @return bool|null|string
     * @since v1.0.0
     */
    public function plgVmOnPaymentResponseReceived(&$virtuemart_order_id, &$html)
    {
        if (!($method = $this->getVmPluginMethod(vRequest::getInt('pm')))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        vmLanguage::loadJLang('com_virtuemart', true);
        vmLanguage::loadJLang('com_virtuemart_orders', true);

        $gingerOrder = $this->getGingerClient()->getOrder(vRequest::get('order_id'));

        if (!$gingerOrder instanceof GingerPayments\Payment\Order) {
            return JFactory::getApplication()->enqueueMessage("Error: Some text!", 'error');
        }

        $virtuemart_order_id = $this->getOrderIdByGingerOrder(vRequest::get('order_id'));

        $statusSucceeded = $this->updateOrder($gingerOrder->getStatus(), $virtuemart_order_id);
        $html = "<p>" . IngpspHelper::getOrderDescription($virtuemart_order_id) . "</p>";
        
        if ($statusSucceeded) {
            $this->emptyCart(null, $virtuemart_order_id);
            $html .= "<p>". JText::_('INGPSP_LIB_THANK_YOU_FOR_YOUR_ORDER'). "</p>";
            vRequest::setVar('html', $html);
            return true;
        }
        $html .= "<p>" . JText::_("INGPSP_LIB_ERROR_STATUS") . "</p>";
        $this->processFalseOrderStatusResponse($html);
    }

    /**
     * Webhook action
     *
     * @return void
     * @since v1.0.0
     */
    public function plgVmOnPaymentNotification()
    {
        if (!($method = $this->getVmPluginMethod(vRequest::getInt('pm')))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $input = json_decode(file_get_contents("php://input"), true);

        if (empty($input['order_id']) || $input['event'] !== 'status_changed') {
            exit('Invalid input');
        }

        $gingerOrder = $this->getGingerClient()->getOrder($input['order_id']);

        if (!$gingerOrder instanceof GingerPayments\Payment\Order) {
            exit("Invalid order");
        }

        $virtuemart_order_id = $this->getOrderIdByGingerOrder($input['order_id']);

        $this->updateOrder($gingerOrder->getStatus(), $virtuemart_order_id);

        exit();
    }

    /**
     *
     * @param type $virtuemart_paymentmethod_id
     * @param type $paymentCurrencyId
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     *
     * @param \VirtueMartCart $cart
     * @param type $selected
     * @param type $htmlIn
     * @return type
     * @since v1.0.0
     */
    public function plgVmDisplayListFEPayment(\VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     *
     * @param \VirtueMartCart $cart
     * @param array $cart_prices
     * @param type $cart_prices_name
     * @return type
     * @since v1.0.0
     */
    public function plgVmonSelectedCalculatePricePayment(\VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * before order is creted
     *
     * @param type $orderDetails
     */
    public function plgVmOnUserOrder(&$orderDetails)
    {
        return true;
    }

    /**
     * This is for checking the input data of the payment method within the checkout
     *
     * @author Valerie Cartan Isaksen
     */
    public function plgVmOnCheckoutCheckDataPayment(\VirtueMartCart $cart)
    {
        return true;
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }
}

// No closing tag
