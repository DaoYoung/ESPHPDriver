<?php
namespace ESPHPDriver\Lib;

require VENDOR_PATH.'elasticsearch/vendor/autoload.php';
//require '/vendor/autoload.php';
require __DIR__ . '/../SearchDriver.class.php';
require(__DIR__ . '/ElasticException.class.php');
require(__DIR__ . '/ElasticParser.class.php');

/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2018/1/3
 * Time: 15:13
 */
class ElasticDriver extends SearchDriver
{
    protected $connect, $config;
    /**
     * @var ElasticIndex $index_helper
     */
    protected $index_helper;

    /** 查询参数
     * [index, type, from, size]
     * [body] => [$param_multi_match, $param_filters]
     * [sort] => $param_sort
     * [collapse] => $param_collapse
     */
    protected $params;

    /**
     * 多匹配查询参数
     * [query, fields]
     */
    protected $param_multi_match;

    /**
     * 不匹配查询参数
     * [query, fields]
     */
    protected $param_not_match;

    /**
     * 过滤参数
     * [bool][must|should|must_not]
     */
    protected $param_filters;

    /** cpm套餐旅拍过滤参数
     * [bool][must|should|must_not]
     */
    protected $param_lvpai_filters;

    /**
     * 排序参数
     */
    protected $param_sort;

    /**
     * 折叠参数
     */
    protected $param_collapse;
    /**
     * 高亮参数
     */
    protected $param_highlight;

    /**
     * ES CPM搜索变量
     */
    protected $cpm_count;

    private $control_types = [];//用于搜索词控制分类输出
    private $control_poster;//控制poster输出
    private $param_shop_product_category;//命中婚品类目
    /**
     * 额外查询参数
     */
    protected $param_extend_fields;

    /**
     * 匹配模式 （新娘说需要使用type:phrase模式）
     */
    protected $match_type;

    /**
     * 匹配关键字数组 （新娘说）
     */
    protected $match_words;

    /**
     * 是否是新娘说查询
     */
    protected $is_bride_talk;

    /**
     * 解析参数
     */
    protected $param_parser;

    /**
     * 数组匹配参数
     */
    protected $param_array_parser;
    /**
     * 1商家流量查询 二级页 feed
     */
    protected $is_flow_search;
    /**
     * 应对cpm在不同页面对同表的不同展示字段
     */
    protected $format_fields;
    protected $use_default_sort;


    const ES_INDEX_SET_MEAL = 'thl_set_meals';
    const ES_INDEX_MERCHANT = 'thl_merchants';
    const ES_INDEX_CAR = 'thl_car_product';
    const ES_INDEX_HOTEL = 'thl_hotel_merchants';
    const ES_INDEX_SHOP_PRODUCT = 'thl_shop_product';
    const ES_INDEX_NOTE = 'thl_note';
    const ES_INDEX_QA = 'thl_qa_question';
    const ES_INDEX_COMMUNITY_THREAD = 'thl_community_threads';
    const ES_INDEX_COMMUNITY_COMBINE = 'thl_community_combine';

    const ES_FLOW_INDEX_SET_MEAL = 'thl_n_set_meals';//应对feed 二级页的索引
    const ES_INDEX_CPM_PLAN = 'thl_plan';//应对feed 二级页的索引
    const ES_INDEX_CPM_PLAN_KEYWORD = 'thl_plan_keyword';//应对feed 二级页的索引

    const ES_INDEX_MAP = [
        SearchClient::TYPE_PACKAGE => self::ES_INDEX_SET_MEAL,
        SearchClient::TYPE_EXAMPLE => self::ES_INDEX_SET_MEAL,
        SearchClient::TYPE_MERCHANT => self::ES_INDEX_MERCHANT,
        SearchClient::TYPE_CAR => self::ES_INDEX_CAR,
        SearchClient::TYPE_HOTEL => self::ES_INDEX_HOTEL,
        SearchClient::TYPE_SHOP_PRODUCT => self::ES_INDEX_SHOP_PRODUCT,
        SearchClient::TYPE_QA => self::ES_INDEX_QA,
        SearchClient::TYPE_NOTE => self::ES_INDEX_NOTE,
        SearchClient::TYPE_COMMUNITY_THREAD => self::ES_INDEX_COMMUNITY_THREAD,
        SearchClient::TYPE_COMMUNITY_COMBINE => self::ES_INDEX_COMMUNITY_COMBINE,
        SearchClient::TYPE_CPM_PLAN => self::ES_INDEX_CPM_PLAN,
        SearchClient::TYPE_CPM_PLAN_KEYWORD => self::ES_INDEX_CPM_PLAN_KEYWORD,
    ];

