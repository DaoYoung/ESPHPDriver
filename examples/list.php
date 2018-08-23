<?php
/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/8/22
 * Time: 19:58
 */
require "../lib/ElasticDriver.class.php";
$driver = new ElasticDriver();
$_scroll_id = isset($_GET['scroll_id']) ? $_GET['scroll_id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : [];
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
$per_page = isset($_GET['per_page']) ? $_GET['per_page'] : 4;
try {
    $res = $driver->setType($type)->setQuery($keyword)->setFilter($filter)->setSort($sort)->getResultList($_scroll_id, $per_page);
    header('Content-Type:application/json');
    echo json_encode(array('status' => 200, 'data' => $res, "current_time" => time()));
} catch (Exception $e) {
    header('Content-Type:application/json');
    echo $e->getMessage();
}