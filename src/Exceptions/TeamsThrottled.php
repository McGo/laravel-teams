<?php

namespace MirkoHaaser\Teams\Exceptions;

/** Microsoft answered 429. Jobs should release themselves for retryAfter seconds. */
class TeamsThrottled extends TeamsRequestFailed
{
    public function __construct(string $message, public readonly int $retryAfter = 30, string $body = '')
    {
        parent::__construct($message, 429, $body);
    }
}
