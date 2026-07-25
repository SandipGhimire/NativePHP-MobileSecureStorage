import Foundation
import Security

enum SecureStorageFunctions {

    private static let service = "com.sandip.plugins.secure_storage"

    private static let maxKeyLength = 255
    private static let maxValueLength = 8192

    private static func baseQuery(forKey key: String) -> [String: Any] {
        return [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: key,
        ]
    }

    private static func validateKey(_ parameters: [String: Any]) -> Result<String, [String: Any]> {
        guard let rawKey = parameters["key"] as? String else {
            return .failure(BridgeResponse.error(code: "KEY_REQUIRED", message: "A non-empty key is required."))
        }

        let key = rawKey.trimmingCharacters(in: .whitespacesAndNewlines)

        if key.isEmpty {
            return .failure(BridgeResponse.error(code: "KEY_REQUIRED", message: "A non-empty key is required."))
        }

        if key.count > maxKeyLength {
            return .failure(BridgeResponse.error(code: "KEY_TOO_LONG", message: "Key must not exceed \(maxKeyLength) characters."))
        }

        return .success(key)
    }

    class Set: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            switch SecureStorageFunctions.validateKey(parameters) {
            case .failure(let error):
                return error
            case .success(let key):
                var query = SecureStorageFunctions.baseQuery(forKey: key)

                guard let value = parameters["value"] as? String else {
                    SecItemDelete(query as CFDictionary)
                    return BridgeResponse.success(data: ["success": true])
                }

                if value.utf8.count > SecureStorageFunctions.maxValueLength {
                    return BridgeResponse.error(code: "VALUE_TOO_LARGE", message: "Value must not exceed \(SecureStorageFunctions.maxValueLength) bytes.")
                }

                let data = Data(value.utf8)

                let attributesToUpdate: [String: Any] = [kSecValueData as String: data]
                let updateStatus = SecItemUpdate(query as CFDictionary, attributesToUpdate as CFDictionary)

                if updateStatus == errSecItemNotFound {
                    query[kSecValueData as String] = data
                    query[kSecAttrAccessible as String] = kSecAttrAccessibleWhenUnlockedThisDeviceOnly
                    let addStatus = SecItemAdd(query as CFDictionary, nil)

                    guard addStatus == errSecSuccess else {
                        return BridgeResponse.error(code: "WRITE_FAILED", message: "Could not write to Keychain (status \(addStatus)).")
                    }
                } else if updateStatus != errSecSuccess {
                    return BridgeResponse.error(code: "WRITE_FAILED", message: "Could not update Keychain item (status \(updateStatus)).")
                }

                return BridgeResponse.success(data: ["success": true])
            }
        }
    }

    class Get: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            switch SecureStorageFunctions.validateKey(parameters) {
            case .failure(let error):
                return error
            case .success(let key):
                var query = SecureStorageFunctions.baseQuery(forKey: key)
                query[kSecReturnData as String] = true
                query[kSecMatchLimit as String] = kSecMatchLimitOne

                var item: CFTypeRef?
                let status = SecItemCopyMatching(query as CFDictionary, &item)

                guard status == errSecSuccess, let data = item as? Data,
                      let value = String(data: data, encoding: .utf8) else {
                    return BridgeResponse.success(data: ["value": ""])
                }

                return BridgeResponse.success(data: ["value": value])
            }
        }
    }

    class Delete: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            switch SecureStorageFunctions.validateKey(parameters) {
            case .failure(let error):
                return error
            case .success(let key):
                let status = SecItemDelete(SecureStorageFunctions.baseQuery(forKey: key) as CFDictionary)

                guard status == errSecSuccess || status == errSecItemNotFound else {
                    return BridgeResponse.error(code: "DELETE_FAILED", message: "Could not delete Keychain item (status \(status)).")
                }

                return BridgeResponse.success(data: ["success": true])
            }
        }
    }
}
