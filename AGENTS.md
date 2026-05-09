# PPOB Express - AI Agent Guide

## Project Overview

PPOB Express is a Flutter mobile app for purchasing Pulsa (mobile credit), Kuota (data packages), Token Listrik (electricity tokens), and Game vouchers. Connected to Digiflazz API as the main aggregator.

**Tech Stack:**
- Frontend: Flutter (Dart)
- Backend: PHP (CodeIgniter-style)
- Database: MySQL (MariaDB via Laragon)
- Payment Aggregator: Digiflazz API

---

## Architecture Summary

```
ppobexpress_app/          → Flutter mobile app
├── lib/
│   ├── config/          → App configuration
│   │   ├── app_config.dart        → API endpoints, base URL
│   │   └── phone_prefix_config.dart → Phone provider detection
│   ├── screens/         → UI screens
│   │   ├── pulsa_screen.dart      → Buy pulsa
│   │   ├──-kuota_screen.dart     → Buy data package
│   │   ├── token_listrik_screen.dart → Buy electricity token
│   │   └── game_screen.dart      → Buy game voucher
│   ├── services/         → API communication
│   │   └── pulsa_service.dart    → HTTP calls to backend
│   └── models/           → Data models
├── android/             → Android config
└── web/                 → Flutter web build (optional)

laragon/www/payment/     → PHP Backend
├── api/                 → Mobile APIs
│   ├── mobile_beli_pulsa.php
│   ├── mobile_beli_kuota.php
│   ├── mobile_beli_token_listrik.php
│   ├── mobile_beli_game.php
│   └── ...
├── config.php            → Database & app config
├── digiflazz.php        → Digiflazz API wrapper
└── db_ppob.sql         → Database schema
```

---

## Working API Endpoints

### Flutter → PHP Communication

| Feature | Flutter Screen | API Endpoint | HTTP Method |
|---------|---------------|-------------|-------------|
| Pulsa | pulsa_screen.dart | /api/mobile_beli_pulsa.php | POST (JSON) |
| Kuota | quota_screen.dart | /api/mobile_beli_kuota.php | POST (JSON) |
| Token Listrik | token_listrik_screen.dart | /api/mobile_beli_token_listrik.php | POST (JSON) |
| Game | game_screen.dart | /api/mobile_beli_game.php | POST (JSON) |

### Request/Response Format

**Request (Flutter sends):**
```json
{
  "user_id": 1,
  "no_hp": "085183059699",
  "produk_id": 1
}
```

**Response (PHP returns):**
```json
{
  "success": true,
  "message": "Transaksi Sukses",
  "data": {
    "transaksi_id": 54,
    "no_invoice": "INV202604130001",
    "invoice": "INV202604130001",
    "status": "success",
    "reason": "Transaksi Sukses",
    "message": "Transaksi Sukses",
    "no_tujuan": "085183059699",
    "nominal": 5000,
    "produk": "Pulsa Telkomsel 5K",
    "harga": 6500,
    "saldo_sesudah": 233000,
    "sn": "xxx"  // Only for token listrik
  }
}
```

---

## ⚠️ CRITICAL - What NOT To Change

### 1. PHP Include Paths

**WRONG (will cause Parse Error):**
```php
require_once dirname(__DIR__) . '/payment/config.php';
require_once dirname(__DIR__) . '/payment/digiflazz.php';
```

**CORRECT:**
```php
// api/mobile_beli_xxx.php is at: C:\laragon\www\payment\api\
// dirname(__DIR__) = C:\laragon\www\payment\
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/digiflazz.php';
```
> **Reason**: `dirname(__DIR__)` from `api/` folder goes to `payment/`. Adding `/payment/` creates invalid path `payment/payment/`.

### 2. PHP Array Keys - Must Be Strings

**WRONG (will cause Parse Error):**
```php
$errorMappings = [
    66 => 'Sistem sedang maintenance.',  // Integer key!
    ...
];
```

**CORRECT:**
```php
$errorMappings = [
    '66' => 'Sistem sedang maintenance.',  // String key!
    ...
];
```

### 3. PHP Variable Initialization

**WRONG (will cause Undefined variable):**
```php
// $status used below but not set
if ($shouldRollback) {
    $status = 'failed';
}
```

**CORRECT:**
```php
$status = 'pending'; // Always initialize!
if ($condition) {
    $status = 'failed';
}
```

### 4. Flutter Response Parsing

**WRONG (will crash with FormatException):**
```dart
final data = jsonDecode(body);  // No error handling!
```

**CORRECT:**
```dart
final body = response.body.trim();
if (body.isNotEmpty && (body.startsWith('<br') || body.startsWith('<!') || body.contains('Parse error'))) {
    _showError('Server error: $body');
    return;
}

Map<String, dynamic> data;
try {
    data = jsonDecode(body);
} catch (e) {
    _showError('Invalid JSON: $e');
    return;
}
```

---

## Known Issues & Past Bugs

### 1. FormatException "unexpected at character 1"

**Cause**: PHP returns HTML/error instead of JSON
**Fix**: Add defensive code to detect non-JSON (see section 4 above)

### 2. PHP Parse Error "unexpected integer"

**Cause**: Array key without quotes - `66` vs `'66'`
**Fix**: Always quote array keys: `'66' => 'message'`

### 3. Undefined variable $status

