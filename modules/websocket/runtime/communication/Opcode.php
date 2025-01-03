<?php

enum Opcode: int
{
    case CONTINUATION = 0x0; // Continuation frame
    case TEXT = 0x1;         // Text frame
    case BINARY = 0x2;       // Binary frame
    case CLOSE = 0x8;        // Connection close
    case PING = 0x9;         // Ping frame
    case PONG = 0xA;         // Pong frame

    public static function infer(mixed $value): Opcode
    {
        if (is_string($value) === false) {
            return self::BINARY;
        }

        $size = strlen($value);
        $text_encoding = mb_detect_encoding(
            string: $value, 
            encodings: 'UTF-8', 
            strict: true
        );

        return match (true) {
            $size > 1 * 1024 * 1024 => self::CONTINUATION,
            $text_encoding === false => self::BINARY,
            default => self::TEXT,
        };
    }
}
