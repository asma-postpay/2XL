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
               $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
               $storeId = $storeManager->getStore()->getId();
               $productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
               $product = $productRepository->getById($item->getProductId(), false, $storeId);
               /** @var Image $imageHelper */
               $imageHelper = $objectManager->get(Image::class);

               return [
                   'reference' => $product->getId(),
                   'name' => $product->getName(),
                   'description' => $product->getDescription() ? substr($product->getDescription(), 0, 1024):"",
                   'url' => $product->getProductUrl(),
                   'image_url' => $imageHelper->init($product, 'product_base_image')->getUrl(),
                   'unit_price' => ApiAdapter::decimal($item->getBasePrice()),
                   'qty' => $item->getQty(),
               ];
    }
}
