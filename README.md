#搜索和cpm广告

![es](./es.jpg)

##查看搜索索引方法
 1 host绑定 121.196.225.18   search.hunliji.com

 2 访问kibana <http://116.62.11.14:5601/app/kibana#/dev_tools/console?_g=()>

 3 console 输入 
 ``````
 GET hl_shop_product/hl_shop_product/婚品ID 查看正式索引数据
 GET thl_shop_product/thl_shop_product/婚品ID 查看测试索引数据
  ``````          
##举个例子
* 比如搜套餐，url为:<br>
http://www7.hunliji.com/p/wedding/Home/APISearchV3/list?keyword=abc&type=package
* url追加“&debug=999”可以得到搜索里ES语句，用到了hl_set_meals索引，复制到kibana里，稍微改下，就能运行
![es](./es_result.png)

* url追加“&debug=66”可以得到安插在搜索中CPM的ES语句，用到了hl_plan索引
![cpm](./cpm_result.png)

* url追加“&debug=666” 返回json格式CPM相关的ES语句，方便阅读
<br>
<br>
![cpm](./json.png)