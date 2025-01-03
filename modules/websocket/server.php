<?php

require __DIR__ . '/../../config/websocket.php';
require __DIR__ . '/runtime/Fibers.php';
require __DIR__ . '/runtime/Client_registry.php';
require __DIR__ . '/runtime/communication/Frame.php';
require __DIR__ . '/runtime/communication/Opcode.php';
require __DIR__ . '/runtime/scaling/Messenger.php';
require __DIR__ . '/runtime/scaling/connections/Redis_connection.php';
require __DIR__ . '/runtime/Runtime.php';

$fibers = new Fibers();
$clients = new Client_registry();

$messenger = new Messenger(
    fibers: $fibers,
    clients: $clients,
    publisher: new Redis_connection(
        host: REDIS_HOST,
        port: REDIS_PORT
    ),
    subscriber: new Redis_connection(
        host: REDIS_HOST,
        port: REDIS_PORT
    )
);

$runtime = new Runtime(
    fibers: $fibers,
    clients: $clients,
    messenger: $messenger,
    host: WEBSOCKET_HOST,
    port: WEBSOCKET_PORT,
);

$runtime->listen();