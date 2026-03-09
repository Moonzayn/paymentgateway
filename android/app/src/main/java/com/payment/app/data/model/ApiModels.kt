package com.payment.app.data.model

import com.google.gson.annotations.SerializedName

// Auth Models
data class LoginRequest(
    @SerializedName("username") val username: String,
    @SerializedName("password") val password: String,
    @SerializedName("csrf_token") val csrfToken: String
)

data class LoginResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String?,
    @SerializedName("redirect") val redirect: String?,
    @SerializedName("needs_2fa") val needs2fa: Boolean?,
    @SerializedName("user_id") val userId: Int?
)

data class TwoFactorRequest(
    @SerializedName("code") val code: String
)

data class TwoFactorResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String?,
    @SerializedName("redirect") val redirect: String?,
    @SerializedName("attempts") val attempts: Int?,
    @SerializedName("blocked") val blocked: Boolean?
)

// User Models
data class SaldoResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("saldo") val saldo: Double,
    @SerializedName("saldo_display") val saldoDisplay: String,
    @SerializedName("last_update") val lastUpdate: String?,
    @SerializedName("message") val message: String?
)

data class User(
    val id: Int,
    val username: String,
    val namaLengkap: String,
    val role: String,
    val saldo: Double
)

// POS Models
data class QrisCreateRequest(
    @SerializedName("reference") val reference: String,
    @SerializedName("amount") val amount: Int
)

data class QrisCreateResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("qris_image") val qrisImage: String?,
    @SerializedName("qris_string") val qrisString: String?,
    @SerializedName("reference") val reference: String?,
    @SerializedName("expired_at") val expiredAt: String?,
    @SerializedName("message") val message: String?
)

data class QrisStatusResponse(
    @SerializedName("paid") val paid: Boolean,
    @SerializedName("status") val status: String,
    @SerializedName("data") val data: Any?,
    @SerializedName("message") val message: String?
)

data class PosItem(
    val id: Int,
    val nama: String,
    val harga: Int,
    var qty: Int = 1,
    val isManual: Boolean = false,
    val stok: Int? = null
)

data class PosSimpanRequest(
    @SerializedName("metode_bayar") val metodeBayar: String,
    @SerializedName("reference") val reference: String?,
    @SerializedName("qris_string") val qrisString: String?,
    @SerializedName("uang_diberikan") val uangDiberikan: Int?,
    @SerializedName("items") val items: String // JSON string
)

data class PosSimpanResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("invoice") val invoice: String?,
    @SerializedName("transaksi_id") val transaksiId: Int?,
    @SerializedName("total") val total: Int?,
    @SerializedName("kembalian") val kembalian: Int?,
    @SerializedName("status") val status: String?,
    @SerializedName("message") val message: String?
)

data class PosDetailResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("transaksi") val transaksi: PosTransaction?,
    @SerializedName("items") val items: List<PosItem>?,
    @SerializedName("message") val message: String?
)

data class PosTransaction(
    @SerializedName("no_invoice") val noInvoice: String,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("kasir") val kasir: String,
    @SerializedName("nama_toko") val namaToko: String,
    @SerializedName("metode_bayar") val metodeBayar: String,
    @SerializedName("status") val status: String,
    @SerializedName("total_bayar") val totalBayar: Int,
    @SerializedName("uang_diberikan") val uangDiberikan: Int?,
    @SerializedName("kembalian") val kembalian: Int?,
    @SerializedName("total_item") val totalItem: Int
)

// Chat Models
data class ChatSendRequest(
    @SerializedName("message") val message: String,
    @SerializedName("user_id") val userId: Int?
)

data class ChatSendResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String?
)

data class ChatGetRequest(
    @SerializedName("last_id") val lastId: Int,
    @SerializedName("user_id") val userId: Int?
)

data class ChatMessage(
    @SerializedName("id") val id: Int,
    @SerializedName("sender_id") val senderId: Int,
    @SerializedName("sender_role") val senderRole: String,
    @SerializedName("sender_name") val senderName: String,
    @SerializedName("message") val message: String,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("is_read") val isRead: Int
)

data class ChatGetResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("messages") val messages: List<ChatMessage>,
    @SerializedName("unread_count") val unreadCount: Int
)

data class ChatMarkReadRequest(
    @SerializedName("user_id") val userId: Int?
)

data class ChatMarkReadResponse(
    @SerializedName("success") val success: Boolean
)

// Notification Models
data class Notification(
    @SerializedName("type") val type: String,
    @SerializedName("id") val id: Int,
    @SerializedName("nominal") val nominal: Double?,
    @SerializedName("user_name") val userName: String?,
    @SerializedName("title") val title: String,
    @SerializedName("message") val message: String,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("is_read") val isRead: String,
    @SerializedName("time_ago") val timeAgo: String
)

data class NotificationsResponse(
    @SerializedName("notifications") val notifications: List<Notification>,
    @SerializedName("unread") val unread: Int,
    @SerializedName("chat_unread") val chatUnread: Int
)

// Deposit Models
data class DepositRequest(
    @SerializedName("nominal") val nominal: Double,
    @SerializedName("metode_bayar") val metodeBayar: String,
    @SerializedName("csrf_token") val csrfToken: String
)

data class DepositResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String?
)

// Generic Response
data class ApiResponse<T>(
    val success: Boolean,
    val message: String?,
    val data: T?
)
