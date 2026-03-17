<?php

require "EnvParser.php";
require "TgSender.php";

if (!isset($_REQUEST)) {
    return;
}

//"https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}"

// Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));

$env = EnvParser::parse();
$tg = new TgSender($env['TG_BOT_TOKEN']);
//$groupChatId = '-1002176249458';
$groupChatId = '209097576';

function buildWaterBoysMessage(array $waterBoys): string
{
    $message = '';
    foreach ($waterBoys as $userId => $isTurn) {
        $message .= "♥ $userId";
        if ($isTurn) {
            $message .= ' - его очередь!';
        }
        $message .= "\n";
    }

    return $message;
}

function getInitiatorName(object $message): string
{
    if (isset($message->from)) {
        $from = $message->from;
        if (!empty($from->username)) {
            return '@' . $from->username;
        }

        $fullName = trim(($from->first_name ?? '') . ' ' . ($from->last_name ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        if (!empty($from->id)) {
            return (string)$from->id;
        }
    }

    if (isset($message->chat)) {
        $chat = $message->chat;
        if (!empty($chat->username)) {
            return '@' . $chat->username;
        }

        $chatName = trim(($chat->first_name ?? '') . ' ' . ($chat->last_name ?? ''));
        if ($chatName !== '') {
            return $chatName;
        }

        if (!empty($chat->title)) {
            return (string)$chat->title;
        }
    }

    if (isset($message->sender_chat)) {
        $senderChat = $message->sender_chat;
        if (!empty($senderChat->username)) {
            return '@' . $senderChat->username;
        }
        if (!empty($senderChat->title)) {
            return (string)$senderChat->title;
        }
    }

    return 'Неизвестный пользователь';
}

$keyboard = [
    'reply_markup' => json_encode([
        'keyboard' => [
            [['text' => 'Отмена'], ['text' => 'Статус']],
            [['text' => 'Стоп'], ['text' => 'Старт']],
            [['text' => 'Назад'], ['text' => 'Вперед']],
            [['text' => 'Список'], ['text' => 'Помощь']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
    ], JSON_UNESCAPED_UNICODE),
];

// Проверяем, что это сообщение
if (isset($data->message)) {
    $message = $data->message;
    if ($message->chat->type !== 'private') {
        return;
    }
    $chatId = (string)$message->chat->id;
    $text = $message->text;

    // Обработка команд
    switch ($text) {
        case 'Cancel':
        case 'Отмена':
            // Ставим метку, что тренировки не будет
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', 'true');
            $tg->sendMessageToUser($chatId, 'Вода для тренировки отменена', $keyboard);
            break;

        case 'Stop':
        case 'Стоп':
            // Ставим метку, что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', 'true');
            $tg->sendMessageToUser($chatId, 'Бот остановлен. Отправь Start чтобы запустить.', $keyboard);
            break;

        case 'Start':
        case 'Старт':
            // Очищаем метку, что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', '');
            $tg->sendMessageToUser($chatId, 'Бот запущен. Отправь Stop чтобы остановить.', $keyboard);
            break;

        case 'IsWork':
        case 'Статус':
            // Проверяем, работает ли бот
            $stopData = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
            $messageWork = 'Бот работает';
            if (!empty($stopData)) {
                $messageWork = 'Бот не работает';
            }
            $tg->sendMessageToUser($chatId, $messageWork, $keyboard);
            break;

        case 'GetList':
        case 'Список':
            // Получаем JSON список водоносов
            $data = file_get_contents($tg->filePath);
            $mes = $data . "\n\n" .
                "число - это ник пользователя в Telegram\n" .
                "true - его очередь\n" .
                "false - не его очередь\n" .
                "Для перезаписи списка отправь Rewrite:{список пользователей}";
            $tg->sendMessageToUser($chatId, $mes, $keyboard);
            break;

        case 'QueueForward':
        case 'Вперед':
            // Сдвиг очереди вперед
            $waterBoys = $tg->shiftWaterBoysQueue(1);
            $tg->sendMessageToUser($chatId, buildWaterBoysMessage($waterBoys), $keyboard);
            $tg->sendMessageToUser(
                $groupChatId,
                getInitiatorName($message) . ' перевел очередь вперед'
            );
            break;

        case 'QueueBack':
        case 'Назад':
            // Сдвиг очереди назад
            $waterBoys = $tg->shiftWaterBoysQueue(-1);
            $tg->sendMessageToUser($chatId, buildWaterBoysMessage($waterBoys), $keyboard);
            $tg->sendMessageToUser(
                $groupChatId,
                getInitiatorName($message) . ' перевел очередь назад 😱'
            );
            break;

        case 'Help':
        case 'Помощь':
            // Отправляем список команд
            $tg->sendMessageToUser(
                $chatId,
                "Доступные команды:\n" .
                "Cancel - отменить воду на 1 тренировку\n" .
                "IsWork - узнать, работает ли бот\n" .
                "Stop - остановить бота\n" .
                "Start - запустить бота\n" .
                "QueueForward - перевести очередь вперед\n" .
                "QueueBack - перевести очередь назад\n" .
                "GetList - получить JSON список водоносов\n" .
                "Rewrite:{список пользователей} - обновить список водоносов",
                $keyboard
            );
            break;

        default:
            // Проверяем, начинается ли строка с "Rewrite:"
            if (strpos($text, 'Rewrite:') === 0) {
                $isDecode = false;
                preg_match('/\{([^}]+)\}/', $text, $matches);
                if (isset($matches[0])) {
                    $extractedText = $matches[0];
                    $decoded = json_decode($extractedText, true);
                    if ($decoded !== null) {
                        $isDecode = true;
                        $tg->rewriteWaterBoysList($decoded);
                    }
                    if ($isDecode) {
                        $tg->sendMessageToUser($chatId, 'Список водоносов успешно изменен.', $keyboard);
                    } else {
                        $tg->sendMessageToUser($chatId, 'Строка не соответствует шаблону. Список не изменен.', $keyboard);
                    }
                }
                break;
            }

            // Получаем список водоносов
            $waterBoys = $tg->getWaterBoysList();

            $tg->sendMessageToUser($chatId, buildWaterBoysMessage($waterBoys), $keyboard);
            break;
    }
}
