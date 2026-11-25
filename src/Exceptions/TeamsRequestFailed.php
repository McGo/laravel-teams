<?php

namespace MirkoHaaser\Teams\Exceptions;

use RuntimeException;

/** An outgoing call to Graph or the Bot Connector failed. */
class TeamsRequestFailed extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly string $body = '')
    {
        parent::__construct($message.($status ? " (HTTP {$status})" : ''));
    }
}
