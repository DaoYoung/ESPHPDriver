<?php

/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2018/1/8
 * Time: 14:03
 */
abstract class ElasticIndex
{
    /**@var BaseModel $modelName  */
    protected $modelName;

    /** 获取返回字段 */
    abstract function getFields();

    /** 获取过滤规则 */
    abstract function getFilterMap();

    /**
     * 检查数据有效性
     * @param BaseModel $model
     * @return string|true
     */
    protected function isDataValid(BaseModel $model)
    {
        $filters = $this->getValidFilter();
        foreach ($filters as $key => $filter) {
            switch ($filter[0]) {
                case 'term':
                    if ($key!= 'community_channel_id' && $model->{$key} != $filter[2]) {
                        return ", {$key} expected: {$filter[2]}" . ", actual: " . $model->{$key};
                    }
                    break;
                case 'terms':
                    if (!in_array($model->{$key}, $filter[2])) {
                        $exp = join(',', $filter[2]);
                        return ", {$key} expected: {$exp}" . ", actual: " . $model->{$key};
                    }
                    break;
            }
        }
        return true;
    }

    /**
     * 检查CommunityCombine数据有效性
     * @param array $data
     * @param string $entity_type
     * @return string|true
     */
    protected function isCommunityCombineDataValid($data, $entity_type)
    {
        if (!$data) return "data is null";

        $filters = $this->getValidFilter($entity_type);
        foreach ($filters as $key => $filter) {
            switch ($filter[0]) {
                case 'term':
                    if (isset($data[$key]) && $data[$key] != $filter[2]) {
                        return ", {$key} expected: {$filter[2]}" . ", actual: " . $data[$key];
                    }
                    break;
                case 'terms':
                    if (isset($data[$key]) && !in_array($data[$key], $filter[2])) {
                        $exp = join(',', $filter[2]);
                        return ", {$key} expected: {$exp}" . ", actual: " . $data[$key];
                    }
                    break;
            }
        }
        return true;
    }

    /**
     * 设置查询字段权重
     * @return array
     */
    public function setBoost()
    {
        return [];
    }

    /**
     * es查询前 默认过滤
     * @param string $entity_type
     * @return array
     */
    public function getDefaultFilter($entity_type = '')
    {
        return [];
    }

    /**
     * es查询后 校验过滤
     * 默认规则同es查询，不过有特殊的如酒店商家索引，使用的是MerchantModel，需使用Merchant索引的有效性过滤
     * @see ElasticIndexHotel::getValidFilter()
     * @param string $entity_type
     * @return array
     */
    public function getValidFilter($entity_type = '')
    {
        return $this->getDefaultFilter($entity_type);
    }

    /**
     * 获取cpm过滤
     * @param array $config 配置
     * @return array
     */
    public function getCpmFilter($config = [])
    {
        return [];
    }

    /**
     * 获取cpm套餐旅拍过滤  PRD-4195 暂时弃用
     */
    public function getCpmLvPaiFilter()
    {
        return [
            'is_cpm' => ['term', 'must', 1],
            'is_lvpai' => ['term', 'must', 1],
        ];
    }

    /**
     * 获取排序参数
     * @param $sort_type
     * @param array $config
     *
     * @return array
     */
    public function getSortParam($sort_type, $config = [])
    {
        $param = [];
        $func = 'getSortParam'.str_replace(' ','',ucwords(str_replace('_',' ',$sort_type)));
        if (method_exists($this, $func)) {
            $param = $this->{$func}($config);
        }
        return $param;
    }

    /**
     * 获取默认排序
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
        $arr['_score']['order'] = 'desc';
        $arr['_uid']['order'] = 'desc';
        return $arr;
    }

    /**
     * 获取cpm排序
     * @return string
     */
    public function getSortParamCpm()
    {
        return '';
    }

    /**
     * 获取es geo
     * @param array $config
     * @return array
     */
    public function getGeoDistance($config)
    {
        $location = $this->getLocation($config);
        $geo_distance = [
            'distance' => $config['cpm_distance'],
            $config['location_field'] => [
                'lat' => $location['lat'], // '30.295751'
                'lon' => $location['lon'], // '120.141457'
            ],
        ];
        return $geo_distance;
    }

    /**
     * 获取地理位置
     * @param array $config
     * @return array
     */
    public function getLocation($config = [])
    {
        $headers = Utils::get_http_headers();
//        $city = json_decode($headers['city'], true);
//        return [
//            'lat' => $city['gps_latitude'] ? : $config['cpm_lat'],
//            'lon' => $city['gps_longitude'] ? : $config['cpm_lon'],
//        ];
        $cid = $headers['cid'] ?: cookie('city_id') ?: $config['default_cid'];
        $cachedResult = new CachedResult(new ShopAreaModel, ['cid' => $cid]);
        $result = $cachedResult->fetch_one();
        return [
            'lat' => $result->lat,
            'lon' => $result->lng,
        ];
    }

    /**
     * 获取cpm折叠字段
     */
    public function getCpmCollapseField(){
        return '';
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

    /**
     * cpm数据
     * @param $hit
     * @param $plans
     * @param $index_data
     * @param $key
     * @param $cpm_count
     *
     * @return mixed
     */
    private function makeCpmData($hit, $plans, $index_data, $key, $cpm_count = 0)
    {
        if(isset($hit['_source']['cpm_plan_id']) && array_key_exists($hit['_source']['cpm_plan_id'], $plans)){
            $index_data[$hit['_id']]['cpm'] = $this->getCpm($plans[$hit['_source']['cpm_plan_id']]);
        }
        if ($cpm_count && $_GET['type'] == 'merchant' && $key == 0) {
//            $plan = $plans[$hit['_source']['cpm_plan_id']];
//            if ($plan && $plan->image && $plan->endorse_status == 1 && $plan->image_status == 1) {
//                $index_data[$hit['_id']]['cover_path'] = $plans[$hit['_source']['cpm_plan_id']]->image;
//            }
            // 从商家的店铺计划中取素材图，取不到，取头图
            $model = new CpmPlanModel();
            $map = ['merchant_id' => $hit['_id'], 'status' => CpmPlanModel::STATUS_ACTIVE, 'image_status' => 1];
            $image = $model->where($map)->getField('image');
            if ($image) $index_data[$hit['_id']]['cover_path'] = $image;
        }
        // 商家活跃
        if ($_GET['type'] == 'merchant') {
            $index_data[$hit['_id']]['is_active'] = $hit['_source']['active_value'] ? 1 : 0;
        }
        return $index_data;
    }

    private function getPlanIds($hits)
    {
        $plan_ids = [];
        foreach ($hits as $hit) {
            if ($hit['_source']['is_cpm'] && $hit['_source']['cpm_plan_id']) $plan_ids[$hit['_id']] = $hit['_source']['cpm_plan_id'];
        }
        return $plan_ids;
    }

    private function getCpm($plan)
    {
        if (!$plan) return '';
        return CpmService::getEncodeCpm($plan->as_array());
    }
}