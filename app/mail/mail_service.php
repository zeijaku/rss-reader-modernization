<?php

declare(strict_types=1);

/** @return list<array<string,mixed>> */
function mail_service_list_accounts(int $ownerId): array
{
    return mail_account_list($ownerId);
}

/** @return array<string,mixed> */
function mail_service_create_account(int $ownerId, array $input): array
{
    return mail_account_create($ownerId, $input);
}

/** @return array<string,mixed>|null */
function mail_service_update_account(int $ownerId, int $accountId, array $input): ?array
{
    return mail_account_update($ownerId, $accountId, $input);
}

function mail_service_delete_account(int $ownerId, int $accountId): bool
{
    return mail_account_delete($ownerId, $accountId);
}

/** @return array{ok:bool,code:string} */
function mail_service_test_account(int $ownerId, int $accountId): array
{
    $account = mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled'];
    }

    try {
        $password = mail_crypto_decrypt(
            $ownerId,
            $accountId,
            (string) ($account['mail_account_secret'] ?? '')
        );
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }

    try {
        return mail_client_test_credentials([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}
