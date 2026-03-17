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
$groupChatId = '-1002176249458';
//$groupChatId = '209097576'; //me

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

function parseSimpleRewriteList(string $text): ?array
{
    if (!preg_match('/^(Обновить|RewriteList)\s*:\s*(.*)$/su', $text, $matches)) {
        return null;
    }

    $rawList = trim($matches[2]);
    if ($rawList === '') {
        return [];
    }

    $items = preg_split('/[\n,;]+/u', $rawList);
    $prepared = [];
    foreach ($items as $item) {
        $value = trim($item);
        $value = preg_replace('/^\s*(?:[-*•]+|\d+[.)])\s*/u', '', $value);
        $value = trim((string)$value, " \t\n\r\0\x0B\"'");

        if ($value === '') {
            continue;
        }

        $prepared[] = $value;
    }

    $prepared = array_values(array_unique($prepared));
    if (count($prepared) === 0) {
        return [];
    }

    $result = [];
    foreach ($prepared as $index => $userId) {
        $result[$userId] = $index === 0;
    }

    return $result;
}

$keyboard = [
    'reply_markup' => json_encode([
        'keyboard' => [
            [['text' => '🚫 Отмена'], ['text' => '📊 Статус']],
            [['text' => '⏹ Стоп'], ['text' => '▶️ Старт']],
            [['text' => '⬅️ Назад'], ['text' => '➡️ Вперед']],
            [['text' => '📝 Обновить'], ['text' => '📋 Список']],
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
        case '🚫 Отмена':
            // Ставим метку, что тренировки не будет
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', 'true');
            $tg->sendMessageToUser($chatId, 'Вода для тренировки отменена', $keyboard);
            break;

        case 'Stop':
        case 'Стоп':
        case '⏹ Стоп':
            // Ставим метку, что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', 'true');
            $tg->sendMessageToUser($chatId, 'Бот остановлен. Отправь Start чтобы запустить.', $keyboard);
            break;

        case 'Start':
        case 'Старт':
        case '▶️ Старт':
            // Очищаем метку, что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', '');
            $tg->sendMessageToUser($chatId, 'Бот запущен. Отправь Stop чтобы остановить.', $keyboard);
            break;

        case 'IsWork':
        case 'Статус':
        case '📊 Статус':
            // Проверяем, работает ли бот
            $stopData = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
            $messageWork = 'Бот работает';
            if (!empty($stopData)) {
                $messageWork = 'Бот не работает';
            }
            $tg->sendMessageToUser($chatId, $messageWork, $keyboard);
            break;

        case '📝 Обновить':
        case 'Обновить':
            $tg->sendMessageToUser(
                $chatId,
                "Отправь список так:\n" .
                "Обновить:\n" .
                "@nick1\n" .
                "@nick2\n" .
                "Имя Фамилия\n\n" .
                "Можно и в одну строку: Обновить: @nick1, @nick2, Имя Фамилия",
                $keyboard
            );
            break;

        case 'QueueForward':
        case 'Вперед':
        case '➡️ Вперед':
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
        case '⬅️ Назад':
            // Сдвиг очереди назад
            $waterBoys = $tg->shiftWaterBoysQueue(-1);
            $tg->sendMessageToUser($chatId, buildWaterBoysMessage($waterBoys), $keyboard);
            $tg->sendMessageToUser(
                $groupChatId,
                getInitiatorName($message) . ' перевел очередь назад 😱'
            );
            break;

        default:
            $simpleList = parseSimpleRewriteList($text);
            if ($simpleList !== null) {
                if (count($simpleList) === 0) {
                    $tg->sendMessageToUser($chatId, 'Не удалось распознать список. Добавь хотя бы одного человека после "Обновить:".', $keyboard);
                    break;
                }

                $tg->rewriteWaterBoysList($simpleList);
                $tg->sendMessageToUser($chatId, 'Список водоносов успешно изменен.', $keyboard);
                $tg->sendMessageToUser($chatId, buildWaterBoysMessage($simpleList), $keyboard);
                break;
            }

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
                        $tg->sendMessageToUser($chatId, buildWaterBoysMessage($decoded), $keyboard);
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
