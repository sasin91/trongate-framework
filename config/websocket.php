<?php

define('WEBSOCKET_HOST', $_ENV['WEBSOCKET_HOST'] ?? '127.0.0.1');
define('WEBSOCKET_PORT', $_ENV['WEBSOCKET_PORT'] ?? 8085);

define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT', $_ENV['REDIS_PORT'] ?? '6379');
