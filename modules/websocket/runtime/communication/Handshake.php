<?php

final readonly class Handshake
{
    public function __construct(
        public string $requestHeaders
    )
    {
        //
    }

    public function __toString(): string {
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $this->requestHeaders, $matches) !== false) {
            $secWebSocketKey = trim($matches[1]);

            // Create the Sec-WebSocket-Accept response key
            $secWebSocketAccept = base64_encode(pack('H*', sha1($secWebSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: $secWebSocketAccept\r\n\r\n";

            return $response;
        }

        return '';
    }
}
