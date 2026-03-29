# Digiflazz API Documentation

## Credentials (Development)
- **Username:** pehaduD7V7ro
- **Development Key:** `dev-7e3c8000-6531-11ec-b233-31d3fcbe4c0e`
- **Development IP:** 18.138.221.217
- **Production IP:** 195.88.211.130

## API Base URL
- Production: `https://api.digiflazz.com/v1/`

---

## 1. Cek Saldo Deposit

**Endpoint:** `POST /v1/cek-saldo`

**Request:**
```json
{
    "cmd": "deposit",
    "username": "pehaduD7V7ro",
    "sign": "<MD5(username + apiKey + 'depo'))>"
}
```

**Response:**
```json
{
    "data": {
        "deposit": 158776
    }
}
```

**Sign Formula:** `md5(username + apiKey + "depo")`

---

## 2. Price List

**Endpoint:** `POST /v1/price-list`

**Request Prepaid:**
```json
{
    "cmd": "prepaid",
    "username": "pehaduD7V7ro",
    "sign": "<MD5(username + apiKey + 'pricelist'))>",
    "code": "<optional: buyer_sku_code>",
    "category": "<optional>",
    "brand": "<optional>",
    "type": "<optional>"
}
```

**Request Pascabayar:**
```json
{
    "cmd": "pasca",
    "username": "pehaduD7V7ro",
    "sign": "<MD5(username + apiKey + 'pricelist'))>",
    "code": "<optional>",
    "brand": "<optional>"
}
```

**Sign Formula:** `md5(username + apiKey + "pricelist")`

**Note:** Terdapat limitasi pengecekan, simpan ke database lokal dan update berkala.

---

## 3. Deposit (Tiket Deposit)

**Endpoint:** `POST /v1/deposit`

**Request:**
```json
{
    "username": "pehaduD7V7ro",
    "amount": 200000,
    "bank": "BCA",
    "owner_name": "John Doe",
    "sign": "<MD5(username + apiKey + 'deposit'))>"
}
```

**Sign Formula:** `md5(username + apiKey + "deposit")`

**Bank Options:**
- Perorangan: Flip / ShopeePay
- Perusahaan: BCA / MANDIRI / BRI / BNI

**Minimum Amount:** Rp 200.000

**Response:**
```json
{
    "data": {
        "rc": "00",
        "bank": "BCA",
        "payment_method": "Bank Transfer",
        "account_no": "0123 4567 89",
        "notes": "A6R5UPV",
        "amount": 200000
    }
}
```

**Note:** Butuh PKS (Perjanjian Kerja Sama) dengan Digiflazz untuk aktivasi.

---

## 4. Topup / Transaksi Prepaid

**Endpoint:** `POST /v1/transaction`

**Request:**
```json
{
    "username": "pehaduD7V7ro",
    "buyer_sku_code": "xld25",
    "customer_no": "087800001233",
    "ref_id": "unique_ref_id",
    "sign": "<MD5(username + apiKey + ref_id))>",
    "testing": true,
    "max_price": "<optional: limit harga max>",
    "cb_url": "<optional: callback URL>"
}
```

**Sign Formula:** `md5(username + apiKey + ref_id)`

**Response:**
```json
{
    "data": {
        "ref_id": "unique_ref_id",
        "customer_no": "087800001233",
        "buyer_sku_code": "xld25",
        "message": "Transaksi Pending",
        "status": "Pending",
        "rc": "03",
        "sn": "<serial number>",
        "buyer_last_saldo": 990000,
        "price": 10000
    }
}
```

**Status Codes:**
- `rc: "00"` = Sukses
- `rc: "03"` = Pending (cek ulang dengan ref_id sama)
- `rc: "06"` = Gagal

---

## 5. Inquiry Tagihan Pascabayar

**Endpoint:** `POST /v1/transaction`

**Request:**
```json
{
    "commands": "inq-pasca",
    "username": "pehaduD7V7ro",
    "buyer_sku_code": "pln",
    "customer_no": "530000000003",
    "ref_id": "unique_ref_id",
    "sign": "<MD5(username + apiKey + ref_id))>",
    "testing": true
}
```

**Sign Formula:** `md5(username + apiKey + ref_id)`

**Product Codes:**
- `pln` - PLN Postpaid
- `pdam` - PDAM
- `internet` - Internet (Telkom, dll)
- `bpjs` - BPJS Kesehatan
- `multifinance` - Multifinance
- `pgas` - GAS Negara / Pertagas
- `tv` - TV (Kabel/Parabola)
- `bpjstk` - BPJSTK (Tenaga Kerja)
- `bpjstkpu` - BPJSTKPU (Penerima Upah)
- `plnnontaglist` - PLN Nontaglis
- `pdl` - Pajak Daerah Lainnya
- `emoney` - E-Money (format: `kode,nomor`)
- `samsat` - SAMSAT (format: `kode,nomor_identitas`)
- `cimahi` - PBB Cimahi (format: `kode,nomor`)
- `hp` - HP / Lainnya

### Test Cases Pascabayar

