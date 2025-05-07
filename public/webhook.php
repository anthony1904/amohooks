<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/logger.php';

use Dotenv\Dotenv;
use App\AmoClient;

// Загружаем переменные окружения
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

log_message('Сырой $_POST', $_POST);

// Получаем данные
$leads = $_POST['leads'] ?? null;
$account = $_POST['account'] ?? null;

log_message('Получен входящий вебхук', [
    'account' => $account,
    'leads' => $leads,
]);

// Обработка событий
if (isset($leads['add'])) {
    log_message('Тип события', ['type' => 'create']);
    processLeads($leads['add'], 'create');
} elseif (isset($leads['update'])) {
    log_message('Тип события', ['type' => 'update']);
    processLeads($leads['update'], 'update');
}

function processLeads(array $leads, string $eventType): void
{
    $amo = new AmoClient();

    foreach ($leads as $lead) {
        $leadId = (int)$lead['id'];
        $noteText = generateNoteText($lead, $eventType);

        if ($noteText) {
            log_message('Добавляем примечание к сделке', [
                'id' => $leadId,
                'text' => $noteText
            ]);

            try {
                $amo->addNote($leadId, 'leads', $noteText);
                log_message('Примечание добавлено');
            } catch (\Throwable $e) {
                log_message('Ошибка при добавлении примечания', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

function generateNoteText(array $lead, string $eventType): ?string
{
    $createdAt = date('Y-m-d H:i:s', (int)($lead['created_at'] ?? time()));
    $updatedAt = date('Y-m-d H:i:s', (int)($lead['updated_at'] ?? time()));
    $userId = $lead['responsible_user_id'] ?? null;
    $userName = "Пользователь ID: {$userId}"; // Можно заменить на имя через API

    if ($eventType === 'create') {
        return "Создана новая сделка:\n" .
               "Название: " . ($lead['name'] ?? '-') . "\n" .
               "Ответственный: {$userName}\n" .
               "Время: {$createdAt}";
    }

    if ($eventType === 'update') {
        $text = "Обновление сделки:\n";

        $changedFields = [];
        $fieldMap = [
            'name' => 'Название',
            'price' => 'Цена',
            'status_id' => 'Статус ID',
            'pipeline_id' => 'Воронка ID',
            'responsible_user_id' => 'Ответственный ID'
        ];

        foreach ($fieldMap as $field => $label) {
            if (isset($lead[$field])) {
                $changedFields[] = "{$label}: {$lead[$field]}";
            }
        }

        if (!empty($changedFields)) {
            $text .= implode("\n", $changedFields) . "\n";
        }

        $text .= "Время: {$updatedAt}";
        return $text;
    }

    return null;
}
