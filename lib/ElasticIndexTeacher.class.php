<?php
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
            'match_major' => ['term', 'must'],
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
        return ["match_name^2", "match_major^2"];
    }

}