    const ES_FLOW_INDEX_MAP = [
        SearchClient::TYPE_PACKAGE => self::ES_FLOW_INDEX_SET_MEAL,
        SearchClient::TYPE_EXAMPLE => self::ES_FLOW_INDEX_SET_MEAL,
    ];

    public function __construct()
    {
        $this->config = require(__DIR__ . '/ElasticConfig.php');

        $this->connect = ClientBuilder::create()->setConnectionPool(
            '\Elasticsearch\ConnectionPool\SniffingConnectionPool', []
        )->setHosts(
            $this->config['host']
        )->build();

    }

    /**
     * 设置索引
     * @param string $type_name
     * @param int $cpm_count 搜索cpm的数量
     * @param bool $is_cpm_only 是否只搜索cpm结果
     */
    public function setType($type_name, $cpm_count=0, $is_cpm_only=false, $use_flow_index=false)
    {
        $this->cpm_count = $cpm_count;
        $this->is_flow_search = $use_flow_index;
        if($use_flow_index)
            $this->params['type'] = $this->params['index'] = C('IS_REL') ? substr(self::ES_FLOW_INDEX_MAP[$type_name], 1) : self::ES_FLOW_INDEX_MAP[$type_name];
        else
            $this->params['type'] = $this->params['index'] = C('IS_REL') ? substr(self::ES_INDEX_MAP[$type_name], 1) : self::ES_INDEX_MAP[$type_name];
        $this->param_sort = $this->param_filters = null;//置空，便于多次调用
        $this->setIndexHelper($type_name);
    }

