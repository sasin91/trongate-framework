<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/websocket.php';
require_once __DIR__ . '/runtime/Websocket_server.php';

$server = new Websocket_server(
    host: WEBSOCKET_HOST,
    port: WEBSOCKET_PORT,
    redis_host: REDIS_HOST,
    redis_port: REDIS_PORT,
);

$server->listen();