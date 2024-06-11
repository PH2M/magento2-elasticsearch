<?php

use Elasticsearch\ClientBuilder;

require dirname(__DIR__) . '/app/bootstrap.php';
$magentoEnv = dirname(__DIR__) . '/app/etc/env.php';

$config = [
    'app' => require $magentoEnv,
];

if (
    empty($config['app']['system']['default']['catalog']['search']['elasticsearch7_server_hostname'])
    || empty($config['app']['system']['default']['catalog']['search']['elasticsearch7_server_port'])
    || empty($config['app']['system']['default']['catalog']['search']['elasticsearch7_index_prefix'])
) {
    echo json_encode([]);
    exit;
}

$hostName = $config['app']['system']['default']['catalog']['search']['elasticsearch7_server_hostname'];
$port = $config['app']['system']['default']['catalog']['search']['elasticsearch7_server_port'];
$indexPrefix = $config['app']['system']['default']['catalog']['search']['elasticsearch7_index_prefix'];

$clientBuilder = ClientBuilder::create();

if (
    !empty($config['app']['system']['default']['catalog']['search']['elasticsearch7_enable_auth'])
    && $config['app']['system']['default']['catalog']['search']['elasticsearch7_enable_auth'] === '1') {
    $clientBuilder->setBasicAuthentication(
        $config['app']['system']['default']['catalog']['search']['elasticsearch7_username'],
        $config['app']['system']['default']['catalog']['search']['elasticsearch7_password']
    );
}

$client = $clientBuilder->setHosts([$hostName . ':' . $port])
    ->build();

$termToSearch = mb_strtolower($_GET['q']);

$params = [
    'index' => $indexPrefix . '_product_' . $_GET['store'],
    'body' => [
        'query' => [
            'function_score' => [
                'query' => [
                    'bool' => [
                        'minimum_should_match' => 1,
                        'should' => [
                            [
                                'multi_match' => [
                                    'analyzer' => 'french_heavy',
                                    'query' => $termToSearch,
                                    'type' => 'cross_fields',
                                    'fields' => [
                                        'brand_value',
                                        'name.prefix^10',
                                        'name.keyword_prefix^20',
                                        'name.trigram^15',
                                        'name^5',
                                        'sku_to_search^50',
                                    ],
                                    'operator' => 'AND',
                                    'tie_breaker' => 0.1,
                                ],
//                                'bool' => [
//                                    'should' => [
//                                    [
//                                        'multi_match' => [
//                                            'analyzer' => 'french_heavy',
//                                            'query' => $termToSearch,
//                                            'type' => 'cross_fields',
//                                            'fields' => [
//                                                'brand_value',
//                                                'name.prefix^10',
//                                                'name.keyword_prefix^20',
//                                                'name^5',
//                                                'sku_to_search^50',
//                                            ],
//                                            'operator' => 'AND',
//                                            'tie_breaker' => 0.1,
//                                        ],
//                                    ],
//                                    [
//                                        'fuzzy' => [
//                                            'name' => [
//                                                'value' => $termToSearch,
//                                                'fuzziness' => 2,
//                                            ],
//                                        ],
//                                    ],
//                                    [
//                                        'fuzzy' => [
//                                            'brand_value' => [
//                                                'value' => $termToSearch,
//                                                'fuzziness' => 1,
//                                            ],
//                                        ],
//                                    ],
//                                ]
                            ]
                        ],
//                        [
//                            'multi_match' => [
//                                'analyzer' => 'french_heavy',
//                                'query' => $termToSearch,
//                                'type' => 'cross_fields',
//                                'fields' => [
//                                    'brand_value',
//                                    'brand_value.std',
//                                    'brand_value.std3',
//                                    'brand_value.keyword_prefix^5',
//                                    'brand_value.prefix',
//                                    'name^5',
//                                    'name.std3^5',
//                                    'name.std^5',
//                                    'name.prefix^5',
//                                    'name.suffix^5',
//                                    'name.trigram^5',
//                                    'sku_to_search',
//                                ],
//                                'operator' => 'AND',
//                                'tie_breaker' => 0.1,
//                            ],
//                        ],
//                            [
//                                'multi_match' => [
//                                    'query' => $termToSearch,
//                                    'type' => 'phrase_prefix',
//                                    'fields' => ['name.completion^5', 'brand_value.completion^5'],
//                                    'tie_breaker' => 0.1,
//                                ],
//                            ],
//                            [
//                                'prefix' => [
//                                    'sku_to_search' => [
//                                        'value' => $termToSearch,
//                                        'boost' => 50
//                                    ]
//                                ],
//                            ],
//                            [
//                                'keyword_prefix' => [
//                                    'name' => [
//                                        'value' => $termToSearch,
//                                        'boost' => 30
//                                    ]
//                                ],
//                            ],
//                            [
//                                'prefix' => [
//                                    'brand_value' => [
//                                        'value' => $termToSearch,
//                                        'boost' => 20
//                                    ]
//                                ],
//                            ],
//                        ],
                    ],
                ],
//                'boost_mode' => 'sum',
//                'functions' => [
//                    [
//                        'field_value_factor' => [
//                            'field' => 'product_weight',
//                            'factor' => 10,
//                            'modifier' => 'square',
//                            'missing' => 0,
//                        ],
//                    ],
//                ],
            ],
        ],
    ],
    'size' => 12,
    '_source' => ['name_to_display', 'sku', 'url_key', 'image', 'brand_value']
];

$results = $client->search($params);

// clean hits by return only _source
$results['hits']['hits'] = array_map(function ($hit) {
    $urlKey = is_array($hit['_source']['url_key']) ? $hit['_source']['url_key'][0] : $hit['_source']['url_key'];
    $hit['_source']['url_key'] = "/{$urlKey}.html";

    if (!empty($hit['_source']['image'])) {
        $hit['_source']['image'] = '/media/catalog/product' . $hit['_source']['image'];
    }

    return $hit['_source'];
}, $results['hits']['hits']);

echo json_encode($results['hits']['hits']);
