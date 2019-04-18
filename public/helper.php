<?php
	if (!function_exists("route_on_start")) {
	 	function route_on_start($woker)
	    {
            require __DIR__."/redis.php";
            //初始化附带信息
	    	global $redis,$ip_array;
	    	$ip_array=[];
            $config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
            $redis = Rediss::getInstance($config);
//	    	$redis=new \Redis();
//	    	$redis->connect('13.250.109.37',6379);
	    	$notice_woker=new Workerman\Worker('websocket://0.0.0.0:2350');
	    	$notice_woker->onMessage='notice_onmessage';
	    	$notice_woker->onConnect=function($con){
	    		var_dump($con->id.'connection');
	    		$con->send('hello');
	    	};
	    	$notice_woker->listen();
	    }
	}
	if (!function_exists("route_on_connect")) {
	 	function route_on_connect($connection)
	    {
	    	//初始化附带信息
	    	$con_msg=route_msg_start();
	        $ip=$connection->getRemoteIp();
	        global $route_connections,$ip_array;
	        $connection->msg=['ip'=>$ip];
	        $route_connections[$ip][$connection->id]=$connection;
	        //记录ip与对应线程数
	        if(!isset($ip_array[$ip])){
	        	$ip_array[$ip]=1;
	        }else{
	        	$ip_array[$ip]+=1;
	        }
	        echo 'ip:'.$ip."/n";
	    }
	}
    if (!function_exists("route_on_message")) {
	 	function route_on_message($connection,$data)
	    {	
	    	global $redis;
	    	$data=json_decode($data,true);
	    	if(isset($data['heartbeat'])){
	    		return;
	    	}
	    	if(!isset($data['route'])||!isset($data['ip_info'])){
	    		$connection->send(ws_return('route or ip_info not found',1));
	    		return;
	    	}
	        $route=$data['route'];
	        $connection->msg['route']=$route;
	        var_dump('m'.$connection->msg['route']);
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
	        var_dump($ip_info);
	        $redis->hSet('route_ip_msg',$connection->msg['ip'],$ip_info);
	        $connection->send( ws_return('connect_success',0));
	        return;
	    }
	}
	if (!function_exists("route_on_close")) {
	 	function route_on_close($connection)
	    {
	    	global $redis,$ip_array,$route_connections;
	    	var_dump('c'.$connection->msg);
	    	$route_msg=$connection->msg;
	    	$ip=$route_msg['ip'];
	    	//删除ip——连接数租中的此连接
	    	unset($route_connections[$ip][$connection->id]);
	    	$route_num=$redis->hGet('routes',$route_msg['route']);
		    	if($route_num<=0){
		    		return;
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
	    }
	}
	/**
	 * websocket协议下的信息返回
	 * @msg 返回信息描述
	 * @status 返回状态码（0为成功）
	 */
	if (!function_exists("ws_return")) {
	 	function ws_return($msg,$status=0)
	    {
	    	return json_encode(['msg'=>$msg,'status'=>$status]);
	    }
	}
	if (!function_exists("route_msg_start")) {
	 	function route_msg_start()
	    {
	    	return ['ip'=>'','route'=>''];
	    }
	}
	if (!function_exists("notice_onmessage")) {
	 	function notice_onmessage($con,$data)
	    {
	    	var_dump($data);
	    	$data=json_decode($data,true);
	    	var_dump($data);
	    	global $route_connections;
	    	if($data['type']!=0){
	    		foreach($route_connections[$data['ip']] as $k => $v){
		    		$v->send($data['msg']);
		    	}
		    	$con->send(json_encode(['msg'=>'已向'.$data['ip'].'发送通知','status'=>0]));
	    	}else{
	    		//广播通知
	    		foreach($route_connections as $k => $v){
	    			foreach($v as $key => $val){
	    				$val->send($data['msg']);
	    			}
	    		}
	    		$con->send(json_encode(['msg'=>'已向所有用户发送通知','status'=>0]));
	    	}
	    }
	}