<?php
/**
* Copyright © PH2M SARL. All rights reserved.
* See LICENSE for license details.
*/
declare(strict_types=1);

namespace PH2M\Elasticsearch\Model\Adapter\BatchDataMapper;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class ToDisplayFieldsProvider implements AdditionalFieldsProviderInterface
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function getFields(array $productIds, $storeId)
    {
        $fields = [];

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->addFilter('store_id', $storeId)
        ;
        $products = $this->productRepository->getList($searchCriteria->create())->getItems();

        /** @var ProductInterface $product */
        foreach ($products as $product) {
            $fields[$product->getId()] = [
                'name_to_display' => $product->getName(),
                'sku_to_search' => $product->getSku(),
                'image' => $product->getImage()
            ];
        }

        return $fields;
    }
}
