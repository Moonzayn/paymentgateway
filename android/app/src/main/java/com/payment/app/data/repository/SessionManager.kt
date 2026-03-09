package com.payment.app.data.repository

import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class SessionManager @Inject constructor(
    @ApplicationContext context: Context
) {
    private val prefs: SharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    var isLoggedIn: Boolean
        get() = prefs.getBoolean(KEY_IS_LOGGED_IN, false)
        set(value) = prefs.edit { putBoolean(KEY_IS_LOGGED_IN, value) }

    var userId: Int
        get() = prefs.getInt(KEY_USER_ID, -1)
        set(value) = prefs.edit { putInt(KEY_USER_ID, value) }

    var username: String?
        get() = prefs.getString(KEY_USERNAME, null)
        set(value) = prefs.edit { putString(KEY_USERNAME, value) }

    var namaLengkap: String?
        get() = prefs.getString(KEY_NAMA_LENGKAP, null)
        set(value) = prefs.edit { putString(KEY_NAMA_LENGKAP, value) }

    var role: String?
        get() = prefs.getString(KEY_ROLE, null)
        set(value) = prefs.edit { putString(KEY_ROLE, value) }

    var saldo: Double
        get() = prefs.getFloat(KEY_SALDO, 0f).toDouble()
        set(value) = prefs.edit { putFloat(KEY_SALDO, value.toFloat()) }

    var csrfToken: String?
        get() = prefs.getString(KEY_CSRF_TOKEN, null)
        set(value) = prefs.edit { putString(KEY_CSRF_TOKEN, value) }

    var needs2FA: Boolean
        get() = prefs.getBoolean(KEY_NEEDS_2FA, false)
        set(value) = prefs.edit { putBoolean(KEY_NEEDS_2FA, value) }

    var baseUrl: String
        get() = prefs.getString(KEY_BASE_URL, DEFAULT_BASE_URL) ?: DEFAULT_BASE_URL
        set(value) = prefs.edit { putString(KEY_BASE_URL, value) }

    fun saveUser(id: Int, username: String, namaLengkap: String, role: String, saldo: Double) {
        prefs.edit {
            putInt(KEY_USER_ID, id)
            putString(KEY_USERNAME, username)
            putString(KEY_NAMA_LENGKAP, namaLengkap)
            putString(KEY_ROLE, role)
            putFloat(KEY_SALDO, saldo.toFloat())
            putBoolean(KEY_IS_LOGGED_IN, true)
        }
    }

    fun clearSession() {
        prefs.edit {
            clear()
        }
    }

    fun isAdmin(): Boolean = role == "admin" || role == "superadmin"

    companion object {
        private const val PREFS_NAME = "payment_prefs"
        private const val KEY_IS_LOGGED_IN = "is_logged_in"
        private const val KEY_USER_ID = "user_id"
        private const val KEY_USERNAME = "username"
        private const val KEY_NAMA_LENGKAP = "nama_lengkap"
        private const val KEY_ROLE = "role"
        private const val KEY_SALDO = "saldo"
        private const val KEY_CSRF_TOKEN = "csrf_token"
        private const val KEY_NEEDS_2FA = "needs_2fa"
        private const val KEY_BASE_URL = "base_url"
        private const val DEFAULT_BASE_URL = "http://invitationai.my.id/"
    }
}
