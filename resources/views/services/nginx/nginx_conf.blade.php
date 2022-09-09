{{--@formatter:off--}}
<?php /** @var \App\Docker\Services\Nginx $service */ ?>
worker_processes  4;

error_log  /var/log/nginx/error.log warn;
pid        /var/run/nginx.pid;

events {
    worker_connections  1024;
}

http {
    map $http_upgrade $connection_upgrade {
        default upgrade;
        '' close;
    }

    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;


    log_format main '$remote_addr - $remote_user [$time_local] "$host" - "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" $request_time';

    access_log  /var/log/nginx/access.log main;

    # Switch logging to console out to view via Docker
    #access_log /dev/stdout;
    #error_log /dev/stderr;

    sendfile        on;
    keepalive_timeout  65;

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-available/*.conf;

    client_max_body_size 5G;
    fastcgi_read_timeout 600;
    proxy_read_timeout 600;
}
