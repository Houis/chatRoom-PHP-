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
                }
            }

        }while(true);
    }


}