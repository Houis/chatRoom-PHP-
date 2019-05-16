<?php

/**
 * Created by PhpStorm.
 * User: dev_001
 * Date: 19/5/15
 * Time: 下午4:51
 */
class WebSocket
{
    public $sockets;    //socket连接池
    public $users;      //所有client连接进来的信息，包括socket、client名字等
    public $server;       //socket的resource，前期初始化时返回的socket资源


//    private $sda=array();   //已接收的数据
//    private $slen=array();  //数据总长度
//    private $sjen=array();  //接收数据长度
//    private $ar=array();    //加密key
//    private $n=array();

    public function __construct($address,$port)
    {
        $this->server = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_set_option($this->server,SOL_SOCKET,SO_REUSEADDR,1);//接收所有数据
        socket_bind($this->server,$address,$port);
        socket_listen($this->server);
        $this->sockets[] = $this->server;
    }

    /**
     * 新客户端加入进来
     * @param $socket
     * @return bool
     */
     protected function login($socket){
        if($this->searchBySocket($socket)){
            return false;
        }

        $key = uniqid();
        $this->users[$key] = array(
            'key'=>$key,
            'socket'=>$socket,
            'handshake'=>false,
        );
        $this->sockets[] = $socket;
        return true;
    }

    public function logout($client){
        socket_close($client['socket']);
        unset($this->users[$client['key']]);
    }

    /**
     * 查找客户端
     * @param $socket
     * @return bool
     */
    protected function searchBySocket($socket){
        if(empty($this->users)) return false;

        foreach ($this->users as $sock){
            if($socket == $sock['socket']){
                return $socket;
            }
        }

        return false;
    }

    protected function search($socket)
    {
        if(empty($this->users)) return false;

        foreach ($this->users as $key => $value) {
            if($value['socket'] == $socket){
                return $key;
            }
        }

        return false;
    }

    protected function handshake($client,$buffer){
        //截取Sec-WebSocket-key的值并加密。其中$key后面的一部分258EAFA5-E914-47DA-95CA-C5AB0DC85B11字符串应该是固定的
        $buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-key:')+18)
        $key = trim(substr($buf, 0,strpos($buf,"\r\n")));
        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));

        //根据协议组合信息进行返回
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message.= "Upgrade: websocket\r\n";
        $new_message.= "Sec-WebSocket-key: 13\r\n";
        $new_message.= "Connection: Upgrade\r\n";
        $new_message.= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_write($this->users[$client['key']]['socket'], $new_message,strlen($new_message));

        //对已经握手的client做表示
        $this->users[$client['key']]['handshake'] = true;

        return true;
    }

    function run(){
        do{
            $socket_list = $this->sockets;
            /**
             * 阻塞监听
             * @params $socket_list 可以理解为一个数组，这个数组存放的是文件描述符。当它有变化（有新消息或者有客户端连接/断开）是，socket_select返回结果，继续往下执行
             * @params $write       监听客户端是否有写数据，传入NULL不关心写变化
             * @params $except      $socket_list里面要被排除的元素，传入NULL监听全部
             * @params $tv_sec      超时时间    0：立刻结束|n>1：最多在N秒介素，如某一个连接有新动态，则提前返回|null 如果某一个连接有新动态，则返回
             */
            socket_select($socket_list,$write = NULL,$except = NULL,NULL);
            foreach ($socket_list as $sock){
                if($this->server == $sock){
                    $client = socket_accept($this->server);
                    $this->login($client);
                }else{
                    $len = 0;
                    $buffer = '';
                    do{
                        $l = socket_recv($sock,$buf,1000,0);
                        $len+=$l;
                        $buffer.=$buf;
                    }while($l==1000);

                    //查找socket
                    $client = $this->searchBySocket($sock);
                    if($len < 7){
                        //如果接收的信息长度小于7，则该client的socket为断开连接
                        $this->logout($client);
                        continue;
                    }

                    if(!$client['handshake']){
                        $this->handshake($client,$buffer);
                    }else{
                        //给该client发送信息，对接收到的信息
                        $str = "12312";
                        socket_write($client['socket'],$str,strlen($str));
                    }
                }
            }

        }while(true);
    }


}