| buyer_sku_code | customer_no | Status |
|----------------|-------------|--------|
| **PLN** |||
| pln | 530000000001 | Sukses |
| pln | 530000000002 | Inquiry Gagal |
| pln | 530000000003 | Pembayaran Gagal |
| **PDAM** |||
| pdam | 1013226 | Sukses |
| pdam | 1013227 | Inquiry Gagal |
| **Internet** |||
| internet | 6391601001 | Sukses |
| internet | 6391601002 | Inquiry Gagal |
| **BPJS** |||
| bpjs | 8801234560001 | Sukses |
| bpjs | 8801234560002 | Inquiry Gagal |
| **PDL** |||
| pdl | 3298010921 | Sukses |
| pdl | 3298010922 | Inquiry Gagal |
| pdl | 4298010921 | Pending → Sukses |
| **GAS** |||
| pgas | 0110014601 | Sukses (1 Tagihan) |
| pgas | 0110014602 | Sukses (2 Tagihan) |
| pgas | 0110014603 | Inquiry Gagal |
| **TV** |||
| tv | 127246500101 | Sukses |
| tv | 127246500102 | Inquiry Gagal |
| tv | 227246500101 | Pending → Sukses |
| **BPJSTK** |||
| bpjstk | 8102051011270001 | Sukses |
| bpjstk | 8102051011270002 | Inquiry Gagal |
| **BPJSTKPU** |||
| bpjstkpu | 400000100001 | Sukses |
| bpjstkpu | 400000100002 | Inquiry Gagal |
| **PLN Nontaglis** |||
| plnnontaglist | 3225030005921 | Sukses |
| plnnontaglist | 3225030005922 | Inquiry Gagal |
| **E-Money** |||
| emoney | 082100000001 | Sukses |
| emoney | 082100000002 | Inquiry Gagal |
| **SAMSAT** |||
| samsat | 9658548523568701,0212502110170100 | Sukses |
| samsat | 9658548523568702,0212502110170100 | Inquiry Gagal |
| **HP** |||
| hp | 081234554320 | Sukses |
| hp | 081234554321 | Inquiry Gagal |

---

## 6. Bayar Tagihan Pascabayar

**Endpoint:** `POST /v1/transaction`

**Penting:**
- Anda hanya dapat melakukan pembayaran pada **tanggal yang sama** dengan tanggal inquiry
- `ref_id` harus **sama** dengan saat Inquiry

**Request:**
```json
{
    "commands": "pay-pasca",
    "username": "pehaduD7V7ro",
    "buyer_sku_code": "pln",
    "customer_no": "530000000003",
    "ref_id": "same_ref_id_as_inquiry",
    "sign": "<MD5(username + apiKey + ref_id))>",
    "testing": true
}
```

**Sign Formula:** `md5(username + apiKey + ref_id)`

**Product Codes:** Sama seperti Inquiry (pln, pdam, bpjs, dll)

**Response:**
```json
{
    "data": {
        "ref_id": "unique_ref_id",
        "customer_no": "530000000001",
        "customer_name": "Nama Pelanggan",
        "buyer_sku_code": "pln",
        "admin": 2500,
        "message": "Transaksi Sukses",
        "status": "Sukses",
        "rc": "00",
        "periode": "201901",
        "sn": "S1234554321N",
        "buyer_last_saldo": 90000,
        "price": 10000,
        "selling_price": 11000,
        "desc": {
            "tarif": "R1",
            "daya": 1300,
            "lembar_tagihan": 1,
            "detail": [
                {
                    "periode": "201901",
                    "nilai_tagihan": "8000",
                    "admin": "2500",
                    "denda": "500"
                }
            ]
        }
    }
}
```

---

## 7. Cek Status Transaksi Pascabayar

**Endpoint:** `POST /v1/transaction`

**Request:**
```json
{
    "commands": "status-pasca",
    "username": "pehaduD7V7ro",
    "buyer_sku_code": "pln",
    "customer_no": "530000000003",
    "ref_id": "unique_ref_id",
    "sign": "<MD5(username + apiKey + ref_id))>"
}
```

**Sign Formula:** `md5(username + apiKey + ref_id)`

---

## 8. Inquiry PLN (Cek Validasi ID PLN)

**Endpoint:** `POST /v1/inquiry-pln`

**Request:**
```json
{
    "username": "pehaduD7V7ro",
    "customer_no": "1234554321",
    "sign": "<MD5(username + apiKey + customer_no))>"
}
```

**Sign Formula:** `md5(username + apiKey + customer_no)`

**Response:**
```json
{
    "data": {
        "message": "Transaksi Sukses",
        "status": "Sukses",
        "rc": "00",
        "customer_no": "1234554321",
        "meter_no": "1234554321",
        "subscriber_id": "523300817840",
        "name": "DAVID",
        "segment_power": "R1 /000001300"
    }
}
```

---

## Test Results (Staging)

