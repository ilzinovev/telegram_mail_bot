<?php

namespace Mail;

use Ddeboer\Imap\MessageIterator;


class MailFilter
{
    public function __construct($stop_list, $parse_table_list, $app_name)
    {
        $this->stop_list = $stop_list;
        $this->is_parse_table = $parse_table_list;
        $this->app_name = $app_name;

    }

    public function mailsPrepare(MessageIterator $messages)
    {
        foreach ($messages as $key => $message) {
            if (!$this->checkId((string)$message->getNumber()) or !$this->checkStopList($message))
                continue;
            $emails[$key]['id'] = $message->getNumber();
            $emails[$key]['subject'] = $message->getSubject();
            $emails[$key]['mail'] = $message->getFrom()->getAddress();
            switch ($emails[$key]['mail']) {
                case '5post_cs@x5.ru':
                    $emails[$key]['html'] = MailParser::fivepostParseMail($message->getBodyHtml());
                    break;
                case 'noreply@cdek.ru':
                    $emails[$key]['html'] = MailParser::cdekParseMail($message->getBodyHtml());
                    break;

                default:
                    $emails[$key]['html'] = MailParser::mailClean($message, $this->is_parse_table);
                    break;
            }
            $emails[$key]['attachments'] = $message->getAttachments();
        }

        if (!empty($emails))
            return $emails;
        else
            return false;
    }

    protected function checkId($id)
    {
        $last_id_file_path = ROOT . '/app/' . $this->app_name . '_last_id.txt';
        $last_id = (int)file_get_contents(ROOT . '/app/' . $this->app_name . '_last_id.txt', $last_id_file_path);
        if (empty($last_id))
            file_put_contents($last_id_file_path, $id);
        if ($last_id < $id) {
            file_put_contents($last_id_file_path, $id);
            return true;
        } else
            return false;
    }

    protected function checkStopList($message)
    {
        if (isset($this->stop_list)) {
            $address = $message->getFrom()->getAddress();
            $subject = $message->getSubject();
            foreach ($this->stop_list as $item) {
                if (mb_strripos($subject, 'Re:') or mb_strripos($subject, 'Re:') === 0)
                    return false;
                if ($address == $item[0] and (mb_strripos($subject, $item[1]) or mb_strripos($subject, $item[1]) === 0))
                    return false;
            }
        }
        return true;
    }


}