<?php

/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/8/22
 * Time: 19:59
 */
class ElasticIndexStudent extends ElasticIndex
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