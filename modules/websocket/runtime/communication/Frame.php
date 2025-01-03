<?php

final readonly class Frame implements Stringable
{
    public static function decode(string $data): ?Frame {
        if (empty($data)) {
            return null;
        }

        // Read the first byte
        $firstByte = ord($data[0]);
        $fin = ($firstByte >> 7) & 0b1;
        $opcode = $firstByte & 0b00001111; // 0x0F === 0b00001111
        
        // @see https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#pings_and_pongs_the_heartbeat_of_websockets
        if ($opcode === 0xA) {
            return new static(
                fin: 1,
                opcode: Opcode::PONG,
                payload: ''
            );
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
        
        return new static(
            fin: $fin,
            opcode: Opcode::from($opcode),
            payload: $payloadData
        );
    }

    public static function encode(string $message, Opcode $opcode): Frame {
        $frameHead = [];
        $payloadLength = strlen($message);

        // Frame header: FIN, Opcode 0x1 (text frame)
        // $frameHead[0] = 129;
        $frameHead[0] = 0x80 | $opcode->value; // FIN bit set, custom opcode (0x1 for text, 0x9 for ping, etc.)

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
        $payload = $frameHeadStr . $message;

        return new static(
            fin: 1,
            opcode: $opcode,
            payload: $payload
        );
    }

    public function __construct(
        /**
         * The final bit (FIN bit) in a websocket frame
         * indicates whether it's the last bit of a message
         * 
         * @var int
         */
        public int $fin,

        /**
         * The opcode indicates which type of payload is being transmitted
         * 
         * @var Websocket_opcode
         */
        public Opcode $opcode,

        /**
         * The payload depends on the opcode.
         * In the case of a text payload, it is often a JSON string.
         * 
         * @var string
         */
        public string $payload
    )
    {
        //
    }

    
    public function __toString(): string {
        return $this->payload;
    }
}
