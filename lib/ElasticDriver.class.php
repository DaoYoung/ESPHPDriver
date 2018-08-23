<?php
require '../vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: DaoYoung
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
     * [collapse] => $param_collapse //todo
     */
    protected $params;
    protected $param_multi_match = [];
    protected $param_not_match;
    protected $param_filters;
    protected $param_sort;
    protected $param_collapse;
    protected $param_highlight;

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
            require_once  $class . '.class.php';
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
            case "teachers":
                $this->index_helper = new ElasticIndexTeacher();
                break;
            default:
                throw new Exception('NO_ElasticIndex_Helper:'.$type_name);
        }
    }

    /**
     * 设置过滤
     * @param array $where
     * @return static
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
        $filters = $this->index_helper->getFilterDefault();
        foreach ($filters as $key => $filter) {
            list($operator, $symbol, $value) = $filter;
            $this->param_filters[$symbol][][$operator] = [$key => $value];
        }
    }
    /**
     * 设置查询关键字
     * @param string $query_string
     * @return static
     */
    public function setQuery($query_string, $is_highlight=false)
    {
        if (!empty($query_string)) {
            $boost = $this->index_helper ? $this->index_helper->setBoost() : [];
            $this->param_multi_match = [
                'tie_breaker' => '0.3',
                'minimum_should_match' => '80%',
                'query' => $query_string,
                'fields' => array_merge($this->config['match_field'], $boost)
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
     * @param string $str
     * @return static
     */
    public function setSort($str)
    {
        if(empty(trim($str))) return $this;
        $arr = explode(',', $str);
        foreach ($arr as $item){
            if(empty($item)) continue;
            $item = explode(" ", $item);
            if($item[0] == "func"){
                $this->param_sort[] = $this->index_helper->getSortParamsByFunc($item[1]);
            }else{
                if(empty($item[1])) $item[1] = "desc";
                $this->param_sort[][$item[0]] = $item[1];
            }
        }
        return $this;
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
            throw new ElasticException($this->connect, 'getResultList error', $e->getMessage());
        }
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
     * 聚合数据
     * @throws Exception
     * @param array $where
     * @return array
     */
    public function getTips($where=[])
    {
        try {
            $params = $this->makeupAggParams($where);
            if (isset($_GET['debug']) && $_GET['debug'] == 'dump') {
                exit(json_encode($params));
            }
            $data = $this->connect->search($params);
            return $data;
        } catch (Exception $e) {
            throw new ElasticException($this->connect, 'getTips error', $e->getMessage());
        }
    }
    /**
     * 聚合过滤
     * @throws Exception
     * @param array $where
     * @return array
     */
    private function makeupAggParams($where=[])
    {
        $agg_params = $filters = [];
        $agg_params['index'] = $this->config['agg_index'];
        $agg_params['size'] = 0;
        if ($this->param_multi_match) $agg_params['body']['query']['bool']['must']['multi_match'] = $this->param_multi_match;
        if ($this->param_not_match) $agg_params['body']['query']['bool']['must_not']['multi_match'] = $this->param_not_match;
        if ($this->param_highlight) $agg_params['body']['highlight'] = $this->param_highlight;
        foreach (self::INDEX_MAP as $type_name => $index_name) {
            $this->param_filters = [];
            $this->setIndexHelper($type_name);
            $this->setFilter($where)->setDefaultFilter();
            $this->param_filters['must'][]['term'] = ['_index' => $index_name];
            $this->param_filters['must'][]['term'] = ['_type' => $type_name];
            $filters[$type_name.'_count']['filter']['bool'] = $this->param_filters;
        }
        $agg_params['body']['aggs'] = $filters;
        return $agg_params;
    }
}