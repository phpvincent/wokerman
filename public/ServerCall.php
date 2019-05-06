<?php
require_once './helper.php';
	/**
	 * 服务端通讯
	 */
class ServerCall{
	public static function server_send(Array $data,$con)
	{
		if(isset($data['ip_msg'])){
			self::ip_msg_call($data,$con);
		}else{
			$connection->send(ws_return('route or ip_info not found',1));
	    	return false;
		}
	}

	private static function ip_msg_call($data,$con)
	{
		//用户联系方式存储
		$ip_info=$redis->hGet('route_ip_msg',$connection->msg['ip']);
		if($ip_info==false){
			$connection->send(ws_return('ip_info not access',1));
		    return;
		}else{
			$ip_info=json_decode($ip_info,true);
			$ip_info['ip_msg']=$data['ip_msg'];
			$redis->hSet('route_ip_msg',$connection->msg['ip'],json_encode($ip_info));
			if(isset($connection->msg['route'])){
			call_server(0,call_arr(['msg'=>'输入联系方式','ip'=>$connection->msg['ip'],'ip_msg'=>$data['ip_msg'],'route'=>$connection->msg['route'],'time'=>date("Y-m-d H:i:s",time()]));
			}else{
				call_server(0,call_arr(['msg'=>'输入联系方式','ip'=>$connection->msg['ip'],'ip_msg'=>$data['ip_msg'],'time'=>date("Y-m-d H:i:s",time()]));
			}
			$connection->send(ws_return('ip_msg save success',0));
		    return;
		}
	}
}