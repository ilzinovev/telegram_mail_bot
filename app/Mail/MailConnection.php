<?php

namespace Mail;

use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Server;
use DateTimeImmutable;

class  MailConnection
{
    public function __construct($hostname, $user, $password, $folder)
    {
        $this->server = new Server($hostname);
        $this->connection = $this->server->authenticate($user, $password);
        $this->folder = $folder;

    }

    public function __desctruct()
    {
        $this->connection->close();
    }

    public function getMessages()
    {

        $this->mailbox = $this->connection->getMailbox($this->folder);
        $this->date = new DateTimeImmutable(date('Y-m-d', time()));
        $this->search = new SearchExpression();
        $this->search->addCondition(new Since($this->date));
        $this->messages = $this->mailbox->getMessages($this->search);
        return $this->messages;
    }


}
