<?php

namespace CF\Scout\Engines;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\ElasticsearchEngine;

class NestedElasticSearchEngine extends ElasticsearchEngine
{

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $query
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $query, array $options = [])
    {
        $termFilters = [];

        $matchQueries[] = [
            'match' => [
                '_all' => [
                    'query' => $query->query,
                    'fuzziness' => 1
                ]
            ],
        ];

        if (array_key_exists('filters', $options) && $options['filters']) {
            $paths = [];
            foreach ($options['filters'] as $field => $value) {

                // Nested query
                if(strpos($field, '.') !== false) {
                    $path = substr($field, 0, strpos($field, '.'));
                    $paths[$path][] = [
                        'field' => $field,
                        'value' => $value,
                    ];
                } elseif(is_numeric($value)) {
                    $termFilters[] = [
                        'term' => [
                            $field => $value,
                        ],
                    ];
                } elseif(is_string($value)) {
                    $matchQueries[] = [
                        'match' => [
                            $field => [
                                'query' => $value,
                                'operator' => 'and'
                            ]
                        ]
                    ];
                }
            }
            if(!empty($paths)) {
                foreach ($paths as $path => $path_data) {
                    $matches = [];
                    foreach ($path_data as $field_value) {
                        $matches[]['match'] = [
                            $field_value['field'] => $field_value['value']
                        ];
                    }
                    $matchQueries[]['nested'] = [
                        'path' => $path,
                        'score_mode' => 'max',
                        'query' => [
                            'bool' => [
                                'must' => $matches
                            ],
                        ],
                    ];
                }
            }
        }

        $searchQuery = [
            'index' =>  $this->index,
            'type'  =>  $query->model->searchableAs(),
            'body' => [
                'query' => [
                    'filtered' => [
                        'filter' => $termFilters,
                        'query' => [
                            'bool' => [
                                'must' => $matchQueries
                            ]
                        ],
                    ],
                ],
            ],
        ];

        if (array_key_exists('size', $options)) {
            $searchQuery = array_merge($searchQuery, [
                'size' => $options['size'],
            ]);
        }

        if (array_key_exists('from', $options)) {
            $searchQuery = array_merge($searchQuery, [
                'from' => $options['from'],
            ]);
        }

        return $this->elasticsearch->search($searchQuery);
    }

}
