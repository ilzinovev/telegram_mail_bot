<?php

namespace Mail;

use DOMDocument;

class MailParser
{

    public static function mailClean($message, $parseTableList)
    {
        $output = strip_tags(
            str_replace('<br />', "\n",
                str_replace('</div>', "\n",
                    MailParser::htmlParseTable($message->getBodyHtml() . $message->getBodyText(), $message, $parseTableList))
            ));
        $remove_text = [
            '-------- Пересылаемое сообщение --------',
            '-------- Конец пересылаемого сообщения --------',
            'Данное письмо сформировано автоматически'
        ];
        foreach ($remove_text as $item) {
            $output = str_replace($item, '', $output);
        }
        return $output;
    }

    public static function htmlParseTable($html, $message, $parseTableList)
    {
        if (!empty($html) and isset($parseTableList)) {
            $address = $message->getFrom()->getAddress();
            $subject = $message->getSubject();
            foreach ($parseTableList as $item) {
                if ($address == $item[0] and (mb_strripos($subject, $item[1]) or mb_strripos($subject, $item[1]) === 0)) {
                    $html = $message->getBodyHtml();
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

    public static function fivepostParseMail($content)
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

    public static function cdekParseMail($content)
    {
        $replace_nbsp = [
            '&nbsp;', PHP_EOL
        ];
        $content = strip_tags(str_replace($replace_nbsp, "", $content));
        $message_start_pos = strpos($content, "Уважаемый клиент!");
        if ($message_start_pos) {
            return substr($content, $message_start_pos);
        }
        return '';
    }
}