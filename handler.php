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
        $userId = $data->object->from_id;

        if ($data->object->text === 'Cancel') {
            //ставим метку что тренировки не будет
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', 'true');
            $vk->sendMessageToUser($userId, 'Вода для тренировки отменена');
            return;
        }

        if ($data->object->text === 'Stop') {
            //ставим метку что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', 'true');
            $vk->sendMessageToUser($userId, 'Бот остановлен. Отправь Start чтобы запустить.');
            return;
        }

        if ($data->object->text === 'Start') {
            //очищаем метку что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', '');
            $vk->sendMessageToUser($userId, 'Бот запущен. Отправь Stop чтобы остановить.');
            return;
        }

        if ($data->object->text === 'IsWork') {
            //Поверяем работает ли бот
            $stopData = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
            $messageWork = 'Бот работает';
            if (empty(json_decode($stopData)) === false) {
                $messageWork = 'Бот не работает';
            }
            $vk->sendMessageToUser($userId, $messageWork);
            return;
        }


        //получаем список водоносов
        $waterBoys = $vk->getWaterBoysList();

        if ($data->object->text === 'GetList') {
            //получаем json список
            $data = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'waterboys.json');
            $mes = $data . '<br>' .
                'число - это id пользователя вк <br> true - его очередь <br> false - не его очередь <br> очень важно сохранить структуру, все : {} " , иначе ничего не будет работать'
                . 'Для перезаписи списка отправь Rewrite:{список пользователей}';
            $vk->sendMessageToUser($userId, $mes);
            return;
        }

        if ($data->object->text === 'Help') {
            $vk->sendMessageToUser($userId, "
                Любое сообщение - получить список водоносов <br>
                Cancel - отменить воду на 1 тренировку <br>
                IsWork - Узнать работает ли бот <br>
                Stop - остановить бота насовсем <br>
                Start - запустить бота <br>
                GetList - получить json список водоносов для перезаписи <br>
                Rewrite:{список пользователей} - обновить список водоносов
            ");
            return;
        }

        // Проверяем, начинается ли строка с "Rewrite:"
        if (strpos($data->object->text, 'Rewrite:') === 0) {
            $isDecode = false;
            // Найдено совпадение
            // Извлекаем текст между фигурными скобками
            preg_match('/\{([^}]+)\}/', $data->object->text, $matches);
            if (isset($matches[0])) {
                $extractedText = $matches[0];

                // $matches[0] содержит всю найденную строку
                if (json_decode($extractedText, true) !== null) {
                    $isDecode = true;
                    $vk->rewriteWaterBoysList($extractedText);
                }
                if ($isDecode === false) {
                    $vk->sendMessageToUser($userId, 'Строка не соответствует шаблону. список не изменен.');
                } else {
                    $vk->sendMessageToUser($userId, 'Список водоносов успешно изменен.');
                }
                return;
            }
        }

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
