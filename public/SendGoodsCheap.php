<?php

require_once './helper.php';

/**
 * 推送优惠券或者消息
 */
Class SendGoodsCheap {

    private static $config_arr=['0'=>'send_message','1'=>'send_goods_cheap','2'=>'send_all_message','3'=>'send_all_goods_cheap'];
    private static $route_connections;

    /**
     * 发送消息/发送优惠券
     * @param $data
     * @param $route_connections
     * @return bool
     */
    public static function server_send($data,$route_connections){
        $key=$data['type'];
        if($key=='0' && !isset($data['ip'])){
            $key='2';
        }
        if($key=='1' && !isset($data['ip'])){
            $key='3';
        }
        if(!isset(self::$config_arr[$key]) || count($route_connections) < 1){
            return false;
        }
        self::$route_connections=$route_connections;
        $fun_name=self::$config_arr[$key];
        return self::$fun_name($data);
    }

    /**
     * 指定IP发送消息公告
     * @param $data
     * @return bool
     */
    public static function  send_message($data)
    {
        $ip=$data['ip']; //用户的IP
        foreach (self::$route_connections[$ip] as $key => $connect){
            $connect->send(ws_return('success',0,$data));
        }

        return true;
    }

    /**
     * 指定IP发送优惠券
     * @param $data
     * @return bool
     */
    public static function  send_goods_cheap($data)
    {
        $ip=$data['ip']; //用户的IP
        $send_bool = false;
        if(isset(self::$route_connections[$ip]) && count(self::$route_connections[$ip]) > 0){
            foreach (self::$route_connections[$ip] as $key => $connect){
                $url = $connect->msg['route'];
                if(preg_match("/\/pay/", $url)){
                    $connect->send(ws_return('success',0,$data));
                    $send_bool = true;
                }
            }
        }
        if($send_bool) return true;
        return false;
    }

    /**
     * 在线用户广播消息公告
     * @param $data
     * @return bool
     */
    public static function send_all_message($data)
    {
        foreach (self::$route_connections as $key => $ip_connect){
            foreach ($ip_connect as $connect){
                $connect->send(ws_return('success',0,$data));
            }
        }

        return true;
    }

    /**
     * 给全部在线用户发送优惠券
     * @param $data
     * @return bool
     */
    public static function send_all_goods_cheap($data)
    {
        $send_bool = false;
        foreach (self::$route_connections as $connection) {
            if(count($connection) > 0){
                foreach ($connection as $key => $connect) {
                    $url = $connect->msg['route'];
                    if (preg_match("/\/pay/", $url)) {
                        $connect->send(ws_return('success', 0, $data));
                        $send_bool = true;
                    }
                }
            }
        }

        if($send_bool) return true;
        return false;
    }
}