    /**
     * 设置过滤
     * @param array $filter_array
     */
    public function setFilter($filter_array)
    {
        if (!$this->index_helper) return;
        $this->setDefaultFilter();
        $filter_map = $this->index_helper->getFilterMap();
        if ($filter_array && $filter_map) {
            foreach ($filter_array as $key => $val) {

                $term_word = $filter_map[strtolower($key)][0];
                $symbol = $filter_map[strtolower($key)][1];
                switch ($term_word) {
                    case 'term':
                        $this->param_filters[$symbol][]['term'] = [$key => $val];
                        break;
                    case 'terms':
                        $this->param_filters[$symbol][]['terms'] = [$key => explode(',', $val)];
                        break;
                    case 'term_not':
                        $key = str_replace('_not', '', $key);
                        $this->param_filters[$symbol][]['term'] = [$key => $val];
                        break;
                    case 'exists':
                        $this->param_filters[$symbol][]['exists']['field'] = $key;
                        break;
                    case 'range':
                        $range = explode(',', $val);
                        if ($range) {
                            if ($range[0]) $arr['gte'] = $range[0];
                            if ($range[1]) $arr['lte'] = $range[1];
                            if ($arr) $this->param_filters[$symbol][]['range'] = [$key => $arr];
                        }
                        break;
                    case 'gte':
                        $arr2['gte'] = $val;
                        $this->param_filters[$symbol][]['range'] = [$key => $arr2];
                        break;
                    case 'lte':
                        $arr3['lte'] = $val;
                        $this->param_filters[$symbol][]['range'] = [$key => $arr3];
                        break;
                    case 'bool': // 新娘说相关
                        $this->param_filters[$symbol][]['bool']['must'] = $val;
                        break;
                    case 'all_term':
                        if(!is_array($val))
                            $val = explode(',', $val);
                        if(isset($filter_map[strtolower($key)][2]))
                            $key = $filter_map[strtolower($key)][2];//转换字段名
                        if($symbol=='should'){
                            $arr = [];
                            foreach ($val as $item) {
                                $arr[$symbol][]['term'] = [$key => $item];
                            }
                            $this->param_filters['must'][]['bool'] = $arr;//外部转为一个必要条件，内部多个或组成。sql理解为 "and (a=b or a=c)"
                        }else{
                            foreach ($val as $item) {
                                $this->param_filters[$symbol][]['term'] = [$key => $item];
                            }
                        }
                        break;
                    case 'or_tags':// 二级页标签组合过滤
                        foreach ($val as $item) {
                            $tags = explode(',', $item);
                            $should = [];
                            foreach ($tags as $tag)
                                $should[]['term'] = ['tag_ids' => $tag];
                            $this->param_filters[$symbol][]['bool']['should'] = $should;
                        }
                        break;
                    case 'distance': // dsp
                        $dis['distance'] = $val;
                        $location = $this->index_helper->getLocation();
                        $dis['location']['lat'] = $location['lat'];
                        $dis['location']['lon'] = $location['lon'];
                        $this->param_filters[$symbol][]['geo_distance'] = $dis;
                        break;
                    case 'collapse':
                        $this->param_collapse['field'] = $val;
                        break;
                    case 'cid_terms'://周边、本地、全国广告综合排序
                        $p=[];
                        $p[]['term'] = ['cid'=>0];
                        $p[]['term'] = ['cid_type'=>1];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        $p=[];
                        $p[]['term'] = ['cid'=>$val];
                        $p[]['term'] = ['cid_type'=>3];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        $p=[];
                        $p[]['term'] = ['cid_around'=>$val];
                        $p[]['term'] = ['cid_type'=>2];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        $p=[];
                        $p[]['term'] = ['cid'=>$val];
                        $p[]['term'] = ['cid_type'=>2];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        break;
                    case 'cid_alocal_terms'://周边、本地广告综合排序
                        $p=[];
                        $p[]['term'] = ['cid'=>$val];
                        $p[]['term'] = ['cid_type'=>3];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        $p=[];
                        $p[]['term'] = ['cid_around'=>$val];
                        $p[]['term'] = ['cid_type'=>2];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        $p=[];
                        $p[]['term'] = ['cid'=>$val];
                        $p[]['term'] = ['cid_type'=>2];
                        $this->param_filters[$symbol][]['bool']['must'] = $p;
                        break;
                    default:
                        break;
                }
            }
        }

    }

    /**
     * 设置默认过滤
     */
    public function setDefaultFilter()
    {

        $filters = $this->index_helper->getDefaultFilter($this->is_flow_search);
        foreach ($filters as $key => $filter) {
            $this->param_filters[$filter[1]][][$filter[0]] = [$key => $filter[2]];
        }
    }

    /**
     * 设置查询关键字
     * @param string $query_string
     */
    public function setQuery($query_string)
    {
        $fields = $this->config['match_field'];

        if ($this->index_helper && $this->is_bride_talk) {
            if ($this->match_words) {
                foreach ($this->match_words as $word) {
                    $match = [
                            'tie_breaker' => '0.3',
                            'minimum_should_match' => '80%',
                            'query' => $word,
                            'fields' => 'match_title'
                    ];
                    if ($this->match_type) $match['type'] = $this->match_type;
                    $this->param_multi_match['bool']['should'][]['multi_match'] = $match;

                }
            }

        } else {
            if (!empty($query_string)) {
//http://jira.hunliji.com/browse/DEV-7851
                if(C('IS_REL') == 1&&$this->use_default_sort && EsIkDicModel::isMatch($query_string)){
                    $this->setReSort(['defaultPRO']);
                }
                // 将营销词转换成热搜词
                $query_string = SearchWordV2Model::alias2title($query_string);
                $this->isMatchPreWord($query_string);
                $boost = $this->index_helper ? $this->index_helper->setBoost() : [];
                $this->param_multi_match = [
                    'tie_breaker' => '0.3',
                    'minimum_should_match' => '80%',
                    'query' => $query_string,
                    'fields' => array_merge($fields, $boost)
                ];
                if ($this->match_type) $this->param_multi_match['type'] = $this->match_type;
            }
        }

        if(C('IS_REL')){
            $this->param_not_match = [
                'query' => $this->config['not_match_word'],
                'fields' => $fields
            ];
        }

    }

