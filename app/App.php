<?php

use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\Search\State\NewMessage;
use TelegramBot\Api\BotApi;

class App
{
    private $server;
    private $connection;
    private $mailbox;
    private $date;
    private $search;
    private $telegram_bot;
    private $chat_id;
    private $valid_mails;
    private $valid_subjects;

    public function __construct($hostname, $user, $password, $telegram_token, $chat_id, $valid_mails, $valid_subjects)
    {
        $this->server = new Server($hostname);
        $this->connection = $this->server->authenticate($user, $password);
        $this->mailbox = $this->connection->getMailbox('INBOX');
        $this->date = new DateTimeImmutable(date('Y-m-d', time()));
        $this->search = new SearchExpression();
        $this->search->addCondition(new Since($this->date));
        $this->messages = $this->mailbox->getMessages($this->search);
        $this->telegram_bot = new BotApi($telegram_token);
        $this->telegram_bot->setCurlOption(CURLOPT_TIMEOUT, 15);
        $this->chat_id = $chat_id;
        $this->valid_mails = $valid_mails;
        $this->valid_subjects = $valid_subjects;
    }

    public function run()
    {
        if ($this->messages->count() > 0) {
            $emails = $this->mail_prepare($this->messages);
            if (!empty($emails)) {
                $this->send_mail_with_valid_address($emails, $this->valid_mails);
                $this->send_mail_with_valid_subject($emails, $this->valid_subjects);
            }
        }
        $this->connection->close();
    }

    public function mail_prepare($messages)
    {
        foreach ($messages as $key => $message) {
            if (!$this->check_id((string)$message->getNumber()))
                continue;
            $emails[$key]['id'] = $message->getNumber();
            $emails[$key]['subject'] = $message->getSubject();
            $emails[$key]['mail'] = $message->getFrom()->getAddress();
            $emails[$key]['html'] = $this->mail_clean(
                strip_tags(
                    $message->getBodyHtml() . $message->getBodyText()
                ));
            $emails[$key]['attachments'] = $message->getAttachments();
        }
        if (!empty($emails))
            return $emails;
        else
            return false;
    }

    public function check_id($id)
    {
        $last_id_file_path = ROOT . '/app/last_id.txt';
        $last_id = (int)file_get_contents('last_id.txt', $last_id_file_path);
        if (empty($last_id))
            file_put_contents($last_id_file_path, $id);
        if ($last_id < $id) {
            file_put_contents($last_id_file_path, $id);
            return true;
        } else
            return false;
    }

    public function send_mail_with_valid_address(&$emails, $valid_address)
    {
        foreach ($emails as $key => $email) {
            if (in_array($email['mail'], $valid_address))
                $this->send_mail_to_telegram($email);
            unset($emails[$key]);
        }
    }

    public function send_mail_with_valid_subject($emails, $valid_subject)
    {
        foreach ($emails as $email) {
            foreach ($valid_subject as $subject) {
                if (strripos($email['subject'], $subject))
                    $this->send_mail_to_telegram($email);
            }
        }
    }

    public function send_mail_to_telegram($email)
    {

        $message = "<b>Новое письмо</b>\n";
        $message .= "От: <b> {$email['mail']}</b>\n";
        $message .= "Тема: {$email['subject']}\n";
        $message .= "Содержимое письма:\n\n {$email['html']}";
        $this->telegram_bot->sendMessage($this->chat_id, $message, 'HTML');
        $this->send_attachments_to_telegram($email['attachments'], $email['mail']);
    }

    public function send_attachments_to_telegram($attachments, $email)
    {
        foreach ($attachments as $attachment) {
            if (!empty($attachment->getFilename())) {
                $file_path = ROOT . '/tmp/' . str_replace('/', '', $attachment->getFilename());
                file_put_contents($file_path, $attachment->getDecodedContent());
                $document = new \CURLFile($file_path);
                $this->telegram_bot->sendDocument($this->chat_id, $document, 'файл из письма от ' . $email);
                array_map('unlink', array_filter((array)glob(ROOT . '/tmp/*')));
            }
        }
    }

    public function mail_clean($message)
    {
        $remove_text = [
            '-------- Пересылаемое сообщение --------',
            '-------- Конец пересылаемого сообщения --------',
            'Данное письмо сформировано автоматически'
        ];
        foreach ($remove_text as $item) {
            $message = str_replace($item, '', $message);
        }
        return $message;
    }
}