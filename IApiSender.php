<?php

interface IApiSender
{
    public function getUsersInfo(string $userId): array;

    public function sendMessageToUser(string $userId, string $message);

    public function getWaterBoysList(): array;

    public function rewriteWaterBoysList(array $data): void;
}