| Endpoint | Status | Response |
|----------|--------|----------|
| Cek Saldo | ✅ | `{"deposit":158776}` |
| Price List | ✅ | Berhasil (104KB+ data, ada limitasi) |
| Deposit | ⚠️ | RC 64 - Butuh PKS activation |
| Transaction (Prepaid) | ✅ | RC 03 - Pending (testing mode works) |
| Inquiry Pascabayar | ⚠️ | RC 02 - Gagal (perlu nomor valid) |
| Cek Status | ❌ | 403 Forbidden (belum diuji) |

---

## Response Codes

| RC | Message | Status | Transaksi | Deskripsi |
|----|---------|--------|-----------|-----------|
| 00 | Transaksi Sukses | Sukses | Ya | - |
| 01 | Timeout | Gagal | Ya | - |
| 02 | Transaksi Gagal | Gagal | Ya | - |
| 03 | Transaksi Pending | Pending | Ya | - |
| 40 | Payload Error | Gagal | Tidak | Tipe data/parameter tidak sesuai |
| 41 | Signature tidak valid | Gagal | Tidak | Cek formula sign & mode API |
| 42 | Gagal memproses API Buyer | Gagal | Tidak | Username belum sesuai |
| 43 | SKU tidak ditemukan/Non-Aktif | Gagal | Tidak | - |
| 44 | Saldo tidak cukup | Gagal | Tidak | - |
| 45 | IP tidak dikenali | Gagal | Tidak | Whitelist IP di dashboard |
| 47 | Transaksi sudah terjadi di buyer lain | Gagal | Tidak | - |
| 49 | Ref ID tidak unik | Gagal | Tidak | - |
| 50 | Transaksi Tidak Ditemukan | Gagal | Ya | - |
| 51 | Nomor Tujuan Diblokir | Gagal | Ya | - |
| 52 | Prefix Tidak Sesuai Operator | Gagal | Ya | - |
| 53 | Produk Seller Sedang Tidak Tersedia | Gagal | Ya | - |
| 54 | Nomor Tujuan Salah | Gagal | Ya | - |
| 55 | Produk Sedang Gangguan | Gagal | Ya | - |
| 57 | Jumlah Digit Kurang/Lebih | Gagal | Ya | - |
| 58 | Sedang Cut Off | Gagal | Ya | - |
| 59 | Tujuan di Luar Wilayah/Cluster | Gagal | Ya | - |
| 60 | Tagihan belum tersedia | Gagal | Ya | - |
| 61 | Belum pernah deposit | Gagal | Tidak | - |
| 62 | Seller sedang gangguan | Gagal | Tidak | - |
| 63 | Tidak support transaksi multi | Gagal | Tidak | - |
| 64 | Tarik tiket gagal | Gagal | Tidak | Coba nominal lain atau hubungi admin |
| 66 | Cut Off (Perbaikan Sistem) | Gagal | Tidak | - |
| 67 | Seller belum ter-verifikasi | Gagal | Tidak | - |
| 68 | Stok habis | Gagal | Tidak | - |
| 69 | Harga seller lebih besar | Gagal | Tidak | - |
| 70 | Timeout Dari Biller | Gagal | Ya | - |
| 71 | Produk Sedang Tidak Stabil | Gagal | Ya | - |
| 72 | Lakukan Unreg Paket Dahulu | Gagal | Ya | - |
| 73 | Kwh Melebihi Batas | Gagal | Ya | - |
| 74 | Transaksi Refund | Gagal | Ya | - |
| 80 | Akun diblokir oleh Seller | Gagal | Tidak | - |
| 81 | Seller diblokir oleh Anda | Gagal | Tidak | - |
| 82 | Akun belum ter-verifikasi | Gagal | Tidak | - |
| 83 | Limitasi pricelist | Gagal | Tidak | Max 1x per 5 menit |
| 84 | Nominal tidak valid | Gagal | Ya | - |
| 85 | Limitasi transaksi | Gagal | Ya | Coba 1 menit lagi |
| 86 | Limitasi PLN inquiry | Gagal | Ya | Coba beberapa saat lagi |
| 87 | E-money wajib kelipatan Rp 1.000 | Gagal | Tidak | - |
| 88 | Tidak dapat melakukan aksi ini | Gagal | Tidak | - |
| 99 | DF Router Issue | Pending | Ya | - |

| Endpoint | Status | Response |
|----------|--------|----------|
| Cek Saldo | ✅ | `{"deposit":158776}` |
| Price List | ✅ | Berhasil (104KB+ data, ada limitasi) |
| Deposit | ⚠️ | Butuh PKS activation |
| Transaction | ✅ | Pending/Sukses (testing mode works) |
| Inquiry Pascabayar | ⚠️ | RC 02 (Gagal - perlu nomor valid) |

---

## Implementation Notes

1. **Sign Generation:** Semua sign menggunakan MD5 dengan formula yang berbeda-beda
2. **Rate Limiting:** Price list memiliki limitasi - simpan ke database lokal
3. **PKS Required:** Deposit butuh aktivasi PKS dari Digiflazz
4. **Testing Mode:** Gunakan `testing: true` untuk development
5. **Callback/Webhook:** Bisa diset di dashboard atau pakai `cb_url` per request
6. **Pending Handling:** Transaksi pending bisa dicek ulang dengan ref_id yang sama
