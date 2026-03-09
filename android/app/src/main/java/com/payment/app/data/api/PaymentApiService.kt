package com.payment.app.data.api

import com.payment.app.data.model.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.*

interface PaymentApiService {

    // Auth
    @FormUrlEncoded
    @POST("login.php")
    suspend fun login(
        @Field("username") username: String,
        @Field("password") password: String,
        @Field("csrf_token") csrfToken: String
    ): Response<ResponseBody>

    @FormUrlEncoded
    @POST("api/2fa_verify.php?action=verify")
    suspend fun verify2FA(
        @Field("code") code: String
    ): Response<TwoFactorResponse>

    @GET("logout.php")
    suspend fun logout(): Response<ResponseBody>

    // User
    @GET("api/get_saldo.php")
    suspend fun getSaldo(): Response<SaldoResponse>

    @GET("api/2fa_setup.php?action=status")
    suspend fun get2FAStatus(): Response<ApiResponse<Boolean>>

    // POS
    @FormUrlEncoded
    @POST("api/pos_qris_create.php")
    suspend fun createQris(
        @Field("reference") reference: String,
        @Field("amount") amount: Int
    ): Response<QrisCreateResponse>

    @GET("api/pos_qris_status.php")
    suspend fun getQrisStatus(
        @Query("reference") reference: String
    ): Response<QrisStatusResponse>

    @FormUrlEncoded
    @POST("api/pos_simpan.php")
    suspend fun simpanPos(
        @Field("metode_bayar") metodeBayar: String,
        @Field("reference") reference: String?,
        @Field("qris_string") qrisString: String?,
        @Field("uang_diberikan") uangDiberikan: Int?,
        @Field("items") items: String
    ): Response<PosSimpanResponse>

    @GET("api/pos_get_detail.php")
    suspend fun getPosDetail(
        @Query("invoice") invoice: String
    ): Response<PosDetailResponse>

    // Chat
    @FormUrlEncoded
    @POST("api/chat_send.php")
    suspend fun sendChatMessage(
        @Field("message") message: String,
        @Field("user_id") userId: Int?
    ): Response<ChatSendResponse>

    @FormUrlEncoded
    @POST("api/chat_get.php")
    suspend fun getChatMessages(
        @Field("last_id") lastId: Int,
        @Field("user_id") userId: Int?
    ): Response<ChatGetResponse>

    @FormUrlEncoded
    @POST("api/chat_mark_read.php")
    suspend fun markChatRead(
        @Field("user_id") userId: Int?
    ): Response<ChatMarkReadResponse>

    // Notifications
    @GET("api/get_notifications.php")
    suspend fun getNotifications(): Response<NotificationsResponse>

    @GET("api/mark_notif_read.php")
    suspend fun markNotificationRead(
        @Query("id") id: Int,
        @Query("type") type: String
    ): Response<ApiResponse<Unit>>

    @GET("api/mark_all_notif_read.php")
    suspend fun markAllNotificationsRead(): Response<ApiResponse<Unit>>

    // Deposit
    @Multipart
    @POST("deposit.php")
    suspend fun submitDeposit(
        @Part("nominal") nominal: RequestBody,
        @Part("metode_bayar") metodeBayar: RequestBody,
        @Part("csrf_token") csrfToken: RequestBody,
        @Part bukti: MultipartBody.Part?
    ): Response<ResponseBody>
}
