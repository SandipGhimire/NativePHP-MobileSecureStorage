package com.sandip.plugins.secure_storage

import android.content.Context
import android.content.SharedPreferences
import androidx.fragment.app.FragmentActivity
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import java.security.GeneralSecurityException

object SecureStorageFunctions {

    private const val PREFS_NAME = "sghimire_secure_storage_prefs"

    private const val MAX_KEY_LENGTH = 255
    private const val MAX_VALUE_LENGTH = 8192

    private fun prefs(context: Context): SharedPreferences {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()

        return EncryptedSharedPreferences.create(
            context,
            PREFS_NAME,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    private fun validateKey(parameters: Map<String, Any>): Pair<String?, Map<String, Any>?> {
        val key = (parameters["key"] as? String)?.trim()

        if (key.isNullOrEmpty()) {
            return null to BridgeResponse.error("KEY_REQUIRED", "A non-empty key is required.")
        }

        if (key.length > MAX_KEY_LENGTH) {
            return null to BridgeResponse.error("KEY_TOO_LONG", "Key must not exceed $MAX_KEY_LENGTH characters.")
        }

        return key to null
    }

    class Set(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val (key, keyError) = validateKey(parameters)
            if (keyError != null) return keyError

            val value = parameters["value"] as? String

            if (value != null && value.toByteArray(Charsets.UTF_8).size > MAX_VALUE_LENGTH) {
                return BridgeResponse.error("VALUE_TOO_LARGE", "Value must not exceed $MAX_VALUE_LENGTH bytes.")
            }

            return try {
                val editor = prefs(activity).edit()
                if (value == null) {
                    editor.remove(key)
                } else {
                    editor.putString(key, value)
                }

                if (editor.commit()) {
                    BridgeResponse.success(mapOf("success" to true))
                } else {
                    BridgeResponse.error("WRITE_FAILED", "Could not write to secure storage.")
                }
            } catch (e: GeneralSecurityException) {
                BridgeResponse.error("WRITE_FAILED", e.message ?: "Could not access the secure keystore.")
            } catch (e: Exception) {
                BridgeResponse.error("WRITE_FAILED", e.message ?: "Could not write to secure storage.")
            }
        }
    }

    class Get(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val (key, keyError) = validateKey(parameters)
            if (keyError != null) return keyError

            return try {
                val value = prefs(activity).getString(key, null)
                BridgeResponse.success(mapOf("value" to (value ?: "")))
            } catch (e: GeneralSecurityException) {
                BridgeResponse.error("READ_FAILED", e.message ?: "Could not access the secure keystore.")
            } catch (e: Exception) {
                BridgeResponse.error("READ_FAILED", e.message ?: "Could not read from secure storage.")
            }
        }
    }

    class Delete(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val (key, keyError) = validateKey(parameters)
            if (keyError != null) return keyError

            return try {
                if (prefs(activity).edit().remove(key).commit()) {
                    BridgeResponse.success(mapOf("success" to true))
                } else {
                    BridgeResponse.error("DELETE_FAILED", "Could not delete from secure storage.")
                }
            } catch (e: GeneralSecurityException) {
                BridgeResponse.error("DELETE_FAILED", e.message ?: "Could not access the secure keystore.")
            } catch (e: Exception) {
                BridgeResponse.error("DELETE_FAILED", e.message ?: "Could not delete from secure storage.")
            }
        }
    }
}
