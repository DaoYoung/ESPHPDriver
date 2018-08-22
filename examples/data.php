<?php
/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/8/22
 * Time: 19:59
 */

require "../lib/ElasticDriver.class.php";
$driver = new ElasticDriver();
$params = [
    'index' => 'es_php',
    'body' => [
        'mappings' => [
            'students' => [
                '_source' => ['enabled' => true],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'age' => ['type' => 'integer'],
                    'status' => ['type' => 'integer'],
                    'match_name' => ['type' => 'string'],
                    'match_sport' => ['type' => 'string']
                ]
            ],
            'teachers' => [
                '_source' => ['enabled' => true],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'status' => ['type' => 'integer'],
                    'match_name' => ['type' => 'string'],
                    'match_major' => ['type' => 'string']
                ]
            ]
        ]
    ]
];
$driver->client()->indices()->delete(['index' => 'es_php']);
$response = $driver->client()->indices()->create($params);
$data = [];
for ($i = 0; $i < 100; $i++) {
    $data['body'][] = [
        'index' => [
            '_index' => 'es_php',
            '_type' => 'students',
        ]
    ];

    $data['body'][] = [
        'id' => $i + 1,
        'age' => $i % 2 ? 15 : 18,
        'status' => $i % 3,
        'match_name' => 'Clone boy:' . $i,
        'match_sport' => $i % 2 ? 'football' : 'basketball'
    ];
}
$responses = $driver->client()->bulk($data);