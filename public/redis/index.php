<?php
namespace redis;
//var_dump(__DIR__."\Base.php");
//use redis\Base;
require __DIR__."/Base.php";
//$my_redis = new Base();
//$my_redis = Base::getRedis();
$my_redis = \Redis::set('aa','123456');
\Redis::get('aa');
//$aa = $my_redis->set('aa','1234');
//var_dump($my_redis->get('aa'));