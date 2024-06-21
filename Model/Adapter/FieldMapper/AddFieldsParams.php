<?php
declare(strict_types=1);

namespace PH2M\Elasticsearch\Model\Adapter\FieldMapper;

use Magento\Elasticsearch\Model\Adapter\FieldsMappingPreprocessorInterface;

class AddFieldsParams implements FieldsMappingPreprocessorInterface
{
    public function process(array $mapping): array
    {
        foreach ($mapping as $field => $definition) {
            if (!in_array($field, ['name', 'brand_value'])) {
                continue;
            }

            $definition['fields']['prefix'] = [
                'type' => 'text',
                'analyzer' => 'text_prefix',
            ];

            $mapping[$field] = $definition;
        }

        return $mapping;
    }
}
