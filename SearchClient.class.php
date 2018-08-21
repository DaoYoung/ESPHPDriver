<?php
/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2016/11/9
 * Time: 14:29
 * @method  SearchClient setType($type_name, $cpm_count=0, $is_cpm_only=false, $use_flow_index=false)
 * @method  SearchClient setSort($sort_type)
 * @method  SearchClient setReSort($sort_type)
 * @method  SearchClient setFilter($filter_array)
 * @method  SearchClient setQuery($query_string)
 * @method  SearchClient setQueryCpm($keyword, $es_fields, $type)
 * @method  SearchClient setHighlight($is_highlight)
 * @method  SearchClient setExtendFields($fields='')
 * @method  SearchClient setMatchType($match_type)
 * @method  SearchClient setMatchWords($match_words)
 * @method  SearchClient setBrideTalk($is_bride_talk)
 * @method  SearchClient setParser($node)
 * @method  SearchClient setArrayParser($arr)
 * @method  SearchClient setTranFilter($fields)
 * @method  SearchClient setTranSort($sort)
 * @method  writeLog($type)
 * @method  getPlanList()
 * @method  getResultList()
 * @see ElasticDriver::getResultList()
 * @method  getAggsData()
 * @see ElasticDriver::getAggsData()
 * @method  SearchClient setFormatFields($format_fields)
 * @method  SearchClient setPage($page=1, $size)
 * @see ElasticDriver::setPage()
 */
class SearchClient
{

    const TYPE_PACKAGE = 'package';
    const TYPE_EXAMPLE = 'example';
    const TYPE_MERCHANT = 'merchant';
    const TYPE_SHOP_PRODUCT = 'shop_product';
    const TYPE_HOTEL = 'hotel';
    const TYPE_CAR = 'car';
    const TYPE_NOTE = 'note';
    const TYPE_QA = 'qa';
    const TYPE_COMMUNITY_THREAD = 'community_thread';
    const TYPE_COMMUNITY_COMBINE = 'community_combine';
    const TYPE_CPM_PLAN = 'plan';
    const TYPE_CPM_PLAN_KEYWORD = 'plan_keyword';

    private $_driver_path = 'Elastic';
    private $_driver_path_common = 'ElasticDriver';
    private $_driver_path_cpm = 'ElasticDriverCpm';
    private $_driver_path_dsp = 'ElasticDriverDsp';
    /**
     * @var SearchDriver $_driver
     */
    public $_driver;

    /**
     * 取得搜索类实例
     * @static
     * @return SearchClient 返回数据库驱动类
     */
    public static function getInstance() {
        $args = func_get_args();
        return get_instance_of(__CLASS__,'factory',$args);
    }

    /**
     * 加载搜索接口
     * @throws Exception
     * @return SearchClient
     */
    public function factory() {
        $args = func_get_args();
        $class_name = $args[0] ? $this->_driver_path_cpm : $this->_driver_path_common;
        if($args[0]=='dsp') $class_name=$this->_driver_path_dsp;
        $file = LIB_PATH.'Service/Search/'.$this->_driver_path.'/'.$class_name.'.class.php';
        // 检查驱动类
        if(file_exists($file)) {
            require_cache($file) ;
            $client = new self;
            $client->setDriver(new $class_name);
            return $client;
        }else {
            // 类没有定义
            throw_exception('NO_SEARCH_DRIVER: ' . $file);
        }
    }

    /**
     * 设置搜索驱动类
     */
    public function setDriver(SearchDriver $driver)
    {
        $this->_driver = $driver;
    }

    /**
     * 调用driver方法
     *
     * @return SearchClient|array
     */
    public function __call($name, $arguments)
    {
        if(method_exists($this->_driver, $name)){
//            count($arguments) == 1 && $arguments = array_pop($arguments);
            $res = call_user_func_array(array($this->_driver, $name), $arguments);
            if(substr($name, 0, 3) == 'set'){
                return $this;
            }else{
                return $res;
            }
        }

    }

}

class Node {
    public $value;
    public $left;
    public $right;
}