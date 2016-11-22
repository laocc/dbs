<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

include '../kernel/autoload.php';

$action = isset($_GET['action']) ? $_GET['action'] : null;

echo <<<HTML
    <style>
        html,body{width:100%;}
        a{color:#000;}
        ul,li{list-style: none;}
        ul{clear:both;display:block;width:100%;height:50px;}
        li{float:left;margin:10px;}
    </style>
    <ul>
        <li><a href="?action=test">测试读写</a></li>
        <li><a href="?action=speed">Mary速度对比</a></li>
    </ul>
HTML;

$config = load('config.php');

switch ($action) {
    case 'test':
        $cont = new \demo\TestController($config);
        //$cont->mysql();
        $cont->yac();
        $cont->memcache();
        $cont->memcached();
        $cont->redis();
        $cont->mongodb();
        $cont->apcu();
        break;

    case 'speed':
        $obj = new \demo\SpeedController($config, 'set', 10000);
        //$obj->memcache();
        $obj->memcached();
        $obj->apcu();
        $obj->redis();
        $obj->yac();
        $obj = new \demo\SpeedController($config, 'get', 10000);
        //$obj->memcache();
        $obj->memcached();
        $obj->apcu();
        $obj->redis();
        $obj->yac();
        break;
    default:

}
