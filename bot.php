<?php

require 'EnvParser.php';
//require 'VkSender.php';
require 'TgSender.php';

$trainingDays = [
    1, //понедельник
    3, //среда
    5, //пятница
    6 //суббота
];

$dayOfWeek = date('N', time()); //текущий день недели


//если сегодня нет тренировки, то ничего не делаем
if (!in_array($dayOfWeek, $trainingDays)) {
    return false;
}

//если в файле стопа стоит метка что бот не работает, то ничего не шлем
$stopMark = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
if (!empty($stopMark)) {
    return false;
}

//если в файле пропуска стоит метка что следующей тренировки не будет, то ничего не шлем и чистим метку
$passMark = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json');
if (!empty($passMark)) {
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', '');
    return false;
}


$env = EnvParser::parse();

//подключаем отправщик в вк или тг
//$sender = new VkSender($env['VK_BOT_TOKEN']);
$sender = new TgSender($env['TG_BOT_TOKEN']);

//получаем список водоносов
$waterBoys = $sender->getWaterBoysList();

//ищем того кто должен нести
$userId = (string)array_search(true, $waterBoys);
//отправляем сообщение пользователю
$userInfo = $sender->getUsersInfo($userId);

$userName = $userInfo[0]->first_name;
$message = "$userName, сегодня твоя вода. Хорошей тренировки!😉";

//id группы можно узнать через https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
$sendToId = $userId;
if ($sender instanceof TgSender) {
    //$sendToId = '-1002492024075';
    //$sendToId = '-1001795487199' - fenomen-a
    $sendToId = '-1002176249458';// - svoi
}

$sender->sendMessageToUser($sendToId, $message);


// Сдвигаем очередь вперед на одного
$sender->shiftWaterBoysQueue(1);


