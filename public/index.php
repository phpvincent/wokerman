<?php
use Workerman\Worker;
require_once './Workerman/Autoloader.php';
require_once './helper.php';
    // 初始化一个worker容器，监听1234端口
    global $worker;
    Worker::$stdoutFile = '/tmp/stdout.log';
    $route_worker = new Worker('websocket://0.0.0.0:2349');
    // 这里进程数必须设置为1
    $route_worker->count = 1;
    $route_connections=[];
   
    // worker进程启动后建立一个内部通讯端口
    $route_worker->onWorkerStart=function($woker)
    {
        
            require __DIR__."/redis.php";
            //初始化附带信息
            global $redis,$ip_array;
            $ip_array=[];
//            $config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
//            $redis = Rediss::getInstance($config);
            $redis=new \Redis();
            $redis->connect('13.250.109.37',6379);
            $notice_woker=new Worker('text://0.0.0.0:2350');
            $notice_woker->onMessasge='notice_onmessage';
            $notice_woker->listen();
        
    };
    $route_worker->onConnect='route_on_connect';
    $route_worker->onMessage='route_on_message';
    $route_worker->onClose='route_on_close';
    // 运行所有的worker（其实当前只定义了一个）
    Worker::runAll();