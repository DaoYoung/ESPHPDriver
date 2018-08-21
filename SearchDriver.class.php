<?php

/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2016/11/9
 * Time: 14:29
 */
abstract class SearchDriver
{
    const SORT_DEFAULT = 'default';
    const SORT_NEWEST = 'newest';
    const SORT_PRICE_DESC = 'price_desc';
    const SORT_PRICE_ASC = 'price_asc';
    const SORT_HOT = 'hot';
    const SORT_SOLD_COUNT = 'sold_count';
    const SORT_CPM = 'cpm';
    
    /**
     * 设置索引名
     * @param $index_name
     *
     */
    abstract function setType($type_name, $cpm_count=0, $is_cpm_only=false);

    abstract function setSort($sort_type);

    abstract function setFilter($filter_array);

//    abstract function setPage($page, $page_size=20);

    /**
     * 设置文字匹配条件
     * @param $query_string
     *
     */
    abstract function setQuery($query_string);
    abstract function setPage($page=1);
    abstract function setExtendFields($field='');

    /**
     * 普通查询，返回查询结果
     * @return mixed
     */
    abstract function getResultList();

    /**
     * 聚合查询
     * @return mixed
     */
    abstract function getAggsData();

    abstract function writeLog($type);

}