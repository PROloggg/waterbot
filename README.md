# Бот-Егор

ВК бот 
https://vk.com/club174535003

решает кому нести воду на тренировку
-----------------
Аккаунты для рассылки задаются в файле waterboys.json - {"vkPageId":<его ли очередь>}

Чтобы сообщения доходили пользователю необходимо первому написать сообщение боту
```
{"43775101":false}
```
----------------

Любое сообщение - получить список водоносов

Cancel - отменить воду на 1 тренировку

IsWork - Узнать работает ли бот

Stop - остановить бота насовсем

Start - запустить бота

GetList - получить json список водоносов для перезаписи

Rewrite:{список пользователей} - обновить список водоносов

---------------
Для рассылки необдимо вызывать bot.php
например cron командой (каждый день в 15:00) 
```
0 15 * * *
```

Полезные ссылки:
```
Установить Docker
https://docs.docker.com/get-docker/

Установить Docker-compose
https://docs.docker.com/compose/install/

Установить traefik
https://github.com/mediaten/traefik
```