    /**
     * @param $query_string
     * @author yidao
     */
    private function isMatchPreWord($query_string)
    {
        $check = SearchPreWordModel::checkWord($query_string);
        if($check){
            if($check['type'] == SearchPreWordModel::TYPE_LIST){
                $this->control_types = $check['type_value'];
            }else{
                $this->control_poster['poster'] = $check['type_value'];
            }
        }
        $shopCate = ShopCategoryModel::isMatchCategory($query_string);
        if(!empty($shopCate)){
            switch ($shopCate['level']){
                case 1:
                    $this->param_shop_product_category['cate_id_one_level'] = $shopCate['id'];
                    break;
                case 2:
                    $this->param_shop_product_category['cate_id_two_level'] = $shopCate['id'];
                    break;
                case 3:
                    $this->param_shop_product_category['cate_id_three_level'] = $shopCate['id'];
                    break;
            }
        }
    }
    /**
     * 重新设置排序
     * @param array $sort_array 排序规则
     */
    public function setReSort($sort_array)
    {
        $this->param_sort = [];
        $this->setSort($sort_array);
    }
    /**
     * 设置排序
     * @param array $sort_array 排序规则
     */
    public function setSort($sort_array)
    {
        if (!$this->index_helper) return;
        if($this->is_flow_search && $sort_array[0] == 'default'){
            $sort['flow']['order'] = 'desc';
            $this->param_sort[] = $sort;
            return;
        }
        foreach ($sort_array as $sort_type) {
            if($sort_type=='default'&&(get_class($this->index_helper) == 'ElasticIndexSetMeal'||get_class($this->index_helper) == 'ElasticIndexMerchant'))
                $this->use_default_sort = true;
            $sort = $this->index_helper->getSortParam($sort_type, $this->config);
            if ($sort) $this->param_sort[] = $sort;
        }
    }
    /**
     * 设置高亮
     * @param $bool
     */
    public function setHighlight($bool)
    {
        if($bool)
            $this->param_highlight['fields'][$this->config['highlight_field']]['type'] = 'plain';
        else
            $this->param_highlight = null;
    }

    /**
     * 设置分页
     * @param int $page
     * @param int $size
     */
    public function setPage($page = 1, $size = 0)
    {
        $size = $size ?: CommDef::$PageSize;
        if($size>50) $size = CommDef::$PageSize;
        $this->params['from'] = ($page- 1) * $size;
        $this->params['size'] = $size;
    }

    /**
     *
     * 正式测试索引名保持一致，方便与静态变量比较
     * @param $index_name
     *
     * @return string
     */
    protected function syncEsIndex($index_name){
        return C('IS_REL') ? 't'.$index_name : $index_name;
    }

    protected function syncEsType($index_name){
        return C('IS_REL') ? substr($index_name, 1) : $index_name;
    }



    /**
     * @param $type
     * @author yidao
     *
     * @return bool
     */
    private function isAllowType($type){
        if(count($this->control_types)==0 ||
            count($this->control_types) && in_array($type, $this->control_types) )
            return true;
        else
            return false;
    }
    /**
     * 格式化聚合数据
     * @param $buckets
     *
     * @return array
     */
    protected function aggFormatGroup($buckets){
        $res = [];
        $type_map = array_flip(self::ES_INDEX_MAP);
        foreach ($buckets as $row){
            switch ($key = $this->syncEsIndex($row['key'])){
                case self::ES_INDEX_SET_MEAL:
                    foreach ($row['commodity_type']['buckets'] as $sub_agg){
                        if($sub_agg['key'] == SetMealModel::$Type_Works && $this->isAllowType(SearchClient::TYPE_PACKAGE)){
                            $res['ECommerces'][] = ['key'=>SearchClient::TYPE_PACKAGE, 'doc_count'=>$sub_agg['doc_count']];
                        }
                        if($sub_agg['key'] == SetMealModel::$Type_Cases && $this->isAllowType(SearchClient::TYPE_EXAMPLE)){
                            $res['contents'][] = ['key'=>SearchClient::TYPE_EXAMPLE, 'doc_count'=>$sub_agg['doc_count']];
                        }
                    }
                    break;
                case self::ES_INDEX_MERCHANT:
                case self::ES_INDEX_HOTEL:
                case self::ES_INDEX_CAR:
                case self::ES_INDEX_SHOP_PRODUCT:
                    if(!$this->isAllowType($type_map[$key]))  break;
                    $res['ECommerces'][] = ['key'=>$type_map[$key], 'doc_count'=>$row['doc_count']];
                    break;
                case self::ES_INDEX_QA:
                case self::ES_INDEX_NOTE:
                case self::ES_INDEX_COMMUNITY_THREAD:
                    if(!$this->isAllowType($type_map[$key]))  break;
                    $res['contents'][] = ['key'=>$type_map[$key], 'doc_count'=>$row['doc_count']];
                    break;
            }
        }
        $res['is_display_merchant'] = intval($this->isAllowType(SearchClient::TYPE_MERCHANT));
        //套餐、案例是拆分出来的，需要重新排序
        if(isset($res['ECommerces']))
            array_multisort(array_column($res['ECommerces'], 'doc_count'), SORT_DESC, $res['ECommerces']);
        if(isset($res['contents']))
            array_multisort(array_column($res['contents'], 'doc_count'), SORT_DESC, $res['contents']);
        return $res;
    }

