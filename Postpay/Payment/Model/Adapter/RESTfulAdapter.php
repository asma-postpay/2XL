<?php
/**
 * Copyright © Postpay. All rights reserved.
 * See LICENSE for license details.
 */
namespace Postpay\Payment\Model\Adapter;

/**
 * Postpay RESTful Adapter.
 */
class RESTfulAdapter extends ApiAdapter implements AdapterInterface
{
    /**
     * Send a POST request and return the response.
     *
     * @param string $path
     * @param array $params
     *
     * @return array
     *
     * @throws \Postpay\Exceptions\ApiException
     */
    public function post($path, array $params = [])
    {
        return $this->request('POST', $path, $params);
    }

    public function get($path ,  array $params = []){

        return $this->request('GET', $path , $params);
    }

    /**
     * @inheritdoc
     */
    public function checkout(array $params)
    {
        return $this->post('/checkouts', $params);
    }

    /**
     * @inheritdoc
     */
    public function capture($id)
    {
        return $this->post('/orders/' . $id . '/capture');
    }

    /**
     * @inheritdoc
     */
    public function refund($id, $refundId, $amount)
    {
        return $this->post('/orders/' . $id . '/refunds', [
            'refund_id' => $refundId,
            'amount' => self::decimal($amount)
        ]);
    }

    public function getSingleOrder($orderId)
    {
        return $this->get('/orders/' . $orderId);
    }
}
