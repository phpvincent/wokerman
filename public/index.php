<?php
use Workerman\Worker;
require_once './Workerman/Autoloader.php';
    // 初始化一个worker容器，监听1234端口
    global $worker;
    Worker::$stdoutFile = '/tmp/'.date('Y_m_d').'stdout.log';
    $worker = new Worker('text://0.0.0.0:2349');
    // 这里进程数必须设置为1
    $worker->count = 1;
    $workers=[];
    // worker进程启动后建立一个内部通讯端口
    $worker->onWorkerStart = function($worker)
    {
        global $workers;
        $workers[1]=$innet_woker1=new woker('websocket://0.0.0.0:2350');
        $innet_woker1->reusePort=true;
        $innet_woker->onMessage(function($con,$buffet){
            //...
        })
        $innet_woker1->listen();
        $workers[2]=$innet_woker2=new woker('websocket://0.0.0.0:2350');
         $innet_woker2->reusePort=true;
        $innet_woker->onMessage(function($con,$buffet){
            //...
        })
        $innet_woker2->listen();
        $workers[3]=$innet_woker3=new woker('websocket://0.0.0.0:2350');
         $innet_woker3->reusePort=true;
        $innet_woker->onMessage(function($con,$buffet){
            //...
        })
        $innet_woker3->listen();
    };
    // 新增加一个属性，用来保存uid到connection的映射
    $worker->uidConnections = array();
    // 当有客户端发来消息时执行的回调函数
    $worker->onMessage = function($connection, $data)use($worker)
    {
    };
    
    // 当有客户端连接断开时
    $worker->onClose = function($connection)use($worker)
    {
        
    };

    // 向所有验证的用户推送数据
    function broadcast($message)
    {
       global $worker;
       foreach($worker->uidConnections as $connection)
       {
            $connection->send($message);
       }
    }
    
    // 针对uid推送数据
    function sendMessageByUid($uid, $message)
    {
        global $worker;
        if(isset($worker->uidConnections[$uid]))
        {
            $connection = $worker->uidConnections[$uid];
            $connection->send($message);
            return true;
        }
        return false;
    }
    
    // 运行所有的worker（其实当前只定义了一个）
    Worker::runAll();