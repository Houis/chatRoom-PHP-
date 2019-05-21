<?php

/**
 * Created by PhpStorm.
 * User: dev_001
 * Date: 19/5/17
 * Time: 下午3:04
 */
class Demo
{
    var $master;    //连接server的client
    var $sockets = array();     //不同状态的socket管理
    var $handshake = false;     //判断是否握手

    function __construct($address,$port)
    {
        $this->master = socket_create(AF_INET,SOCK_STREAM,SOL_TCP) OR DIE("SOCKET_CREATE FAIL".socket_strerror(socket_last_error()));
        socket_set_option($this->master,SOL_SOCKET,SO_REUSEADDR,1) OR DIE("SOCKET_OPTION FAIL".socket_strerror(socket_last_error()));
        socket_bind($this->master,$address,$port);
        socket_listen($this->master,4);
        $this->sockets[] = $this->master;

        echo("Master socket :".$this->master."\n");
        while (true){
            //自动选择来消息的socket如果是握手，自动选择主机
            socket_select($this->sockets,$write=null,$except=null,NULL);
            foreach ($this->sockets as $socket){
                if($this->master == $socket){
                    //接收一个连接
                    $client = socket_accept($this->master);
                    if($client < 0){
                        echo "socket_accept failed";
                        continue;
                    }else{
                        array_push($this->sockets,$client);
                        echo "client connect \n";
                    }
                }else{
                    $bytes = @socket_recv($socket,$buffer,2048,0);
//                    $read = socket_read($socket,1024,0);
//                    echo $read;
                    if($bytes == 0) return;

                    if(!$this->handshake){
                        $this->handShake($socket,$buffer);
                        echo "shakeHandle\n";
                        continue;
                    }
                    $msg =  $this->uncode($buffer,'');
                    echo $msg;
                    $msg = $this->code($msg);
                    socket_write($socket,$msg,strlen($msg));
                }
            }
        }
    }


    function handShake($socket,$buffer){
        $buf  = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);
        $key  = trim(substr($buf,0,strpos($buf,"\r\n")));
        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));

        $new_message  = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_write($socket,$new_message,strlen($new_message));
        $this->handshake = true;
    }
    //解码函数
    function uncode($str,$key=''){
        $mask = array();
        $data = '';
        $msg = unpack('H*',$str);
        $head = substr($msg[1],0,2);

        $len=substr($msg[1],2,2);
        $len=hexdec($len);//把十六进制的转换为十进制
        if(substr($msg[1],2,2)=='fe'){
                $len=substr($msg[1],4,4);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],4);
        }else if(substr($msg[1],2,2)=='ff'){
            $len=substr($msg[1],4,16);
            $len=hexdec($len);
            $msg[1]=substr($msg[1],16);
        }
        $mask[] = hexdec(substr($msg[1],4,2));
        $mask[] = hexdec(substr($msg[1],6,2));
        $mask[] = hexdec(substr($msg[1],8,2));
        $mask[] = hexdec(substr($msg[1],10,2));
        $s = 12;
        $n=0;

        $e = strlen($msg[1])-2;
        for ($i=$s; $i<= $e; $i+= 2) {
            $data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
            $n++;
        }
        $dlen=strlen($data);

        if($len > 255 && $len > $dlen+intval($this->sjen[$key])){
            $this->ar[$key]=$mask;
            $this->slen[$key]=$len;
            $this->sjen[$key]=$dlen+intval($this->sjen[$key]);
            $this->sda[$key]=$this->sda[$key].$data;
            $this->n[$key]=$n;
            return false;
        }else{
            unset($this->ar[$key],$this->slen[$key],$this->sjen[$key],$this->n[$key]);
            $data=$this->sda[$key].$data;
            unset($this->sda[$key]);
            return $data;
        }

    }


    //与uncode相对
    function code($msg){
        $frame = array();
        $frame[0] = '81';
        $len = strlen($msg);
        if($len < 126){
            //dechex() 函数把十进制转换为十六进制。
            $frame[1] = $len<16?'0'.dechex($len):dechex($len);
        }else if($len < 65025){
            $s=dechex($len);
            //str_repeat()把字符串重复到指定次数
            $frame[1]='7e'.str_repeat('0',4-strlen($s)).$s;
        }else{
            $s=dechex($len);
            $frame[1]='7f'.str_repeat('0',16-strlen($s)).$s;
        }
        $frame[2] = $this->ord_hex($msg);
//        $data = implode('',$frame);
        $data = join('',$frame);
        //pack()把数据装入一个二进制的字符串里面
        return pack("H*", $data);
    }
    function ord_hex($data)  {
        $msg = '';
        $l = strlen($data);
        for ($i= 0; $i<$l; $i++) {
            //ord()返回某个字符的ASCII值
            $msg .= dechex(ord($data{$i}));
        }
        return $msg;
    }
}

$ws = new Demo('192.168.10.62','8888');