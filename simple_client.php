<?php
/**
 * Created by PhpStorm.
 * User: dev_001
 * Date: 19/5/15
 * Time: 下午3:22
 */

$socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
socket_set_option($socket,SOL_SOCKET,SO_RCVTIMEO,array("sec"=>1,"usec"=>0));
socket_set_option($socket,SOL_SOCKET,SO_SNDTIMEO,array("sec"=>6,"usec"=>0));

if(socket_connect($socket,'192.168.10.62','8888') === false){
    echo "socket connect fail ".socket_strerror(socket_last_error());die;
}

$message = 'l love u mon';
$message = mb_convert_encoding($message,'GBK','utf8');
if(socket_write($socket,$message,strlen($message)) == FALSE){
    echo "socket write fail".socket_strerror(socket_last_error());
}
echo 'client write success'.PHP_EOL;
while ($callback = socket_read($socket,1024)){
    echo $callback.PHP_EOL;
}

socket_close($socket);