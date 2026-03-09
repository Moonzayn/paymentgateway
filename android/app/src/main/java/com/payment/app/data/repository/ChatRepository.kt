package com.payment.app.data.repository

import com.payment.app.data.api.PaymentApiService
import com.payment.app.data.model.*
import com.payment.app.util.Resource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class ChatRepository @Inject constructor(
    private val apiService: PaymentApiService,
    private val sessionManager: SessionManager
) {
    suspend fun sendMessage(message: String, userId: Int? = null): Resource<ChatSendResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.sendChatMessage(message, userId)

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error(response.body()?.message ?: "Gagal mengirim pesan")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun getMessages(lastId: Int = 0, userId: Int? = null): Resource<ChatGetResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getChatMessages(lastId, userId)

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error("Gagal mengambil pesan")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun markAsRead(userId: Int? = null): Resource<ChatMarkReadResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.markChatRead(userId)

                if (response.isSuccessful) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error("Gagal menandai dibaca")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
}
