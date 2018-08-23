<?php

/**
 * Created by PhpStorm.
 * User: DaoYoung
 * Date: 2018/1/31
 * Time: 11:46
 */
class ElasticException extends Exception
{
    function __construct(\Elasticsearch\Client $connect, $error_type, $message = "", $code = 0, Throwable $previous = null)
    {

        $params['index'] = 'es_log';
        $params['type'] = 'es_log';
        $params['body'] = [
            'type' => $error_type,
            'content' => $message,
            'request' => json_encode($_REQUEST),
            'created_at' => date("Y-m-d") . "T". date("H:i:s") ."+08:00",
        ];
//        $connect->index($params);
        return parent::__construct($message, $code, $previous);
    }
}