<?php

declare(strict_types=1);

final class AppRemoteValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('Remote File Manager validation failed.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

final class AppRemoteCredentialException extends RuntimeException
{
}

final class AppRemoteTransportException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = 'Remote file operation failed.'
    ) {
        parent::__construct($message);
    }
}
