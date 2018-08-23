<html>
<body style="align: 0 auto;">
<style>
    ul li{
        margin-bottom: 20px;
    }
</style>
<h1>Examples</h1>
<div>
    <h3>Init data</h3>
    At first, you need create some data in Elastic Search. You should make right params in lib/ElasticConfig.php, then
    <a href="data.php" target="_blank">visit data.php</a>.
</div>
<div>
    <div>
        <h3>Get results list</h3>
        <ul>
            <li> You must add parameter `type`, like `type=students`.
                <br>example:<a href="list.php?type=students" target="_blank">list.php?type=students</a></li>
            <li> You can add parameter `filter[field]`. however, you must declare abstract ElasticIndex::getFilterMap().
                <br>example: find age 10-19 students <a href="list.php?filter[age_range]=10,19&type=students" target="_blank">list.php?filter[age_range]=10,19&type=students</a>
            </li>
            <li> You can add parameter `sort`.
                <ul>
                    <li>it support multi sort, like sql. so it seems `sort=id desc,age asc`, separate by `,`.
                        <br>example:<a href="list.php?filter[age_range]=10,19&type=students&sort=id desc,age asc"
                                       target="_blank">list.php?filter[age_range]=10,19&type=students&sort=id
                            desc,age asc</a></li>
                    <li>it support function sort. so you can make it like `sort=func young`, defined by prefix `func`.
                        <br>example:<a href="list.php?filter[age_range]=10,19&type=students&sort=func young"
                                       target="_blank">list.php?filter[age_range]=10,19&type=students&sort=func
                            young</a>
                    </li>
                </ul>
            </li>
            <li> You can add parameter `debug=dump` to get Elastic Search code.
                <br>example:<a href="list.php?filter[age_range]=10,19&type=students&debug=dump&sort=func young"
                               target="_blank">list.php?filter[age_range]=10,19&type=students&debug=dump&sort=func
                    young</a></li>
        </ul>
    </div>
</div>
<div>
    <div>
        <h3>Aggregation</h3>
    </div>
    example: find how many student/teacher like `dance`
    <a href="tip.php?keyword=dance" target="_blank">tip.php?keyword=dance</a>
</div>
</body>
</html>