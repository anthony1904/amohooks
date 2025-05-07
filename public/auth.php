<?php

session_start();

$_ENV = parse_ini_file(__DIR__ . '/../.env');

// Генерируем безопасный state-параметр и сохраняем его в сессии
$state = bin2hex(random_bytes(16));
$_SESSION['amo_state'] = $state;

// Формируем ссылку авторизации
$query = http_build_query([
    'client_id' => $_ENV['AMO_CLIENT_ID'],
    'state'     => $state,
    'mode'      => 'post_message', // или 'popup' — зависит от UI-интеграции
]);

header("Location: https://www.amocrm.ru/oauth?{$query}");
exit;