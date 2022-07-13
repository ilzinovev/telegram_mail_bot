<?php
require_once 'const.php';
require_once 'vendor/autoload.php';

$app = new App(
    HOSTNAME,
    USERNAME,
    PASSWORD,
    TELEGRAM_TOKEN,
    TELEGRAM_CHAT_ID,
    VALID_MAILS,
    VALID_SUBJECTS
);
$app->run();

