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

    // Сдвиг очереди водоносов: 1 - вперед, -1 - назад
    public function shiftWaterBoysQueue(int $step): array
    {
        $waterBoys = $this->getWaterBoysList();
        $userIds = array_keys($waterBoys);
        $count = count($userIds);

        if ($count === 0) {
            return [];
        }

        $currentUserId = array_search(true, $waterBoys, true);
        $currentIndex = array_search($currentUserId, $userIds, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $nextIndex = ($currentIndex + $step) % $count;
        if ($nextIndex < 0) {
            $nextIndex += $count;
        }

        foreach ($waterBoys as $userId => $isTurn) {
            $waterBoys[$userId] = false;
        }
        $waterBoys[$userIds[$nextIndex]] = true;

        $this->rewriteWaterBoysList($waterBoys);

        return $waterBoys;
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
