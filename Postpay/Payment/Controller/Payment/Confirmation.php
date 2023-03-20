<?php
/**
 * Copyright © Postpay. All rights reserved.
 * See LICENSE for license details.
 */

namespace Postpay\Payment\Controller\Payment;

use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Postpay\Exceptions\RESTfulException;
use Postpay\Payment\Gateway\Config\Config;
use Postpay\Payment\Model\Adapter\AdapterInterface;
use Postpay\Serializers\Decimal;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

/**
 * Order capture controller.
 */
class Confirmation extends Action
{
    private Http $request;
    private AdapterInterface $postpayAdapter;
    private Order $order;
    private Config $config;
    private LoggerInterface $logger;
    private OrderSender $orderSender;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param CartManagementInterface $quoteManagement
     * @param CustomerSession $customerSession
     * @param Session $checkoutSession
     * @param Data $checkoutHelper
     */
    public function __construct(
        Context          $context,
        Http             $request,
        AdapterInterface $postpayAdapter,
        Order            $order,
        Config           $config,
        LoggerInterface  $logger,
        OrderSender      $orderSender,
    )
    {
        parent::__construct($context);
        $this->request = $request;
        $this->postpayAdapter = $postpayAdapter;
        $this->order = $order;
        $this->config = $config;
        $this->logger = $logger;
        $this->orderSender = $orderSender;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $postpayOrderId = $this->request->getParam('order_id');
        $orderId = explode("-", $postpayOrderId)[0];
        $order = $this->order->loadByIncrementId($orderId);
        /** @var \Magento\Quote\Model\Quote\Payment $payment */
        $payment = $order->getPayment();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $orderStatus = 'failure';
        $redirect = 'checkout/cart';
        $comment = 'Authorized but not Captured';

        $status = $this->postpayAdapter->getSingleOrder($postpayOrderId)['status']  ;

        if ($status === 'approved' || $status === 'captured') {
            try {
                $response = $this->postpayAdapter->capture($postpayOrderId);
                $amount = (new Decimal($response['total_amount']))->toFloat();
                $comment = 'Captured amount of ' . $amount. ' ' . $response['currency'] . ' Transaction ID : . ' . $response['order_id'];
                $payment->setTransactionId($orderId);
                $payment->setIsTransactionClosed(true);
                $payment->setTransactionAdditionalInfo(
                    Transaction::RAW_DETAILS,
                    [
                        'Status' => $response['status'],
                        'Amount' => $amount
                    ]
                );
                $this->messageManager->addSuccessMessage(
                    __('Your order Id %1. was created', $orderId)
                );

                $this->changeOrderStatus($order, 'success', $comment);
                return $resultRedirect->setPath('checkout/onepage/success');
            } catch (RESTfulException $exception) {
                $this->messageManager->addErrorMessage(
                    __('Capture error. Id %1. Code: %2.', $orderId, $exception->getErrorCode())
                );
                $this->changeOrderStatus($order, $orderStatus, $exception->getErrorCode());
                return $resultRedirect->setPath($redirect);
            }
        } else if ($status === 'pending') {
            $this->messageManager->addErrorMessage(
                __('Order is created but payment is pending. Id %1. Code: %2.', $order->getIncrementId(), 'Pending Payment')
            );
            return $resultRedirect->setPath('checkout/onepage/success');
        } else {
            $this->changeOrderStatus($order, $orderStatus, $comment);
            return $resultRedirect->setPath($redirect);
        }
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function sendEmail(Order $order)
    {
        try {
            $sent = $this->orderSender->send($order);

            if ($sent) {
                $order->addCommentToStatusHistory('Email sent to the customer');
            } else {
                $this->messageManager->addErrorMessage(
                    __('Unable to Send Order Confirmation Email for order . Id %1.', $order->getIncrementId())
                );
                $order->addCommentToStatusHistory('Unable to Send Order Confirmation Email for order');
            }
            $order->save();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
        return true;
    }


    /**
     * @param Order $order
     * @param $status
     * @return Order|void
     */
    public function changeOrderStatus(Order $order, $status, $comment)
    {
        try {
            if ($status === 'success') {
                $successStatus = $this->config->getCheckoutSuccessStatus();
                $order->setState($successStatus);
                $order->setStatus($successStatus);
                $order->addCommentToStatusHistory($comment);
                $order->save();

                $this->sendEmail($order);

                return $order;
            }
            $failureStatus = $this->config->getCheckoutFailureStatus();
            if ($failureStatus === Order::STATE_CANCELED) {
                $order->addCommentToStatusHistory('Canceled Order, due to.'.$comment);
                $order->cancel()->save();
                return $order;
            }

            $order->setState($failureStatus);
            $order->setStatus($failureStatus);
            $order->addCommentToStatusHistory($comment);
            $order->save();

            return $order;
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(
                __('Unable to change the status of the order. Id %1. Code: %2.', $order->getIncrementId(), $exception->getErrorCode())
            );
        }
    }
}
