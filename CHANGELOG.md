# Changelog

All notable changes to `sghimire/mobile-secure-storage` are documented in this file.

## [1.0.1] - 2026-07-27

### Changed

- Replaced `EncryptedSharedPreferences` on Android with manual AES/GCM encryption backed directly by the Android Keystore.

### Removed

- Hardcoded `version` field from `composer.json`.

## [1.0.0] - 2026-07-26

Initial release.

### Added

- Hardware-backed, encrypted key-value storage for NativePHP Mobile apps — Android Keystore-backed storage on Android, Keychain on iOS.
- Simple, fully synchronous `set` / `get` / `has` / `delete` API in PHP and JS, plus a `secure-storage.d.ts` type declaration file.
- Key and value validation both in PHP/JS and natively before anything touches the keystore/keychain.
- Native Kotlin (Android) and Swift (iOS) bridge implementation, with no dependency on the paid `nativephp/mobile-secure-storage` plugin.
- MIT LICENSE and README with full usage documentation.
