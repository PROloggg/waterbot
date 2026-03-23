<?php


require 'AbstractApiSender.php';

class VkSender extends AbstractApiSender
{
    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->filePath = __DIR__ . DIRECTORY_SEPARATOR . 'waterboys_vk.json';
    }

    //Строка для подтверждения адреса сервера из настроек Callback API
    public $confirmationToken = '762434ce';

    //Secret key
    public $secretKey = 'testSecureKey';

    //Версия Api
    public $v = '5.199';

    // проверяем secretKey
    public function checkSecretKey(object $data): bool
    {
        if (!isset($data->type)) {
            return true;
        }

        if ((string)$data->type === 'confirmation') {
            return false;
        }

        return !isset($data->secret) || strcmp((string)$data->secret, $this->secretKey) !== 0;
    }

    public function getUsersInfo(string $userId): array
    {
        //с помощью users.get получаем данные об авторе
        $userInfo = json_decode(
            file_get_contents(
                "https://api.vk.com/method/users.get?user_ids={$userId}&access_token={$this->token}&v={$this->v}"
            )
        );

        return $userInfo->response;
    }

    public function sendMessageToUser(string $peerId, string $message, array $extraParams = []): void
    {
        //С помощью messages.send и токена сообщества отправляем сообщение
        $request_params = [
            'message' => $message,
            'peer_id' => $peerId,
            'random_id' => random_int(1, PHP_INT_MAX),
            'access_token' => $this->token,
            'v' => $this->v
        ];

        if (!empty($extraParams)) {
            if (isset($extraParams['keyboard']) && is_array($extraParams['keyboard'])) {
                $extraParams['keyboard'] = json_encode($extraParams['keyboard'], JSON_UNESCAPED_UNICODE);
            }
            $request_params = array_merge($request_params, $extraParams);
        }

        $get_params = http_build_query($request_params);

        file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
    }

    public function sendMessageEventAnswer(string $eventId, string $userId, string $peerId, array $eventData): void
    {
        $requestParams = [
            'event_id' => $eventId,
            'user_id' => $userId,
            'peer_id' => $peerId,
            'event_data' => json_encode($eventData, JSON_UNESCAPED_UNICODE),
            'access_token' => $this->token,
            'v' => $this->v,
        ];
        $getParams = http_build_query($requestParams);
        file_get_contents('https://api.vk.com/method/messages.sendMessageEventAnswer?' . $getParams);
    }
}
