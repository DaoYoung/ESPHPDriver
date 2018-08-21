<?php
import('@.Service.Search.Elastic.ElasticIndex');
/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2018/5/15
 * Time: 11:34
 */
class ElasticIndexPlan extends ElasticIndex
{


    public function getFields()
    {
        return "";
    }

    /**
     * 过滤规则
     */
    public function getFilterMap()
    {
        return [
            'image_status' => ['term', 'must'],
            'image_launch_status' => ['term', 'must'],
            'image_search_status' => ['term', 'must'],
            'endorse_status' => ['term', 'must'],
            'property_id' => ['term', 'must'],
            'property_ids' => ['all_term', 'should', 'property_id'],
            'entity_type' => ['term', 'must'],
            'cid' => ['term', 'must'],
            'cid_not' => ['term_not', 'must_not'],
            'cid_type' => ['all_term', 'should'],
            'cid_around' => ['term', 'must'],
            'cid_list' => ['all_term', 'should', 'cid'],
            'cid_term' => ['cid_terms', 'should', 'cid'],
            'cid_alocal_terms' => ['cid_alocal_terms', 'should', 'cid'],
            'merchant_cid' => ['term', 'must'],
            'location' => ['distance', 'must'],
            'collapse_field' => ['collapse', 'must'],
        ];
    }

    /**
     * 获取默认过滤
     */
    public function getDefaultFilter($is_flow_search=false)
    {
         
            return [
                'deleted' => ['term', 'must', 0],
                'status' => ['term', 'must', 1], // 状态：已发布
                'is_supplement' => ['term', 'must_not', 1],
                'last_stop_at' => ['exists', 'must_not', 0],
                'score' => ['range', 'must', 0],
            ];
    }


    function getSortParamScore()
    {

        $param['score']['order'] = 'desc';
        $param['updated_at']['order'] = 'asc';
        return $param;
    }

    /**
     * 对命中索引进行数据修饰，通过MODEL补足字段
     * @param        $hits
     * @param string $fields
     * @param \Elasticsearch\Client $connect
     * @param int $cpm_count
     * @param string $extend_fields
     * @param string $collapse_field
     *
     * @return array|null
     */
    public function makeUpListData($hits, $fields = null, $connect, $cpm_count = 0, $extend_fields = '', $is_flow_search, $collapse_field, $cpm_type)
    {
        $res = [];
        foreach ($hits as $hit){
            try{
                $plan = CpmPlanModel::byID($hit['_source']['id']);
                $plan_arr = $plan->as_array();
                $ids = explode(';', $hit['_source']['entity_ids']);
                if($hit['_source']['entity_type'] == CpmPlanModel::TYPE_VIDEO){
                    if(C('IS_REL') ==1 && version_compare($_SERVER["HTTP_APPVER"], "8.0.5", "<"))
                        continue;
                    $row = [];
                    $row['id'] = $plan_arr['id'];
                    $row['entity_type'] = $plan_arr['entity_type'];
                    $row['entity_title'] = $plan_arr['entity_title'];
                    $row['title'] = $plan_arr['title'];
                    $row['video'] = $plan_arr['video'];
                    $row['button_list'] = $plan_arr['button_list'];
                    $title = $row['title'];
                    $row['title'] = $row['entity_title'];
                    $row['entity_title'] = $title;//调换下顺序，统一文档
                    $row['poster']['target_id'] = $plan->merchant_id;
                    $row['poster']['target_type'] = 5;
                }else{
                    if($cpm_type == 'extendKeywordPackAlocalCpm' || $cpm_type == 'extendKeywordPackNationalCpm' || $cpm_type == 'extendMerPackAlocalCpm' || $cpm_type == 'extendMerPackNationalCpm'){
                        $condition['merchant_id'] = $hit['_source']['merchant_id'];
                        $condition['is_sold_out'] = 0;
                        $condition['status'] = 1;
                        $condition['deleted'] = 0;
                        $condition['hot_tag'] = ['gt', 0];
                        $mod = new CachedResult(new SetMealModel, $condition);
                        $ids = $mod->ids();
                        $collapse_field='set_meal';
                    }
                    if($collapse_field!=''&&$collapse_field=='set_meal' || $collapse_field==''&&$hit['_source']['entity_type'] == CpmPlanModel::TYPE_SET_MEAL || $hit['_source']['entity_type'] == CpmPlanModel::TYPE_CASE){
                        shuffle($ids);
                        $count = count($ids);
                        $set_meal_id = array_pop($ids);
                        if($count == 0 || empty($set_meal_id)) continue;
                        $row = SetMealModel::byID($set_meal_id)->as_array($fields?:"id,title,commodity_type,show_price,cover_path,actual_price,market_price,show_price,vertical_image,is_lvpai,second_category_id,media_items,_es_merchant,_city,_free_trial_yarn,rule,media_items_count");
                    }
                    if($collapse_field!=''&&$collapse_field=='merchant_id' || $collapse_field==''&&$hit['_source']['entity_type'] == CpmPlanModel::TYPE_SHOP){
                        $row = MerchantModel::byID($hit['_source']['merchant_id'])->as_array($fields?:"id,name,user_id,logo_path,fans_count,collectors_count,active_works_pcount,active_cases_pcount,bond_sign,sign,shop_type,grade,privilege,address,_merchant_comment,comments_count,is_pro,cover_path,_shop_area,_es_price_start,_max_coupon_value,latitude,longitude,_recommend_meals,_merchant_achievement");
                        $row['is_active'] = $hit['_source']['is_active'] ? 1 : 0;
                        if($hit['_source']['entity_type'] == CpmPlanModel::TYPE_SHOP){
                            if ($plan->image) $row['cover_path'] = $plan->image;
                        }
                    }
                }
                $row['cpm_entity_type'] = $hit['_source']['entity_type'];
                $row['cpm_id'] = $hit['_source']['id'];
                $row['cpm_type'] = $cpm_type;
                $row['cpm_merchant_id'] = $hit['_source']['merchant_id'];
                if($plan->entity_type == CpmPlanModel::TYPE_KEYWORD && $plan->endorse_status == 1 && $plan->status == 1 || $plan->entity_type != CpmPlanModel::TYPE_KEYWORD)
                    $row['cpm'] = CpmService::getEncodeCpm($plan_arr);
                $res[] = $row;
            }catch (NotFound $e){
                continue;
            }
        }
        return $res;
    }
}