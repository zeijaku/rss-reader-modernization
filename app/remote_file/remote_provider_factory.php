<?php

declare(strict_types=1);

/** @param array<string,mixed> $connection @param array<string,string> $credentials @param array<string,mixed> $target */
function remote_provider_create(array $connection, array $credentials, array $target, ?callable $transport = null): RemoteFileProvider
{
    $protocol = (string) ($connection['remote_connection_protocol'] ?? '');
    return match ($protocol) {
        'ftp' => new FtpProvider($connection, $credentials, $target, $transport),
        'ftps' => new FtpsProvider($connection, $credentials, $target, $transport),
        'sftp' => new SftpProvider($connection, $credentials, $target, $transport),
        'webdav' => new WebDavProvider($connection, $credentials, $target, $transport),
        default => throw new AppRemoteValidationException('invalid_protocol'),
    };
}
