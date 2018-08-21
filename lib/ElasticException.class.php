<?php

/**
 * Created by PhpStorm.
 * User: yidao
 * Date: 2018/1/31
 * Time: 11:46
 */
class ElasticException extends Exception
{
    function __construct(\Elasticsearch\Client $connect, $error_type, $message = "", $code = 0, Throwable $previous = null)
    {
//        $headers = Utils::get_http_headers();
//        $data[] = ['index' => new stdClass()];
//        $data[] = [
//            'type' => $error_type,
//            'app_name' => $headers['appname'],
//            'app_ver' => $headers['appver'],
//            'device' => $headers['devicekind'],
//            'content' => $message,
//            'request' => json_encode_ex($_REQUEST),
//            'headers' => json_encode_ex($headers),
//            'created_at' => date("Y-m-d") . "T". date("H:i:s") ."+08:00",
//        ];
//        $params['index'] = 'es_log_index';
//        $params['type'] = 'es_log_type';
//        $params['body'] = $data;
//        $connect->bulk($params);

        $headers = Utils::get_http_headers();
        $params['index'] = 'es_log';
        $params['type'] = 'es_log';
        $params['body'] = [
            'type' => $error_type,
            'app_name' => $headers['appname'],
            'app_ver' => $headers['appver'],
            'device' => $headers['devicekind'],
            'content' => $message,
            'request' => $_REQUEST,
            'headers' => $headers,
            'created_at' => date("Y-m-d") . "T". date("H:i:s") ."+08:00",
        ];
//        $connect->index($params);
        log_write("ElasticException ".$message);
        return parent::__construct($message, $code, $previous);
    }
}