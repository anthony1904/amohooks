<?php

session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use App\AmoAuth;

$_ENV = parse_ini_file(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/logger.php';

if (!isset($_GET['code'], $_GET['state'])) {
    die('Missing parameters');
}

if (!isset($_SESSION['amo_state']) || $_GET['state'] !== $_SESSION['amo_state']) {
    die('State mismatch — possible CSRF attack');
}

$auth = new AmoAuth();
$auth->getInitialTokens($_GET['code']);

echo "Токены успешно получены и сохранены.";