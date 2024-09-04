<?php
declare(strict_types=1);

namespace PH2M\Elasticsearch\Plugin\Index;

use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Magento\Config\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Indexer\Model\IndexerFactory;
use Psr\Log\LoggerInterface;

class InvalidateOnConfigChange
{
    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
        protected IndexerFactory $indexerFactory,
        protected LoggerInterface $logger,
    ) {
    }

    public function beforeSave(Config $subject)
    {
        try {
            $savedSection = $subject->getSection();

            if ($savedSection !== 'catalog') {
                return null;
            }

            $synonymsValue = 'catalog/search/synonyms';
            $path = explode('/', $synonymsValue);
            $section = $path[0];
            $group = $path[1];
            $field = $path[2];

            if (isset($subject['groups'][$group]['fields'][$field])) {
                $savedField = $subject['groups'][$group]['fields'][$field];
                $beforeValue = $this->scopeConfig->getValue($synonymsValue);
                $afterValue = $savedField['value'] ?? $savedField['inherit'] ?? null;
                if ($beforeValue != $afterValue) {
                    $this->indexerFactory->create()->load(Fulltext::INDEXER_ID)->invalidate();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error during catalogsearch_fulltext invalidation on config save: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        return null;
    }
}
