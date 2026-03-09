package com.payment.app.data.repository

import com.payment.app.data.api.PaymentApiService
import com.payment.app.data.model.SaldoResponse
import com.payment.app.util.Resource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class UserRepository @Inject constructor(
    private val apiService: PaymentApiService,
    private val sessionManager: SessionManager
) {
    suspend fun getSaldo(): Resource<SaldoResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getSaldo()

                if (response.isSuccessful && response.body()?.success == true) {
                    val body = response.body()!!
                    sessionManager.saldo = body.saldo
                    Resource.Success(body)
                } else {
                    Resource.Error(response.body()?.message ?: "Gagal mengambil saldo")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    fun getCurrentSaldo(): Double = sessionManager.saldo

    fun getUserName(): String = sessionManager.namaLengkap ?: sessionManager.username ?: ""

    fun getUserRole(): String = sessionManager.role ?: "member"

    fun isAdmin(): Boolean = sessionManager.isAdmin()
}
