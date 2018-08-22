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

        $params['index'] = 'es_log';
        $params['type'] = 'es_log';
        $params['body'] = [
            'type' => $error_type,
            'content' => $message,
//            'server' => $_SERVER,
//            'request' => $_REQUEST,
            'created_at' => date("Y-m-d") . "T". date("H:i:s") ."+08:00",
        ];
        print_r($params);exit;
        return parent::__construct($message, $code, $previous);
    }
}