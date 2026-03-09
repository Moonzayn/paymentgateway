package com.payment.app.data.repository

import com.payment.app.data.api.PaymentApiService
import com.payment.app.data.model.*
import com.payment.app.util.Resource
import com.google.gson.Gson
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class PosRepository @Inject constructor(
    private val apiService: PaymentApiService,
    private val sessionManager: SessionManager
) {
    private val gson = Gson()

    suspend fun createQrisPayment(reference: String, amount: Int): Resource<QrisCreateResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.createQris(reference, amount)

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error(response.body()?.message ?: "Gagal membuat QRIS")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun getQrisStatus(reference: String): Resource<QrisStatusResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getQrisStatus(reference)

                if (response.isSuccessful) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error("Gagal cek status QRIS")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun saveTransaction(
        metodeBayar: String,
        reference: String?,
        qrisString: String?,
        uangDiberikan: Int?,
        items: List<PosItem>
    ): Resource<PosSimpanResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val itemsJson = gson.toJson(items)
                val response = apiService.simpanPos(
                    metodeBayar = metodeBayar,
                    reference = reference,
                    qrisString = qrisString,
                    uangDiberikan = uangDiberikan,
                    items = itemsJson
                )

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error(response.body()?.message ?: "Gagal menyimpan transaksi")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    suspend fun getTransactionDetail(invoice: String): Resource<PosDetailResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getPosDetail(invoice)

                if (response.isSuccessful && response.body()?.success == true) {
                    Resource.Success(response.body()!!)
                } else {
                    Resource.Error(response.body()?.message ?: "Gagal mengambil detail")
                }
            } catch (e: Exception) {
                Resource.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    fun generateReference(): String {
        return "POS-${System.currentTimeMillis()}"
    }
}
