<?php
/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/8/22
 * Time: 19:59
 */
require "../lib/ElasticDriver.class.php";
$driver = new ElasticDriver();
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : [];
try {
    $res = $driver->setQuery($keyword, true)->getTips($filter);
    header('Content-Type:application/json');
    echo json_encode(array('status' => 200, 'data' => $res, "current_time" => time()));
} catch (Exception $e) {
    header('Content-Type:application/json');
    echo $e->getMessage();
}