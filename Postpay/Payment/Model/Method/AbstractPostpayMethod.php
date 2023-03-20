<?php
/**
 * Copyright © Postpay. All rights reserved.
 * See LICENSE for license details.
 */
namespace Postpay\Payment\Model\Method;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order\Payment\Transaction;
use Postpay\Payment\Model\Adapter\AdapterInterface;
use Postpay\Serializers\Decimal;

/**
 * Postpay payment method abstract class.
 */
abstract class AbstractPostpayMethod extends AbstractMethod
{
    /**
     * Postpay transaction ID key
     */
    const TRANSACTION_ID_KEY = 'postpay_id';

    /**
     * Number of instalments
     */
    const NUM_INSTALMENTS = null;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = false;

    /**
     * Flag if we need to run payment initialize while order place
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var AdapterInterface
     */
    private $postpayAdapter;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param AdapterInterface $postpayAdapter
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        AdapterInterface $postpayAdapter,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->postpayAdapter = $postpayAdapter;
    }

    /**
     * Capture an order.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Postpay\Exceptions\ApiException
     */
    public function capture(InfoInterface $payment, $amount)
    {
        $id = $payment->getAdditionalInformation(self::TRANSACTION_ID_KEY);
        $response = $this->postpayAdapter->capture($id);

        $payment->setTransactionId($id);
        $payment->setIsTransactionClosed(false);
        $payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            [
                'Status' => $response['status'],
                'Amount' => (new Decimal($response['total_amount']))->toFloat()
            ]
        );
        return $this;
    }

    /**
     * Capture an order.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Postpay\Exceptions\ApiException
     */
    public function getSingleOrder($orderId)
    {

        $response = $this->postpayAdapter->capture($orderId);

        $payment->setTransactionId($id);
        $payment->setIsTransactionClosed(false);
        $payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            [
                'Status' => $response['status'],
                'Amount' => (new Decimal($response['total_amount']))->toFloat()
            ]
        );
        return $this;
    }

    /**
     * Refund a capture transaction.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     *
     * @throws \Postpay\Exceptions\ApiException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $id = $payment->getParentTransactionId();
        $refundId = $payment->getOrder()->getIncrementId() . '-' . uniqid();
        $this->postpayAdapter->refund($id, $refundId, $amount);

        $payment->setTransactionId($refundId);
        $payment->setParentTransactionId($id);
        $payment->setIsTransactionClosed(true);
        return $this;
    }
}