    /**
     * 格式化聚合中的商家数据
     * @param $res
     * @param $hits
     *
     * @return mixed
     */
    protected function aggFormatMerchant($res, $hits){
        $this->setIndexHelper(SearchClient::TYPE_MERCHANT);
        $res['merchants']  = $this->index_helper->makeUpListData($hits, 'id,name,area_name,_es_price_start,_from_hotel', $this->connect);
        return $res;
    }

    /**
     * 组合查询语句
     * @return array
     */
    protected function buildParams()
    {
        $this->params['body'] = $this->getBodyParams();
//        debug(json_encode($this->params));

        return $this->params;
    }

    // 目前只针对cpm套餐
    protected function buildMultiParams()
    {
        $multi_params['body'][] = $this->getSearchType();
        $multi_params['body'][] = $this->getBodyParams(1);
        $multi_params['body'][] = $this->getSearchType();
        $multi_params['body'][] = $this->getBodyParams(2);
//        debug(json_encode_ex($multi_params));
        return $multi_params;
    }

    protected function getBodyParams($cpm_type = 0)
    {
        if ($this->is_bride_talk) {
            if ($this->param_multi_match) $params['query']['bool']['must'] = $this->param_multi_match;
        } else {
            $params = $this->getMultiMatchParam();
        }

        if ($this->param_not_match) $params['query']['bool']['must_not']['multi_match'] = $this->param_not_match;
        if ($this->param_sort) $params['sort'] = $this->param_sort;
        if ($this->param_collapse) $params['collapse'] = $this->param_collapse;
        if ($this->param_highlight) $params['highlight'] = $this->param_highlight;
        switch ($cpm_type) {
            case 0: // 普通查询
                if ($this->param_filters) $params['query']['bool']['filter']['bool'] = $this->param_filters;
                break;
            case 1: // cpm查询
                if ($this->param_filters) $params['query']['bool']['filter']['bool'] = $this->param_filters;
                $params['from'] = 0;
                $params['size'] = $this->cpm_count;
                break;
            case 2: // cpm套餐旅拍
                if ($this->param_lvpai_filters) $params['query']['bool']['filter']['bool'] = $this->param_lvpai_filters;
                $params['from'] = 0;
                $params['size'] = $this->config['cpm_lvpai_count'];
                break;
        }

        return $params;
    }

    protected function getSearchType()
    {
        return [
            'index' => $this->params['index'],
            'type' => $this->params['type']
        ];
    }

