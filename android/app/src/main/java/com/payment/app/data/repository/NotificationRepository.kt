package com.payment.app.data.repository

import com.payment.app.data.api.PaymentApiService
import com.payment.app.data.model.*
import com.payment.app.util.Resource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class NotificationRepository @Inject constructor(
    private val apiService: PaymentApiService,
    private val sessionManager: SessionManager
) {
    suspend fun getNotifications(): Resource<NotificationsResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getNotifications()

                if (response.isSuccessful) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error("Gagal mengambil notifikasi")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun markAsRead(id: Int, type: String): Resource<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.markNotificationRead(id, type)

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(Unit)
                } else {
                    Resource.Error("Gagal menandai notifikasi")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun markAllAsRead(): Resource<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.markAllNotificationsRead()

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(Unit)
                } else {
                    Resource.Error("Gagal menandai semua notifikasi")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
}
