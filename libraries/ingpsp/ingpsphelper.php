<?php

defined('_JEXEC') or die('Restricted access');

class IngpspHelper
{
    
    /**
     * @param string $amount
     * @return int
     * @since v1.0.0
     */
    public static function getAmountInCents($amount)
    {
        return (int) round($amount * 100);
    }

    /**
     * @return mixed
     * @since v1.0.0
     */
    public static function getLocale()
    {
        $lang = JFactory::getLanguage();
        return str_replace('-', '_', $lang->getTag());
    }

    /**
     * Method obtains plugin information from the manifest file
     *
     * @param string $name
     * @return string
     * @since v1.0.0
     */
    public static function getPluginVersion($name)
    {
        $xml = JFactory::getXML(JPATH_SITE."/plugins/vmpayment/{$name}/{$name}.xml");

        return sprintf('Joomla Virtuemart v%s', (string) $xml->version);
    }
    
    /**
     * @param string $orderId
     * @return type
     * @since v1.0.0
     */
    public static function getOrderDescription($orderId) 
    {
        return sprintf(\JText::_("INGPSP_LIB_ORDER_DESCRIPTION"), $orderId, JFactory::getConfig()->get('sitename'));
    }
    
     /**
     * @param string $orderId
     * @return type
     * @since v1.0.0
     */
    public static function getReturnUrl($orderId) 
    {
        return sprintf('%s?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=%d', \JURI::base(), intval($orderId));
    }
    
}
