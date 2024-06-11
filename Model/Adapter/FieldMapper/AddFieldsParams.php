<?php
declare(strict_types=1);

namespace PH2M\Elasticsearch\Model\Adapter\FieldMapper;

use Magento\Elasticsearch\Model\Adapter\FieldsMappingPreprocessorInterface;

class AddFieldsParams implements FieldsMappingPreprocessorInterface
{
    public function process(array $mapping): array
    {
        foreach ($mapping as $field => $definition) {
            if ($field !== 'name') {
                continue;
            }

//            $definition['analyzer'] = 'french_heavy';

            $definition['fields']['keyword_prefix'] = [
                'type' => 'text',
                'analyzer' => 'keyword_prefix',
            ];
            $definition['fields']['prefix'] = [
                'type' => 'text',
                'analyzer' => 'text_prefix',
            ];

            $mapping[$field] = $definition;
        }

        return $mapping;
    }
}
