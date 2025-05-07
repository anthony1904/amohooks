<?php

function log_message($message, $context = [])
{
    $logFile = __DIR__ . '/../storage/logs/webhook.log';
    $date = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
    file_put_contents($logFile, "[$date] $message\n$contextStr\n\n", FILE_APPEND);
}