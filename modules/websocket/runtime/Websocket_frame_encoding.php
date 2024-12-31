<?php

trait Websocket_frame_encoding
{
    /**
     * Sends a WebSocket handshake response to the client.
     * 
     * @param mixed $clientSocket 
     * @param mixed $requestHeaders 
     * @return void 
     */
    protected function send_websocket_handshake($clientSocket, $requestHeaders): void {
        // Extract the Sec-WebSocket-Key from the request headers
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $requestHeaders, $matches)) {
            $secWebSocketKey = trim($matches[1]);

            // Create the Sec-WebSocket-Accept response key
            $secWebSocketAccept = base64_encode(pack('H*', sha1($secWebSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

            // Prepare WebSocket handshake response
            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: $secWebSocketAccept\r\n\r\n";

            // Send handshake response
            fwrite($clientSocket, $response);
        }
    }

    protected function encode_websocket_frame(string $message, int $opcode = 0x1): string
    {
        $frameHead = [];
        $payloadLength = strlen($message);

        // Frame header: FIN, Opcode 0x1 (text frame)
        // $frameHead[0] = 129;
        $frameHead[0] = 0x80 | $opcode; // FIN bit set, custom opcode (0x1 for text, 0x9 for ping, etc.)

        if ($payloadLength <= 125) {
            $frameHead[1] = $payloadLength;
        } elseif ($payloadLength <= 65535) {
            $frameHead[1] = 126;
            $frameHead[2] = ($payloadLength >> 8) & 255;
            $frameHead[3] = $payloadLength & 255;
        } else {
            $frameHead[1] = 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[9 - $i] = ($payloadLength >> ($i * 8)) & 255;
            }
        }

        // Convert the frame head to a binary string
        $frameHeadStr = "";
        foreach ($frameHead as $b) {
            $frameHeadStr .= chr($b);
        }

        // Return the frame with the payload
        return $frameHeadStr . $message;
    }

    private function decode_websocket_frame(string $data): array {
        if (empty($data)) {
            return [];
        }

        // Read the first byte
        $firstByte = ord($data[0]);
        $fin = ($firstByte >> 7) & 0b1;
        $opcode = $firstByte & 0b00001111; // 0x0F === 0b00001111
        
        // @see https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#pings_and_pongs_the_heartbeat_of_websockets
        if ($opcode === 0xA) {
            return ['type' => 'pong', 'payload' => ''];
        }

        // Read the second byte
        $secondByte = ord($data[1]);
        $masked = ($secondByte >> 7) & 0b1; // Mask bit
        $payloadLength = $secondByte & 0b01111111; // Payload length

        // Determine the payload length
        if ($payloadLength === 126) {
            $payloadLength = unpack('n', substr($data, 2, 2))[1]; // Next 2 bytes for length
            $headerLength = 4; // 2 bytes for extended length
        } elseif ($payloadLength === 127) {
            $payloadLength = unpack('P', substr($data, 2, 8))[1]; // Next 8 bytes for length
            $headerLength = 10; // 8 bytes for extended length
        } else {
            $headerLength = 2; // No extended length
        }

        // Extract the masking key (if present)
        if ($masked) {
            $maskingKey = substr($data, $headerLength, 4);
            $headerLength += 4; // Move past the masking key
        } else {
            $maskingKey = null;
        }

        // Extract the payload data
        $payloadData = substr($data, $headerLength, $payloadLength);

        // Unmask the payload data if it was masked
        if ($masked) {
            for ($i = 0; $i < $payloadLength; ++$i) {
                $payloadData[$i] = chr(ord($payloadData[$i]) ^ ord($maskingKey[$i % 4]));
            }
        }

        return [
            'fin' => $fin,
            'opcode' => $opcode,
            'payload' => $payloadData
        ];
    }
}