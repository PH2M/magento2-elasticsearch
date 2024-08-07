<?php
/**
 * Copyright © PH2M SARL. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace PH2M\Elasticsearch\Plugin\Adapter\Index;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Builder
{
    public function __construct(
        protected ScopeConfigInterface $scopeConfig
    ) {
    }

    public function afterBuild(\Magento\Elasticsearch\Model\Adapter\Index\Builder $subject, array $result): array
    {
        if (empty($result['analysis']['analyzer'])) {
            return $result;
        }

        $result['analysis']['analyzer']['sku']['filter'] = [
            'lowercase',
            'asciifolding'
        ];

        $result['analysis']['analyzer']['sku']['tokenizer'] = 'default_tokenizer';
        $result['analysis']['analyzer']['sku_prefix_search']['tokenizer'] = 'default_tokenizer';

        $result['analysis']['analyzer']['text_prefix'] = [
            'tokenizer' => 'standard',
            'char_filter' => 'html_strip',
            'filter' => [
                "elision",
                "asciifolding",
                "lowercase",
                "stop",
                "edge_ngram_front",
            ],
        ];

        $result['analysis']['filter']["french_elision"] = [
            "type" => "elision",
            "articles_case" => true,
            "articles" => ["l", "m", "t", "qu", "n", "s", "j", "d", "c", "jusqu", "quoiqu", "lorsqu", "puisqu"],
        ];

        $result['analysis']['filter']["strip_spaces"] = [
            "pattern" => "\\s",
            "type" => "pattern_replace",
            "replacement" => ""
        ];
        $result['analysis']['filter']["strip_dots"] = [
            "pattern" => "\\.",
            "type" => "pattern_replace",
            "replacement" => ""
        ];
        $result['analysis']['filter']["strip_hyphens"] = [
            "pattern" => "-",
            "type" => "pattern_replace",
            "replacement" => ""
        ];
        $result['analysis']['filter']["edge_ngram_front"] = [
            "min_gram" => "3",
            "side" => "front",
            "type" => "edgeNGram",
            "max_gram" => "20"
        ];

        $synonyms = $this->getSynonyms();

        if (!empty($synonyms)) {
            $result['analysis']['filter']["french_synonym"] = [
                "type" => "synonym_graph",
                "synonyms" => $synonyms,
                "expand" => true,
            ];
        }

        $result['analysis']['filter']["stop_french"] = [
            "type" => "stop",
            "ignore_case" => true,
            "stopwords" => [ "_french_" ]
        ];

        $result['analysis']['analyzer']['std3'] = [
            'tokenizer' => 'icu_tokenizer',
            'char_filter' => 'html_strip', // strip html tags
            'filter' => [
                'french_elision',
                'icu_folding',
                "lowercase",
                "stop",
                "length",
                "french_synonym",
                'default_stemmer',
                'strip_spaces',
                'strip_dots',
                'strip_hyphens'
            ],
        ];

        return $result;
    }

    protected function getSynonyms(): array
    {
        $config = $this->scopeConfig->getValue('catalog/search/synonyms', ScopeInterface::SCOPE_STORE);

        if (empty($config)) {
            return [];
        }

        return explode("\r\n", $config);
    }
}
