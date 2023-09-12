<?php
/**
 * Created by PhpStorm.
 * User: mt
 * Date: 25.09.19
 * Time: 9:38
 */

require "VkSender.php";

$trainingDays = [
    1, //понедельник
    3, //среда
    5, //пятница
    6 //суббота
];

$dayOfWeek = date("N", time()); //текущий день недели


//если сегодня нет тренировки, то ничего не делаем
if (!in_array($dayOfWeek, $trainingDays)) {
    return false;
}

//если в файле стопа стоит метка что бот не работает, то ничего не шлем
$stopmark = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
if (!empty($stopmark)) {
    return false;
}


//если в файле пропуска стоит метка что следующей тренировки не будет, то ничего не шлем и чистим метку
$passmark = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json');
if (!empty($passmark)) {
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', '');
    return false;
}


$vk = new VkSender();
//получаем список водоносов
$waterBoys = $vk->getWaterBoysList();

//ищем того кто должен нести
$userId = array_search(true, $waterBoys);

//отправляем собощение пользователю
$userInfo = $vk->getUsersInfo($userId);
$userName = $userInfo[0]->first_name;
$message = "$userName, сегодня твоя вода. Хорошей тренировки!😉";
$vk->sendMessageToUser($userId, $message);


//обнуляем водоносов и назначаем следующего
$ready = false;
foreach ($waterBoys as $id => $flag) {
    $waterBoys[$id] = false;
    if ($ready) {
        $waterBoys[$id] = true;
    }

    $ready = $flag;
}

//перезаписываем файл со списком водоносов
$vk->rewriteWaterBoysList(json_encode($waterBoys));



