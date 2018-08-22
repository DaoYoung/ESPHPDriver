<?php
require '../vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2018/1/3
 * Time: 15:13
 */
class ElasticDriver
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
    protected $param_multi_match = [];
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
     * 应对cpm在不同页面对同表的不同展示字段
     */
    protected $format_fields;

    const INDEX_MAP = [
        "students" => "es_php",
        "teachers" => "es_php",
    ];

    public function __construct()
    {
        $this->config = require(__DIR__ . '/ElasticConfig.php');

        $this->connect = Elasticsearch\ClientBuilder::create()->setConnectionPool(
            '\Elasticsearch\ConnectionPool\SniffingConnectionPool', []
        )->setHosts(
            $this->config['host']
        )->build();
        spl_autoload_register(function ($class) {
            include  $class . '.class.php';
        });
    }

    /**
     * @return Elasticsearch\Client
     */
    public function client()
    {
        return $this->connect;
    }

    /**
     * 设置索引index\type
     */
    public function setType($type_name)
    {
        $this->setIndexHelper($type_name);
        $this->params['type'] = $type_name;
        $this->params['index'] = self::INDEX_MAP[$type_name];
        $this->setDefaultFilter();
        return $this;
    }

    /**
     * @param $type_name
     * @throws Exception
     */
    private function setIndexHelper($type_name)
    {
        switch ($type_name) {
            case "students":
                $this->index_helper = new ElasticIndexStudent();
                break;
            default:
                throw new Exception('NO_ElasticIndex_Helper');
        }
    }

    /**
     * 设置过滤
     * @param array $where
     * @return self
     */
    public function setFilter($where)
    {
        $filter_map = $this->index_helper->getFilterMap();
        foreach ($where as $key => $val) {
            if (!array_key_exists($key, $filter_map)) continue;
            list($operator, $symbol) = $filter_map[$key];
            switch ($operator) {
                case 'term':
                    $this->param_filters[$symbol][][$operator] = [$key => $val];
                    break;
                case 'range':
                    if (strstr($val, ',')) {
                        $key = str_replace("_range", "", $key);
                        list($min, $max) = explode(',', $val);
                        $range = [];
                        if ($min !== '') $range['gte'] = $min;
                        if ($max !== '') $range['lte'] = $max;
                        if (count($range)) $this->param_filters[$symbol][][$operator] = [$key => $range];
                    }
                    break;
                default:
                    break;
            }
        }
        return $this;
    }
    /**
     * 设置默认过滤
     */
    private function setDefaultFilter()
    {
        $filters = $this->index_helper->getDefaultFilter();
        foreach ($filters as $key => $filter) {
            list($operator, $symbol, $value) = $filter;
            $this->param_filters[$symbol][][$operator] = [$key => $value];
        }
    }
    /**
     * 设置查询关键字
     * @param string $query_string
     */
    public function setQuery($query_string, $is_highlight=false)
    {
        if (!empty($query_string)) {
            $this->param_multi_match = [
                'tie_breaker' => '0.3',
                'minimum_should_match' => '80%',
                'query' => $query_string,
                'fields' => array_merge($this->config['match_field'], $this->index_helper->getBoost())
            ];
        }
        if (isset($this->config['not_match_word'])) {
            $this->param_not_match = [
                'query' => $this->config['not_match_word'],
                'fields' => $this->config['match_field']
            ];
        }
        if ($is_highlight)
            $this->param_highlight['fields'][$this->config['match_field'][0]]['type'] = 'plain';
        return $this;
    }
    /**
     * 设置排序
     * @param array $sort_array
     */
    public function setSort($sort_array)
    {
        foreach ($sort_array as $sort_type) {
            $sort = $this->index_helper->getSortParams($sort_type);
            if ($sort) $this->param_sort[] = $sort;
        }
        return $this;
    }

    private function makeupParams()
    {
        $params = [];
        if ($this->param_multi_match) $params['query']['bool']['must']['multi_match'] = $this->param_multi_match;
        if ($this->param_not_match) $params['query']['bool']['must_not']['multi_match'] = $this->param_not_match;
        if ($this->param_sort) $params['sort'] = $this->param_sort;
        if ($this->param_collapse) $params['collapse'] = $this->param_collapse;
        if ($this->param_highlight) $params['highlight'] = $this->param_highlight;
        if ($this->param_filters) $params['query']['bool']['filter']['bool'] = $this->param_filters;
        $this->params['body'] = $params;
    }
    /**
     * 获取结果列表
     * @return array
     * @throws Exception
     */
    public function getResultList($scroll_id = '', $size = 20)
    {
        if ($size > 50) $size = 20;
        $this->params['size'] = $size;
        $this->params['scroll'] = "30s";
        try {
            $this->makeupParams();
            if (isset($_GET['debug']) && $_GET['debug'] == 'dump') {
                exit(json_encode($this->params));
            }
            $list = $this->connect->search($this->params);
            if($scroll_id){
                $scroll['scroll'] = "1s";
                $scroll['scroll_id'] = $scroll_id;
                $list = $this->connect->scroll($scroll);
            }
            return $list;
        } catch (Exception $e) {
            throw new ElasticException($this->connect, 'search error', $e->getMessage());
        }
    }
    /**
     * 聚合数据
     */
    public function getTips()
    {
        $this->makeupParams();
        $params['index'] = $this->config['test_agg_index'];
//        $params['body']['aggs'] = $this->getAggFilter();
        $data = $this->connect->search($params);
        return $data;
    }

    /**
     * 返回聚合必要过滤条件
     * @return mixed
     */
    private function getAggFilter()
    {
        foreach (self::ES_INDEX_MAP as $type_name => $index_name) {
            if ($type_name == SearchClient::TYPE_CPM_PLAN || $type_name == SearchClient::TYPE_CPM_PLAN_KEYWORD)
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

    public function setFormatFields($format_fields)
    {
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
        $es_hit_total = $list['hits']['total'] ?: 0; //es命中总数
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
        if (count($req) > 100) return;//防止过大
        $params['body'] = [
            'type' => $type,
            'request' => $req,
            'header' => $headers,
            'created_at' => date("Y-m-d") . "T" . date("H:i:s") . "+08:00",
        ];
        log_write("writeLog " . json_encode($params));
        $this->connect->index($params);
    }


}