<?php
use Workerman\Worker;
require_once './Workerman/Autoloader.php';
require_once './helper.php';
require __DIR__."/redis.php";

    // 初始化一个worker容器，监听1234端口
    global $worker;
    Worker::$stdoutFile = '/tmp/stdout.log';
    $route_worker = new Worker('websocket://0.0.0.0:1234');
    // 这里进程数必须设置为1
    $route_worker->count = 4;
    $route_connections=[];
    $datas = [];

    // worker进程启动后建立一个内部通讯端口
    $route_worker->onWorkerStart=function ($woker)
    {
        //初始化附带信息
        global $redis,$ip_array;
        $ip_array=[];
        $config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
        $redis = Rediss::getInstance($config);
        var_dump($redis);
        //        $redis=new \Redis();
        //        $redis->connect('13.250.109.37',6379);
        $notice_woker=new Workerman\Worker('websocket://0.0.0.0:5678');
        $notice_woker->reusePort = true;
        $notice_woker->onMessasge=function ($connection,$data)
        {
            var_dump(111);
            global $redis,$datas;
            var_dump($redis);
            if(!in_array($data, $datas)){
                array_push($datas, $data);
                $data=json_decode($data,true);
                if(!isset($data['route'])||!isset($data['ip_info'])){
                    $connection->send(ws_return('route or ip_info not found',1));
                    return;
                }
                $route=$data['route'];
                $connection->msg['route']=$route;
                if($redis->hGet('routes',$route)==null||$redis->hGet('routes',$route)==false){
                    $redis->hSet('routes',$route,1);
                    $redis->hSet('routes_ips',$route,$connection->msg['ip']);
                }else{
                    $old_ips=$redis->hGet('routes_ips',$route);
                    if($old_ips==false||$old_ips==null){
                        $redis->hSet('routes_ips',$route,$connection->msg['ip']);
                    }else{
                        $ips=explode(',', $old_ips);
                        if(!in_array($connection->msg['ip'], $ips)){
                            if(count($ips)<=0){
                                $ips=[];
                                $ips[]=$connection->msg['ip'];
                            }else{
                                $ips[]=$connection->msg['ip'];
                            }
                            $redis->hSet('routes',$route,$redis->hGet('routes',$route)+1);
                            $redis->hSet('routes_ips',$route,implode(',', $ips));
                        }
                    }
                }
                $ip_info=$data['ip_info'];
                $redis->hSet('route_ip_msg',$connection->msg['ip'],$ip_info);
                $connection->send( ws_return('connect_success',0));
                return;
            }
        };
        $notice_woker->listen();
    };

    $route_worker->onConnect=function ($connection)
    {
        global $redis;
        //初始化附带信息
        $con_msg=route_msg_start();
        $ip=$connection->getRemoteIp();
        global $route_connections,$ip_array;
        $connection->msg=['ip'=>$ip];
        $redis->set('JC:'.$ip,$connection->id);
        $route_connections[$ip][$connection->id]=$connection;
        //记录ip与对应线程数
        if(!isset($ip_array[$ip])){
            $ip_array[$ip]=1;
        }else{
            $ip_array[$ip]+=1;
        }
        echo 'ip:'.$ip."PHP_EOL";
    };

    $route_worker->onMessage=function ($connection,$data)
    {
        var_dump(22222);
        global $redis,$datas;
        var_dump($redis);
        if(!in_array($data, $datas)) {
            array_push($datas, $data);
            $data = json_decode($data, true);
            if (!isset($data['route']) || !isset($data['ip_info'])) {
                $connection->send(ws_return('route or ip_info not found', 1));
                return;
            }
            $route = $data['route'];
            $connection->msg['route'] = $route;
            if ($redis->hGet('routes', $route) == null || $redis->hGet('routes', $route) == false) {
                $redis->hSet('routes', $route, 1);
                $redis->hSet('routes_ips', $route, $connection->msg['ip']);
            } else {
                $old_ips = $redis->hGet('routes_ips', $route);
                if ($old_ips == false || $old_ips == null) {
                    $redis->hSet('routes_ips', $route, $connection->msg['ip']);
                } else {
                    $ips = explode(',', $old_ips);
                    if (!in_array($connection->msg['ip'], $ips)) {
                        if (count($ips) <= 0) {
                            $ips = [];
                            $ips[] = $connection->msg['ip'];
                        } else {
                            $ips[] = $connection->msg['ip'];
                        }
                        $redis->hSet('routes', $route, $redis->hGet('routes', $route) + 1);
                        $redis->hSet('routes_ips', $route, implode(',', $ips));
                    }
                }
            }
            $ip_info = $data['ip_info'];
            $redis->hSet('route_ip_msg', $connection->msg['ip'], $ip_info);
            $connection->send(ws_return('connect_success', 0));
            return;
        }
    };

    $route_worker->onClose = function ($connection)
    {
        global $redis,$ip_array,$route_connections;
        $route_msg=$connection->msg;
        $ip=$route_msg['ip'];
        //删除ip——连接数租中的此连接
        unset($route_connections[$ip][$connection->id]);

        if(isset($route_msg['route'])){
            $route_num=$redis->hGet('routes',$route_msg['route']);
            if($route_num<=0){
                return;
            }
        }

        if(isset($ip_array[$ip])&&$ip_array[$ip]>1){
            //当前ip下还有其它进程在连接，停止删除数据
            $ip_array[$ip]-=1;
            return;
        }elseif(isset($ip_array[$ip])&&$ip_array[$ip]<=1){
            unset($ip_array[$ip]);
        }
        /*$ready_count=0;
        foreach($connection->worker->connections as $con){
            if($con->msg['ip']==$ip) $ready_count+=1;
        }
        if($ready_count>1) return;*/
        $ips=$redis->hGet('routes_ips',$route_msg['route']);
        if($ips==false||$ips==null){
            return;
        }
        $ips=explode(',', $ips);
        if(count($ips)<=0){
            return;
        }
        if(!in_array($ip, $ips)){
            return;
        }
        //处理routes的人数
        if($route_num>1){
            $redis->hSet('routes',$route_msg['route'],$route_num-1);
        }else{
            $redis->hDel('routes',$route_msg['route']);
        }
        $ip_key=array_search($connection->msg['ip'],$ips);
        if($ip_key!==false){
            //删除ip组中的此ip
            unset($ips[$ip_key]);
        }
        if($ips!=null){
            $redis->hSet('routes_ips',$route_msg['route'],implode(',', $ips));
        }else{
            $redis->hDel('routes_ips',$route_msg['route']);
        }
        $redis->hDel('route_ip_msg',$connection->msg['ip']);
        echo 'del'.json_encode($route_msg)."/n";
    };
    // 运行所有的worker（其实当前只定义了一个）

    function ws_return($msg,$status=0)
    {
        return json_encode(['msg'=>$msg,'status'=>$status]);
    }

    function route_msg_start()
    {
        return ['ip'=>'','route'=>''];
    }

    function notice_onmessage($con,$data)
    {
        $data=json_decode($data,true);
        global $route_connections,$redis;
        $ip=$con->getRemoteIp();
        if($data['type']!=0){
            foreach($route_connections[$data['ip']] as $k => $v){
                $v->send($data['msg']);
            }
            $con->send(json_encode(['msg'=>'已向'.$data['ip'].'发送通知','status'=>0]));
            var_dump($redis->get('JC:'.$ip));
            var_dump($con->id);
//            $con->send(json_encode(['msg'=>'已向'.$data['ip'].'发送通知','status'=>0]));
        }else{
            //广播通知
            foreach($route_connections as $k => $v){
                foreach($v as $key => $val){
                    $val->send($data['msg']);
                }
            }
            $con->send(json_encode(['msg'=>'已向所有用户发送通知','status'=>0]));
            var_dump($redis->get('JC:'.$ip));
            var_dump($con->id);
        }
    }
    Worker::runAll();


