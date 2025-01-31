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
            // Ставим метку, что тренировки не будет
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', 'true');
            $tg->sendMessageToUser($chatId, 'Вода для тренировки отменена');
            break;

        case 'Stop':
            // Ставим метку, что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', 'true');
            $tg->sendMessageToUser($chatId, 'Бот остановлен. Отправь Start чтобы запустить.');
            break;

        case 'Start':
            // Очищаем метку, что бот остановлен
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', '');
            $tg->sendMessageToUser($chatId, 'Бот запущен. Отправь Stop чтобы остановить.');
            break;

        case 'IsWork':
            // Проверяем, работает ли бот
            $stopData = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
            $messageWork = 'Бот работает';
            if (!empty($stopData)) {
                $messageWork = 'Бот не работает';
            }
            $tg->sendMessageToUser($chatId, $messageWork);
            break;

        case 'GetList':
            // Получаем JSON список водоносов
            $data = file_get_contents($tg->filePath);
            $mes = $data . "\n\n" .
                "число - это ник пользователя в Telegram\n" .
                "true - его очередь\n" .
                "false - не его очередь\n" .
                "Для перезаписи списка отправь Rewrite:{список пользователей}";
            $tg->sendMessageToUser($chatId, $mes);
            break;

        case 'Help':
            // Отправляем список команд
            $tg->sendMessageToUser(
                $chatId,
                "Доступные команды:\n" .
                "Cancel - отменить воду на 1 тренировку\n" .
                "IsWork - узнать, работает ли бот\n" .
                "Stop - остановить бота\n" .
                "Start - запустить бота\n" .
                "GetList - получить JSON список водоносов\n" .
                "Rewrite:{список пользователей} - обновить список водоносов"
            );
            break;

        default:
            // Проверяем, начинается ли строка с "Rewrite:"
            if (strpos($text, 'Rewrite:') > 0) {
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
                        $tg->sendMessageToUser($chatId, 'Список водоносов успешно изменен.');
                    } else {
                        $tg->sendMessageToUser($chatId, 'Строка не соответствует шаблону. Список не изменен.');
                    }
                }
                break;
            }

            // Получаем список водоносов
            $waterBoys = $tg->getWaterBoysList();

            // Формируем сообщение со списком водоносов
            $message = '';
            foreach ($waterBoys as $userId => $isTurn) {
                $message .= "♥ $userId";
                if ($isTurn) {
                    $message .= ' - его очередь!';
                }
                $message .= "\n";
            }

            $tg->sendMessageToUser($chatId, $message);
            break;
    }
}
