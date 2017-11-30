<?php
namespace Ingpsp\Lib;

class Customer {

    private $address;
    private $address_type;
    private $country;
    private $email_address;
    private $first_name;
    private $last_name;
    private $merchant_customer_id;
    private $phone_numbers;
    private $locale;
    private $gender;
    private $birthdate;
    private $ip_address;

    function __construct($address, $address_type, $country, $email_address, $first_name, $last_name, $merchant_customer_id, $phone_numbers, $locale = null, $ipAddress = null, $gender = null, $birthdate  = null) {
        $this->address = $address;
        $this->address_type = $address_type;
        $this->country = $country;
        $this->email_address = $email_address;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->merchant_customer_id = $merchant_customer_id;
        $this->phone_numbers = $phone_numbers;
        $this->locale = $locale;
        $this->gender = $gender;
        $this->birthdate = $birthdate;
        $this->ip_address = $ipAddress;
    }

    public function getAddress() {
        return $this->address;
    }

    public function getAddressType() {
        return $this->address_type;
    }

    public function getCountry() {
        return $this->country;
    }

    public function getEmailAddress() {
        return $this->email_address;
    }

    public function getFirstname() {
        return $this->first_name;
    }

    public function getLastname() {
        return $this->last_name;
    }

    public function getMerchantCustomerId() {
        return $this->merchant_customer_id;
    }

    public function getPhoneNumbers() {
        return $this->phone_numbers;
    }

    public function getLocale() {
        return $this->locale;
    }

    public function getGender() {
        return $this->gender;
    }

    public function getBirthdate() {
        return $this->birthdate;
    }

    public function getIpAddress() {
        return $this->ip_address;
    }

    public function toArray() {
        $response = [];
        foreach ($this as $key => $value) {
            if (!empty($value)) {
                $response[$key] = $value;
            } 
        }
        return   \GingerPayments\Payment\Common\ArrayFunctions::withoutNullValues($response);
    }

}