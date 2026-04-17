<?php

namespace App\Services\Workflow\DAG\Exceptions;

class StepExecutionException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $stepKey,
        public readonly bool $retryable = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
