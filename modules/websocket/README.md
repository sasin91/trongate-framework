# Websocket in pure PHP
This is a simple but efficient implementation written in pure PHP.
It offers a great starting point for interactivity, like chat, notifications, etc.
>Websockets are bi-directional, full-duplex, and long-lived connections,
>meaning the client can send messages to the server
>and the server can push messages to the client at any time.

While this ia great for adding a bit of interactivity to your website,
it may not be the best solution for low-latency applications like games or webrtc videos.

For those use-cases i suggest going with Go + Gin + Gorilla Websockets.

## Prerequisites
This module requires PHP 8.1 or higher to run.
> This module makes use of PHP Fibers
> to allow for fast responses through co-operative multitasking.

Redis server
> This module uses Redis to listen for messages from other modules
> and other instances of this module.

## How to use
This module is intended to be ran as a standalone server, 
either directly connected to or proxied to from nginx.

To start it locally, simply run the following command:
```shell
    cd modules/websocket
    php server.php
```

you should now be able to connect to the websocket server at `ws://localhost:8085`

This library comes with a convenient client library that you can use to connect to the server.

e.g. in `templates/views/public.php`
```html
    <!-- Shortened for brevity -->
    <header>
        <div class="logo">
            <?= anchor(BASE_URL, WEBSITE_NAME) ?>
            <span class="online_count badge">0 Online</span>
        </div>
    </header>
    <!-- Shortened for brevity -->

    <script src="websocket_module/js/websocket.js"></script>
    <script>
        const online_count = document.querySelectorAll('.online_count');

        const socket = new Socket(
            `<?= WEBSOCKET_URL ?>?trongateToken=<?= $token ?? '' ?>&user_id=<?= $user_id ?? null ?>`
        );

        socket.onStateChange('num_online', (value) => {
            online_count.forEach((element) => {
                element.innerHTML = `${value} Online`;
            });
        });
    </script>
```
> In addition to the number of unique clients (num_online), you may also listen for `num_clients` which shows the number of established connections.
> e.g. if a user connects to the website on multiple tabs in the same browser, then the `num_online` would remain the same, but the `num_clients` would increase.

When the app is deployed, the communication should happen through a reverse proxy like Nginx

You may want to reproduce this locally.

## Extending
You're free to change this module to suit your needs.
I have however tried to account for the 80% use-case by providing a simple way to add functionality.

Inside of the `runtime` directory, there is a directory called `handlers`. <br>
These handlers are responsible for handling incoming messages from the client, there is already a `Controller.php` handler. <br>
This handler provides for an easy way to add functionality using your own modules. <br>
Here is an example: <br>
Somewhere in your JavaScript
```javascript
socket.send(
    JSON.stringify({ 
        handler: 'controller', 
        module: 'chat', 
        data: 'hello world'
    })
)
```
In your modules
`modules/Chat/controllers/Chat.php`
```php
<?php

class Chat extends Trongate {
  public function _on_websocket_message(array $json, array $state) {
     $data = $json['data']; // 'hello world'
     $userId = $state['user_id']; // null if not logged in, otherwise the user's id

     return 'Ahoy!';
  }
}
```

### That's awesome, but how do i trigger something from the server?
This is a little bit cumbersome, but let me outline the steps for you with some psuedo-code:

## 1. Load the WebSocket Module in Your Controller
```php
$this->module('websocket');
```
## 2. Publish a Message as JSON
```php
$this->websocket->_publish('live_streams', json_encode([
    'status' => 'new',
    'id' => $update_id,
]));
```
## 3. Subscribe to the Event in the WebSocket Runtime
Add a listener and broadcaster in the `websocket/runtime/Pub_sub_messaging.php` file starting on line 46 (`protected function subscribe_to_events(): void;` method)
```php
$live_streams = new Fiber($this->json_subscription(
    'live_streams',
    $this->broadcast(...)
));
$live_streams->start($this->subscriber);
$this->fibers->enqueue($live_streams);
```
## 4. Listen for Events in JavaScript
Handle incoming events dynamically:
```php
const liveStreamEventHandler = new LiveStreamEventHandler();

// Listen for the 'live_streams' event
socket.onMessage('live_streams', (event) => {
    liveStreamEventHandler.handle(event);
});
```

Swap `'live_streams'` for your event name

## Deployment

### Nginx
```nginx
server {
    listen 80;
    server_name myapp.com;

    root /var/www/html/public;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;  # Adjust socket if needed
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_index index.php;
    }

    # This is the important block.
    # Forward websocket requests to the websocket server
    location /ws {
        proxy_pass http://127.0.0.1:8085;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

To keep the server running, you may want to use a process manager like `pm2`, `supervisord` or `systemd`.
### Systemd
`/etc/systemd/system/websocket-server.service` 
or locally `~/.config/systemd/user/websocket-server.service`
```ini
[Unit]
Description=WebSocket Server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/modules/websocket/server.php
Restart=always
User=www-data
Group=www-data
Environment=PATH=/usr/bin:/usr/local/bin
WorkingDirectory=/var/www

[Install]
WantedBy=multi-user.target
```
>If you chose to install the service locally, you need to pass it `--user` to `systemctl`
```shell
systemctl enable --now websocket-server
```

## License
This module is licensed under the MIT License - see the [LICENSE](LICENSE) file for details. <br>
In short, you are free to use & modify this module for personal or commercial use.

## Contact
If you have any questions or other inquiries, feel free to reach out to me at [jonas.kerwin.hansen@gmail.com](mailto:jonas.kerwin.hansen@gmail.com)