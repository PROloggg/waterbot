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
    public $v = '5.81';

    // проверяем secretKey
    public function checkSecretKey(object $data): bool
    {
        return (strcmp($data->secret, $this->secretKey) !== 0 && strcmp($data->type, 'confirmation') !== 0);
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

    public function sendMessageToUser(string $userId, string $message): void
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
}
