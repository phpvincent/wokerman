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
	        	$ips=explode(',', $redis->hget('routes_ips',$route));
	        	if(!in_array($connection->msg['ip'], $ips)){//var_dump($connection->msg['ip'],$ips);
	        		if($ips==null){
	        			$ips=[];
	        			$ips[]=$connection->msg['ip'];
	        		}else{
	        			$ips[]=$connection->msg['ip'];
	        		}
	        		$redis->hset('routes',$route,$redis->hget('routes',$route)+1);
	        		$redis->hset('routes_ips',$route,implode(',', $ips));
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
	    	global $redis;
	    	$route_msg=$connection->msg;
	    	$route_num=$redis->hget('routes',$route_msg['route']);
	    	if($route_num>0){
	    		$redis->hset('routes',$route_msg['route'],$route_num-1);
	    	}else{
	    		$redis->hdel('routes',$route_msg['route']);
	    	}
	    	$ips=explode(',', $redis->hget('routes_ips',$route_msg['route']));
	    	unset($ips[array_search($route_msg['ip'],$ips)]);
	    	if($ips!=null){
	    		$redis->hset('routes_ips',$route_msg['route'],$ips);
	    	}else{
	    		$redis->hdel('routes_ips',$route_msg['route']);
	    	}
	    	$redis->hdel('route_ip_msg',$route_msg['ip']);
	    	echo 'del'.json_encode($route_msg);
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