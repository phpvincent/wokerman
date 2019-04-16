<?php
use Workerman\Worker;
require_once './Workerman/Autoloader.php';
require __DIR__."/redis.php";

// 初始化一个worker容器，监听1234端口
$worker = new Worker('websocket://0.0.0.0:1234');

/*
 * 注意这里进程数必须设置为1，否则会报端口占用错误
 * (php 7可以设置进程数大于1，前提是$inner_text_worker->reusePort=true)
 */
$worker->count = 4;
// worker进程启动后创建一个text Worker以便打开一个内部通讯端口
$worker->onWorkerStart = function($worker)
{
    global $redis;
    $config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
    $redis = Rediss::getInstance($config);
    // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
    $inner_worker1 = new Worker('text://0.0.0.0:5678');
    $inner_worker1->onMessage = function($connection, $buffer)
    {

        // $data数组格式，里面有uid，表示向那个uid的页面推送数据
//        $data = json_decode($buffer, true);
//        $uid = $data['uid'];
//         通过workerman，向uid的页面推送数据
//        $ret = sendMessageByUid($uid, $buffer);
        // 返回推送结果
        $connection->send('ok');
        var_dump(111);

        var_dump(2222);
    };
    // ## 执行监听 ##
    $inner_worker1->listen();
};
// 新增加一个属性，用来保存uid到connection的映射
$worker->uidConnections = array();

$worker->onConnect=function ($connection)
{
    //初始化附带信息
//    $con_msg=route_msg_start();
    $ip=$connection->getRemoteIp();
    var_dump(3333);
    var_dump($connection);
    var_dump($ip);
    var_dump(444);
//    global $route_connections,$ip_array;
//    $connection->msg=['ip'=>$ip];
//    $route_connections[$ip]=$connection;
//    //记录ip与对应线程数
//    if(!isset($ip_array[$ip])){
//        $ip_array[$ip]=1;
//    }else{
//        $ip_array[$ip]+=1;
//    }
//    echo 'ip:'.$ip."/n";
};

// 当有客户端发来消息时执行的回调函数
$worker->onMessage = function($connection, $data)
{
    global $worker;
    // 判断当前客户端是否已经验证,既是否设置了uid
    if(!isset($connection->uid))
    {
        // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
        $connection->uid = $data;
        /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
         * 实现针对特定uid推送数据
         */
        $worker->uidConnections[$connection->uid] = $connection;
        return;
    }
};

// 当有客户端连接断开时
$worker->onClose = function($connection)
{
    global $worker;
    if(isset($connection->uid))
    {
        // 连接断开时删除映射
        unset($worker->uidConnections[$connection->uid]);
    }
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

// 运行所有的worker
Worker::runAll();