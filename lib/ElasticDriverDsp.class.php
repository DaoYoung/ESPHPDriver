<?php

import("@.Service.Search.Elastic.ElasticDriver");

class ElasticDriverDsp extends ElasticDriver
{
    /**
     * 设置索引
     * @param string $type_name
     * @param int $cpm_count 搜索cpm的数量
     * @param bool $is_cpm_only 是否只搜索cpm结果
     */
    public function setType($type_name, $cpm_count=0, $is_cpm_only=false, $use_flow_index=false)
    {
        $this->params['type'] = $this->params['index'] = C('IS_REL') ? substr(self::ES_INDEX_MAP[$type_name], 1) : self::ES_INDEX_MAP[$type_name];
        $this->param_sort = $this->param_filters = null;//置空，便于多次调用
        $this->setIndexHelper($type_name);
    }
    /**
     * 设置默认过滤
     */
    public function setDefaultFilter()
    {
        $filters = $this->index_helper->getDefaultFilter($this->is_flow_search);
        foreach ($filters as $key => $filter) {
            if($filter[0] == 'exists')
                $this->param_filters[$filter[1]][]['exists']['field'] = $key;
            elseif($filter[0] == 'range')
                $this->param_filters[$filter[1]][][$filter[0]][$key]['gte'] = $filter[2];
            else
                $this->param_filters[$filter[1]][][$filter[0]] = [$key => $filter[2]];
        }
    }
    /**
     * 设置查询关键字
     * @param string $query_string
     */
    public function setQueryCpm($query_string, $fields, $type='')
    {
        if($fields && !is_array($fields))
            $fields = explode(',', $fields);
        if (!empty($query_string)) {
            // 将营销词转换成热搜词
            $query_string = SearchWordV2Model::alias2title($query_string);
            $match = $type==SearchClient::TYPE_CPM_PLAN_KEYWORD?"100%":"60%";
            $this->param_multi_match = [
                'tie_breaker' => "0.3",
                'minimum_should_match' => $match,
                'query' => $query_string,
                'fields' => $fields
            ];
        }
        $collapse = $this->index_helper->getCpmCollapseField();
        if ($collapse) $this->param_collapse['field'] = $collapse;

    }

    /**
     * 设置排序
     * @param array $sort_array 排序规则
     */
    public function setSort($sort_array)
    {
        if (!$this->index_helper) return;
        $sort = $this->index_helper->getSortParam('score');
        if($sort) $this->param_sort[] = $sort;
        //去除测试CPM
        if(get_class($this->index_helper)=='ElasticIndexPlan' && C('IS_REL')==1)
            $this->param_not_match = [
                'query' => $this->config['not_match_word'],
                'fields' => 'entity_title'
            ];
    }
    /**
     * 获取结果列表
     * @return array
     * @throws Exception
     */
    public function getPlanList($cpm_type='')
    {
        try {
            $params = $this->buildParams();
            if(isset($_GET['debug'])&& $_GET['debug']==66){
                if(!isset($_GET['debug_type']) || $_GET['debug_type']==$cpm_type)
                    debug(json_encode($params));
            }
            $collapse_field = isset($params['body']['collapse']['field'])?$params['body']['collapse']['field']:'';
            $list = $this->connect->search($params);
            $res = $this->formatListData($list, $collapse_field,$cpm_type);
            if(isset($_GET['debug'])&& $_GET['debug']==666){
                $res['debug_plan'] = $params;
            }
            return $res;
        } catch (Exception $e) {
            throw new ElasticException($this->connect, 'search error', $e->getMessage());
        }
    }

    /**
     * 格式化列表分页数据
     * @param array $list
     * @return array
     */
    protected function formatListData($list, $collapse_field,$cpm_type)
    {
        $data = [
            'page_count' => ceil($list['hits']['total'] / CommDef::$PageSize),
            'list' => $this->index_helper->makeUpListData($list['hits']['hits'], $this->format_fields, $this->connect, $this->cpm_count, $this->param_extend_fields, $this->is_flow_search, $collapse_field,$cpm_type)
        ];
        $es_hit_total = $list['hits']['total'] ? : 0; //es命中总数
        $es_hit_count = count($list['hits']['hits']); //es命中数
        $actual_count = count($data['list']); //程序处理后实际返回数
        $actual_total = $es_hit_total - ($es_hit_count - $actual_count); // 实际返回总数
        $data['total'] = $actual_total > 0 ? $actual_total : 0;
        return $data;
    }
}