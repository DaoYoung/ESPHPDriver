<?php

/**
 * Created by PhpStorm.
 * User: yidao
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
     * @param $sort_type
     * @return array
     */
    public function getSortParams($sort_type)
    {
        $param = [];
        $func = 'getSort'.str_replace(' ','',ucwords(str_replace('_',' ',$sort_type)));
        if (method_exists($this, $func)) {
            $param = $this->{$func}();
        }
        return $param;
    }

    /**
     * 默认排序
     * @return []
     */
    public function getSortDefault()
    {
        $arr['id']['order'] = 'desc';
        return $arr;
    }

    /**
     * 对命中索引进行数据修饰，通过MODEL补足字段
     * @param        $hits
     * @param string $fields
     * @param \Elasticsearch\Client $connect
     * @param int $cpm_count
     * @param string $extend_fields
     *
     * @return array|null
     */
    public function makeUpListData($hits, $fields = null, $connect, $cpm_count = 0, $extend_fields = '', $is_flow_search)
    {

        if($is_flow_search){
            $ids = [];
            foreach ($hits as $hit){
                $ids[] = $hit['_source']['id'];
            }
        }else
            $ids = $this->modelName == 'CommunityCombineModel' ? $this->getIds($hits) : array_column($hits, '_id');
        if(empty($ids))
            return null;
        $fields = $fields ? : trim($this->getFields() . ',' . $extend_fields, ',');
        $compare = $this->makeCompareEsFields($fields, $hits);
        $index_data = $this->makeIndexData($hits, $compare['exist_fields'], $cpm_count);
        return $this->makeModelData($ids, $compare['un_exist_fields'], $index_data, $connect);
    }

    private function getIds($hits)
    {
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit['_source']['id'];
        }
        return $ids;
    }

    /**
     * 比较显示字段和ES字段，提取ES里存在|不存在的字段
     * @param $fields
     * @param $hits
     *
     * @return mixed
     */
    private function makeCompareEsFields($fields, $hits)
    {
        $fields_array = explode(',', $fields);
        $compare_index_fields = array_intersect($fields_array, array_keys($hits[0]['_source']));
        $res['exist_fields'] = array_flip($compare_index_fields);
        $compare_data_fields = $fields_array;
        // $res['un_exist_fields'] = implode(',', array_diff($compare_data_fields, $compare_index_fields));
        $res['un_exist_fields'] = $fields; // 直接返回model查询字段
        return $res;
    }
    /**
     * 提取model数据，并合并ES的数据
     * @param $ids
     * @param $un_exist_fields
     * @param $index_data array ES的数据
     * @param \Elasticsearch\Client $connect
     *
     * @return array
     */
    private function makeModelData($ids, $un_exist_fields, $index_data, $connect)
    {
        $models = ($this->modelName)::byID($ids);
        $res = $invalid_data = $exception_data = [];
        foreach ($models as $model) {
            try {
                $item = $model->as_array($un_exist_fields);
            } catch (Exception $e) {
                // as_array错误，写入es
                new ElasticException($connect, 'as_array data', $e->getMessage());
                continue; // 不返回非法数据
            }
            // 检查数据有效性，写入es
            $check = $this->modelName == 'CommunityCombineModel' ? $this->isCommunityCombineDataValid($item['entity'], $model->entity_type)
                        : $this->isDataValid($model);
            if ($check !== true) {
                new ElasticException($connect, 'invalid data', $this->modelName . " " . $model->id() . " " . $check);
                continue; // 不返回非法数据
            }

            $res[] = array_merge($index_data[$model->id()], $item);
        }
        return $res;
    }
    /**
     * 提取ES的数据
     * @param $hits
     * @param $exist_fields
     * @param $cpm_count
     *
     * @return array|mixed
     */
    private function makeIndexData($hits, $exist_fields, $cpm_count = 0)
    {
        $index_data = [];
        $plan_ids = $this->getPlanIds($hits);
        $plans = count($plan_ids) ? CpmPlanModel::byID($plan_ids) : [];
        foreach ($hits as $key => $hit) {
            $index_data[$hit['_source']['id']] = array_intersect_key($hit['_source'], $exist_fields);
            $index_data = $this->makeCpmData($hit, $plans, $index_data, $key, $cpm_count);
            $index_data = $this->makeHighlightData($hit, $index_data);
        }
        return $index_data;
    }

    /**
     * 高亮
     * @param $hit
     * @param $index_data
     *
     * @return mixed
     */
    private function makeHighlightData($hit, $index_data)
    {
        if(isset($hit['highlight'])){
            foreach ($hit['highlight'] as $key=>$val){
                $key = preg_replace('/match\_/', '', $key, 1);
                $index_data[$hit['_id']][$key] = array_pop($val);
            }
        }
        return $index_data;
    }

}