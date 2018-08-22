<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/21
 * Time: 17:21
 */
require "lib/ElasticDriver.class.php";
$driver = new ElasticDriver();
//$params = [
//    'index' => 'es_php',
//    'body' => [
//        'mappings' => [
//            'students' => [
//                '_source' => ['enabled' => true],
//                'properties' => [
//                    'id' => ['type' => 'integer'],
//                    'age' => ['type' => 'integer'],
//                    'status' => ['type' => 'integer'],
//                    'match_name' => ['type' => 'string'],
//                    'match_sport' => ['type' => 'string']
//                ]
//            ],
//            'teachers' => [
//                '_source' => ['enabled' => true],
//                'properties' => [
//                    'id' => ['type' => 'integer'],
//                    'status' => ['type' => 'integer'],
//                    'match_name' => ['type' => 'string'],
//                    'match_major' => ['type' => 'string']
//                ]
//            ]
//        ]
//    ]
//];
//$driver->client()->indices()->delete(['index' => 'es_php']);
//$response = $driver->client()->indices()->create($params);
//$data = [];
//for ($i = 0; $i < 100; $i++) {
//    $data['body'][] = [
//        'index' => [
//            '_index' => 'es_php',
//            '_type' => 'students',
//        ]
//    ];
//
//    $data['body'][] = [
//        'id' => $i + 1,
//        'age' => $i % 2 ? 15 : 18,
//        'status' => $i % 3,
//        'match_name' => 'Clone boy:' . $i,
//        'match_sport' => $i % 2 ? 'football' : 'basketball'
//    ];
//}
//$responses = $driver->client()->bulk($data);
$_scroll_id = isset($_GET['_scroll_id'])? $_GET['_scroll_id']: '';
$keyword = isset($_GET['keyword'])? $_GET['keyword']: '';
$filter = isset($_GET['filter'])? $_GET['filter']: [];
$sort = isset($_GET['sort'])? $_GET['sort']: [];
try{
    $res = $driver->setType("students")->setQuery($keyword)->setFilter($filter)->setSort($sort)->getResultList($_scroll_id);
    header('Content-Type:application/json');
    echo json_encode(array('status'=>200, 'data'=>$res,"current_time"=>time()));
}catch (Exception $e){
    echo $e->getMessage();
}