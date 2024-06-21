# Simple Magento 2 search with ElasticSearch

## Installation
You need to install the analysis-icu plugin for ElasticSearch.

You have to lock the Elasticsearch configurations:
```
php bin/magento config:set --lock catalog/search/elasticsearch7_server_hostname <elasticsearch_hostname>
php bin/magento config:set --lock catalog/search/elasticsearch7_server_port <elasticsearch_port>
php bin/magento config:set --lock catalog/search/elasticsearch7_index_prefix <elasticsearch_index_prefix>
php bin/magento config:set --lock catalog/search/elasticsearch7_enable_auth <elasticsearch_enable_auth>
php bin/magento config:set --lock catalog/search/elasticsearch7_username <elasticsearch_username>
php bin/magento config:set --lock catalog/search/elasticsearch7_password <elasticsearch_password>
```

Copy file `es.php.sample` to your Magento `pub` directory and customize it if needed.
```
cp vendor/ph2m/magento2-elasticsearch/etc/es.php.sample pub/es.php
```

Update your `Magento_Theme/templates/html/header.phtml` file to include the search form.

```html
<input name="es_search"
   id="es_search"
   type="text"
   placeholder="<?php echo __('Search a product or a brand') ?>"
   class="!pr-[84px] w-full bg-gray-very-light-warm !h-18 text-dark placeholder:text-xxs placeholder:leading-5 focus:placeholder:font-normal !text-xl !leading-6.75"
   @keyup="runSearch()" />
<div class="absolute bottom-0 translate-y-full z-50 left-0 bg-white w-full grid grid-cols-4 gap-5 p-5 border" x-cloak x-show="results.length > 0">
    <template x-for="result in results" :key="result.sku">
        <a :href="result.url_key" class="flex items-center gap-3">
            <img :src="result.image" :alt="result.name_to_display" width="80" height="80" />
            <div class="flex flex-col">
                <span x-text="result.name_to_display"></span>
                <span class="text-xs" x-text="result.brand_value"></span>
            </div>
        </a>
    </template>
</div>
```

And at the end of the file add the following script:
```javascript
<script>
    function initSearch() {
        return {
            results: [],
            runSearch() {
                if (this.$el.value.length < 3) {
                    this.results = [];
                    return;
                }

                const url = BASE_URL + 'es.php?q=' + this.$el.value + '&store=' + CURRENT_STORE_ID;

                fetch(url, {
                    headers: { "content-type": "application/json" },
                    method: "GET",
                }).then(response => {
                    if (response.ok) {
                        return response.json();
                    }
                }).then(result => {
                    this.results = result;
                });
            }
        }
    }
</script>
```

After installation, reindex.
```
php bin/magento indexer:reindex
```

## Add a new object to search (brand, blog article etc.)
Let's imagine you have a module Vendor_Brand and you want to add brands to the search.

Update the `di.xml` to add the field mapper:
```xml
<type name="Magento\Elasticsearch\Model\Adapter\FieldMapper\FieldMapperResolver">
    <arguments>
        <argument name="fieldMappers" xsi:type="array">
            <item name="brand" xsi:type="string">Vendor\Brand\Model\Adapter\FieldMapper\BrandFieldMapper</item>
        </argument>
    </arguments>
</type>
```

Create the field mapper class:
```php
<?php
declare(strict_types=1);

namespace Vendor\Brand\Model\Adapter\FieldMapper;

use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;

class BrandFieldMapper implements FieldMapperInterface
{
    public function getFieldName($attributeCode, $context = [])
    {
        return $attributeCode;
    }

    public function getAllAttributesTypes($context = [])
    {
        return [
            'name' => [
                'type' => 'text',
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword',
                    ],
                ]
            ],
            'url_key' => [
                'type' => 'text',
            ],
        ];
    }
}
```

Declare a new indexer in `etc/indexer.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Indexer/etc/indexer.xsd">
    <indexer id="brandsearch_fulltext" view_id="brandsearch_fulltext" class="Vendor\Brand\Model\Indexer\Fulltext">
        <title translate="true">Brand Search</title>
        <description translate="true">Rebuild Brand fulltext search index</description>
    </indexer>
</config>
```

Declare the view in `etc/mview.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Mview/etc/mview.xsd">
    <view id="brandsearch_fulltext" class="Vendor\Brand\Model\Indexer\Fulltext" group="indexer">
        <subscriptions>
            <table name="brand" entity_column="brand_id"/>
        </subscriptions>
    </view>
</config>
```

Create the indexer class `Vendor\Brand\Model\Indexer\Fulltext`:
```php
<?php
declare(strict_types=1);

namespace Vendor\Brand\Model\Indexer;

use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Elasticsearch\Model\Adapter\Elasticsearch;
use Magento\Elasticsearch\Model\Adapter\Index\IndexNameResolver;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Vendor\Brand\Api\Data\BrandInterface;
use Vendor\Brand\Query\Brand\GetListQuery;
use Psr\Log\LoggerInterface;

class Fulltext implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected ClientResolver $clientResolver,
        protected GetListQuery $getListQuery,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected Elasticsearch $elasticsearchAdapter,
        protected LoggerInterface $logger,
        protected IndexNameResolver $indexNameResolver
    ) {
    }

    public function execute($ids)
    {
        $this->executeList($ids);
    }

    public function executeFull(): void
    {
        $this->elasticsearchAdapter->cleanIndex(1, 'brand');
        $this->executeList([]);
        $this->elasticsearchAdapter->updateAlias(1, 'brand');
    }

    public function executeList(array $ids): void
    {
        $searchCriteria = $this->searchCriteriaBuilder;

        if (!empty($ids)) {
            $searchCriteria->addFilter(BrandInterface::BRAND_ID, $ids, 'in');
        }

        $brands = $this->getListQuery->execute($searchCriteria->create())->getItems();

        $brandsToReindex = [];
        foreach ($brands as $brand) {
            $brandsToReindex[] = [
                'name' => $brand->getName(),
                'url_key' => $brand->getUrlKey(),
            ];
        }

        try {
            $this->elasticsearchAdapter->addDocs($brandsToReindex, 1, 'brand');
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function executeRow($id)
    {
        $this->executeList([$id]);
    }
}
```

Add a `save_after` observer in `etc/events.xml`:
```xml
<event name="brand_model_save_after">
    <observer name="brand_model_save_after" instance="Vendor\Brand\Observer\SaveAfter" />
</event>
```

And the corresponding class:
```php
<?php declare(strict_types=1);

namespace Vendor\Brand\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\StateInterface;
use Magento\Indexer\Model\IndexerFactory;

class SaveAfter implements ObserverInterface
{
    public function __construct(
        protected IndexerFactory $indexerFactory,
        protected \Vendor\Brand\Model\Indexer\Fulltext $fulltextIndexer
    ) {
    }

    public function execute(Observer $observer): void
    {
        $index = $this->indexerFactory->create()->load('brandsearch_fulltext');

        if ($index->isScheduled()) {
            $index->invalidate();
        } else {
            $this->fulltextIndexer->executeRow($observer->getData('object')->getBrandId());

            $state = $index->getState();
            $state->setStatus(StateInterface::STATUS_VALID);
            $state->save();
            $index->setState($state);
        }
    }
}
```

## Hyvä ready
This module is designed for [Hyvä](https://www.hyva.io/) out of the box.
