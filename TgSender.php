<?php

require 'AbstractApiSender.php';

class TgSender extends AbstractApiSender
{
    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->filePath = __DIR__ . DIRECTORY_SEPARATOR . 'waterboys_tg.json';
    }


    // Получение информации о пользователе
    public function getUsersInfo(string $userId): array
    {
        return [
            (object)['first_name' => $userId]
        ];
    }

    // Отправка сообщения пользователю
    public function sendMessageToUser(string $userId, string $message): void
    {
        $request_params = [
            'chat_id' => $userId,
            'text' => $message,
        ];

        $get_params = http_build_query($request_params);
        file_get_contents("https://api.telegram.org/bot{$this->token}/sendMessage?{$get_params}");

        echo 'ok';
    }
}