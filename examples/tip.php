<?php
/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/8/22
 * Time: 19:59
 */
require "../lib/ElasticDriver.class.php";
$driver = new ElasticDriver();
$_scroll_id = isset($_GET['_scroll_id']) ? $_GET['_scroll_id'] : '';
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : [];
$sort = isset($_GET['sort']) ? $_GET['sort'] : [];
try {
    $res = $driver->setQuery($keyword)->getTips();
    header('Content-Type:application/json');
    echo json_encode(array('status' => 200, 'data' => $res, "current_time" => time()));
} catch (Exception $e) {
    echo $e->getMessage();
}