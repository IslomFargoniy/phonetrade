package uz.simulator.phonetrade.data

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKeys
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * Token va tanlangan filial. Token shifrlangan holda saqlanadi (Android Keystore).
 */
class Session(context: Context) {
    private val prefs: SharedPreferences = open(context.applicationContext)

    private val _token = MutableStateFlow(prefs.getString(KEY_TOKEN, null))
    val token: StateFlow<String?> = _token

    private val _profile = MutableStateFlow<Profile?>(null)
    val profile: StateFlow<Profile?> = _profile

    var branchId: Int?
        get() = prefs.getInt(KEY_BRANCH, 0).takeIf { it > 0 }
        set(value) {
            prefs.edit().putInt(KEY_BRANCH, value ?: 0).apply()
        }

    var printerAddress: String?
        get() = prefs.getString(KEY_PRINTER, null)
        set(value) {
            prefs.edit().putString(KEY_PRINTER, value).apply()
        }

    /** Chek qog'ozi kengligi, belgilarda: 58 mm = 32, 80 mm = 48. */
    var printerColumns: Int
        get() = prefs.getInt(KEY_COLUMNS, 32)
        set(value) {
            prefs.edit().putInt(KEY_COLUMNS, value).apply()
        }

    fun signIn(profile: Profile) {
        prefs.edit().putString(KEY_TOKEN, profile.token).apply()
        _token.value = profile.token
        setProfile(profile)
    }

    fun setProfile(profile: Profile) {
        _profile.value = profile
        branchId = profile.store.branch?.id
    }

    fun signOut() {
        prefs.edit().remove(KEY_TOKEN).remove(KEY_BRANCH).apply()
        _token.value = null
        _profile.value = null
    }

    private fun open(context: Context): SharedPreferences {
        fun create(): SharedPreferences = EncryptedSharedPreferences.create(
            FILE,
            MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC),
            context,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )

        return try {
            create()
        } catch (_: Exception) {
            // Kalit yo'qolgan (masalan, zaxiradan tiklangan qurilma): fayl o'qib bo'lmaydi, qayta kiriladi.
            context.deleteSharedPreferences(FILE)
            create()
        }
    }

    private companion object {
        const val FILE = "session"
        const val KEY_TOKEN = "token"
        const val KEY_BRANCH = "branch_id"
        const val KEY_PRINTER = "printer_address"
        const val KEY_COLUMNS = "printer_columns"
    }
}
