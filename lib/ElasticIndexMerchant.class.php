<?php
import('@.Service.Search.Elastic.ElasticIndex');
/**
 * 商家
 * Created by PhpStorm.
 * User: JiangFeng
 * Date: 2018/1/9
 * Time: 16:57
 */
class ElasticIndexMerchant extends ElasticIndex
{
    public function __construct()
    {
        $this->modelName = 'MerchantModel';
    }

    public function getFields()
    {
        return "id,name,user_id,logo_path,fans_count,collectors_count,active_works_pcount,active_cases_pcount,bond_sign,sign,shop_type,grade,privilege,address,_merchant_comment,comments_count,is_pro,cover_path,_shop_area,_es_price_start,_max_coupon_value,latitude,longitude,_merchant_achievement";
    }

    public function getFilterMap()
    {
        return [
            'property_id' => ['term', 'must'],
            'is_pro' => ['term', 'must'],
        ];
    }

    /**
     * 获取默认过滤
     */
    public function getDefaultFilter()
    {
        return [
            'deleted' => ['term', 'must', 0],
            'hidden' => ['term', 'must', 0],
            //'status' => ['term', 'must', 2],
            'examine' => ['term', 'must', 1], // 注册审核通过
            'shop_type' => ['term', 'must', 0], // 服务商家
            'set_meal_switch' => ['term', 'must', 1], //　开启发布套餐权限
        ];
    }

    /**
     * 获取cpm过滤
     * @param array $config
     * @return array
     */
    public function getCpmFilter($config = [])
    {
        return [
            'is_cpm' => ['term', 'must', 1],
            'geo_distance' => ['geo_distance', 'must', $this->getGeoDistance($config)],
        ];
    }

    /**
     * 设置查询字段权重
     * @return array
     */
    public function setBoost()
    {
        return ["match_name^10", "match_property_name^2"];
    }

    public function getSortParamHot()
    {
        $param['fans_count']['order'] = 'desc';
        return $param;
    }

    public function getSortParamNewest()
    {
        $param['created_at']['order'] = 'desc';
        return $param;
    }

    function getSortParamCpm()
    {
        $param['is_cpm']['order'] = 'desc';
        $param['cpm_value']['order'] = 'desc';
        return $param;
    }

    /**
     * 获取默认排序 经纬度>找商家权重>相关度
     * @param array $config
     * @return string
     */
    public function getSortParamDefault($config = [])
    {
        $param[$config['location_field']] = $this->getLocation($config);
        $param['order'] = 'asc';
        $param['unit'] = 'km';
        $param['distance_type'] = 'plane';
        $arr['_geo_distance'] = $param;
        $arr['active_value']['order'] = 'desc';
        $arr['weight']['order'] = 'desc';
        $arr['_score']['order'] = 'desc';
        return $arr;
    }
    /**
     * 行业词排序
     * @param array $config
     * @return string
     */
    public function getSortParamDefaultPRO($config = [])
    {
        $param[$config['location_field']] = $this->getLocation($config);
        $param['order'] = 'asc';
        $param['unit'] = 'km';
        $param['distance_type'] = 'plane';
        $arr['_geo_distance'] = $param;
        $arr['active_value']['order'] = 'desc';
        $arr['_score']['order'] = 'desc';
        $arr['weight']['order'] = 'desc';
        return $arr;
    }
    /**
     * 按相关性
     * @return string
     */
    public function getSortParamRelated($config = [])
    {
        $arr['_score']['order'] = 'desc';
        return $arr;
    }
}