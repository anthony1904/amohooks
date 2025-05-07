<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/logger.php';

use Dotenv\Dotenv;
use App\AmoClient;

// Загрузка переменных окружения
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

log_message('Сырой $_POST', $_POST);

$account  = $_POST['account'] ?? null;
$leads    = $_POST['leads'] ?? [];
$contacts = $_POST['contacts'] ?? [];

log_message('Получен входящий вебхук', [
    'account'  => $account,
    'leads'    => $leads,
    'contacts' => $contacts,
]);

// Обработка сделок
if (!empty($leads['add'])) {
    log_message('Тип события', ['type' => 'lead_create']);
    processEntities($leads['add'], 'leads', 'create');
}
if (!empty($leads['update'])) {
    log_message('Тип события', ['type' => 'lead_update']);
    processEntities($leads['update'], 'leads', 'update');
}

// Обработка контактов
if (!empty($contacts['add'])) {
    log_message('Тип события', ['type' => 'contact_create']);
    processEntities($contacts['add'], 'contacts', 'create');
}
if (!empty($contacts['update'])) {
    log_message('Тип события', ['type' => 'contact_update']);
    processEntities($contacts['update'], 'contacts', 'update');
}

function processEntities(array $entities, string $entityType, string $eventType): void
{
    $amo = new AmoClient();

    foreach ($entities as $entity) {
        $entityId = (int)($entity['id'] ?? 0);
        if (!$entityId) {
            log_message('Нет ID сущности, пропускаем', $entity);
            continue;
        }

        $noteText = generateNoteText($entity, $entityType, $eventType);
        if ($noteText) {
            log_message('Добавляем примечание', [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'text'        => $noteText,
            ]);

            try {
                $amo->addNote($entityId, $entityType, $noteText);
            } catch (\Throwable $e) {
                log_message('Ошибка при добавлении примечания', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

function generateNoteText(array $entity, string $entityType, string $eventType): ?string
{
    $createdAt = !empty($entity['created_at']) ? date('Y-m-d H:i:s', (int)$entity['created_at']) : '-';
    $updatedAt = !empty($entity['updated_at']) ? date('Y-m-d H:i:s', (int)$entity['updated_at']) : '-';
    $userId = $entity['responsible_user_id'] ?? '-';
    $userName = "Пользователь ID: {$userId}";

    if ($entityType === 'leads') {
        if ($eventType === 'create') {
            return "Создана новая сделка:\n" .
                "Название: " . ($entity['name'] ?? '-') . "\n" .
                "Ответственный: {$userName}\n" .
                "Время: {$createdAt}";
        }

        if ($eventType === 'update') {
            $changedFields = [];
            $fieldMap = [
                'name' => 'Название',
                'price' => 'Цена',
                'status_id' => 'Статус ID',
                'pipeline_id' => 'Воронка ID',
                'responsible_user_id' => 'Ответственный ID',
            ];

            foreach ($fieldMap as $field => $label) {
                if (isset($entity[$field])) {
                    $changedFields[] = "{$label}: {$entity[$field]}";
                }
            }

            return "Обновление сделки:\n" .
                (!empty($changedFields) ? implode("\n", $changedFields) . "\n" : '') .
                "Время: {$updatedAt}";
        }
    }

    if ($entityType === 'contacts') {
        $name = $entity['name'] ?? '-';
        $company = $entity['company_name'] ?? '-';
        $companyId = $entity['linked_company_id'] ?? '-';
        $phone = '-';

        if (!empty($entity['custom_fields'])) {
            foreach ($entity['custom_fields'] as $field) {
                if (isset($field['code']) && $field['code'] === 'PHONE') {
                    $phone = $field['values'][0]['value'] ?? '-';
                    break;
                }
            }
        }

        if ($eventType === 'create') {
            return "Создан новый контакт:\n" .
                "Имя: {$name}\n" .
                "Компания: {$company} (ID: {$companyId})\n" .
                "Телефон: {$phone}\n" .
                "Ответственный: {$userName}\n" .
                "Время создания: {$createdAt}";
        }

        if ($eventType === 'update') {
            return "Обновление контакта:\n" .
                "Имя: {$name}\n" .
                "Компания: {$company} (ID: {$companyId})\n" .
                "Телефон: {$phone}\n" .
                "Ответственный: {$userName}\n" .
                "Время обновления: {$updatedAt}";
        }
    }

    return null;
}
