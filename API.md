# PPOB Express - API Documentation

## Base URL
```
http://invitationai.my.id/
```

## Authentication
- **Session-based** using PHP sessions
- CSRF token required for POST requests
- Login returns user data and CSRF token

---

## Endpoints

### 1. Login
**POST** `/login.php`

Request (Form URL-encoded):
```http
POST /login.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

username=admin&password=password
```

Response Success (No 2FA):
```json
{
  "success": true,
  "needs_2fa": false,
  "user": {
    "id": 1,
    "username": "admin",
    "nama_lengkap": "Administrator",
    "role": "admin",
    "saldo": 100000
  },
  "csrf_token": "abc123...",
  "message": "Login berhasil"
}
```

Response Success (Need 2FA):
```json
{
  "success": true,
  "needs_2fa": true,
  "user_id": -1,
  "csrf_token": "abc123...",
  "message": "Verifikasi 2FA diperlukan"
}
```

Response Error:
```json
{
  "success": false,
  "message": "Username tidak ditemukan!"
}
```

---

### 2. 2FA Verify
**POST** `/api/2fa_verify.php?action=verify`

Request:
```http
POST /api/2fa_verify.php?action=verify HTTP/1.1
Content-Type: application/x-www-form-urlencoded

code=123456
```

Response:
```json
{
  "success": true,
  "message": "Verifikasi berhasil"
}
```

---

### 3. Get Saldo
**GET** `/api/get_saldo.php`

Request:
```http
GET /api/get_saldo.php HTTP/1.1
Cookie: PHPSESSID=your_session_id
```

Response:
```json
{
  "success": true,
  "saldo": 100000,
  "username": "admin"
}
```

---

### 4. 2FA Setup Status
**GET** `/api/2fa_setup.php?action=status`

Response:
```json
{
  "enabled": false,
  "secret": null
}
```

---

### 5. Create QRIS Payment
**POST** `/api/pos_qris_create.php`

Request:
```http
POST /api/pos_qris_create.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

amount=10000&product=Pulsa 10rb
```

Response:
```json
{
  "success": true,
  "trx_id": "TRX123456",
  "qris_image": "data:image/png;base64,..."
}
```

---

### 6. Check QRIS Status
**GET** `/api/pos_qris_status.php?trx_id=TRX123456`

Response:
```json
{
  "success": true,
  "status": "pending",
  "amount": 10000
}
```

---

### 7. Save Transaction
**POST** `/api/pos_simpan.php`

Request:
```http
POST /api/pos_simpan.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

trx_id=TRX123456&amount=10000&metode=qris
```

Response:
```json
{
  "success": true,
  "message": "Transaksi berhasil disimpan"
}
```

---

### 8. Get Transaction Detail
**GET** `/api/pos_get_detail.php?trx_id=TRX123456`

Response:
```json
{
  "success": true,
  "transaction": {
    "id": 1,
    "trx_id": "TRX123456",
    "amount": 10000,
    "status": "success"
  }
}
```

---

### 9. Send Chat Message
**POST** `/api/chat_send.php`

Request:
```http
POST /api/chat_send.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

message=Halo&recipient_id=2
```

Response:
```json
{
  "success": true,
  "message": "Pesan terkirim"
}
```

---

### 10. Get Chat Messages
**POST** `/api/chat_get.php`

Request:
```http
POST /api/chat_get.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

other_user_id=2
```

Response:
```json
{
  "success": true,
  "messages": [
    {
      "id": 1,
      "sender_id": 1,
      "message": "Halo",
      "timestamp": "2024-01-01 12:00:00"
    }
  ]
}
```

---

### 11. Get All Chats
**GET** `/api/chat_get_all.php`

Response:
```json
{
  "success": true,
  "chats": [
    {
      "user_id": 2,
      "username": "member1",
      "last_message": "Halo",
      "timestamp": "2024-01-01 12:00:00",
      "unread": 1
    }
  ]
}
```

---

### 12. Mark Chat Read
**POST** `/api/chat_mark_read.php`

Request:
```http
POST /api/chat_mark_read.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

other_user_id=2
```

Response:
```json
{
  "success": true
}
```

---

### 13. Get Notifications
**GET** `/api/get_notifications.php`

Response:
```json
{
  "success": true,
  "notifications": [
    {
      "id": 1,
      "title": "Transaksi Berhasil",
      "message": "Pembelian pulsa berhasil",
      "is_read": false,
      "created_at": "2024-01-01 12:00:00"
    }
  ]
}
```

---

### 14. Mark Notification Read
**GET** `/api/mark_notif_read.php?id=1`

Response:
```json
{
  "success": true
}
```

---

### 15. Mark All Notifications Read
**GET** `/api/mark_all_notif_read.php`

Response:
```json
{
  "success": true
}
```

---

### 16. Logout
**GET** `/logout.php`

Response:
```json
{
  "success": true,
  "message": "Logout berhasil"
}
```

---

## Response Format

All API responses follow this format:

### Success
```json
{
  "success": true,
  "data": { ... }
}
```

### Error
```json
{
  "success": false,
  "message": "Error description"
}
```

---

## Notes

- All endpoints (except login) require valid PHP session
- Session is maintained via cookies
- CSRF token should be included in requests for security
- All monetary values are in Indonesian Rupiah (IDR)