    protected function setIndexHelper($type_name)
    {
        switch (strtolower($type_name)) {
            case SearchClient::TYPE_PACKAGE: // 套餐
            case SearchClient::TYPE_EXAMPLE: // 案例
                require_cache(__DIR__ . '/ElasticIndexSetMeal.class.php');
                $this->index_helper = new ElasticIndexSetMeal($type_name, $this->is_flow_search);
                break;
            case SearchClient::TYPE_MERCHANT: // 商家
                require_cache(__DIR__ . '/ElasticIndexMerchant.class.php');
                $this->index_helper = new ElasticIndexMerchant();
                break;
            case SearchClient::TYPE_CAR: // 婚车
                require_cache(__DIR__ . '/ElasticIndexCarProduct.class.php');
                $this->index_helper = new ElasticIndexCarProduct();
                break;
            case SearchClient::TYPE_SHOP_PRODUCT: // 婚品
                require_cache(__DIR__ . '/ElasticIndexShopProduct.class.php');
                $this->index_helper = new ElasticIndexShopProduct();
                break;
            case SearchClient::TYPE_HOTEL: // 酒店
                require_cache(__DIR__ . '/ElasticIndexHotel.class.php');
                $this->index_helper = new ElasticIndexHotel();
                break;
            case SearchClient::TYPE_QA: // 问答
                require_cache(__DIR__ . '/ElasticIndexQa.class.php');
                $this->index_helper = new ElasticIndexQa();
                break;
            case SearchClient::TYPE_COMMUNITY_THREAD: // 帖子
                require_cache(__DIR__ . '/ElasticIndexCommunityThread.class.php');
                $this->index_helper = new ElasticIndexCommunityThread();
                break;
            case SearchClient::TYPE_NOTE: //笔记
                require_cache(__DIR__ . '/ElasticIndexNote.class.php');
                $this->index_helper = new ElasticIndexNote();
                break;
            case SearchClient::TYPE_COMMUNITY_COMBINE: //结婚宝典
                require_cache(__DIR__ . '/ElasticIndexCommunityCombine.class.php');
                $this->index_helper = new ElasticIndexCommunityCombine();
                break;
            case SearchClient::TYPE_CPM_PLAN:
                require_cache(__DIR__ . '/ElasticIndexPlan.class.php');
                $this->index_helper = new ElasticIndexPlan();
                break;
            case SearchClient::TYPE_CPM_PLAN_KEYWORD:
                require_cache(__DIR__ . '/ElasticIndexPlanKeyword.class.php');
                $this->index_helper = new ElasticIndexPlanKeyword();
                break;
            default:
                throw_exception('NO_ElasticIndex_Helper');
        }

    }

    /**
     * 获取结果列表
     * @return array
     * @throws Exception
     */
    public function getResultList()
    {
        try {
            $params = $this->buildParams();
            if(isset($_GET['debug']) && $_GET['debug']==999){
                debug(json_encode($params));
            }

            $list = $this->connect->search($params);
            $this->unsetParam();
            $res = $this->formatListData($list);
            if(isset($_GET['debug']) && $_GET['debug']==1){
                $res['debug_search'] = $params;
            }
            return $res;
        } catch (Exception $e) {
            throw new ElasticException($this->connect, 'search error', $e->getMessage());
        }
    }

    private function unsetParam()
    {
        unset($this->param_filters);
        unset($this->param_sort);
        unset($this->param_multi_match);
        // unset($this->param_extend_fields);
        unset($this->param_collapse);
    }

    /**
     * 多次查询请求合并为一次请求
     */
    protected function multiSearch()
    {
        $data = $this->connect->msearch($this->buildMultiParams());
        $list = [];
        $cpm_list = $data['responses'][0]['hits']['hits'];
        $cpm_lvpai_list = $data['responses'][1]['hits']['hits'];
        $key = 0;
        foreach ($cpm_list as $item) {//本地CPM
            $key++;
            if ($key == 1 || $key == 3 || $key == 5) {
                //固定位插旅拍
                if ($cpm_lvpai_list) $list[] = array_pop($cpm_lvpai_list);
            }
            $list[] = $item;
        }
        //固定位不足时，入栈剩余旅拍
        while (count($cpm_lvpai_list)) {
            $list[] = array_pop($cpm_lvpai_list);
        }
        $d['hits']['hits'] = $list;
        return $d;
    }

