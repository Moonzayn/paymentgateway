# API v1 Documentation

## Overview
API v1 provides RESTful endpoints for Flutter mobile app integration.

## Base URL
```
http://localhost/payment/api/v1/
```

## Authentication
All endpoints (except login) require API Key authentication using Bearer token.

### Header Format
```
Authorization: Bearer <api_key>
```

## Response Format

### Success
```json
{
  "success": true,
  "data": { ... },
  "message": "Success message"
}
```

### Error
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error message"
  }
}
```

---

## Authentication Endpoints

### Login
**POST** `/auth/login.php`

Request:
```json
{
  "username": "admin",
  "password": "password123"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "token": "sk_abc123...",
    "secret": "secret_xyz789...",
    "expires_at": "2025-03-18 12:00:00",
    "user": {
      "id": 1,
      "username": "admin",
      "nama_lengkap": "Administrator",
      "email": "admin@example.com",
      "no_hp": "081234567890",
      "role": "admin",
      "saldo": 100000,
      "saldo_display": "Rp 100.000"
    },
    "2fa_required": false
  },
  "message": "Login berhasil"
}
```

### Logout
**POST** `/auth/logout.php`

Header: `Authorization: Bearer <token>`

Response:
```json
{
  "success": true,
  "message": "Logout berhasil"
}
```

### Get Current User
**GET** `/auth/me.php`

Header: `Authorization: Bearer <token>`

### Refresh Token
**POST** `/auth/refresh.php`

Header: `Authorization: Bearer <token>`

---

## 2FA Endpoints

### Setup 2FA
**POST** `/auth/2fa/setup.php`

Header: `Authorization: Bearer <token>`

Request:
```json
{
  "enable": true
}
```

### Verify 2FA
**POST** `/auth/2fa/verify.php`

Header: `Authorization: Bearer <token>`

Request:
```json
{
  "code": "123456"
}
```

### Disable 2FA
**POST** `/auth/2fa/disable.php`

Header: `Authorization: Bearer <token>`

Request:
```json
{
  "password": "user_password"
}
```

---

## User Endpoints

### Get Saldo
**GET** `/user/saldo.php`

Header: `Authorization: Bearer <token>`

### Get/Update Profile
**GET** `/user/profile.php` - Get profile

**PUT** `/user/profile.php` - Update profile

Header: `Authorization: Bearer <token>`

Request (PUT):
```json
{
  "nama_lengkap": "John Doe",
  "email": "john@example.com",
  "no_hp": "081234567890"
}
```

### Notifications
**GET** `/user/notifications.php` - List notifications

**PUT** `/user/notifications.php` - Mark as read

**POST** `/user/notifications.php` - Mark all as read

Header: `Authorization: Bearer <token>`

---

## Chat Endpoints

### Get Chat Rooms
**GET** `/chat/rooms.php`

Header: `Authorization: Bearer <token>`

### Messages
**GET** `/chat/messages.php?room_id=xxx` - Get messages

**POST** `/chat/messages.php` - Send message

Header: `Authorization: Bearer <token>`

Request:
```json
{
  "room_id": "user_1",
  "message": "Hello!"
}
```

---

## POS Endpoints

### Get Products
**GET** `/pos/products.php`

Header: `Authorization: Bearer <token>`

Query Parameters:
- `store_id` (optional)
- `category_id` (optional)
- `search` (optional)
- `page` (default: 1)
- `limit` (default: 50)

### Transactions
**GET** `/pos/transactions.php` - List transactions

**POST** `/pos/transactions.php` - Create transaction

Header: `Authorization: Bearer <token>`

Request (POST):
```json
{
  "store_id": 1,
  "items": [
    {
      "produk_id": 1,
      "nama_produk": "Product 1",
      "harga": 10000,
      "jumlah": 2
    }
  ],
  "metode_bayar": "tunai",
  "bayar": 25000
}
```

---

## Testing

### Using cURL

```bash
# Login
curl -X POST http://localhost/payment/api/v1/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'

# Get Saldo (replace <token> with actual token)
curl http://localhost/payment/api/v1/user/saldo.php \
  -H "Authorization: Bearer <token>"
```

---

## Notes

1. Run migration first: `migration_api_keys.sql`
2. The API key table must exist before using the API
3. All monetary values are returned as floats
4. Timestamps are in MySQL format: `YYYY-MM-DD HH:MM:SS`
