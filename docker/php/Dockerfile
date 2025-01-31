FROM yiisoftware/yii2-php:7.2-fpm

RUN docker-php-ext-install sockets

RUN apt-get update && apt-get install -y --no-install-recommends \
        cron \
     && rm -r /var/lib/apt/lists/*
