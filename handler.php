<?php
if (!isset($_REQUEST)) {
    return;
}
require "VkSender.php";

//Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));

$vk = new VkSender();

// проверяем secretKey
if ($vk->checkSecretKey($data)) {
    return;
}

//Проверяем, что находится в поле "type"
switch ($data->type) {
    //Если это уведомление для подтверждения адреса сервера...
    case 'confirmation':
        //...отправляем строку для подтверждения адреса
        echo $vk->confirmationToken;
        break;

    //Если это уведомление о новом сообщении...
    case 'message_new':
        //...получаем id его автора
        $userId = $data->object->user_id;

        if ($data->object->body === 'Cancel') {
            //ставим метку что тренировки не будет
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', 'true');
            $vk->sendMessageToUser($userId, "Вода для тренировки отменена");
            return;
        }

        //получаем список водоносов
        $waterBoys = $vk->getWaterBoysList();

        //получаем информацию о пользователях
        $usersInfo = [];
        $ids = array_keys($waterBoys);
        $ids = implode(",", $ids);
        $usersInfo = $vk->getUsersInfo($ids);

        //формируем сообщение со списком водоносов
        $message = '';
        foreach ($usersInfo as $user) {
            $message .= "♥ https://vk.com/id$user->id - $user->first_name $user->last_name";
            if ($waterBoys[$user->id]) {
                $message .= ' - его очередь!';
            }
            $message .= '<br>';
        }

        $vk->sendMessageToUser($userId, $message);
        break;

}
