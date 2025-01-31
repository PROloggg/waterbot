<?php

require 'IApiSender.php';

class AbstractApiSender implements IApiSender
{
    // Путь до JSON файла со списком водоносов
    public $filePath = 'waterboys.json';
    public $token = '';

    // Токен вашего бота
    public function __construct(string $token)
    {
        $this->token = $token;
    }


    // Получение списка водоносов
    public function getWaterBoysList(): array
    {
        $data = file_get_contents($this->filePath);
        $waterBoys = json_decode($data, true);

        $userId = array_search(true, $waterBoys);

        if (!$userId) {
            $userId = key($waterBoys);
            $waterBoys[$userId] = true;
        }

        return $waterBoys;
    }

    // Перезапись списка водоносов
    public function rewriteWaterBoysList(array $data): void
    {
        file_put_contents($this->filePath, json_encode($data));
    }

    public function getUsersInfo(string $userId): array
    {
        // TODO: Implement getUsersInfo() method.
        return [];
    }

    public function sendMessageToUser(string $userId, string $message)
    {
        // TODO: Implement sendMessageToUser() method.
    }
}