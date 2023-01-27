<?php

namespace Mail;

use TelegramBot\Api\BotApi;
use SplFileInfo;

class MailSender
{
    public function __construct($telegram_token, $chat_id, $use_attachments)
    {
        $this->telegram_bot = new BotApi($telegram_token);
        $this->telegram_bot->setCurlOption(CURLOPT_TIMEOUT, 15);
        $this->chat_id = $chat_id;
        $this->use_attachments = $use_attachments;
    }

    public function sendMailWithValidAdress(&$emails, $valid_address)
    {
        foreach ($emails as $key => $email) {
            if (in_array($email['mail'], $valid_address)) {
                $this->sendMailToTelegram($email);
                sleep(1);
                unset($emails[$key]);
            }
        }
    }

    public function sendMailWithValidSubject($emails, $valid_subject)
    {
        foreach ($emails as $email) {
            foreach ($valid_subject as $subject) {
                if (mb_strripos($email['subject'], $subject) or mb_strripos($email['subject'], $subject) === 0)
                    $this->sendMailToTelegram($email);
                sleep(1);
            }
        }
    }

    protected function sendMailToTelegram($email)
    {
        $message = "<b>Новое письмо</b>\n";
        $message .= "От: <b> {$email['mail']}</b>\n";
        $message .= "Тема: {$email['subject']}\n";
        $message .= "Содержимое письма:\n\n {$email['html']}";
        if (mb_strlen($message) > 4096)
            $message = mb_substr($message, 0, 4096);
        $this->telegram_bot->sendMessage($this->chat_id, $message, 'HTML');
        if ($this->use_attachments)
            $this->sendAttachmentsToTelegram($email['attachments'], $email['mail']);
    }

    protected function sendAttachmentsToTelegram($attachments, $email)
    {
        $count = 0;
        foreach ($attachments as $attachment) {
            if (!empty($attachment->getFilename())) {
                sleep(1);
                $file_path = ROOT . '/tmp/' . str_replace('/', '', $attachment->getFilename());
                file_put_contents($file_path, $attachment->getDecodedContent());
                $file_extension = new SplFileInfo($file_path);
                if (!in_array($file_extension->getExtension(), ['jpg', 'jpeg', 'png'])) {
                    $document = new \CURLFile($file_path);
                    $this->telegram_bot->sendDocument($this->chat_id, $document, 'файл из письма от ' . $email);
                    $count++;
                    if ($count == 20)
                        sleep(40);
                }
                array_map('unlink', array_filter((array)glob(ROOT . '/tmp/*')));
            }
        }
    }
}