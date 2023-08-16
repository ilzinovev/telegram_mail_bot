<?php
require_once 'const.php';
require_once 'call_center_mail_const.php';
require_once 'deliveries_mail_const.php';
require_once 'vendor/autoload.php';


$app_call_center = new App(
    'call_center',
    HOSTNAME,
    USERNAME,
    PASSWORD,
    TELEGRAM_TOKEN,
    TELEGRAM_CHAT_ID,
    VALID_MAILS,
    VALID_SUBJECTS,
    IGNORE_LIST,
    IS_PARSE_TABLE,
    true,
    ['INBOX', 'INBOX.ДАЛЛИ', 'INBOX.ДПД']
);
$app_call_center->run();


$app_deliveries = new App(
    'delivery_chat',
    HOSTNAME,
    USERNAME,
    PASSWORD,
    TELEGRAM_TOKEN,
    DELIVERY_TELEGRAM_CHAT_ID,
    DELIVERY_VALID_MAILS,
    null,
    null,
    null,
    false,
    ['INBOX', 'INBOX.ДАЛЛИ', 'INBOX.ДПД']
);
$app_deliveries->run();



