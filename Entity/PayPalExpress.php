<?php

namespace Plugin\PayPalExpress\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PayPalExpress
 */
class PayPalExpress extends \Eccube\Entity\AbstractEntity
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $api_user;

    /**
     * @var string
     */
    private $api_password;

    /**
     * @var string
     */
    private $api_signature;

    /**
     * @var boolean
     */
    private $use_express_btn;

    /**
     * @var string
     */
    private $corporate_logo;

    /**
     * @var string
     */
    private $border_color;

    /**
     * @var boolean
     */
    private $use_sandbox;

    /**
     * @var string
     */
    private $paypal_logo;

    /**
     * @var string
     */
    private $payment_paypal_logo;

    /**
     * @var integer
     */
    private $is_configured;

    /**
     * @var integer
     */
    private $payment_id;

    /**
     * @var string
     */
    private $in_context;

    /**
     * Set id
     *
     * @param integer $id
     * @return PayPalExpress
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set api_user
     *
     * @param string $apiUser
     * @return PayPalExpress
     */
    public function setApiUser($apiUser)
    {
        $this->api_user = $apiUser;

        return $this;
    }

    /**
     * Get api_user
     *
     * @return string
     */
    public function getApiUser()
    {
        return $this->api_user;
    }

    /**
     * Set api_password
     *
     * @param string $apiPassword
     * @return PayPalExpress
     */
    public function setApiPassword($apiPassword)
    {
        $this->api_password = $apiPassword;

        return $this;
    }

    /**
     * Get api_password
     *
     * @return string
     */
    public function getApiPassword()
    {
        return $this->api_password;
    }

    /**
     * Set api_signature
     *
     * @param string $apiSignature
     * @return PayPalExpress
     */
    public function setApiSignature($apiSignature)
    {
        $this->api_signature = $apiSignature;

        return $this;
    }

    /**
     * Get api_signature
     *
     * @return string
     */
    public function getApiSignature()
    {
        return $this->api_signature;
    }

    /**
     * Set use_express_btn
     *
     * @param boolean $useExpressBtn
     * @return PayPalExpress
     */
    public function setUseExpressBtn($useExpressBtn)
    {
        $this->use_express_btn = $useExpressBtn;

        return $this;
    }

    /**
     * Get use_express_btn
     *
     * @return boolean
     */
    public function getUseExpressBtn()
    {
        return $this->use_express_btn;
    }

    /**
     * Set corporate_logo
     *
     * @param string $corporateLogo
     * @return PayPalExpress
     */
    public function setCorporateLogo($corporateLogo)
    {
        $this->corporate_logo = $corporateLogo;

        return $this;
    }

    /**
     * Get corporate_logo
     *
     * @return string
     */
    public function getCorporateLogo()
    {
        return $this->corporate_logo;
    }

    /**
     * Set border_color
     *
     * @param string $borderColor
     * @return PayPalExpress
     */
    public function setBorderColor($borderColor)
    {
        $this->border_color = $borderColor;

        return $this;
    }

    /**
     * Get border_color
     *
     * @return string
     */
    public function getBorderColor()
    {
        return $this->border_color;
    }

    /**
     * Set use_sandbox
     *
     * @param boolean $useSandbox
     * @return PayPalExpress
     */
    public function setUseSandbox($useSandbox)
    {
        $this->use_sandbox = $useSandbox;

        return $this;
    }

    /**
     * Get use_sandbox
     *
     * @return boolean
     */
    public function getUseSandbox()
    {
        return $this->use_sandbox;
    }

    /**
     * Set paypal_logo
     *
     * @param string $paypalLogo
     * @return PayPalExpress
     */
    public function setPaypalLogo($paypalLogo)
    {
        $this->paypal_logo = $paypalLogo;

        return $this;
    }

    /**
     * Get paypal_logo
     *
     * @return string
     */
    public function getPaypalLogo()
    {
        return $this->paypal_logo;
    }

    /**
     * Set payment_paypal_logo
     *
     * @param string $paymentPaypalLogo
     * @return PayPalExpress
     */
    public function setPaymentPaypalLogo($paymentPaypalLogo)
    {
        $this->payment_paypal_logo = $paymentPaypalLogo;

        return $this;
    }

    /**
     * Get payment_paypal_logo
     *
     * @return string
     */
    public function getPaymentPaypalLogo()
    {
        return $this->payment_paypal_logo;
    }

    /**
     * Set is_configured
     *
     * @param integer $isConfigured
     * @return PayPalExpress
     */
    public function setIsConfigured($isConfigured)
    {
        $this->is_configured = $isConfigured;

        return $this;
    }

    /**
     * Get is_configured
     *
     * @return integer
     */
    public function getIsConfigured()
    {
        return $this->is_configured;
    }

    /**
     * Set payment_id
     *
     * @param integer $paymentId
     * @return PayPalExpress
     */
    public function setPaymentId($paymentId)
    {
        $this->payment_id = $paymentId;

        return $this;
    }

    /**
     * Get payment_id
     *
     * @return integer
     */
    public function getPaymentId()
    {
        return $this->payment_id;
    }

    /**
     * Set in_context
     *
     * @param string $inContext
     * @return PayPalExpress
     */
    public function setInContext($inContext)
    {
        $this->in_context = $inContext;

        return $this;
    }

    /**
     * Get in_context
     *
     * @return string
     */
    public function getInContext()
    {
        return $this->in_context;
    }

}
