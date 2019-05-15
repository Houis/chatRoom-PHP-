<?php
/**
 * Created by PhpStorm.
 * User: dev_001
 * Date: 19/5/15
 * Time: 下午2:59
 */
//创建一个套节流
$socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
socket_set_option($socket,SOL_SOCKET,SO_REUSEADDR,1);
if(socket_bind($socket,'192.168.10.62','8888') === FALSE){
    echo "cannot bind socket".socket_strerror(socket_last_error());die;
}
if(socket_listen($socket,3) === FALSE){
    echo "cannot listen socket".socket_strerror(socket_last_error());die;
}

//死循环等待客户端发来的消息
do{
    $socket_resource = socket_accept($socket);
    if($socket_resource){
        //读取客户端发来的信息，并转化为字符串
        $string = socket_read($socket_resource,1024);
        if($string != false){
            $message = 'Client send message:'.$string;
            echo $message.PHP_EOL;
            socket_write($socket_resource,$message,strlen($message));
        }else{
            echo 'socket_read is fail'.socket_strerror(socket_last_error()).PHP_EOL;
        }
    }
    socket_close($socket_resource);
}while(true);
socket_close($socket);