    /**
     * 返回聚合需要的es索引数组
     * @param null $only_index
     * @param null $except_index
     * @author yidao
     */
    private function getAggsIndexArray($only_index = null, $except_index = null){
        $real_index = [];
        foreach (self::ES_INDEX_MAP as $index){
            if($except_index == $index)
                continue;
            $item = C('IS_REL') ? substr($index, 1) : $index;
            if($only_index == $index)
                return [$item];
            else
                $real_index[] = $item;
        }
        return $real_index;
    }
    /**
     * 对婚品分类处理
     * @param null $only_index
     * @param null $except_index
     * @author yidao
     */
    private function getMultiMatchParam($from_agg = false){
        $params = [];
        if(empty($this->param_shop_product_category)){
            if ($this->param_multi_match)
                $params['query']['bool']['must']['multi_match'] = $this->param_multi_match;
        }else{
            if($this->params['index'] == $this->syncEsType(self::ES_INDEX_SHOP_PRODUCT)){
                //婚品（类目ID）搜索
                $params['query']['bool']['must']['term'] = $this->param_shop_product_category;
            }else{
                $normal_param = [];
                if($from_agg){
                    $shop_product_param['bool']['must'][]['terms']['_index'] = $this->getAggsIndexArray(self::ES_INDEX_SHOP_PRODUCT);
                    $shop_product_param['bool']['must'][]['term'] = $this->param_shop_product_category;
                    $params['query']['bool']['should'][] = $shop_product_param;
                    $normal_param['bool']['must'][]['terms']['_index'] = $this->getAggsIndexArray(null,self::ES_INDEX_SHOP_PRODUCT);
                }
                if ($this->param_multi_match){
                    $normal_param['bool']['must'][]['multi_match'] = $this->param_multi_match;
                }
                if(!empty($normal_param)){
                    if($from_agg)
                        $params['query']['bool']['should'][] = $normal_param;
                    else
                        $params['query']['bool']['must'][] = $normal_param;
                }


            }
        }
        return $params;
    }
    /**
     * 聚合数据
     */
    public function getAggsData()
    {
        $version = Utils::get_version();
        if(!empty($this->control_poster) && $version>7.539){
            return $this->control_poster;
        }
        $params['index'] = C('IS_REL') ? $this->config['agg_index'] : $this->config['test_agg_index'];
        $params['size'] = 0;
        $params['body']['highlight']['fields'][$this->config['highlight_field']]['type'] = 'plain';
        $params['body'] = $this->getMultiMatchParam(true);
        if ($this->param_not_match) $params['body']['query']['bool']['must_not']['multi_match'] = $this->param_not_match;
        $params['body']['aggs'] = $this->getAggFilter();
        $data = $this->connect->search($params);
        $res = $this->aggFormatGroup($data['aggregations']['filter_type']['count_type']['buckets']);
        return $res;
    }

    /**
     * 返回聚合必要过滤条件
     * @return mixed
     */
    private function getAggFilter(){
        foreach (self::ES_INDEX_MAP as $type_name=>$index_name){
            if($type_name==SearchClient::TYPE_CPM_PLAN || $type_name==SearchClient::TYPE_CPM_PLAN_KEYWORD)
                continue;
            $this->setIndexHelper($type_name);
            $filters = $this->index_helper->getDefaultFilter();
            $param_filters = [];
            $sync_index = C('IS_REL') ? substr($index_name, 1) : $index_name;
            $param_filters['bool']['must'][]['term'] = ['_index' => $sync_index];
            foreach ($filters as $key => $filter) {
                $param_filters['bool'][$filter[1]][][$filter[0]] = [$key => $filter[2]];
            }
            $param['filter_type']['filter']['bool']['should'][] = $param_filters;
        }
        $param['filter_type']['aggs']['count_type']['terms']['field'] = $this->config['agg_field'];
        $param['filter_type']['aggs']['count_type']['aggs']['commodity_type']['terms']['field'] = $this->config['agg_set_meal_field'];

        return $param;
    }
    public function setFormatFields($format_fields){
        $this->format_fields = $format_fields;
    }
    /**
     * 格式化列表分页数据
     * @param array $list
     * @return array
     */
    protected function formatListData($list)
    {
        $data = [
            'page_count' => ceil($list['hits']['total'] / CommDef::$PageSize),
            'list' => $this->index_helper->makeUpListData($list['hits']['hits'], $this->format_fields, $this->connect, $this->cpm_count, $this->param_extend_fields, $this->is_flow_search),
        ];
        $es_hit_total = $list['hits']['total'] ? : 0; //es命中总数
        $es_hit_count = count($list['hits']['hits']); //es命中数
        $actual_count = count($data['list']); //程序处理后实际返回数
        $actual_total = $es_hit_total - ($es_hit_count - $actual_count); // 实际返回总数
        $data['total'] = $data['total_count'] = $actual_total > 0 ? $actual_total : 0;//追加total_count，统一服务端api
        return $data;
    }


