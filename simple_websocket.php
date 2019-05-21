<?php
/**
 * Created by PhpStorm.
 * User: dev_001
 * Date: 19/5/17
 * Time: 上午11:05
 */
$sockets = [];
$handshake = false;

function encry($req){
    //提取Sec-WebSocket-Key信息
    $key = null;
    $mask = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    if(preg_match("/Sec-WebSocket-Key:(.*)\r\n/",$req,$match)){
        $key = $match[1];
    }
    return base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
}
function doHandShake($socket,$req){
    //获取加密key
    $acceptKey = encry($req);
    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Version: 13\r\n".
        "Sec-WebSocket-Accept: "  .$acceptKey. "\r\n" .
        "\r\n";
    // 写入socket
    socket_write($socket,$upgrade.chr(0), strlen($upgrade.chr(0)));

}



$master = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
socket_set_option($master,SOL_SOCKET,SO_REUSEPORT,1);//接收一切
socket_bind($master,'192.168.10.62','8888');
socket_listen($master,4);
$sockets[] = $master;
while(true){
    //自动选择来消息的socket，如果是握手，自动选择主机
    socket_select($sockets,$write=null,$except=null,null);
    foreach ($sockets as $socket){
        //连接主机的client
        if($socket == $master){
            $client = socket_accept($master);
            if($client < 0){
                echo "socket_accept() failed: ".socket_strerror(socket_last_error());
                continue;
            }
            array_push($sockets,$client);
            echo "connect client\n";
            continue;
        }else{
            //函数 socket_recv() 从 socket 中接受长度为 len 字节的数据，并保存在 buf 中。 socket_recv() 用于从已连接的socket中接收数据。除此之外，可以设定一个或多个 flags 来控制函数的具体行为
            $bytes = @socket_recv($socket,$buffer,2048,0);
            if($bytes == 0) return;
            if(!$handshake){
                $handshake = true;
                echo doHandShake($socket,$buffer);
                echo "handshake".PHP_EOL;
            }

        }

    }
}