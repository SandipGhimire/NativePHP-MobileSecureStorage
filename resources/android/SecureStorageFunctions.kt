package com.sandip.plugins.secure_storage

import android.content.Context
import android.content.SharedPreferences
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import java.security.GeneralSecurityException
import java.security.KeyStore
import java.security.MessageDigest
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

object SecureStorageFunctions {

    private const val PREFS_NAME = "sghimire_secure_storage_prefs"
    private const val ANDROID_KEYSTORE = "AndroidKeyStore"
    private const val KEY_ALIAS = "sghimire_secure_storage_master_key"
    private const val TRANSFORMATION = "AES/GCM/NoPadding"
    private const val GCM_TAG_LENGTH_BITS = 128
    private const val GCM_IV_LENGTH_BYTES = 12

    private const val MAX_KEY_LENGTH = 255
    private const val MAX_VALUE_LENGTH = 8192

    private fun prefs(context: Context): SharedPreferences =
            context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    private fun secretKey(): SecretKey {
        val keyStore = KeyStore.getInstance(ANDROID_KEYSTORE).apply { load(null) }

        (keyStore.getKey(KEY_ALIAS, null) as? SecretKey)?.let {
            return it
        }

        val keyGenerator =
                KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, ANDROID_KEYSTORE)
        keyGenerator.init(
                KeyGenParameterSpec.Builder(
                                KEY_ALIAS,
                                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT
                        )
                        .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                        .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                        .setKeySize(256)
                        .build()
        )
        return keyGenerator.generateKey()
    }

    private fun storageKey(key: String): String {
        val digest = MessageDigest.getInstance("SHA-256").digest(key.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(digest, Base64.NO_WRAP or Base64.URL_SAFE)
    }

    private fun encrypt(plainText: String): String {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, secretKey())
        val cipherText = cipher.doFinal(plainText.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(cipher.iv + cipherText, Base64.NO_WRAP)
    }

    private fun decrypt(encoded: String): String {
        val combined = Base64.decode(encoded, Base64.NO_WRAP)
        val iv = combined.copyOfRange(0, GCM_IV_LENGTH_BYTES)
        val cipherText = combined.copyOfRange(GCM_IV_LENGTH_BYTES, combined.size)

        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.DECRYPT_MODE, secretKey(), GCMParameterSpec(GCM_TAG_LENGTH_BITS, iv))
        return String(cipher.doFinal(cipherText), Charsets.UTF_8)
    }

    private fun validateKey(parameters: Map<String, Any>): Pair<String?, Map<String, Any>?> {
        val key = (parameters["key"] as? String)?.trim()

        if (key.isNullOrEmpty()) {
            return null to BridgeResponse.error("KEY_REQUIRED", "A non-empty key is required.")
        }

        if (key.length > MAX_KEY_LENGTH) {
            return null to
                    BridgeResponse.error(
                            "KEY_TOO_LONG",
                            "Key must not exceed $MAX_KEY_LENGTH characters."
                    )
        }

        return key to null
    }

    class Set(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val (key, keyError) = validateKey(parameters)
            if (keyError != null) return keyError

            val value = parameters["value"] as? String

            if (value != null && value.toByteArray(Charsets.UTF_8).size > MAX_VALUE_LENGTH) {
                return BridgeResponse.error(
                        "VALUE_TOO_LARGE",
                        "Value must not exceed $MAX_VALUE_LENGTH bytes."
                )
            }

            return try {
                val editor = prefs(activity).edit()
                if (value == null) {
                    editor.remove(storageKey(key!!))
                } else {
                    editor.putString(storageKey(key!!), encrypt(value))
                }

                if (editor.commit()) {
                    BridgeResponse.success(mapOf("success" to true))
                } else {
                    BridgeResponse.error("WRITE_FAILED", "Could not write to secure storage.")
                }
            } catch (e: GeneralSecurityException) {
                BridgeResponse.error(
                        "WRITE_FAILED",
                        e.message ?: "Could not access the secure keystore."
                )
            } catch (e: Exception) {
                BridgeResponse.error(
                        "WRITE_FAILED",
                        e.message ?: "Could not write to secure storage."
                )
            }
        }
    }

    class Get(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val (key, keyError) = validateKey(parameters)
            if (keyError != null) return keyError

            return try {
                val stored = prefs(activity).getString(storageKey(key!!), null)
                BridgeResponse.success(mapOf("value" to (stored?.let { decrypt(it) } ?: "")))
            } catch (e: GeneralSecurityException) {
                BridgeResponse.error(
                        "READ_FAILED",
                        e.message ?: "Could not access the secure keystore."
                )
            } catch (e: Exception) {
                BridgeResponse.error(
                        "READ_FAILED",
                        e.message ?: "Could not read from secure storage."
                )
            }
        }
    }

    class Delete(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val (key, keyError) = validateKey(parameters)
            if (keyError != null) return keyError

            return try {
                if (prefs(activity).edit().remove(storageKey(key!!)).commit()) {
                    BridgeResponse.success(mapOf("success" to true))
                } else {
                    BridgeResponse.error("DELETE_FAILED", "Could not delete from secure storage.")
                }
            } catch (e: GeneralSecurityException) {
                BridgeResponse.error(
                        "DELETE_FAILED",
                        e.message ?: "Could not access the secure keystore."
                )
            } catch (e: Exception) {
                BridgeResponse.error(
                        "DELETE_FAILED",
                        e.message ?: "Could not delete from secure storage."
                )
            }
        }
    }
}
