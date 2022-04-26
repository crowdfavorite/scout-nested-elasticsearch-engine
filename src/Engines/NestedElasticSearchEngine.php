<?php

namespace CF\Scout\Engines;

use Laravel\Scout\Builder;
use Tamayo\LaravelScoutElastic\Engines\ElasticsearchEngine;

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
        $nestedFilters = [];

        $queryStrings[] = [
            'query_string' => [
                '_all' => [
                    'query' => $query->query,
                    'fuzziness' => 0
                ]
            ],
        ];

        if (array_key_exists('numericFilters', $options) && $options['numericFilters']) {
            foreach ($options['numericFilters'] as $i => $value) {
                $field = key($value['terms']);
                $type = $value['terms'][$field]['type'];
                if (is_array($value)) {
                    $value = isset($value['terms'][$field]['value']) ? $value['terms'][$field]['value'] : $value;

                }

                // Nested query
                if (strpos($field, '.') !== false) {
                    $path = substr($field, 0, strpos($field, '.'));
                    $paths[$path][] = [
                        'field' => $field,
                        'value' => $value,
                        'type' => $type,
                    ];
                } elseif (is_numeric($value) || 'filter' == $type) {
                    $termFilters[ $field ][] = $value;
                } elseif (is_string($value)) {
                    $queryStrings[] = [
                        'match' => [
                            $field => [
                                'query' => $value,
                                'operator' => 'and'
                            ]
                        ]
                    ];
                }
            }

            if (!empty($paths)) {
                foreach ($paths as $path => $path_data) {
                    $matches = [];
                    foreach ($path_data as $field_value) {
                        $type = $field_value['type'];
                        if (is_numeric($field_value['value']) || 'filter' == $type) {
                            $matches['terms'][] = [
                                $field_value['field'] => $field_value['value']
                            ];
                        } elseif ('range' == $type) {
                            $matches['range'][] = [
                                $field_value['field'] => $field_value['value']
                            ];
                        } elseif ('query_string' == $type) {
                            $mainQueries['nested'][$path][] = [
                                $field_value['field'] => $field_value['value'].'*'
                            ];
                        } else {
                            $matches['match'][] = [
                                $field_value['field'] => $field_value['value']
                            ];
                        }
                    }

                    if (!empty($matches['terms'])) {
                        $nestedFilters[$path]['terms'] = $matches['terms'];
                    }
                    if (!empty($matches['range'])) {
                        $nestedFilters[$path]['range'] = $matches['range'];
                    }
                    if (!empty($matches['match'])) {
                        $queryStrings[]['nested'] = [
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
        }

        if (!empty($mainQueries)) {
            foreach ($mainQueries as $type => $pathData) {
                if ('nested' == $type) {
                    foreach ($pathData as $path => $queryData) {
                        $tmpQuery = [];
                        $tmpQuery['path'] = $path;
                        $tmpQuery['query'] = [
                            'bool' => [
                                'should' => []
                            ]
                        ];
                        foreach ($queryData as $match) {
                            foreach ($match as $key => $value) {
                                $tmpQuery['query']['bool']['should'][] = [
                                    'query_string' => [
                                        'default_field' => $key,
                                        'query' => $value
                                    ]
                                ];
                            }
                        }

                        if (!empty($tmpQuery)) {
                            $queryStrings[]['nested'] = $tmpQuery;
                        }
                    }
                }
            }
        }

        if (1 == count($queryStrings)) {
            // Scout doesnt accept single element arrays
            $queryStrings = array_shift($queryStrings);
        }

        $searchQuery = [
            'index' => $this->index,
            'type'  => $query->model->searchableAs(),
            'body' => [
                'query' => [
                    'filtered' => [
                        'filter' => [],
                        'query' => [
                            'bool' => [
                                'should' => $queryStrings
                            ]
                        ],
                    ],
                ],
            ],
        ];

        $terms = array();
        $nested = array();

        if (!empty($termFilters)) {
            $terms = $termFilters;
        }
        if (!empty($nestedFilters)) {
            foreach ($nestedFilters as $path => $pathdata) {
                $nested['path'] = $path;
                $nested['filter'] = [
                    'bool' => [
                        'must' => [

                        ]
                    ],
                ];
                foreach ($pathdata as $filter_type => $data) {
                    foreach ($data as $datum) {
                        $nested['filter']['bool']['must'][] = [$filter_type => $datum];
                    }
                }
            }
        }

        if (!empty($terms) && !empty($nested)) {
            $searchQuery['body']['query']['filtered']['filter']['bool']['must'][]['terms'] = $terms;
            $searchQuery['body']['query']['filtered']['filter']['bool']['must'][]['nested'] = $nested;
        } else if (!empty($terms)) {
            $searchQuery['body']['query']['filtered']['filter']['terms'] = $terms;
        } else if (!empty($nested)) {
            $searchQuery['body']['query']['filtered']['filter']['nested'] = $nested;
        }

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

        if (!empty($query->orders)) {
            $searchQuery['sort'] = [];
            foreach ($query->orders as $order) {
                $searchQuery['sort'][] = "{$order['column']}:{$order['direction']}";
            }
        }

        return $this->elastic->search($searchQuery);
    }

}
