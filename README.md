# my_te
> PHP 网络引擎框架。



1. Server
   - [x] 应用层收发缓冲区
   - [x] 心跳检查
   - [x] IO 复用
     - [x] Select
     - [x] Epoll
   - [ ] 协议设计
     - [ ] text
     - [ ] stream
     - [ ] http
     - [ ] websocket
     - [ ] mqtt
   - [x] 多进程设计
     - [x] 多进程work
     - [x] 多进程 task 异步处理
     - [x] 事件监听回收
   - [x] 平滑重启 
   - [x] socket 惊群
   - [x] 禁用 nagle 算法

2. Client

   - [ ] 并发客户端
   - [ ] text client
   - [ ] stream client
   - [ ] http client
   - [ ] websocket client
   - [ ] mqtt client


## socket 惊群

### 什么是 惊群
在多进程或者多线程的网络模型下, 父进程进行 `socket_create`, `socket_build`,
然后在创建子进程，这个时候子进程继承了付进程的监听socket, 当有客户端进行连接时, 这些进程被同时唤醒，就是“惊群”。
然后每个子进程进行 `accpet`, 只有一个子进程能 `accept` 成功。

### 有什么影响
我们知道进程被唤醒，需要进行内核重新调度，这样每个进程同时去响应这一个事件，而最终只有一个进程能处理事件成功，其他的进程在处理该事件失败后重新休眠或其他。影响性能。

### 如何解决

#### 使用 `accpet` 堵塞
当我们直接使用 `accpet` 堵塞，在 Linux2.6 的版本钟已经修复了该问题。处理方式是:当内核收到客户端连接的时候,**只会唤醒等待队列上的第一个进程或线程。**
#### 使用 IO 复用函数
1. Nginx中使用mutex互斥锁解决这个问题。
2. 使用 `reuseport` 选项socket,由内核进行负载均衡通知进程。


## Nagle 算法

### 什么是 Nagle 算法
Nagle算法的基本定义是任意时刻，最多只能有一个未被确认的小段。 所谓“小段”，指的是小于MSS尺寸的数据块，所谓“未被确认”，是指一个数据块发送出去后，没有收到对方发送的ACK确认该数据已收到。
规则：
```
if (包长度达到MSS) {
	return "允许发送"
}

if (包标志位含有FIN) {
    return "允许发送"
}

if (socket 设置了TCP_NODELAY) {
    return "允许发送"
}

if (未设置TCP_CORK选项 && 所有发出去的小数据包（包长度小于MSS）均被确认) {
    return "允许发送"
}

if (发生了超时 200ms) {
    return "允许发送"
}

return "不允许发送"

```    

### 有什么影响
 Nagle 算法会导致许多小包不会发送, 为了尽可能发送大块数据，避免网络中充斥着许多小数据块。

### 如何解决
    设置 `TCP_NODELAY`

   



