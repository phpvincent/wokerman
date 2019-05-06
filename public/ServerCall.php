<?php
require_once './helper.php';
	/**
	 * 服务端通讯
	 */
class ServerCall{
	private static $config_arr=['ip_msg'=>'ip_msg_call'];
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
		return self::$fun_name($data,$con);
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
			self::call_server(0,call_arr(['msg'=>'输入联系方式','ip'=>$connection->msg['ip'],'ip_msg'=>$data['ip_msg'],'route'=>$connection->msg['route'],'time'=>date("Y-m-d H:i:s",time())]));
			}else{
				self::call_server(0,call_arr(['msg'=>'输入联系方式','ip'=>$connection->msg['ip'],'ip_msg'=>$data['ip_msg'],'time'=>date("Y-m-d H:i:s",time())]));
			}
			$connection->send(ws_return('ip_msg save success',0));
		    return true;
		}
	}
	public static function call_server($type,$msg,$route=null)
	    {	var_dump($msg);
	    	global $notice_woker;
	    	foreach($notice_woker->connections as $k => $con)
	    	{
	    		$con->send(json_encode(['msg_type'=>'notice','code'=>0,'msg'=>json_encode(['type'=>$type,'msg'=>$msg])]));
	    	}
	    }
}