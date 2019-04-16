<?php
	if (!function_exists("route_on_start")) {
	 	function route_on_start($connection)
	    {
	    	//初始化附带信息
	    	global $redis;
	    	$redis=new \Redis(); 
	    	$redis->connect('13.250.109.37',6379);
	    }
	}
	if (!function_exists("route_on_connect")) {
	 	function route_on_connect($connection)
	    {
	    	//初始化附带信息
	    	$con_msg=route_msg_start();
	        $ip=$connection->getRemoteIp();
	        global $route_connections;
	        $connection->msg=['ip'=>$ip];
	        $route_connections[$ip]=$connection;
	        echo 'ip:'.$ip.'/n';
	    }
	}
    if (!function_exists("route_on_message")) {
	 	function route_on_message($connection,$data)
	    {	
	    	global $redis;
	    	$data=json_decode($data,true);
	    	if(!isset($data['route'])||!isset($data['ip_info'])){
	    		$connection->send(ws_return('route or ip_info not found',1));
	    		return;
	    	}
	        $route=$data['route'];
	        $connection->msg['route']=$route;
	        if($redis->hget('routes',$route)==null||$redis->hget('routes',$route)==false){
	        	$redis->hset('routes',$route,1);
	        	$redis->hset('routes_ips',$route,$connection->msg['ip']);
	        }else{
	        	$old_ips=$redis->hget('routes_ips',$route);
	        	if($old_ips==false||$old_ips==null){
	        		var_dump('0',$connection->msg['ip']);
	        		$redis->hset('routes_ips',$route,$connection->msg['ip']);
	        	}else{
	        		$ips=explode(',', $old_ips);
		        	if(!in_array($connection->msg['ip'], $ips)){
		        		if(count($ips)<=0){
		        			$ips=[];
		        			$ips[]=$connection->msg['ip'];
		        			var_dump('1',$ips);
		        		}else{
		        			$ips[]=$connection->msg['ip'];
		        			var_dump('2',$ips);
		        		}
		        		$redis->hset('routes',$route,$redis->hget('routes',$route)+1);
		        		$redis->hset('routes_ips',$route,implode(',', $ips));
		        	}else{
		        		var_dump('3',$connection->msg['ip'],$ips);	
		        	}	
	        	} 	        
	        }
	        $ip_info=$data['ip_info'];
	        $redis->hset('route_ip_msg',$connection->msg['ip'],$ip_info);
	        $connection->send( ws_return('connect_success',0));
	        return;
	    }
	}
	if (!function_exists("route_on_close")) {
	 	function route_on_close($connection)
	    {
	    	var_dump($connection->getRemoteIp());
	    	global $redis;
	    	$route_msg=$connection->msg;
	    	$route_num=$redis->hget('routes',$route_msg['route']);
		    	if($route_num<=0){
		    		return;
		    	}
	    	$ip=$route_msg['ip'];
	    	$ready_count=0;
	    	foreach($connection->worker->connections as $con){
	    		if($con->msg['ip']==$ip) $ready_count+=1;
	    	}
	    	if($ready_count>1) return;
	    	$ips=$redis->hget('routes_ips',$route_msg['route']);
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
		    	if($route_num>0){
		    		$redis->hset('routes',$route_msg['route'],$route_num-1);
		    	}else{
		    		$redis->hdel('routes',$route_msg['route']);
		    	}
	    	$ip_key=array_search($connection->msg['ip'],$ips);
	    	if($ip_key!=false||$ip_key!=null){
	    		unset($ips[$ip_key]);
	    	}else{
	    		var_dump($ip_key,$ips,$ip);
	    	}
	    	if($ips!=null){
	    		var_dump('aaaa',$ips);
	    		$redis->hset('routes_ips',$route_msg['route'],implode(',', $ips));
	    	}else{
	    		var_dump('bbb',$ips);
	    		$redis->hdel('routes_ips',$route_msg['route']);
	    	}
	    	$redis->hdel('route_ip_msg',$connection->msg['ip']);
	    	echo 'del'.json_encode($route_msg).'/n';
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