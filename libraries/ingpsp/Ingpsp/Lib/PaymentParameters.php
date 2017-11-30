<?php

namespace Ingpsp\Lib;

/**
 * Paymentparameters
 *
 * @author GingerPayments
 */
class PaymentParameters 
{

    public static $mapping = [
        'INGPSP_API_KEY' => 'apiKey',
        'INGPSP_LIB_BUNDLE_CA_CERT' => 'bundleCaCert',
        'INGPSP_LIB_PRODUCT' => 'ingPspProduct',
        'INGPSP_ALLOW_NOTIFICATIONS_FROM_X' => 'allowNotification',
        'INGPSP_STATUS_NEW' => 'statusNew',
        'INGPSP_STATUS_PENDING' => 'statusPending',
        'INGPSP_STATUS_PROCESSING' => 'statusProcessing',
        'INGPSP_STATUS_ERROR' => 'statusError',
        'INGPSP_STATUS_COMPLETED' => 'statusCompleted',
        'INGPSP_STATUS_CANCELED' => 'statusCanceled',
        'INGPSP_STATUS_EXPIRED' => 'statusExpired',
        'INGPSP_STATUS_ACCEPTED' => 'statusAccepted',
        'INGPSP_STATUS_CAPTURED' => 'statusCaptured',
        'INGPSP_ALLOWED_IP_ADDRESSES' => 'allowedIpAddresses',
        'INGPSP_TEST_API_KEY' => 'testApiKey'
    ];
    private $apiKey;
    private $bundleCaCert;
    private $ingPspProduct;
    private $allowNotification;
    private $statusNew;
    private $statusPending;
    private $statusProcessing;
    private $statusError;
    private $statusCompleted;
    private $statusCanceled;
    private $statusExpired;
    private $statusAccepted;
    private $statusCaptured;
    private $allowedIpAddresses;
    private $testApiKey;

    public function apiKey() 
    {
        return $this->apiKey;
    }

    public function bundleCaCert() 
    {
        return boolval($this->bundleCaCert);
    }

    public function ingPspProduct() 
    {
        return $this->ingPspProduct;
    }

    public function allowNotification() 
    {
        return $this->allowNotification;
    }

    public function statusNew() 
    {
        return $this->statusNew;
    }

    public function statusPending() 
    {
        return $this->statusPending;
    }

    public function statusProcessing() 
    {
        return $this->statusProcessing;
    }

    public function statusError() 
    {
        return $this->statusError;
    }

    public function statusCompleted() 
    {
        return $this->statusCompleted;
    }

    public function statusCanceled() 
    {
        return $this->statusCanceled;
    }

    public function statusExpired() 
    {
        return $this->statusExpired;
    }

    public function statusAccepted() 
    {
        return $this->statusAccepted;
    }

    public function statusCaptured() 
    {
        return $this->statusCaptured;
    }

    public function isApiKeyValid() 
    {
        return $this->apiKey !== null && strlen($this->apiKey) > 0;
    }

    /**
     * id addresses for klarna
     * 
     * @return null|array
     */
    public function allowedIpAddresses() 
    {
        if (empty($this->allowedIpAddresses)) {
            return null;
        }
        $addresses = explode(',', $this->allowedIpAddresses); 
        array_walk($addresses, 
                function(&$val) {   
                    return trim($val);
                });
        return $addresses;        
    }

    
    public function testApiKey() 
    {
        return $this->testApiKey;
    }

    public function getKlarnaApiKey() 
    {
        if (!empty($this->testApiKey)) {
            return $this->testApiKey;
        }
        return $this->apiKey;
    }
    
}
