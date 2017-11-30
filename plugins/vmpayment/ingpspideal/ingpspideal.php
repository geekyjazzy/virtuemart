<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

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

class plgVmPaymentIngpspideal extends IngpspVmPaymentPlugin
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
     * This shows the plugin for choosing in the payment list of the checkout process.
     *
     * @param VirtueMartCart $cart
     * @param type $selected
     * @param array $htmlIn
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        if ($this->getPluginMethods($cart->vendorId) === 0) {
            if (empty($this->_name)) {
                $app = JFactory::getApplication();
                $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                return false;
            } else {
                return false;
            }
        }
        $method_name = $this->_psType . '_name';
        vmLanguage::loadJLang('com_virtuemart', true);
        $htmla = array();
        $html = '';
        foreach ($this->methods as $currentMethod) {
            if ($this->checkConditions($cart, $currentMethod, $cart->cartPrices)) {
                $cartPrices = $cart->cartPrices;
                $methodSalesPrice = $this->setCartPrices($cart, $cartPrices, $currentMethod);
                $currentMethod->$method_name = $this->renderPluginName($currentMethod);
                $html = $this->getPluginHtml($currentMethod, $selected, $methodSalesPrice);
                $htmla[] = $html . '<br />' . $this->customInfoHTML();
            }
        }
        $htmlIn[] = $htmla;

        return $this->isPaymentSelected($selected);
    }

    /**
     * check if current method is selected
     *
     * @param int $selected
     * @return boolean
     * @since v1.0.0
     */
    private function isPaymentSelected($selected)
    {
        $method = array_shift($this->methods);
        if (is_object($method)) {
            return $method->virtuemart_paymentmethod_id === $selected;
        }
        return false;
    }

    /**
     * @return string
     * @since v1.0.0
     */
    public function customInfoHTML()
    {
        $issuers = $this->getGingerClient()->getIdealIssuers()->toArray();
        $html = '<select name="issuer" id="issuer" class="' . $this->_name . '">';
        foreach ($issuers as $issuer) {
            $html .= '<option value="' . $issuer['id'] . '">' . $issuer['name'] . "</option>";
        }
        $html .= "</select>";

        return $html;
    }

    /**
     * store the choosen isseur into session
     *
     * @param VirtueMartCart $cart
     * @param type $msg
     * @return type
     * @since v1.0.0
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        JFactory::getSession()->set('ingpspideal_issuer', vRequest::getVar('issuer'), 'vm');
        return $this->OnSelectCheck($cart);
    }

    /**
     *
     * @param \VirtueMartCart $cart
     * @param array $cart_prices
     * @param type $cart_prices_name
     * @return type
     * @since v1.0.0
     */
    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$payment_name)
    {
        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return false;
        }

        if (!$this->checkConditions($cart, $this->_currentMethod, $cart_prices)) {
            return false;
        }
        $payment_name = $this->renderPluginName($this->_currentMethod);

        $this->setCartPrices($cart, $cart_prices, $this->_currentMethod);

        return true;
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
        $issuer = JFactory::getSession()->get('ingpspideal_issuer', null, 'vm');
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
            $response = $this->getGingerClient()->createIdealOrder(
                    $totalInCents,          // Amount in cents
                    $currency_code_3,       // Currency
                    $issuer,                // Issuer Id
                    $description,           // Description
                    $orderId,               // Merchant Order Id
                    $returnUrl,             // Return URL
                    null,                   // Expiration Period
                    $customer->toArray(),   // Customer Information
                    $plugin,                // Extra Information
                    $webhook               // WebHook URL
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
        
        JFactory::getSession()->clear('ingpspideal_issuer', 'vm'); //clear session values

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
        
        if ($this->isProcessingOrderNotConfirmedRedirect()) {
            $this->emptyCart(null, $virtuemart_order_id);
            $html .= "<p>" . JText::_("INGPSP_LIB_NO_BANK_RESPONSE") . "</p>";
            $this->processFalseOrderStatusResponse($html);
        }
        
        if ($gingerOrder->getStatus() === 'processing' && !$this->isProcessingOrderNotConfirmedRedirect()) {
            $box = '
                jQuery(document).ready(function($) {
                    var fallback_url = \''. JURI::root() .'?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm='.vRequest::getInt('pm').'&project_id='.vRequest::getInt('project_id').'&order_id='.vRequest::get('order_id').'&no_confirmation_redirect=1\';
                    var ajaxResponseUrl = \'/?option=com_virtuemart&view=plugin&type=vmpayment&name='.$this->_name."&call=selffe&order_id=".vRequest::get('order_id').'\' ;
                    var counter = 0;
                    var loop = setInterval(
                            function refresh_pending() {
                                counter++;
                                $.ajax({
                                    type: "GET",
                                    url: ajaxResponseUrl,
                                    success: function (data) {
                                        if (data.redirect == true) {
                                            location.reload();
                                        }
                                    }
                                });
                                if (counter >= 6) {
                                    clearInterval(loop);
                                    location.href = fallback_url;
                                }
                            },
                        10000
                    );
                });
                ';
            $html = $this->renderByLayout('payment_processing', array(
                        'description' =>  "<p>" . IngpspHelper::getOrderDescription($virtuemart_order_id) . "</p>",
                        'logo' => sprintf('%s/assets/images/ajax-loader.gif', (JURI::root() . $this->getOwnUrl()))
            ));
            vmJsApi::addJScript('box', $box);
            vRequest::setVar('html', $html);
            vRequest::setVar('display_title', false);
            return false;
        }
      
        if ($statusSucceeded === false) {
            switch ($gingerOrder->getStatus()) {
                case 'expired':
                    $html .=  "<p>" . JText::_("INGPSP_LIB_ERROR_STATUS_EXPIRED") . "</p>";
                    break;
                case 'cancelled':
                    $html .=  "<p>" . JText::_("INGPSP_LIB_ERROR_STATUS_CANCELED") . "</p>";
                    break;
                default:
                    $html .= "<p>" . JText::_("INGPSP_LIB_ERROR_STATUS") . "</p>" ;
                    break;
            }
            $this->processFalseOrderStatusResponse($html);
        }
        
        $this->emptyCart(null, $virtuemart_order_id);
        $html .= "<p>". JText::_('INGPSP_LIB_THANK_YOU_FOR_YOUR_ORDER'). "</p>";
        vRequest::setVar('html', $html);
        vRequest::setVar('display_title', false);
       
        return true;
    }

    /**
     * Check should page after no response from the bank should be redirected
     *
     * @return bool
     * @since v1.0.0
     */
    protected function isProcessingOrderNotConfirmedRedirect()
    {
        return (bool) (vRequest::get('no_confirmation_redirect') !== null  && vRequest::get('no_confirmation_redirect') == '1');
    }
    
    /**
     * Handle ajax call for checking order status
     *
     * @param type $type
     * @param type $name
     * @param type $render
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmOnSelfCallFE($type, $name, &$render)
    {
        if ($name != $this->_name || $type != 'vmpayment') {
            return false;
        }
        
        $gingerOrder = $this->getGingerClient()->getOrder(vRequest::get('order_id'));
        $response = ['status' => 'not valid order','redirect' => true];
        
        if ($gingerOrder instanceof GingerPayments\Payment\Order) {
            if ($gingerOrder->getStatus() == 'processing') {
                $response = ['status' => $gingerOrder->getStatus(),'redirect' => false];
            } else {
                $response = ['status' => $gingerOrder->getStatus(),'redirect' => true];
            }
        }
        die(json_encode($response));
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
            return null;
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
        return true;
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

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
}
