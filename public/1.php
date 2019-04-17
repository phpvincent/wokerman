<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/16 0016
 * Time: 12:00
 */
require __DIR__."/redis.php";
$config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
$redis = Rediss::getInstance($config);
//$redis=new \Redis();
//$redis->connect('127.0.0.1',6379);
$redis->hset('routes','999',2);
var_dump($redis->hGetAll("routes"));
//$my_redis = new \Redis();
//SET runoobkey redis
//$aa = $my_redis->set('runoobkey','redis');
//var_dump($my_redis->get('runoobkey'));
