<?php

use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Date\Since;
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
    private $stop_list;
    private $is_parse_table;
    private $message;
    private $use_attachments;

    public function __construct($app_name, $hostname, $user, $password, $telegram_token, $chat_id, $valid_mails, $valid_subjects, $stop_list, $is_parse_table, $use_attachments = false)
    {
        $this->app_name = $app_name;
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
        $this->stop_list = $stop_list;
        $this->is_parse_table = $is_parse_table;
        $this->use_attachments = $use_attachments;
    }

    public function run()
    {
        if ($this->messages->count() > 0) {
            $emails = $this->mail_prepare($this->messages);
            if (!empty($emails)) {
                if (isset($this->valid_mails))
                    $this->send_mail_with_valid_address($emails, $this->valid_mails);
                if (isset($this->valid_subjects))
                    $this->send_mail_with_valid_subject($emails, $this->valid_subjects);
            }
        }
        $this->connection->close();
    }

    public function mail_prepare($messages)
    {
        foreach ($messages as $key => $message) {
            if (!$this->check_id((string)$message->getNumber()) or !$this->check_stop_list($message))
                continue;
            $this->message = $message;
            $emails[$key]['id'] = $message->getNumber();
            $emails[$key]['subject'] = $message->getSubject();
            $emails[$key]['mail'] = $message->getFrom()->getAddress();
            switch ($emails[$key]['mail']) {
                case '5post_cs@x5.ru':
                    $emails[$key]['html'] = $this->fivepost_parse_mail($message->getBodyHtml());
                    break;

                default:
                    $emails[$key]['html'] = $this->mail_clean(
                        strip_tags(
                            str_replace('<br />', "\n",
                                str_replace('</div>', "\n",
                                    $this->html_parse_table($message->getBodyHtml() . $message->getBodyText()))
                            )));
                    break;
            }
            $emails[$key]['attachments'] = $message->getAttachments();
        }

        if (!empty($emails))
            return $emails;
        else
            return false;
    }

    public function check_id($id)
    {
        $last_id_file_path = ROOT . '/app/' . $this->app_name . '_last_id.txt';
        $last_id = (int)file_get_contents($this->app_name . '_last_id.txt', $last_id_file_path);
        if (empty($last_id))
            file_put_contents($last_id_file_path, $id);
        if ($last_id < $id) {
            file_put_contents($last_id_file_path, $id);
            return true;
        } else
            return false;
    }

    public function check_stop_list($message)
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

    public function send_mail_with_valid_address(&$emails, $valid_address)
    {
        foreach ($emails as $key => $email) {
            if (in_array($email['mail'], $valid_address)) {
                $this->send_mail_to_telegram($email);
                sleep(1);
                unset($emails[$key]);
            }
        }
    }

    public function send_mail_with_valid_subject($emails, $valid_subject)
    {
        foreach ($emails as $email) {
            foreach ($valid_subject as $subject) {
                if (mb_strripos($email['subject'], $subject) or mb_strripos($email['subject'], $subject) === 0)
                    $this->send_mail_to_telegram($email);
                sleep(1);
            }
        }
    }

    public function send_mail_to_telegram($email)
    {
        $message = "<b>Новое письмо</b>\n";
        $message .= "От: <b> {$email['mail']}</b>\n";
        $message .= "Тема: {$email['subject']}\n";
        $message .= "Содержимое письма:\n\n {$email['html']}";
        if (mb_strlen($message) > 4096)
            $message = mb_substr($message, 0, 4096);
        $this->telegram_bot->sendMessage($this->chat_id, $message, 'HTML');
        if ($this->use_attachments)
            $this->send_attachments_to_telegram($email['attachments'], $email['mail']);
    }

    public function send_attachments_to_telegram($attachments, $email)
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

    public function html_parse_table($html)
    {
        if (!empty($html) and isset($this->is_parse_table)) {
            $address = $this->message->getFrom()->getAddress();
            $subject = $this->message->getSubject();
            foreach ($this->is_parse_table as $item) {
                if ($address == $item[0] and (mb_strripos($subject, $item[1]) or mb_strripos($subject, $item[1]) === 0)) {
                    $html = $this->message->getBodyHtml();
                    $dom = new DOMDocument();
                    $source = mb_convert_encoding($html, 'HTML-ENTITIES', 'utf-8');
                    $dom->loadHTML($source);
                    $tables = $dom->getElementsByTagName('tr');
                    if ($tables->length > 0) {
                        foreach ($tables as $key => $table)
                            foreach ($table->childNodes as $child) {
                                $data[$key][] = $child->textContent;
                                $child->textContent = '';
                            }
                        $output = array();
                        $headers = $data[0];
                        unset($data[0]);
                        foreach ($data as $k => $item)
                            foreach ($item as $j => $t)
                                $output[$k][] = $headers[$j] . ' : ' . $t;

                        $message = '';
                        foreach ($output as $item) {
                            $message .= '-----------' . "\n";
                            foreach ($item as $text)
                                $message .= $text . "\n";
                        }
                        return $message;
                    }
                }
            }
        }
        return $html;
    }

    public function fivepost_parse_mail($content)
    {
        $clean_list = [
            "&laquo;", "&raquo;", "&quot;",
            "Это сообщение и любые документы, приложенные к нему, содержат информацию, составляющую коммерческую тайну ООО Корпоративный центр ИКС 5, 109029, г. Москва, ул. Средняя Калитниковская, д.28, стр.4.",
            "Если это сообщение не предназначено Вам, настоящим уведомляем Вас о том, что использование, копирование, распространение информации, содержащейся в настоящем сообщении, а также осуществление любых действий на основе этой информации, строго запрещено. Если",
            "Вы получили это сообщение по ошибке, пожалуйста, сообщите об этом отправителю по электронной почте и удалите это сообщение.",
            "This message and any documents attached to it contain information that constitutes a commercial secret of LLC Corporate Center X5, 109029, Moscow, Srednaya Kalitnikovskaya str., 28, p. 4. If you",
            "are not an addressee or otherwise authorized to receive this message, you should not use, copy, disclose or take any action based on this e-mail or any information contained in the message. If you have received this material in error, please advise the sender",
            "immediately by reply e-mail and delete this message."
        ];
        $replace_nbsp = [
            '&nbsp;', PHP_EOL
        ];
        $content = str_replace($clean_list, '', $content);

        $dom = new domDocument;
        libxml_use_internal_errors(true);
        $source = mb_convert_encoding($content, 'HTML-ENTITIES', 'utf-8');
        $dom->loadHTML($source);
        $tables = $dom->getElementsByTagName('tr');
        if ($tables->length > 0) {
            foreach ($tables as $key => $table)
                foreach ($table->childNodes as $child) {
                    $data[$key][] = $child->textContent;
                    $child->textContent = '';
                }
            $output = array();
            $headers = $data[0];
            unset($data[0]);

            foreach ($data as $k => $item) {
                $item = array_unique($item);
                foreach ($item as $j => $t) {
                    $output[$k][] = str_replace(PHP_EOL, '', $headers[$j] . ": " . $t);
                }
            }


            $message = '';
            foreach ($output as $item) {
                $message .= '-----------' . "\n";
                foreach ($item as $text) {
                    $message .= $text . "\n";
                }
            }

            return $message;
        }

        libxml_clear_errors();

        $content = strip_tags(
            str_replace($replace_nbsp, " ", $content)
        );

        return $content;
    }

}