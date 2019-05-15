<?php
function WebSocket($address,$port){
    $socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
    socket_set_option($socket,SOL_SOCKET,SO_REUSEADDR,1);//表示接收所有的数据包
    socket_bind($socket,$address,$port);
    socket_listen($socket);
    return $socket;
}
function search(){

}
function run(){
    //死循环，直至socket断开
    do{
        $changes = $this->sockets;
        $write = null;
        $except = null;

        socket_select($changes,$write,$except,NULL);
        foreach ($changes as $sock){
            if($sock == $this->master){
                $client = socket_accept($this->master);
                $key = uniqid();
                $this->sockets[] = $client; //将新连接进来的socket存进连接池
                $this->users[$key] = array(
                    'socket'=>$client, //记录新连接进来的client的socket信息
                    'handle'=>false,      //标志该socket资源没有完成握手
                );
            }else{
                $len = 0;
                $buffer = '';

                do{
                    /*
                     * 从已连接的socket接收数据，
                     * socket_recv(resource $socket,string &$buf,int $len,int $flags) : int
                     * 函数socket_recv()从socket中接受长度为len字节的数据，并保存在buf中。socket_recv()用于从已连接的socket中接收数据。除此之外，可以设定一个或多个flags来控制函数的具体行为。
                     * buf以引用形式传递，因此必须是一个已声明的有效的冰凉。从socket中接收到的数据将会保存在buf中。
                     * @params socket：必须是一个由socket_create()创建的socket资源。
                     * @params buf：从socket中获取的数据蒋杯保存在由buf指定的变量中。如果有错误发生，如连接被重置，buf将被设为NULL。
                     * @params len：长度最多为len字节的数据将被接收。
                     * @params flags：flags的值可以为下列任意的falg的组合。使用按位或运算符(/)来组合不同的falg。MSG_OOB|MSG_PEEK|MSG_WAITALL|MGS_DONTWAIT
                     */
                    $l = socket_recv($sock,$buf,1000,0);
                    $len+=$l;
                    $buffer.=$buf;
                }while($l==1000);
                //根据socket在user池里面查找相应的$k，即健ID
                $k = $this->search($sock);
                //如果接受的消息长度小于7，则改client的socket为断开连接
                if($len < 7){
                    //给该client的socket进行断开操作，并在$this->sockets和$this->users里面进行删除
                    $this->send2($k);
                    continue;
                }
                //判断该socket是否已经握手
                if(!$this->users[$k]['handle']){
                    //如果没有握手，则惊醒握手处理
                    $this->handshake($k,$buffer);
                }else{
                    //走到这步就该给client发送消息了，对接收到的消息进行uncode处理
                    $buffer = $this->uncode($buffer,$k);
                }

            }
        }
    }while(true);
}

function close($k){
    socket_close($this->users[$k]['socket']);
    unset($this->uers[$k]);
    $this->sockets = array($this->master);
    foreach ($this->users as $v){
        $this->sockets[] = $v['socket'];
    }
    //输出日志
    $this->e("key:$k close");
}