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
     - [ ] 多进程 task 异步处理
     - [x] 事件监听回收
   - [x] 平滑重启 
   - [ ] socket 惊群
   - [ ] 禁用 nagle 算法

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

   



