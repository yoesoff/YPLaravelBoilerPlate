<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class EmailDispatchException extends RuntimeException
{
    public function __construct(string $phase, string $message = "Email dispatch failed")
    {
        parent::__construct($message . " ({$phase})");
    }
}
