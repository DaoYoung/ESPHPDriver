<?php
/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/1/4
 * Time: 16:59
 */
return [
    'host' => ['search.hunliji.com'],
    'match_field' => ['match_*'],
    'agg_index' => 'hl_*',
    'test_agg_index' => 'thl_*',
    'agg_field' => '_index',
    'agg_set_meal_field' => 'commodity_type',//套餐案例索引在一起，不能通过类型区分，只能通过commodity_type区分
    'highlight_field' => 'match_*',
    'index_name' => 'wedding',
    'cpm_distance' => '50km',
    'location_field' => 'city_location',
    'default_cid' => 256, // 全国城市定位三亚
    'log_index' => 'wedding_log',
    'test_log_index' => 'test_wedding_log',
    'cpm_lvpai_count' => 3, // cpm套餐旅拍城市单独取3个
    'not_match_word' => '测试', //不匹配字段 多个用空格分隔
    'merchant_word' => '罗莱家纺', //http://jira.hunliji.com/browse/PRD-4076
    'car_keywrod' => '婚车,婚车商家,婚车商,租婚车,订婚车,预约婚车,婚车预约,婚车档期,宝马,奥迪,奔驰,保时捷,玛莎拉蒂,宾利,劳斯莱斯,法拉利,兰博基尼,林肯,大巴,大众,哈雷,Jeep,布加迪,加长林肯,老爷车,尼桑,沃尔沃,英菲尼迪,迈凯伦,福特,本田,凯迪拉克,丰田,捷豹,路虎,悍马,马自达,克莱斯勒', // http://jira.hunliji.com/browse/DEV-5940
];