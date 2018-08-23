<?php

/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/1/8
 * Time: 14:03
 */
abstract class ElasticIndex
{
    /** 获取返回字段 */
    abstract function getFields();

    /** 获取过滤规则 */
    abstract function getFilterMap();

    /**
     * 设置查询字段权重
     * @return array
     */
    abstract function getBoost();

    /**
     * es查询前 默认过滤
     * @return array
     */
    abstract function getFilterDefault();

    /**
     * 获取排序参数
     * @param string $sort_func
     * @return array
     */
    public function getSortParamsByFunc($sort_func)
    {
        $param = [];
        $func = 'getSort' . str_replace(' ', '', ucwords(str_replace('_', ' ', $sort_func)));
        if (method_exists($this, $func)) {
            $param = $this->{$func}();
        }
        return $param;
    }
}