    public function writeLog($type)
    {
        $headers = Utils::get_http_headers();
        $params['type'] = $params['index'] = C('IS_REL') ? $this->config['log_index'] : $this->config['test_log_index'];
        $req = $_GET;
        unset($req['_URL_']);
        if(count($req)>100) return;//防止过大
        $params['body'] = [
            'type' => $type,
            'request' => $req,
            'header' => $headers,
            'created_at' => date("Y-m-d") . "T". date("H:i:s") ."+08:00",
        ];
        log_write("writeLog ".json_encode($params));
        $this->connect->index($params);
    }

    /**
     * 设置额外查询参数
     * @param string $field
     */
    public function setExtendFields($field = '')
    {
        $this->param_extend_fields = $field;
    }

    /**
     * 设置匹配模式
     * @param string $match_type
     */
    public function setMatchType($match_type = '')
    {
        $this->match_type = $match_type;
    }

    /**
     * 设置匹配关键字数组
     * @param array $match_words
     */
    public function setMatchWords($match_words = [])
    {
        $this->match_words = $match_words;
    }

    /**
     * 设置新娘说类型
     * @param int $is_bride_talk  1新娘说
     */
    public function setBrideTalk($is_bride_talk)
    {
        $this->is_bride_talk = $is_bride_talk;
    }

    /**
     * 解析
     * @param Node $node
     */
    public function setParser($node)
    {
        $this->param_multi_match = ElasticParser::parser($node);
//        exit(json_encode_ex(ElasticParser::parser($node)));
    }

    /**
     * 数组参数
     * @param array $arr
     */
    public function setArrayParser($arr)
    {
        $this->param_multi_match = $arr;
    }
    /**
     * 二级页筛选项转换为ES filter
     * @author yidao
     */
    public function setTranFilter($fields){
        $res = [];
        $is_lvpai = 0;
        $fields = explode(',', $fields);
        if(isset($_GET['filter']['price_min']) || isset($_GET['filter']['price_max'])){
            $min = isset($_GET['filter']['price_min'])?$_GET['filter']['price_min']:0;
            $max = isset($_GET['filter']['price_max'])?$_GET['filter']['price_max']:9999999;
            $res['actual_price'] = $min.','.$max;
        }
        foreach($fields as $field){
            if(isset($_GET[$field]) && $_GET[$field]){
                $res[$field] = $_GET[$field];
            }
            if(strstr($field, 'filter.')!==false){
                $key = str_replace('filter.', '', $field);
                $val = $_GET['filter'][$key];
                if(empty($val))
                    continue;
                switch ($key){
                    case 'service':
                        if($val==1) $res['shiping'] = 0;
                        if($val==2) $res['can_refund'] = 1;
                        break;
                    case 'category_tag_id':
                        if($val == 3280)
                            $is_lvpai = 1;
                        $tags = CategoryTagModel::getChildIds(intval($val));
                        $res[$key] = $tags;
                        break;
                    case 'tags':
                        $res[$key] = $val;
                        break;
                    case 'filter_second_category':
                        $key = 'second_category_id';
                    case 'shop_area_id':
                    case 'sale_way':
                        $res[$key] = $val;
                        break;
                }
            }
        }
        $headers = Utils::get_http_headers();
        if(isset($headers['cid'])&&intval($headers['cid']) && !$is_lvpai)
            $res['city_code'] = $headers['cid'];
        $this->setFilter($res);
    }
    /**
     * 二级页排序转换为ES sort
     * @author yidao
     */
    public function setTranSort($sort){
        $res = [];
        switch ($sort){
            case 'create_time':
                $res[] = 'newest';break;
            case 'price_up':
                $res[] = 'price_asc';break;
            case 'price_down':
                $res[] = 'price_desc';break;
            case 'sold_desc':
                $res[] = 'sold_desc';break;
            default:
                $res[] = 'default';
        }
        $this->setSort($res);
    }
}