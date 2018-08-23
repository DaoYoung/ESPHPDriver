# Examples

### Init data
At first, you need create some data in Elastic Search. You should make right params in lib/ElasticConfig.php, then visit data.php.
### Get results list
* You must add parameter `type`, like `list.php?type=students`.
* You can add parameter `filter[field]`. however, you must declare abstract ElasticIndex::getFilterMap(). <br>example:`list.php?filter[age_range]=10,19&type=students`
* You can add parameter `sort`.
   * it support multi sort, like sql. so it seems `sort=id desc,age asc`, separate by `,`. 
   <br>example:`list.php?filter[age_range]=10,19&type=students&sort=id desc,age asc`
   * it support function sort. so you can make it like `sort=func young`, defined by prefix `func`. 
   <br>example:`list.php?filter[age_range]=10,19&type=students&sort=func
                                   young`
* You can add parameter `debug=dump` to get Elastic Search code.
<br>example:`list.php?filter[age_range]=10,19&type=students&debug=dump&sort=func
                    young`
### Aggregation
example: find how many student/teacher like dance, `tip.php?keyword=dance`


