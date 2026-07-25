# Mobile Secure Storage

Hardware-backed, encrypted key-value storage for [NativePHP Mobile](https://nativephp.com) apps — Android Keystore-backed `EncryptedSharedPreferences` on Android, Keychain on iOS.

This package is a **free, self-contained alternative** to the paid `nativephp/mobile-secure-storage` plugin. It ships its own Laravel facade, a validated implementation, JS/TypeScript bindings, and the native Kotlin/Swift implementation — with no dependency on the paid plugin.

## Features

- Simple `set` / `get` / `has` / `delete` API for storing small secrets (auth tokens, PINs, flags) securely on-device.
- Backed by the platform's real secure storage — Android Keystore and iOS Keychain — not plain preferences/`UserDefaults`.
- Key and value validated both in PHP/JS *and* natively before anything touches the keystore/keychain.
- Fully synchronous from the caller's point of view — no events to listen for.
- Works from PHP (Blade/Livewire) and from JavaScript (Vue, React, Inertia, or plain JS).

## Requirements

- PHP ^8.2
- [`nativephp/mobile`](https://nativephp.com) ^3.0

## Installation

```bash
composer require sghimire/mobile-secure-storage
```

Laravel's package auto-discovery registers `SecureStorageServiceProvider` for you. Then register the plugin with NativePHP:

```bash
php artisan native:plugin:register
```

This wires up the plugin's `nativephp.json` manifest (bridge functions) into your native build. Rebuild/reinstall the native shell afterwards (`php artisan native:install` or `native:run`) so the bridge functions are picked up. No special permissions are required on either platform.

## How It Works (Under the Hood)

Unlike the biometrics and scanner plugins, secure storage is a **synchronous request/response** bridge call — `set()`, `get()`, and `delete()` all resolve immediately with their result. There's no `native-event` listener involved and nothing to subscribe to.

JS calls reach the native side via `fetch('/_native/api/call', { method: 'Vault.Set' | 'Vault.Get' | 'Vault.Delete', params })`; PHP calls the same bridge directly through the global `nativephp_call()` function. Either way, the bridge router matches the method name to `SecureStorageFunctions.Set` / `.Get` / `.Delete` (Kotlin on Android, Swift on iOS), which reads or writes the platform's secure store and returns the result in that same call — no second round-trip, no injected event script, unlike the async flow in the biometrics/scanner plugins.

Every call is validated twice:

1. In PHP/JS, before the bridge is even called (fast fail, no round-trip).
2. Natively, on the Android/iOS side, before touching the Keystore/Keychain (defense in depth).

### Limits

| | Limit |
|---|---|
| Key length | 255 characters, must be non-empty (not just whitespace) |
| Value size | 8192 bytes (UTF-8 encoded) |

Passing `null` as a value to `set()` is always allowed regardless of size limits — it deletes the key.

---

## PHP Usage

### The `SecureStorage` facade

```php
use Sandip\SecureStorage\Native\Facades\SecureStorage;

// Store a value
SecureStorage::set('auth_token', 'eyJhbGciOi...');

// Retrieve it
$token = SecureStorage::get('auth_token'); // string, or null if not set

// Check existence
if (SecureStorage::has('auth_token')) {
    // ...
}

// Remove it
SecureStorage::delete('auth_token');

// set(key, null) also deletes
SecureStorage::set('auth_token', null);
```

#### Method reference

| Method | Signature | Description |
|---|---|---|
| `set` | `(string $key, ?string $value): bool` | Stores `$value` under `$key`, or deletes the key if `$value` is `null`. Returns `true` on success. |
| `get` | `(string $key): ?string` | Returns the stored value, or `null` if the key doesn't exist. |
| `has` | `(string $key): bool` | Shorthand for `get($key) !== null`. |
| `delete` | `(string $key): bool` | Removes the key. Returns `true` on success. |

#### Validation

`set()`, `get()`, and `delete()` all throw `InvalidArgumentException` for an invalid key or value **before** calling the native bridge:

```php
use Sandip\SecureStorage\Native\Facades\SecureStorage;

SecureStorage::set('', 'value');                       // throws: key must not be empty
SecureStorage::set('   ', 'value');                     // throws: key must not be empty (whitespace-only)
SecureStorage::set(str_repeat('a', 256), 'value');      // throws: key exceeds 255 characters
SecureStorage::set('key', str_repeat('a', 8193));       // throws: value exceeds 8192 bytes
SecureStorage::set('key', null);                        // OK — null bypasses the value size check (it's a delete)
```

### A practical example

```php
use Sandip\SecureStorage\Native\Facades\SecureStorage;

class AuthTokenStore
{
    protected string $key = 'auth_token';

    public function remember(string $token): void
    {
        SecureStorage::set($this->key, $token);
    }

    public function token(): ?string
    {
        return SecureStorage::get($this->key);
    }

    public function forget(): void
    {
        SecureStorage::delete($this->key);
    }
}
```

---

## JavaScript Usage

### Importing

This package doesn't publish a `#nativephp` import alias (that's reserved for NativePHP's first-party plugins). Import the file directly — either from the vendor path, or copy it into your own `resources/js/` and import it from there:

```js
import { SecureStorage } from '../../vendor/sghimire/mobile-secure-storage/resources/js/secure-storage.js';
```

Full TypeScript types are included in `secure-storage.d.ts` alongside it.

### Basic usage

Every method is `async` and returns a `Promise`:

```js
import { SecureStorage } from '../../vendor/sghimire/mobile-secure-storage/resources/js/secure-storage.js';

// Store a value
await SecureStorage.set('auth_token', 'eyJhbGciOi...');

// Retrieve it — { value: string } shape
const { value } = await SecureStorage.get('auth_token');

// Check existence — returns a plain boolean
const exists = await SecureStorage.has('auth_token');

// Remove it
await SecureStorage.delete('auth_token');

// set(key, null) also deletes
await SecureStorage.set('auth_token', null);
```

Invalid keys/values throw synchronously-rejected errors before any network call is made:

```js
try {
  await SecureStorage.set('', 'value');
} catch (error) {
  console.error(error.message); // "SecureStorage key must be a non-empty string."
}
```

### Vue 3 example

```vue
<script setup>
import { ref } from 'vue';
import { SecureStorage } from '../../vendor/sghimire/mobile-secure-storage/resources/js/secure-storage.js';

const token = ref(null);

async function saveToken(value) {
  await SecureStorage.set('auth_token', value);
  token.value = value;
}

async function loadToken() {
  const { value } = await SecureStorage.get('auth_token');
  token.value = value;
}

async function clearToken() {
  await SecureStorage.delete('auth_token');
  token.value = null;
}
</script>

<template>
  <button @click="loadToken">Load token</button>
  <button @click="clearToken">Clear token</button>
  <p v-if="token">Token: {{ token }}</p>
</template>
```

### React example

```jsx
import { useState, useCallback } from 'react';
import { SecureStorage } from '../../vendor/sghimire/mobile-secure-storage/resources/js/secure-storage.js';

export function TokenManager() {
  const [token, setToken] = useState(null);

  const loadToken = useCallback(async () => {
    const { value } = await SecureStorage.get('auth_token');
    setToken(value);
  }, []);

  const clearToken = useCallback(async () => {
    await SecureStorage.delete('auth_token');
    setToken(null);
  }, []);

  return (
    <>
      <button onClick={loadToken}>Load token</button>
      <button onClick={clearToken}>Clear token</button>
      {token && <p>Token: {token}</p>}
    </>
  );
}
```

### JS API reference

| Method | Signature | Description |
|---|---|---|
| `SecureStorage.set` | `(key: string, value?: string \| null) => Promise<{ success: true }>` | Stores `value` under `key`, or deletes the key if `value` is `null`/omitted. |
| `SecureStorage.get` | `(key: string) => Promise<{ value: string }>` | Resolves with the stored value (empty string if not found — use `.has()` to check existence explicitly). |
| `SecureStorage.has` | `(key: string) => Promise<boolean>` | Resolves `true` if a non-empty value exists for `key`. |
| `SecureStorage.delete` | `(key: string) => Promise<{ success: true }>` | Removes `key`. |

## Error codes

If validation fails on the native side (a defense-in-depth check behind the PHP/JS validation above), the bridge call rejects/returns an error with one of these codes:

| Code | Meaning |
|---|---|
| `KEY_REQUIRED` | Key was empty or missing. |
| `KEY_TOO_LONG` | Key exceeded 255 characters. |
| `VALUE_TOO_LARGE` | Value exceeded 8192 bytes. |
| `WRITE_FAILED` | (iOS) Keychain write/update failed. |
| `DELETE_FAILED` | (iOS) Keychain delete failed. |

In JS, the thrown `Error` carries this in `error.code`.

## Implementation Guide: Persisting an Auth Token After Login

A typical mobile pattern: your Laravel API returns an auth token on login, you store it in the platform Keystore/Keychain instead of `localStorage`, then attach it to every subsequent API call.

### 1. Backend: issue a token on login

```php
// routes/api.php
Route::post('/login', LoginController::class);
```

```php
// app/Http/Controllers/LoginController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! auth()->attempt($request->only('email', 'password'))) {
            abort(401);
        }

        return response()->json([
            'token' => auth()->user()->createToken('mobile')->plainTextToken,
        ]);
    }
}
```

### 2a. Livewire: store and reuse the token server-side

If your Livewire component makes the login request itself (e.g. via `Http::post(...)`), store the resulting token right after:

```php
use Illuminate\Support\Facades\Http;
use Sandip\SecureStorage\Native\Facades\SecureStorage;

class LoginForm extends \Livewire\Component
{
    public string $email = '';
    public string $password = '';

    public function login(): void
    {
        $response = Http::post(config('app.url').'/api/login', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        SecureStorage::set('auth_token', $response->json('token'));

        $this->redirect(route('dashboard'));
    }

    public function logout(): void
    {
        SecureStorage::delete('auth_token');
        $this->redirect(route('login'));
    }
}
```

### 2b. Vue/React: store the token and attach it via an axios interceptor

```js
// resources/js/auth.js
import axios from 'axios';
import { SecureStorage } from '../../vendor/sghimire/mobile-secure-storage/resources/js/secure-storage.js';

export async function login(email, password) {
  const { data } = await axios.post('/api/login', { email, password });
  await SecureStorage.set('auth_token', data.token);
}

export async function logout() {
  await SecureStorage.delete('auth_token');
}

// Attach the stored token to every outgoing request
axios.interceptors.request.use(async (config) => {
  const { value: token } = await SecureStorage.get('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

```vue
<!-- resources/js/Pages/Login.vue -->
<script setup>
import { ref } from 'vue';
import { login } from '../auth.js';

const email = ref('');
const password = ref('');
const error = ref(null);

async function submit() {
  error.value = null;
  try {
    await login(email.value, password.value);
    window.location.href = '/dashboard';
  } catch (e) {
    error.value = 'Invalid credentials';
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="email" type="email" placeholder="Email" />
    <input v-model="password" type="password" placeholder="Password" />
    <button type="submit">Log in</button>
    <p v-if="error">{{ error }}</p>
  </form>
</template>
```

```jsx
// resources/js/Pages/Login.jsx
import { useState } from 'react';
import { login } from '../auth.js';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);

  async function submit(e) {
    e.preventDefault();
    setError(null);
    try {
      await login(email, password);
      window.location.href = '/dashboard';
    } catch {
      setError('Invalid credentials');
    }
  }

  return (
    <form onSubmit={submit}>
      <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" placeholder="Email" />
      <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" placeholder="Password" />
      <button type="submit">Log in</button>
      {error && <p>{error}</p>}
    </form>
  );
}
```

Because `SecureStorage` is synchronous request/response with no events, the interceptor pattern above is all you need — no listener setup, no cleanup on unmount. Remember `get()` resolves `{ value: '' }` (empty string, not `null`) when the key doesn't exist, so guard with `if (token)` as shown, or call `SecureStorage.has()` first if you need an explicit boolean.

## Platform notes

| | Android | iOS |
|---|---|---|
| Min OS version | API 23 | 15.0 |
| Storage backend | `EncryptedSharedPreferences` (AndroidX Security Crypto, Keystore-backed) | Keychain Services |
| Permissions | None required | None required |
| Native implementation | `resources/android/SecureStorageFunctions.kt` | `resources/ios/SecureStorageFunctions.swift` |

Both are configured automatically by `nativephp.json` — you don't need to edit native project files by hand.

## Testing

```bash
composer install
composer test
```

Outside of a compiled native shell, `SecureStorage::get()` returns `null` and `set()`/`delete()` return `false` (there's no bridge to call) — this is expected and is exactly what the test suite asserts.

## License

MIT
