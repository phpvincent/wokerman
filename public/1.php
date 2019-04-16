<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/16 0016
 * Time: 12:00
 */
require __DIR__."/redis.php";
$config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
$my_redis = Rediss::getInstance($config);
//$my_redis = new \Redis();
$aa = $my_redis->set('aa',100);
var_dump($my_redis->get('aa'));
