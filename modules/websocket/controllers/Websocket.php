<?php

class Websocket extends Trongate
{
    private ?Redis $redis = null;

    public function _redis(): Redis {
        if ($this->redis == null) {
            $this->redis = new Redis();
            $this->redis->connect(
                REDIS_HOST,
                REDIS_PORT
            );
        }

        return $this->redis;
    }

    /*
    public function auth()
    {
        $trongateToken = $_SERVER['HTTP_TOKEN'];

        $this->module('trongate_tokens');
        $this->trongate_tokens->_get_user_id($trongateToken);
    }
    */

    public function _publish(string $channel, string $message): void {
        $this->_redis()->publish($channel, $message);
    }
}
