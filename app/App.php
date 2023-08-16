<?php

use Mail\MailConnection;
use Mail\MailFilter;
use Mail\MailSender;


class App
{

    private $telegram_token;
    private $chat_id;

    public function __construct(
        $app_name,
        $hostname,
        $user,
        $password,
        $telegram_token,
        $chat_id,
        $valid_mails,
        $valid_subjects,
        $stop_list,
        $is_parse_table,
        $use_attachments = false,
        $folders)
    {
        $this->hostname = $hostname;
        $this->user = $user;
        $this->password = $password;
        $this->app_name = $app_name;
        $this->chat_id = $chat_id;
        $this->valid_mails = $valid_mails;
        $this->valid_subjects = $valid_subjects;
        $this->stop_list = $stop_list;
        $this->is_parse_table = $is_parse_table;
        $this->use_attachments = $use_attachments;
        $this->telegram_token = $telegram_token;
        $this->folders = $folders;
    }

    public function run()
    {
        $folders = ['INBOX', 'INBOX.ДАЛЛИ', 'INBOX.ДПД'];
        foreach ($folders as $folder) {
            $mailConnector = new MailConnection($this->hostname, $this->user, $this->password, $folder);
            $this->messages = $mailConnector->getMessages();
            if ($this->messages->count() > 0) {
                $emails = new MailFilter($this->stop_list, $this->is_parse_table, $this->app_name);
                $emails = $emails->mailsPrepare($this->messages, $folder);
                if (!empty($emails)) {
                    $mailSender = new MailSender($this->telegram_token, $this->chat_id, $this->use_attachments);
                    if (isset($this->valid_mails))
                        $mailSender->sendMailWithValidAdress($emails, $this->valid_mails);
                    if (isset($this->valid_subjects))
                        $mailSender->sendMailWithValidSubject($emails, $this->valid_subjects);
                }
            }
        }
    }

}