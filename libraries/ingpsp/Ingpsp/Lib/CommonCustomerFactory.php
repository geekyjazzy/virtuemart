<?php
namespace Ingpsp\Lib;

/**
 * CommonCustomerFactory
 *
 * @author GingerPayments
 */
class CommonCustomerFactory 
{

    public static function create(\stdClass $billingInfo, $locale = null, $ipAddress = null, $gender = null, $birthdate = null) 
    { 
        return new Customer(
                implode("\n", array_filter(array(
                    $billingInfo->address_1,
                    $billingInfo->house_no,
                    //$this->billingInfo->first_name . " " . $this->billingInfo->last_name,
                    $billingInfo->zip . " " . $billingInfo->city,
                ))), 'billing', 
                    \shopFunctions::getCountryByID($billingInfo->virtuemart_country_id, 'country_2_code'), 
                    $billingInfo->email, 
                    $billingInfo->first_name, 
                    $billingInfo->last_name, 
                    $billingInfo->virtuemart_user_id, 
                    array_values(array_unique(array_values([
                        $billingInfo->phone_1,
                        $billingInfo->phone_2,
                    ]))), 
                    $locale,
                    $ipAddress, 
                    $gender,
                    $birthdate
        );
    }

}