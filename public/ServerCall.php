<?php
require_once './helper.php';
	/**
	 * 服务端通讯
	 */
class ServerCall{
	private static $config_arr=['ip_msg'=>'ip_msg_call','ip_event'=>'call_event'];
	private static $redis;
	private static $con;
	public static function server_send(Array $data,$con,$redis)
	{
		reset($data);
		$key=key($data);
		if(!isset(self::$config_arr[$key])){
			return false;
		}
		var_dump(self::$redis);
		self::$redis=$redis;
		self::$con=$con;
		$fun_name=self::$config_arr[$key];
		if(method_exists('ServerCall',$fun_name)){
			return self::$fun_name($data,$con);
		}else{
			return false;
		}
		/*if(isset($data['ip_msg'])){
			return self::ip_msg_call($data,$con);
		}else{
			$connection->send(ws_return('route or ip_info not found',1));
	    	return false;
		}*/
	}
	/**
	 * 存储用户联系方式
	 * @param  [type] $data [客户端通讯数据]
	 * @return [type]       [description]
	 */
	private static function ip_msg_call($data)
	{
		$redis=self::$redis;
		$connection=self::$con;
		//用户联系方式存储
		$ip_info=$redis->hGet('route_ip_msg',$connection->msg['ip']);
		if($ip_info==false){
			$connection->send(ws_return('ip_info not access',1));
		    return false;
		}else{
			$ip_info=json_decode($ip_info,true);
			$ip_info['ip_msg']=$data['ip_msg'];
			$redis->hSet('route_ip_msg',$connection->msg['ip'],json_encode($ip_info));
			if(isset($connection->msg['route'])){
			self::call_data(call_arr(['msg'=>'输入联系方式','ip'=>$connection->msg['ip'],'ip_msg'=>$data['ip_msg'],'route'=>$connection->msg['route'],'time'=>date("Y-m-d H:i:s",time())]));
			}else{
				self::call_data(call_arr(['msg'=>'输入联系方式','ip'=>$connection->msg['ip'],'ip_msg'=>$data['ip_msg'],'time'=>date("Y-m-d H:i:s",time())]));
			}
			$connection->send(ws_return('ip_msg save success',0));
		    return true;
		}
	}
	/**
	 * 通讯系统端监控台通知消息
	 * @param  [string] $type  [0:通知1:事件2:数据捉取]
	 * @param  [json] 数据   [description]
	 * @param  [string] $route [路由]
	 * @return [type]        [description]
	 */
	public static function call_server($msg)
	    {	
	    	$type=0;
	    	var_dump($msg);
	    	global $notice_worker;
	    	foreach($notice_worker->connections as $k => $con)
	    	{
	    		$con->send(json_encode(['msg_type'=>'notice','code'=>$type,'msg'=>json_encode(['type'=>$type,'msg'=>$msg])]));
	    	}
	    }
	/**
	 * 通讯系统服务台事件触发
	 * @param  [type] $type  [0:通知1:事件2:数据捉取]
	 * @param  [type] $msg   [description]
	 * @param  [type] $route [description]
	 * @return [type]        [description]
	 */
	public static function call_event($msg)
	{		
			$type=1;
			var_dump($msg);
			global $notice_worker;
	   	 	foreach($notice_worker->connections as $k => $con)
	    	{	
	    		$con->send(json_encode(['msg_type'=>'event','code'=>$type,'msg'=>json_encode(['type'=>$type,'msg'=>$msg])]));
	    	}
	}
	/**
	 * 数据捉取
	 */
	public static function call_data($msg)
	{		
			$type=2;
			var_dump($msg);
			global $notice_worker;
	   	 	foreach($notice_worker->connections as $k => $con)
	    	{
	    		$con->send(json_encode(['msg_type'=>'data','code'=>$type,'msg'=>json_encode(['type'=>$type,'msg'=>$msg])]));
	    	}
	}
}