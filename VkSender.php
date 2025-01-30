<?php
/**
 * Created by PhpStorm.
 * User: mt
 * Date: 25.09.19
 * Time: 9:31
 */

class VkSender
{
    //Строка для подтверждения адреса сервера из настроек Callback API
    public $confirmationToken = '68ccc70a';

    //Ключ доступа сообщества
    public $token = '6c183f12e6a58269c8a92a4b9c5d9ea0b6cf10706094af0fb10b15abdb92599c7665a33546689c01dc2c7';

    //Secret key
    public $secretKey = 'testSecureKey';

    //Версия Api
    public $v = '5.81';

    //путь до json файла со списком водоносов
    public $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'waterboys.json';

    // проверяем secretKey
    public function checkSecretKey($data)
    {
        return (strcmp($data->secret, $this->secretKey) !== 0 && strcmp($data->type, 'confirmation') !== 0);
    }

    public function getUsersInfo($userId)
    {
        //с помощью users.get получаем данные об авторе
        $userInfo = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids={$userId}&access_token={$this->token}&v={$this->v}"));

        return $userInfo->response;
    }

    public function sendMessageToUser($userId, $message)
    {
        //С помощью messages.send и токена сообщества отправляем сообщение
        $request_params = [
            'message' => $message,
            'user_id' => $userId,
            'peer_id' => $userId,
            'access_token' => $this->token,
            'v' => $this->v
        ];

        $get_params = http_build_query($request_params);

        file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);

        //Возвращаем "ok" серверу Callback API
        echo 'ok';
    }

    public function getWaterBoysList()
    {
        // читаем файл со списком водоносов (в корне) [id_пользоваля_вк => его ли очередь нести]
        $data = file_get_contents($this->filePath);
        $waterBoys = json_decode($data, true);

        //ищем того кто должен нести
        $userId = array_search(true, $waterBoys);

        //если не нашли, берем первого
        if (!$userId) {
            $userId = key($waterBoys);
            $waterBoys[$userId] = true;
        }
        
        return $waterBoys;
    }

    public function rewriteWaterBoysList($data)
    {
        // Пишем содержимое в файл
        file_put_contents($this->filePath, $data);
    }
}
