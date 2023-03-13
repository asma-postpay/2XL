<?php
/**
 * Copyright © Postpay. All rights reserved.
 * See LICENSE for license details.
 */
namespace Postpay\Payment\Model\Request;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Postpay\Payment\Model\Adapter\ApiAdapter;

/**
 * Add item information to checkout request.
 */
class Item
{
    /**
     * Build request.
     *
     * @param QuoteItem $item
     *
     * @return array
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function build(QuoteItem $item)
    {
        $objectManager = ObjectManager::getInstance();
        /** @var ProductFactory $productFactory */
        $productFactory = $objectManager->get(ProductFactory::class);
        $product = $productFactory->create()->load($item->getProductId());
        /** @var Image $imageHelper */
        $imageHelper = $objectManager->get(Image::class);
        $productDescription = '';
        if ($product->getDescription()) {
            $productDescription = substr($product->getDescription(), 0, 1024);
        }
        return [
            'reference' => $item->getProduct()->getId(),
            'name' => $item->getName(),
            'description' => $product->getDescription() ? substr($product->getDescription(), 0, 1024):"",
            'url' => $product->getProductUrl(),
            'image_url' => $imageHelper->init($product, 'product_base_image')->getUrl(),
            'unit_price' => ApiAdapter::decimal($item->getBasePrice()),
            'qty' => $item->getQty(),
        ];
    }
}
