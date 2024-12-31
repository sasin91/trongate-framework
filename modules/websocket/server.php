<?php

require_once __DIR__ . '/../../config/websocket.php';
require_once __DIR__ . '/runtime/Runtime.php';

$server = new Runtime(
    host: WEBSOCKET_HOST,
    port: WEBSOCKET_PORT,
    redis_host: REDIS_HOST,
    redis_port: REDIS_PORT,
);

$server->listen();