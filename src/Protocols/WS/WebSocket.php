<?php

namespace Te\Protocols\WS;

use Te\Protocols\HTTP\Request;
use Te\Protocols\HTTP\Response;

class WebSocket
{
    /**
     * 客户端握手
     *
     * @var string
     */
    private $key;

    const MAGIC_NUMBER = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

    /**
     * 发送websocket的握手
     *
     * @param Request $request
     * @param Response $response
     */
    public function handshake(Request $request, Response $response): bool
    {
        if (!$this->handshakeRequest($request, $response)) {
            return false;
        }

        $this->handshakeResponse($response);
        return true;
    }

    /**
     * 握手响应
     */
    private function handshakeResponse(Response $response)
    {
        // HTTP/1.1 101 Switching Protocols
        // Upgrade: websocket
        // Connection: Upgrade
        // Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
        // Sec-WebSocket-Protocol: chat
        // Sec-WebSocket-Version: 13
        $sign = base64_encode(sha1($this->key . self::MAGIC_NUMBER, true));
        $response->code(101)->setHeader('Connection', 'Upgrade');
        $response->setHeader('Upgrade', 'websocket');
        $response->setHeader('Sec-WebSocket-Accept', $sign);
        $response->setHeader('Sec-WebSocket-Version', '13');
        $response->send();
    }

    /**
     * 握手参数校验
     *
     * @param Request $request
     * @return bool
     */
    private function handshakeRequest(Request $request, Response $response)
    {
        // GET /chat HTTP/1.1
        // Host: server.example.com
        // Upgrade: websocket
        // Connection: Upgrade
        // Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
        // Origin: http://example.com
        // Sec-WebSocket-Protocol: chat, superchat
        // Sec-WebSocket-Version: 13
        if ($request->getMethod() !== 'GET') {
            $response->code(403)->send();
            record(RECORD_ERR, "websocket request method not is GET");
            return false;
        }

        if (strtolower($request->getHeader('Upgrade')) !== 'websocket') {
            $response->code(403)->send();
            record(RECORD_ERR, "websocket  request Header Upgrade illegal");
            return false;
        }

        //  必传, 由客户端随机生成的 16 字节值, 然后做 base64 编码, 客户端需要保证该值是足够随机, 不可被预测的
        // (换句话说, 客户端应使用熵足够大的随机数发生器), 在 WebSocket 协议中, 该头部字段必传, 若客户端发起握手时缺失该字段, 则无法完成握手
        if (($key = $request->getHeader('Sec-WebSocket-Key')) === null) {
            $response->code(403)->send();
            record(RECORD_ERR, "websocket  request Header Sec-WebSocket-Key is empty");
            return false;
        }

        $this->key = $key;

        // 必传, 指示 WebSocket 协议的版本, RFC 6455 的协议版本为 13,
        // 在 RFC 6455 的 Draft 阶段已经有针对相应的 WebSocket 实现, 它们当时使用更低的版本号,
        // 若客户端同时支持多个 WebSocket 协议版本, 可以在该字段中以逗号分隔传递支持的版本列表 (按期望使用的程序降序排列), 服务端可从中选取一个支持的协议版本
        if (($key = $request->getHeader('Sec-WebSocket-Version')) !== '13') {
            $response->code(426)->setHeader('Sec-WebSocket-Version', '13');
            $response->send();
            record(RECORD_ERR, "websocket  request Header Sec-WebSocket-Version not eq 13");
            return false;
        }

        return true;
    }
}