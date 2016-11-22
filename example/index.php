<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

include '../kernel/autoload.php';

$config = load('config.php');
//$cont = new \demo\TestController($config);
////$cont->mysql();
//$cont->yac();
//$cont->memcache();
//$cont->memcached();
//$cont->redis();
//$cont->mongodb();
//$cont->apcu();

//速度测试
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
