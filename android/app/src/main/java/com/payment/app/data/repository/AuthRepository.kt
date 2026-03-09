package com.payment.app.data.repository

import com.payment.app.data.api.PaymentApiService
import com.payment.app.data.model.*
import com.payment.app.util.Resource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.ResponseBody
import org.json.JSONObject
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: PaymentApiService,
    private val sessionManager: SessionManager
) {
    suspend fun login(username: String, password: String): Resource<User> {
        return withContext(Dispatchers.IO) {
            try {
                val csrfToken = sessionManager.csrfToken ?: generateCsrfToken()
                val response = apiService.login(username, password, csrfToken)

                if (response.isSuccessful) {
                    val body = response.body()?.string() ?: ""
                    val json = JSONObject(body)

                    if (json.optBoolean("needs_2fa", false)) {
                        sessionManager.needs2FA = true
                        sessionManager.userId = json.optInt("user_id", -1)
                        sessionManager.csrfToken = json.optString("csrf_token", csrfToken)
                        return@withContext Resource.Success(User(-1, username, "", "member", 0.0))
                    }

                    if (json.optBoolean("success", false)) {
                        // Parse user data from session redirect
                        sessionManager.csrfToken = json.optString("csrf_token", csrfToken)
                        // After login, fetch user info
                        val saldoResponse = apiService.getSaldo()
                        if (saldoResponse.isSuccessful) {
                            saldoResponse.body()?.let { saldo ->
                                sessionManager.saveUser(
                                    id = 0,
                                    username = username,
                                    namaLengkap = sessionManager.namaLengkap ?: username,
                                    role = sessionManager.role ?: "member",
                                    saldo = saldo.saldo
                                )
                                sessionManager.needs2FA = false
                                return@withContext Resource.Success(
                                    User(0, username, sessionManager.namaLengkap ?: username, sessionManager.role ?: "member", saldo.saldo)
                                )
                            }
                        }
                        return@withContext Resource.Success(User(0, username, username, "member", 0.0))
                    } else {
                        val message = json.optString("message", "Login gagal")
                        return@withContext Resource.Error(message)
                    }
                } else {
                    return@withContext Resource.Error("Login gagal: ${response.code()}")
                }
            } catch (e: Exception) {
                return@withContext Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun verify2FA(code: String): Resource<String> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.verify2FA(code)

                if (response.isSuccessful) {
                    val body = response.body()
                    if (body?.success == true) {
                        sessionManager.needs2FA = false

                        // Fetch user data
                        val saldoResponse = apiService.getSaldo()
                        if (saldoResponse.isSuccessful) {
                            saldoResponse.body()?.let { saldo ->
                                sessionManager.saldo = saldo.saldo
                            }
                        }

                        Resource.Success(body.message ?: "Login berhasil")
                    } else {
                        val message = body?.message ?: "Kode salah"
                        Resource.Error(message)
                    }
                } else {
                    Resource.Error("Verifikasi gagal: ${response.code()}")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun logout() {
        withContext(Dispatchers.IO) {
            try {
                apiService.logout()
            } catch (e: Exception) {
                // Ignore errors
            }
            sessionManager.clearSession()
        }
    }

    private fun generateCsrfToken(): String {
        return java.util.UUID.randomUUID().toString().replace("-", "")
    }
}
