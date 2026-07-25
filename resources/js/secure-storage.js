const baseUrl = '/_native/api/call';

const MAX_KEY_LENGTH = 255;
const MAX_VALUE_LENGTH = 8192;

function assertValidKey(key) {
    if (typeof key !== 'string' || key.trim() === '') {
        throw new Error('SecureStorage key must be a non-empty string.');
    }

    if (key.length > MAX_KEY_LENGTH) {
        throw new Error(`SecureStorage key must not exceed ${MAX_KEY_LENGTH} characters.`);
    }
}

function assertValidValue(value) {
    if (value === null || value === undefined) {
        return;
    }

    if (typeof value !== 'string') {
        throw new Error('SecureStorage value must be a string or null.');
    }

    if (new TextEncoder().encode(value).length > MAX_VALUE_LENGTH) {
        throw new Error(`SecureStorage value must not exceed ${MAX_VALUE_LENGTH} bytes.`);
    }
}

async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ method, params }),
    });

    const result = await response.json();

    if (result.status === 'error') {
        const error = new Error(result.message || 'SecureStorage call failed.');
        error.code = result.code;
        throw error;
    }

    return result.data;
}

export const SecureStorage = {
    async set(key, value = null) {
        assertValidKey(key);
        assertValidValue(value);

        return bridgeCall('Vault.Set', { key, value });
    },

    async get(key) {
        assertValidKey(key);

        return bridgeCall('Vault.Get', { key });
    },

    async has(key) {
        const { value } = await SecureStorage.get(key);

        return value !== '';
    },

    async delete(key) {
        assertValidKey(key);

        return bridgeCall('Vault.Delete', { key });
    },
};

export default SecureStorage;
