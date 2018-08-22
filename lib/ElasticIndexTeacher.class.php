<?php
require "ElasticIndex.class.php";
/**
 * 商家
 * Created by PhpStorm.
 * User: JiangFeng
 * Date: 2018/1/9
 * Time: 16:57
 */
class ElasticIndexTeacher extends ElasticIndex
{
    public function getFields()
    {
        return "id,name,age";
    }

    public function getFilterMap()
    {
        return [
            'age' => ['term', 'must'],
            'age_range' => ['range', 'must'],
        ];
    }

    public function getFilterDefault()
    {
        return [
            'status' => ['term', 'must', 1],
        ];
    }

    public function getBoost()
    {
        return ["match_name^10", "match_sport^2"];
    }

    public function getSortAge()
    {
        $param['age']['order'] = 'desc';
        return $param;
    }
}