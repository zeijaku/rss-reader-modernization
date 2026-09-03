<?php

declare(strict_types=1);

/** @return array{read:string,change:string} */
function remote_service_permission_capabilities(int $ownerId, int $connectionId): array
{
    $provider = remote_service_provider($ownerId, $connectionId);
    if (!$provider instanceof RemotePermissionProvider) {
        return ['read' => 'unsupported', 'change' => 'unsupported'];
    }
    return $provider->permissionCapabilities();
}

function remote_service_chmod(int $ownerId, int $connectionId, string $relativePath, string $mode): void
{
    if (remote_path_has_control_characters($relativePath)) {
        throw new AppRemoteValidationException('invalid_path');
    }
    $path = remote_path_normalize_relative($relativePath);
    $normalizedMode = remote_permission_normalize_mode($mode);
    if ($path === null || $path === '/') {
        throw new AppRemoteValidationException('invalid_path');
    }
    if ($normalizedMode === null) {
        throw new AppRemoteValidationException('invalid_mode');
    }

    $provider = remote_service_provider($ownerId, $connectionId);
    if (!$provider instanceof RemotePermissionProvider) {
        throw new AppRemoteTransportException('chmod_unsupported');
    }

    // Never trust a browser-supplied type. Resolve the path on the server and
    // reject symbolic links/unknown components before changing permissions.
    remote_service_assert_safe_path($provider, $path, false, false);
    $provider->chmod($path, $normalizedMode);
}
