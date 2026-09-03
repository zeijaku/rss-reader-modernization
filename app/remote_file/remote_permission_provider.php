<?php

declare(strict_types=1);

interface RemotePermissionProvider
{
    /** @return array{read:string,change:string} */
    public function permissionCapabilities(): array;

    public function chmod(string $relativePath, string $mode): void;
}

function remote_permission_normalize_mode(mixed $mode): ?string
{
    if (!is_string($mode) || preg_match('/\A[0-7]{3}\z/D', $mode) !== 1) {
        return null;
    }
    return $mode;
}
