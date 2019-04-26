<?php
	if (!function_exists("route_on_start")) {
	 	function route_on_start($woker)
	    {
            require __DIR__."/redis.php";
            //初始化附带信息
	    	global $redis,$ip_array,$notice_woker;
	    	$ip_array=[];
            $config = ['port'=>6379,'host'=>"127.0.0.1",'auth'=>''];
            $redis = Rediss::getInstance($config);
//	    	$redis=new \Redis();
//	    	$redis->connect('13.250.109.37',6379);
	    	$notice_woker=new Workerman\Worker('websocket://0.0.0.0:2350');
	    	$notice_woker->onMessage='notice_onmessage';
	    	$notice_woker->onConnect=function($con){
	    		//var_dump($con->id.'connection');
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
	        $connection->con_time=date("Y-m-d H:i:s",time());
	        $route_connections[$ip][$connection->id]=$connection;
	        //记录ip与对应线程数
	        if(!isset($ip_array[$ip])){
	        	$ip_array[$ip]['num']=1;
	        }else{
	        	$ip_array[$ip]['num']+=1;
	        }
	        call_server(0,call_arr(['msg'=>'路由访问','ip'=>$ip]));
	        echo 'ip:'.$ip."/n";
	    }
	}
    if (!function_exists("route_on_message")) {
	 	function route_on_message($connection,$data)
	    {	
	    	global $redis,$ip_array;
	    	$data=json_decode($data,true);
	    	if(isset($data['heartbeat'])){
	    		$connection->send( ws_return('heartbeat success',0));
	    		return;
	    	}
	    	if(!isset($data['route'])||!isset($data['ip_info'])){
	    		if(isset($data['ip_msg'])){
	    			//用户联系方式存储
	    			$ip_info=$redis->hGet('route_ip_msg',$connection->msg['ip']);
	    			if($ip_info==false){
	    				$connection->send(ws_return('ip_info not access',1));
	    			    return;
	    			}else{
	    				$ip_info=json_decode($ip_info,true);
	    				$ip_info['ip_msg']=$data['ip_msg'];
	    				$redis->hSet('route_ip_msg',$connection->msg['ip'],json_encode($ip_info));
	    				$connection->send(ws_return('ip_msg save success',0));
	    			    return;
	    			}
	    			if(isset($connection->msg['route'])){
	    				call_server(0,call_arr(['msg'=>'输入联系方式','ip'=>$ip,'ip_msg'=>$data['ip_msg'],'route'=>$connection->msg['route']]));
	    			}else{
	    				call_server(0,call_arr(['msg'=>'输入联系方式','ip'=>$ip,'ip_msg'=>$data['ip_msg']]));
	    			}
	    		}else{
	    			$connection->send(ws_return('route or ip_info not found',1));
	    			return;
	    		}
	    	}
	        $route=$data['route'];
	        $connection->msg['route']=$route;
	        call_server(0,call_arr(['msg'=>'访问页面','ip'=>$connection->msg['ip'],'route'=>$route]));
	        if(isset($ip_array[$connection->msg['ip']]['route'])){
	        	//处理一个IP访问多个页面
	        	if(!in_array($route,$ip_array[$connection->msg['ip']]['route'])){
	        		$ip_array[$connection->msg['ip']]['route'][]=$route;
	        	}
	        }else{
	        	$ip_array[$connection->msg['ip']]['route'][]=$route;
	        }
	        var_dump('on message:'.$connection->msg['route']);
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
	        $redis->hSet('route_ip_msg',$connection->msg['ip'],json_encode($ip_info));
	        $connection->send( ws_return('connect_success',0));
	        return;
	    }
	}
	if (!function_exists("route_on_close")) {
	 	function route_on_close($connection)
	    {
	    	global $redis,$ip_array,$route_connections;
	    	//var_dump($connection->msg);
	    	$route_msg=$connection->msg;
	    	if(!isset($connection->msg)||!isset($route_msg['ip'])){
	    		return;
	    	}
	    	$ip=$route_msg['ip'];
	    	//从链接映射池里删除此链接
	    	unset($route_connections[$ip][$connection->id]);
	    	//当$route['route']不存在时 代表没有通讯基础数据
	    	if(isset($route_msg['route'])){
	    		if(isset($connection->con_time)){
	    			$stay_time=strtotime($connection->con_time)-time();
	    		}else{
	    			$stay_time=null;
	    		}
	    		call_server(0,call_arr(['msg'=>'离开页面','ip'=>$ip,'route'=>$route_msg['route'],'stay_time'=>$stay_time]));
	    		//删除ip——连接数租中的此连接
		    	$route_num=$redis->hGet('routes',$route_msg['route']);
			    	if($route_num<=0){
			    		return;
			    	}
	    	}
	    	
	    	if(isset($ip_array[$ip]['num'])&&$ip_array[$ip]['num']>1){
	    		//当前ip下还有其它进程在连接，停止删除数据
	    			$ip_array[$ip]['num']-=1;
	    			return;
	    	}elseif(isset($ip_array[$ip])&&$ip_array[$ip]['num']<=1){
	    		var_dump('all_ip left.',$ip_array[$ip]);
	    		foreach ($ip_array[$ip]['route'] as $key => $value) {
	    			try{
	    				//$redis->hDel('routes',$value);
	    				if($redis->hGet('routes',$value)>1){
	    					var_dump('all ip left,route:'.$value.'num -1');
	    					$redis->hSet('routes',$value,$redis->hGet('routes',$value)-1);
	    				}else{
	    					var_dump('all ip left,route:'.$value.'del');
	    					$redis->hDel('routes',$value);
	    				}
	    				$dips=$redis->hGet('routes_ips',$value);	
	    				if($dips==false||$dips==null){
				    		return;
				    	}
	    				$dips=explode(',', $dips);
	    				if(count($dips)<=0){
				    		return;
				    	}
				    	if(!in_array($ip, $dips)){
					    	return;
					    }
					    $ip_key=array_search($connection->msg['ip'],$dips);
				    	if($ip_key!==false){
				    		//删除ip组中的此ip
				    		unset($dips[$ip_key]);
				    	}
				    	if($dips!=null){
				    		$redis->hSet('routes_ips',$value,implode(',', $dips));
				    	}else{
				    		$redis->hDel('routes_ips',$value);
				    	}
	    			}catch(\Exception $e){
	    				var_dump($e);
	    			}
	    		}
	    		unset($ip_array[$ip]);
	    	}else{
	    		if(isset($route_num)&&$route_num>1){
		    		$redis->hSet('routes',$route_msg['route'],$route_num-1);
		    	}else{
		    		$redis->hDel('routes',$route_msg['route']);
		    	}
	    	}
	    	/*$ready_count=0;
	    	foreach($connection->worker->connections as $con){
	    		if($con->msg['ip']==$ip) $ready_count+=1;
	    	}
	    	if($ready_count>1) return;*/
	    	/*$ips=$redis->hGet('routes_ips',$route_msg['route']);
		    	if($ips==false||$ips==null){
		    		return;
		    	}
	    	$ips=explode(',', $ips);
		    	if(count($ips)<=0){
		    		return;
		    	}
			    if(!in_array($ip, $ips)){
			    	return;
			    }*/
			    //处理routes的人数
		    	/*if($route_num>1){
		    		$redis->hSet('routes',$route_msg['route'],$route_num-1);
		    	}else{
		    		$redis->hDel('routes',$route_msg['route']);
		    	}*/
	    	/*$ip_key=array_search($connection->msg['ip'],$ips);
	    	if($ip_key!==false){
	    		//删除ip组中的此ip
	    		unset($ips[$ip_key]);
	    	}
	    	if($ips!=null){
	    		$redis->hSet('routes_ips',$route_msg['route'],implode(',', $ips));
	    	}else{
	    		$redis->hDel('routes_ips',$route_msg['route']);
	    	}*/
	    	//var_dump($redis->hGet('route_ip_msg',$connection->msg['ip']));
	    	$redis->hDel('route_ip_msg',$connection->msg['ip']);
	    	//echo 'del'.json_encode($route_msg)."/n";
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
	/*if (!function_exists("notice_onmessage")) {
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
	}*/
	// 子进程接收消息
    if (!function_exists("notice_onmessage")) {
        function notice_onmessage($connection,$data)
        {
            $data=json_decode($data,true);
            if(!isset($data['ip']) || !isset($data['type'])){
                $connection->send(ws_return('ip or type not found',1));
                return;
            }
            $ip=$data['ip']; //用户的IP
            global $route_connections;
            if(isset($route_connections[$ip]) && !empty($route_connections[$ip])){
                if(count($route_connections[$ip]) <= 1){
                    foreach ($route_connections[$ip] as $key => $connect){
                        $connect->send(ws_return('success',0,$data));
                    }
                }else{
                    foreach ($route_connections[$ip] as $key => $connect){
                        $url = $connect->msg['route'];
                        if(preg_match("/\/pay/", $url)){
                            $connect->send(ws_return('success',0,$data));
                        }
                    }
                }

            }
        }
    }
    //客户端传送数据至服务端
    //type:0:页面访问
    //1:数据输入操作
    //2：。。。
    if (!function_exists("call_server")) {
	 	function call_server($type,$msg,$route=null)
	    {
	    	global $notice_woker;
	    	foreach($notice_woker->connections as $k => $con)
	    	{
	    		$con->send(json_encode(['msg_type'=>'notice','code'=>0,'msg'=>json_encode(['type'=>$type,'msg'=>$msg])]));
	    	}
	    }
	}
	 if (!function_exists("call_arr")) {
	 	function call_arr($ary)
	    {
	    	$arr=['msg'=>null,
	    		  'ip'=>null,
	    		  'time'=>date("Y-m-d H:i:s",time()),
	    		  'route'=>null
	    		 ];
	    	return array_merge($arr,$ary);
	    }
	}