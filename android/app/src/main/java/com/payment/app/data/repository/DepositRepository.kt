package com.payment.app.data.repository

import com.payment.app.data.api.PaymentApiService
import com.payment.app.util.Resource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class DepositRepository @Inject constructor(
    private val apiService: PaymentApiService,
    private val sessionManager: SessionManager
) {
    suspend fun submitDeposit(nominal: Double, metodeBayar: String, buktiFile: File?): Resource<String> {
        return withContext(Dispatchers.IO) {
            try {
                val csrfToken = sessionManager.csrfToken ?: ""

                val nominalPart = nominal.toString().toRequestBody("text/plain".toMediaTypeOrNull())
                val metodePart = metodeBayar.toRequestBody("text/plain".toMediaTypeOrNull())
                val csrfPart = csrfToken.toRequestBody("text/plain".toMediaTypeOrNull())

                val buktiPart = buktiFile?.let {
                    val requestFile = it.asRequestBody("image/*".toMediaTypeOrNull())
                    MultipartBody.Part.createFormData("bukti_transfer", it.name, requestFile)
                }

                val response = apiService.submitDeposit(nominalPart, metodePart, csrfPart, buktiPart)

                if (response.isSuccessful) {
                    Resource.Success("Permintaan deposit berhasil dikirim")
                } else {
                    Resource.Error("Gagal提交 deposit")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
}