**Cause**: Variable used in INSERT query but not initialized
**Fix**: Initialize `$status = 'pending'` before any conditional

### 4. Wrong Include Paths

**Cause**: Adding extra `/payment/` to already-correct path
**Fix**: Use `dirname(__DIR__) . '/config.php'` NOT `'payment/config.php'`

---

## Phone Prefix Detection

File: `lib/config/phone_prefix_config.dart`

```dart
class PhonePrefixConfig {
  static String detectProvider(String phone) {
    // 0851 → Telekomsel (NOT Axis!)
    // Check specific prefixes FIRST, then fallback to default
  }
}
```

**Important**: 0851 is TELKOMSEL, not Axis. This was a bug that was fixed.

---

## Database Important Notes

### Table: transaksi

| Column | Type | Notes |
|--------|------|-------|
| status | varchar(20) | 'pending', 'success', 'failed' |
| no_invoice | varchar(50) | Unique per transaction |
| ref_id | varchar(50) | Reference to Digiflazz |
| no_tujuan | varchar(20) | Phone/meter/player ID |

---

## Testing Checklist

Before testing any changes:

1. ✅ Clear debug log: `C:\laragon\www\payment\api\debug_kuota.log`
2. ✅ Check PHP error log: `C:\laragon\www\payment\api\php_errors.log`
3. ✅ Rebuild Flutter APK: `flutter build apk --debug`
4. ✅ Install to phone: `adb install -r build/app/outputs/flutter-apk/app-debug.apk`

---

## How to Add New API (Step by Step)

### Step 1: Backend (PHP)

```php
// File: laragon/www/payment/api/mobile_beli_xxx.php

// 1. Include config (CORRECT path!)
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/digiflazz.php';

// 2. Return JSON with these fields:
echo json_encode([
    'success' => ($status === 'success'),
    'message' => $keterangan,
    'data' => [
        'transaksi_id' => $transaksi_id,
        'no_invoice' => $invoice,
        'invoice' => $invoice,        // REPEAT for Flutter!
        'status' => $status,
        'reason' => $keterangan,
        'message' => $keterangan,    // REPEAT for Flutter!
        'no_tujuan' => $no_hp,
        'nominal' => $nominal,
        'produk' => $produk['nama_produk'],
        'harga' => $harga,
        'saldo_sesudah' => $saldo_sesudah
    ]
]);
```

### Step 2: Flutter Config

```dart
// File: lib/config/app_config.dart
static const String apiBeliXxx = '/api/mobile_beli_xxx.php';
```

### Step 3: Flutter Service

```dart
// File: lib/services/xxx_service.dart
Future<Map<String, dynamic>> beliXxx({...}) async {
    final response = await http.post(
        Uri.parse('$baseUrl${AppConfig.apiBeliXxx}'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
            'user_id': user.id,
            // ... other fields
        }),
    );

    // Add defensive code!
    final body = response.body.trim();
    if (body.isNotEmpty && (body.startsWith('<br') || body.contains('Parse error'))) {
        return {'success': false, 'message': 'Server error'};
    }

    try {
        return jsonDecode(body);
    } catch (e) {
        return {'success': false, 'message': 'Invalid JSON: $e'};
    }
}
```

### Step 4: Flutter Screen

```dart
// Add _showError and _showInvoiceModal methods
void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), backgroundColor: Colors.red)
    );
}

void _showInvoiceModal(Map<String, dynamic> data) {
    showModalBottomSheet(
        builder: (context) => Container(
            child: Column(
                children: [
                    // Use standard fields:
                    _buildInvoiceRow('Invoice', data['invoice'] ?? '-'),
                    _buildInvoiceRow('No. HP', data['no_tujuan'] ?? '-'),
                    _buildInvoiceRow('Produk', data['produk'] ?? '-'),
                    _buildInvoiceRow('Harga', _formatRupiah(data['harga'] ?? 0)),
                ]
            )
        )
    );
}
```

---

## Build & Deploy Commands

```bash
# Build APK
flutter build apk --debug

# Install to connected phone
adb install -r build/app/outputs/flutter-apk/app-debug.apk
```

---

## Debug Log Locations

| Log | Location |
|-----|---------|
| Kuota debug | `C:\laragon\www\payment\api\debug_kuota.log` |
| Pulsa debug | `C:\laragon\www\payment\api\debug_pulsa.log` |
| PHP errors | `C:\laragon\www\payment\api\php_errors.log` |

---

## Quick Reference

- **Base URL (Debug)**: `http://192.168.1.58/payment`
- **Base URL (Production)**: `http://invitationai.my.id`
- **Flutter source**: `C:\Projects\ppobexpress_app`
- **PHP source**: `C:\laragon\www\payment`

---

## Common Error Messages & Solutions

| Error | Cause | Solution |
|-------|-------|---------|
| FormatException at character 1 | PHP returns HTML | Add non-JSON detection code |
| Parse error unexpected "66" | Array key not quoted | Use `'66'` not `66` |
| Undefined variable $status | Variable not initialized | Add `$status = 'pending'` |
| Server error (empty) | Server not responding | Check if Laragon is running |
| Connection refused | Wrong IP | Update baseUrl in app_config.dart |

---

**IMPORTANT**: Before making ANY changes to PHP files, always:
1. Read the entire file first to understand the flow
2. Check existing patterns for the same operation
3. Test with a simple request before complex operations
4. Check debug log for errors