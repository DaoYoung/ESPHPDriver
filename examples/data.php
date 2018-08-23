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
                    'match_major' => ['type' => 'string'],
                    'match_sport' => ['type' => 'string']
                ]
            ]
        ]
    ]
];
$driver->client()->indices()->delete(['index' => 'es_php']);
$response = $driver->client()->indices()->create($params);
$data = [];
$sports = ["football"=>1,"basketball"=>1,"ping pong"=>1,"swim"=>1,"fish"=>1,"race"=>1,"video game"=>1,"sing"=>1,"dance"=>1];
$majors = ["math","physical","chemistry","english","history"];
for ($i = 0; $i < 100; $i++) {
    $data['body'][] = [
        'index' => [
            '_index' => 'es_php',
            '_type' => 'students',
        ]
    ];
    $data['body'][] = [
        'id' => $i + 1,
        'age' => ($i % 2 * 5 + $i%10),
        'status' => $i % 3,
        'match_name' => 'Clone boy:' . $i,
        'match_sport' => implode(',', array_rand($sports,3))
    ];
    $data['body'][] = [
        'index' => [
            '_index' => 'es_php',
            '_type' => 'teachers',
        ]
    ];
    shuffle($majors);
    $data['body'][] = [
        'id' => $i + 1,
        'status' => $i % 3,
        'match_name' => 'Clone teacher:' . $i,
        'match_major' => $majors[0],
        'match_sport' => implode(',', array_rand($sports,3))
    ];
}
$responses = $driver->client()->bulk($data);
exit("finished");