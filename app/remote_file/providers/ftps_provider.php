<?php

declare(strict_types=1);

final class FtpsProvider extends FtpProvider
{
    protected function transportProtocol(): string
    {
        return 'ftps';
    }
}
