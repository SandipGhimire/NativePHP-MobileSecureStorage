<?php

use Sandip\SecureStorage\Native\SecureStorage;

beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifestPath = $this->pluginPath.'/nativephp.json';
});

describe('Plugin Manifest', function () {
    it('has a valid nativephp.json file', function () {
        expect(file_exists($this->manifestPath))->toBeTrue();

        $content = file_get_contents($this->manifestPath);
        json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });

    it('has required fields', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest)->toHaveKeys(['name', 'namespace', 'bridge_functions']);
        expect($manifest['name'])->toBe('sghimire/mobile-secure-storage');
        expect($manifest['namespace'])->toBe('SecureStorage');
    });

    it('registers its own Vault.* bridge functions, distinct from the core SecureStorage.* contract', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        $names = array_column($manifest['bridge_functions'], 'name');

        expect($names)->toBe(['Vault.Set', 'Vault.Get', 'Vault.Delete']);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function)->toHaveKeys(['name']);
            expect(isset($function['android']) || isset($function['ios']))->toBeTrue();
        }
    });
});

describe('Native Code', function () {
    it('has Android Kotlin file', function () {
        $kotlinFile = $this->pluginPath.'/resources/android/SecureStorageFunctions.kt';

        expect(file_exists($kotlinFile))->toBeTrue();

        $content = file_get_contents($kotlinFile);
        expect($content)->toContain('package com.sandip.plugins.secure_storage');
        expect($content)->toContain('object SecureStorageFunctions');
        expect($content)->toContain('BridgeFunction');
    });

    it('has iOS Swift file', function () {
        $swiftFile = $this->pluginPath.'/resources/ios/SecureStorageFunctions.swift';

        expect(file_exists($swiftFile))->toBeTrue();

        $content = file_get_contents($swiftFile);
        expect($content)->toContain('enum SecureStorageFunctions');
        expect($content)->toContain('BridgeFunction');
    });

    it('has matching bridge function classes in native code', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/SecureStorageFunctions.kt');
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/SecureStorageFunctions.swift');

        foreach ($manifest['bridge_functions'] as $function) {
            if (isset($function['android'])) {
                $parts = explode('.', $function['android']);
                $className = end($parts);
                expect($kotlinContent)->toContain("class {$className}");
            }

            if (isset($function['ios'])) {
                $parts = explode('.', $function['ios']);
                $className = end($parts);
                expect($swiftContent)->toContain("class {$className}");
            }
        }
    });

    it('validates key length and value size natively before touching the keystore/keychain', function () {
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/SecureStorageFunctions.kt');
        $swiftContent = file_get_contents($this->pluginPath.'/resources/ios/SecureStorageFunctions.swift');

        foreach ([$kotlinContent, $swiftContent] as $content) {
            expect($content)->toContain('KEY_REQUIRED');
            expect($content)->toContain('KEY_TOO_LONG');
            expect($content)->toContain('VALUE_TOO_LARGE');
        }
    });
});

describe('PHP Classes', function () {
    it('has service provider', function () {
        $file = $this->pluginPath.'/src/SecureStorageServiceProvider.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Sandip\SecureStorage\Native');
        expect($content)->toContain('class SecureStorageServiceProvider');
    });

    it('has facade', function () {
        $file = $this->pluginPath.'/src/Facades/SecureStorage.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Sandip\SecureStorage\Native\Facades');
        expect($content)->toContain('class SecureStorage extends Facade');
    });

    it('has main implementation class', function () {
        expect(file_exists($this->pluginPath.'/src/SecureStorage.php'))->toBeTrue();
    });
});

describe('SecureStorage manager', function () {
    it('rejects an empty key', function () {
        (new SecureStorage)->set('', 'value');
    })->throws(InvalidArgumentException::class);

    it('rejects a whitespace-only key', function () {
        (new SecureStorage)->set('   ', 'value');
    })->throws(InvalidArgumentException::class);

    it('rejects a key over 255 characters', function () {
        (new SecureStorage)->get(str_repeat('a', 256));
    })->throws(InvalidArgumentException::class);

    it('accepts a key at the 255 character limit', function () {
        expect(fn () => (new SecureStorage)->get(str_repeat('a', 255)))->not->toThrow(InvalidArgumentException::class);
    });

    it('rejects a value over 8192 bytes', function () {
        (new SecureStorage)->set('key', str_repeat('a', 8193));
    })->throws(InvalidArgumentException::class);

    it('allows a null value (delete) regardless of size limits', function () {
        expect(fn () => (new SecureStorage)->set('key', null))->not->toThrow(InvalidArgumentException::class);
    });

    it('returns null from get() outside a native runtime', function () {
        expect((new SecureStorage)->get('auth_token'))->toBeNull();
    });

    it('returns false from set() outside a native runtime', function () {
        expect((new SecureStorage)->set('auth_token', 'value'))->toBeFalse();
    });

    it('returns false from delete() outside a native runtime', function () {
        expect((new SecureStorage)->delete('auth_token'))->toBeFalse();
    });

    it('has() reports false when get() returns null', function () {
        expect((new SecureStorage)->has('auth_token'))->toBeFalse();
    });
});

describe('Composer Configuration', function () {
    it('has valid composer.json', function () {
        $composerPath = $this->pluginPath.'/composer.json';
        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($composer['name'])->toBe('sghimire/mobile-secure-storage');
        expect($composer['type'])->toBe('nativephp-plugin');
        expect($composer['extra']['nativephp']['manifest'])->toBe('nativephp.json');
        expect($composer['autoload']['psr-4'])->toHaveKey('Sandip\\SecureStorage\\Native\\');
    });
});

describe('Lifecycle Hooks', function () {
    it('has copy_assets hook command', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['hooks']['copy_assets'] ?? null)->not->toBeNull();

        $commandFile = $this->pluginPath.'/src/Commands/CopyAssetsCommand.php';
        expect(file_exists($commandFile))->toBeTrue();
    });

    it('copy_assets command extends NativePluginHookCommand', function () {
        $content = file_get_contents($this->pluginPath.'/src/Commands/CopyAssetsCommand.php');

        expect($content)->toContain('extends NativePluginHookCommand');
        expect($content)->toContain('use Native\Mobile\Plugins\Commands\NativePluginHookCommand');
    });

    it('copy_assets command has correct signature', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $expectedSignature = $manifest['hooks']['copy_assets'];

        $content = file_get_contents($this->pluginPath.'/src/Commands/CopyAssetsCommand.php');

        expect($content)->toContain('$signature = \''.$expectedSignature.'\'');
    });
});
