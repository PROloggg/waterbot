<?php

if (!isset($_REQUEST)) {
    return;
}

require "EnvParser.php";
require "VkSender.php";

// Идентификатор общей беседы VK (формат: 2000000000 + chat_id).
// Пустое значение отключает отправку в беседу.
$groupChatPeerId = '';
// Пустой список = без ограничений; добавьте id админов для защиты команд управления.
$adminUserIds = [];

function buildVkKeyboard(): array
{
    return [
        'one_time' => false,
        'inline' => false,
        'buttons' => [
            [
                [
                    'action' => ['type' => 'text', 'label' => '📋 Список', 'payload' => '{"cmd":"list"}'],
                    'color' => 'secondary',
                ],
                [
                    'action' => ['type' => 'text', 'label' => '📊 Статус', 'payload' => '{"cmd":"status"}'],
                    'color' => 'primary',
                ],
            ],
            [
                [
                    'action' => ['type' => 'text', 'label' => '⏹ Стоп', 'payload' => '{"cmd":"stop"}'],
                    'color' => 'secondary',
                ],
                [
                    'action' => ['type' => 'text', 'label' => '▶️ Старт', 'payload' => '{"cmd":"start"}'],
                    'color' => 'positive',
                ],
            ],
            [
                 [
                    'action' => ['type' => 'text', 'label' => '⬅️ Назад', 'payload' => '{"cmd":"queue_back"}'],
                    'color' => 'secondary',
                ],
                [
                    'action' => ['type' => 'text', 'label' => '➡️ Вперед', 'payload' => '{"cmd":"queue_forward"}'],
                    'color' => 'secondary',
                ],
            ],
            [
                [
                    'action' => ['type' => 'text', 'label' => '🚫 Отмена', 'payload' => '{"cmd":"cancel"}'],
                    'color' => 'negative',
                ],
                [
                    'action' => ['type' => 'text', 'label' => '✅ Возобновить', 'payload' => '{"cmd":"resume_training"}'],
                    'color' => 'positive',
                ],
            ],
            [
                [
                    'action' => ['type' => 'text', 'label' => '📝 Обновить', 'payload' => '{"cmd":"rewrite_help"}'],
                    'color' => 'primary',
                ],
            ],
        ],
    ];
}

function isAdmin(string $userId, array $adminUserIds): bool
{
    if (count($adminUserIds) === 0) {
        return true;
    }

    return in_array((int)$userId, $adminUserIds, true) || in_array($userId, $adminUserIds, true);
}

