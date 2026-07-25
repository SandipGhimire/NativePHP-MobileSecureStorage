<?php

namespace Sandip\SecureStorage\Native;

use InvalidArgumentException;

class SecureStorage
{
    protected const MAX_KEY_LENGTH = 255;

    protected const MAX_VALUE_LENGTH = 8192;

    public function set(string $key, ?string $value): bool
    {
        $this->validateKey($key);

        if ($value !== null) {
            $this->validateValue($value);
        }

        if (! function_exists('nativephp_call')) {
            return false;
        }

        $decoded = $this->call('Vault.Set', ['key' => $key, 'value' => $value]);

        return $decoded !== null && ($decoded['success'] ?? false) === true;
    }

    public function get(string $key): ?string
    {
        $this->validateKey($key);

        if (! function_exists('nativephp_call')) {
            return null;
        }

        $decoded = $this->call('Vault.Get', ['key' => $key]);
        $value = $decoded['value'] ?? null;

        return ($value === null || $value === '') ? null : $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);

        if (! function_exists('nativephp_call')) {
            return false;
        }

        return $this->call('Vault.Delete', ['key' => $key]) !== null;
    }

    protected function call(string $function, array $params): ?array
    {
        $result = nativephp_call($function, json_encode($params));

        if ($result === null) {
            return null;
        }

        $decoded = json_decode($result, true);

        if (! is_array($decoded) || ($decoded['status'] ?? null) === 'error') {
            return null;
        }

        return $decoded;
    }

    protected function validateKey(string $key): void
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('SecureStorage key must not be empty.');
        }

        if (strlen($key) > static::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException(
                'SecureStorage key must not exceed '.static::MAX_KEY_LENGTH.' characters.'
            );
        }
    }

    protected function validateValue(string $value): void
    {
        if (strlen($value) > static::MAX_VALUE_LENGTH) {
            throw new InvalidArgumentException(
                'SecureStorage value must not exceed '.static::MAX_VALUE_LENGTH.' bytes.'
            );
        }
    }
}