//use Workerman\Worker;
//require_once './Workerman/Autoloader.php';
//require __DIR__."/redis.php";
//
//// 初始化一个worker容器，监听1234端口
//$worker = new Worker('websocket://0.0.0.0:6666');
//$route_connections=[];
//
///*
// * 注意这里进程数必须设置为1，否则会报端口占用错误
// * (php 7可以设置进程数大于1，前提是$inner_text_worker->reusePort=true)
// */
//$worker->count = 4;
//// worker进程启动后创建一个text Worker以便打开一个内部通讯端口
//$worker->onWorkerStart = function($worker)
//{
//    global $redis;
//    $config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
//    $redis = Rediss::getInstance($config);
//
//    // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
//    $inner_worker1 = new Worker('websocket://0.0.0.0:5678');
//    $inner_worker1->reusePort = true;
//    $inner_worker1->onMessage = function($connection, $buffer)
//    {
//        global $redis;
//        $ip=$connection->getRemoteIp();
//        $uid = $redis->get('UID:'.$ip,$connection->uid);
//
//        // $data数组格式，里面有uid，表示向那个uid的页面推送数据
////        $data = json_decode($buffer, true);
////        $uid = $data['uid'];
////         通过workerman，向uid的页面推送数据
////        $ret = sendMessageByUid($uid, $buffer);
//        // 返回推送结果
////        $connection->send('ok');
//        var_dump(111);
//        var_dump($uid);
////        var_dump($connection->uid);
////        var_dump($connection);
////        var_dump(2222);
//    };
//    // ## 执行监听 ##
//    $inner_worker1->listen();
//};
//// 新增加一个属性，用来保存uid到connection的映射
//$worker->uidConnections = array();
//
//$worker->onConnect=function ($connection)
//{
//    global $redis,$route_connections;
//
//    //初始化附带信息
//    $con_msg=route_msg_start();
//    $ip=$connection->getRemoteIp();
//    global $route_connections,$ip_array;
//    $connection->msg=['ip'=>$ip];
//    $route_connections[$ip][$connection->id]=$connection;
//    //记录ip与对应线程数
//    if(!isset($ip_array[$ip])){
//        $ip_array[$ip]=1;
//    }else{
//        $ip_array[$ip]+=1;
//    }
//    echo 'ip:'.$ip."/n";
//
////    $con_msg=route_msg_start();
//    $ip=$connection->getRemoteIp();
//    var_dump(3333);
//    var_dump($connection->uid);
//    $redis->set('UID:'.$ip,$connection->uid);
//    $redis->set('JC:'.$ip,$connection->id);
//    var_dump($ip);
//    var_dump(444);
////    global $route_connections,$ip_array;
////    $connection->msg=['ip'=>$ip];
////    $route_connections[$ip]=$connection;
////    //记录ip与对应线程数
////    if(!isset($ip_array[$ip])){
////        $ip_array[$ip]=1;
////    }else{
////        $ip_array[$ip]+=1;
////    }
////    echo 'ip:'.$ip."/n";
//};
//
//// 当有客户端发来消息时执行的回调函数
//$worker->onMessage = function($connection, $data)
//{
//    global $worker;
//    // 判断当前客户端是否已经验证,既是否设置了uid
//    if(!isset($connection->uid))
//    {
//        // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
//        $connection->uid = $data;
//        /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
//         * 实现针对特定uid推送数据
//         */
//        $worker->uidConnections[$connection->uid] = $connection;
//        return;
//    }
//};
//
//// 当有客户端连接断开时
//$worker->onClose = function($connection)
//{
//    global $worker;
//    if(isset($connection->uid))
//    {
//        // 连接断开时删除映射
//        unset($worker->uidConnections[$connection->uid]);
//    }
//};
//
//// 向所有验证的用户推送数据
//function broadcast($message)
//{
//    global $worker;
//    foreach($worker->uidConnections as $connection)
//    {
//        $connection->send($message);
//    }
//}
//
//// 针对uid推送数据
//function sendMessageByUid($uid, $message)
//{
//    global $worker;
//    if(isset($worker->uidConnections[$uid]))
//    {
//        $connection = $worker->uidConnections[$uid];
//        $connection->send($message);
//        return true;
//    }
//    return false;
//}
//
//// 运行所有的worker
//Worker::runAll();