function buildWaterBoysMessage(VkSender $vk, array $waterBoys): string
{
    $ids = array_keys($waterBoys);
    if (count($ids) === 0) {
        return 'Список водоносов пуст.';
    }

    $allNumeric = true;
    foreach ($ids as $id) {
        if (!preg_match('/^\d+$/', (string)$id)) {
            $allNumeric = false;
            break;
        }
    }

    if (!$allNumeric) {
        $message = '';
        foreach ($waterBoys as $id => $isTurn) {
            $message .= "♥ {$id}";
            if ($isTurn) {
                $message .= ' - его очередь!';
            }
            $message .= "\n";
        }
        return trim($message);
    }

    $usersInfo = $vk->getUsersInfo(implode(',', $ids));
    if (!is_array($usersInfo)) {
        $usersInfo = [];
    }

    if (count($usersInfo) === 0) {
        $message = '';
        foreach ($waterBoys as $id => $isTurn) {
            $message .= "♥ {$id}";
            if ($isTurn) {
                $message .= ' - его очередь!';
            }
            $message .= "\n";
        }
        return trim($message);
    }

    $message = '';
    foreach ($usersInfo as $user) {
        $id = (string)$user->id;
        $message .= "♥ https://vk.com/id{$id} - {$user->first_name} {$user->last_name}";
        if (!empty($waterBoys[$id])) {
            $message .= ' - его очередь!';
        }
        $message .= "\n";
    }

    return trim($message);
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

function getInitiatorName(VkSender $vk, string $userId): string
{
    $users = $vk->getUsersInfo($userId);
    if (!empty($users[0])) {
        return trim($users[0]->first_name . ' ' . $users[0]->last_name);
    }
    return $userId;
}

//Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));
if (!is_object($data) || !isset($data->type)) {
    http_response_code(400);
    echo 'bad request';
    return;
}

$env = EnvParser::parse();
$vk = new VkSender($env['VK_BOT_TOKEN']);
$keyboard = ['keyboard' => buildVkKeyboard()];

// проверяем secretKey
if ($vk->checkSecretKey($data)) {
    http_response_code(403);
    echo 'forbidden';
    return;
}

switch ($data->type) {
    case 'confirmation':
        echo $vk->confirmationToken;
        return;

    case 'message_event':
        $eventObject = $data->object ?? null;
        if (!is_object($eventObject)) {
            echo 'ok';
            return;
        }

        $eventData = ['type' => 'show_snackbar', 'text' => 'Команда получена'];
        $vk->sendMessageEventAnswer(
            (string)($eventObject->event_id ?? ''),
            (string)($eventObject->user_id ?? ''),
            (string)($eventObject->peer_id ?? ''),
            $eventData
        );
        echo 'ok';
        return;

    case 'message_new':
        $object = $data->object ?? null;
        $messageObject = is_object($object) && isset($object->message) ? $object->message : $object;
        if (!is_object($messageObject)) {
            echo 'ok';
            return;
        }

        $userId = (string)($messageObject->from_id ?? '');
        $peerId = (string)($messageObject->peer_id ?? $userId);
        $text = trim((string)($messageObject->text ?? ''));
        $isAdmin = isAdmin($userId, $adminUserIds);

        if ($text === 'Help' || $text === 'Помощь' || $text === '/help') {
            $vk->sendMessageToUser(
                $peerId,
                "Команды:\n" .
                "• Любое сообщение или «Список» — показать очередь\n" .
                "• Cancel / Отмена — отменить воду на 1 тренировку\n" .
                "• ResumeTraining / Возобновить — вернуть тренировку после отмены\n" .
                "• IsWork / Статус — узнать, работает ли бот\n" .
                "• Stop / Стоп — остановить бота\n" .
                "• Start / Старт — запустить бота\n" .
                "• QueueBack / Назад — сдвинуть очередь назад\n" .
                "• QueueForward / Вперед — сдвинуть очередь вперед\n" .
                "• GetList — получить JSON-список\n" .
                "• Rewrite:{...} — перезаписать список JSON\n" .
                "• Обновить: id1, id2, ... — простой формат обновления",
                $keyboard
            );
            echo 'ok';
            return;
        }

        if (
            in_array($text, ['Cancel', 'Отмена', '🚫 Отмена'], true) ||
            in_array($text, ['ResumeTraining', 'Возобновить', '✅ Возобновить'], true) ||
            in_array($text, ['Stop', 'Стоп', '⏹ Стоп'], true) ||
            in_array($text, ['Start', 'Старт', '▶️ Старт'], true) ||
            in_array($text, ['QueueBack', 'Назад', '⬅️ Назад'], true) ||
            in_array($text, ['QueueForward', 'Вперед', '➡️ Вперед'], true) ||
            $text === 'GetList' ||
            $text === '📝 Обновить' ||
            $text === 'Обновить' ||
            strpos($text, 'Rewrite:') === 0 ||
            parseSimpleRewriteList($text) !== null
        ) {
            if (!$isAdmin) {
                $vk->sendMessageToUser($peerId, 'У вас нет прав на управление ботом.', $keyboard);
                echo 'ok';
                return;
            }
        }

        if (in_array($text, ['Cancel', 'Отмена', '🚫 Отмена'], true)) {
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', 'true');
            $vk->sendMessageToUser($peerId, 'Вода для тренировки отменена.', $keyboard);
            echo 'ok';
            return;
        }

        if (in_array($text, ['ResumeTraining', 'Возобновить', '✅ Возобновить'], true)) {
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json', '');
            $vk->sendMessageToUser($peerId, 'Тренировка возобновлена, отмена снята.', $keyboard);
            echo 'ok';
            return;
        }

        if (in_array($text, ['Stop', 'Стоп', '⏹ Стоп'], true)) {
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', 'true');
            $vk->sendMessageToUser($peerId, 'Бот остановлен. Отправь Start чтобы запустить.', $keyboard);
            echo 'ok';
            return;
        }

        if (in_array($text, ['Start', 'Старт', '▶️ Старт'], true)) {
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json', '');
            $vk->sendMessageToUser($peerId, 'Бот запущен. Отправь Stop чтобы остановить.', $keyboard);
            echo 'ok';
            return;
        }

        if (in_array($text, ['IsWork', 'Статус', '📊 Статус'], true)) {
            $stopData = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stopmark.json');
            $passData = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'passmark.json');

            $botStatus = empty($stopData) ? 'Бот работает' : 'Бот не работает';
            $waterStatus = empty($passData) ? 'вода не отменена' : 'вода отменена';

            $vk->sendMessageToUser($peerId, $botStatus . ', ' . $waterStatus . '.', $keyboard);
            echo 'ok';
            return;
        }

        if ($text === 'GetList') {
            $rawData = file_get_contents($vk->filePath);
            $vk->sendMessageToUser(
                $peerId,
                $rawData . "\n" .
                'число - это id пользователя вк; true - его очередь; false - не его очередь. Для перезаписи отправь Rewrite:{...}',
                $keyboard
            );
            echo 'ok';
            return;
        }

        if ($text === '📝 Обновить' || $text === 'Обновить') {
            $vk->sendMessageToUser(
                $peerId,
                "Отправь список так:\n" .
                "Обновить:\n" .
                "43775101\n" .
                "78507880\n\n" .
                "Можно и в одну строку: Обновить: 43775101, 78507880",
                $keyboard
            );
            echo 'ok';
            return;
        }

        if (in_array($text, ['QueueForward', 'Вперед', '➡️ Вперед'], true)) {
            $waterBoys = $vk->shiftWaterBoysQueue(1);
            $vk->sendMessageToUser($peerId, buildWaterBoysMessage($vk, $waterBoys), $keyboard);
            if ($groupChatPeerId !== '') {
                $vk->sendMessageToUser($groupChatPeerId, getInitiatorName($vk, $userId) . ' перевел очередь вперед.');
            }
            echo 'ok';
            return;
        }

        if (in_array($text, ['QueueBack', 'Назад', '⬅️ Назад'], true)) {
            $waterBoys = $vk->shiftWaterBoysQueue(-1);
            $vk->sendMessageToUser($peerId, buildWaterBoysMessage($vk, $waterBoys), $keyboard);
            if ($groupChatPeerId !== '') {
                $vk->sendMessageToUser($groupChatPeerId, getInitiatorName($vk, $userId) . ' перевел очередь назад.');
            }
            echo 'ok';
            return;
        }

        $simpleList = parseSimpleRewriteList($text);
        if ($simpleList !== null) {
            if (count($simpleList) === 0) {
                $vk->sendMessageToUser($peerId, 'Не удалось распознать список. Добавь хотя бы одного человека после "Обновить:".', $keyboard);
                echo 'ok';
                return;
            }
            $vk->rewriteWaterBoysList($simpleList);
            $vk->sendMessageToUser($peerId, 'Список водоносов успешно изменен.', $keyboard);
            $vk->sendMessageToUser($peerId, buildWaterBoysMessage($vk, $simpleList), $keyboard);
            echo 'ok';
            return;
        }

        if (strpos($text, 'Rewrite:') === 0) {
            preg_match('/\{(.+)\}/s', $text, $matches);
            if (!isset($matches[0])) {
                $vk->sendMessageToUser($peerId, 'Строка не соответствует шаблону. Список не изменен.', $keyboard);
                echo 'ok';
                return;
            }
            $decoded = json_decode($matches[0], true);
            if (!is_array($decoded)) {
                $vk->sendMessageToUser($peerId, 'Строка не соответствует шаблону. Список не изменен.', $keyboard);
                echo 'ok';
                return;
            }
            $vk->rewriteWaterBoysList($decoded);
            $vk->sendMessageToUser($peerId, 'Список водоносов успешно изменен.', $keyboard);
            $vk->sendMessageToUser($peerId, buildWaterBoysMessage($vk, $decoded), $keyboard);
            echo 'ok';
            return;
        }

        $waterBoys = $vk->getWaterBoysList();
        $vk->sendMessageToUser($peerId, buildWaterBoysMessage($vk, $waterBoys), $keyboard);
        echo 'ok';
        return;
}

echo 'ok';
