<?php
//Database settings
define('HOST', $_ENV['DATABASE_HOST'] ?? '127.0.0.1');
define('PORT', $_ENV['DATABASE_PORT'] ?? '3306');
define('USER', $_ENV['DATABASE_USER'] ?? 'root');
define('PASSWORD', $_ENV['DATABASE_PASSWORD'] ?? '');
define('DATABASE', $_ENV['DATABASE_NAME'] ?? 'trongate');

