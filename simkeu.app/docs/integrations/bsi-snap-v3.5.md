# Integrasi BSI Smart Billing BI-SNAP v3.5

Dokumen ini menjelaskan implementasi BSI pada SIMKEU dan kontrak server-to-server untuk SIAKAD. Acuan protokol bank adalah *Specification Host to Host - BI SNAP - Smart Billing BPI v3.5* dan sample source BSI v3.5. Sample dipakai sebagai acuan format, bukan disalin langsung.

## Alur

1. SIAKAD mengambil tagihan mahasiswa dari SIMKEU.
2. Mahasiswa memilih tagihan dan nominal per tagihan di SIAKAD.
3. SIAKAD membuat payment order di SIMKEU dengan `request_id` unik.
4. SIMKEU mengembalikan nomor pembayaran BSI dan nomor VA antarbank.
5. BSI melakukan Auth, Inquiry, lalu Payment ke endpoint BI-SNAP SIMKEU.
6. Payment valid disimpan sebagai transaksi BSI berstatus `success` tanpa menulis ledger SIMKEU.
7. Advice mengembalikan respons Payment yang sama persis. Rekonsiliasi dicatat dan dicocokkan dengan transaksi.

## Konfigurasi

Konfigurasi dilakukan melalui menu **Pengaturan > Konfig BSI** oleh admin.

Halaman dibagi menjadi tab **Ringkasan**, **Konfigurasi H2H**, **Docs API**, dan **Simulasi Pembayaran**. Konfigurasi H2H memiliki subtab Identitas Biller, Kredensial Host-to-Host, Keamanan & Operasional, serta Data Portal BSI. API key SIAKAD dan seluruh endpoint integrasi dikelola dari tab Docs API.

- `KODE BPI`: 4 digit dari BSI.
- `Client ID` dan `Client Secret`: kredensial BI-SNAP.
- `BPI RSA Public Key`: public key untuk memverifikasi signature Auth.
- `Reconciliation Secret`: secret checksum rekonsiliasi; bila kosong memakai Client Secret.
- `Email Tujuan Rekonsiliasi`: alamat yang ditempel pada kolom email rekonsiliasi di portal BSI.
- `Expiry`: masa berlaku payment order.
- `Timestamp tolerance`: toleransi waktu request Auth/transaksi.
- `IP allowlist`: gabungan alamat IP yang tercantum pada tutorial dan spesifikasi v3.5. Whitelist di Cloudflare/firewall tetap direkomendasikan.
- `API Key SIAKAD`: dibuat/dirotasi dari halaman konfigurasi dan hanya tampil sekali.
- `Mode Uji`: bila aktif, semua NIM tetap dapat dilayani dan setiap payment order baru disimpan dengan `data_test=true`.

Secret disimpan terenkripsi. API key SIAKAD hanya disimpan sebagai hash SHA-256.

### Asal kredensial dan onboarding BSI

Dokumen Specification menyebut biller akan memperoleh KODE BPI, credential, dan RSA public key. Tutorial v3.5 memperjelas alur teknisnya: `CLIENT_ID` dan `CLIENT_SECRET` ditentukan oleh biller lalu nilai yang sama ditempel ke portal SmartBilling. Implementasi SIMKEU mengikuti Tutorial tersebut:

1. Daftarkan email institusi di `https://sandbox.bpi.co.id`, aktivasi email, lalu sampaikan email tersebut ke tim BSI agar akun di-assign ke dashboard biller.
2. KODE BPI empat digit dan BPI RSA Public Key diperoleh dari tim/portal BSI; keduanya tidak diterbitkan SIMKEU.
3. Pada **Konfig BSI > Konfigurasi H2H > Kredensial Host-to-Host**, terbitkan Client ID dan Client Secret. Keduanya disimpan terenkripsi dan dapat ditampilkan atau disalin kembali oleh admin untuk dimasukkan ke portal BSI.
4. Terbitkan Secret Rekonsiliasi dari bagian yang sama dan salin sebagai secret key checksum rekonsiliasi pada portal BSI.
5. Pilih skema **Close amount**, lalu masukkan URL Auth, Inquiry, Payment, Advice, dan Webservice Rekonsiliasi dari subtab Data Portal BSI.
6. Jalankan Flagging/SIT di sandbox. Setelah lulus, daftar/aktifkan akun production di `https://bsi.bpi.co.id` dan ulangi konfigurasi menggunakan endpoint production.

### Kontrol operasional SIT

Subtab **Keamanan & Operasional** menyediakan kontrol untuk mengaktifkan endpoint H2H, menerapkan IP whitelist, memverifikasi signature, menyimpan body request/response, menandai seluruh payment order baru sebagai data uji, serta melayani VA uji berawalan `9999` yang sudah dibuat di SIMKEU. Tersedia juga simulasi DB Error untuk endpoint transaksi saja (`5002499`/`5002599`) atau seluruh endpoint termasuk Auth (`5007399`). Seluruh simulasi harus dinonaktifkan kembali setelah pengujian BSI selesai.

Customer number VA memakai NIM mahasiswa setelah karakter titik dan spasi dihapus. Contoh `2020.02.02.0202` menjadi `202002020202`. Hanya satu payment order aktif yang diperbolehkan untuk satu NIM; nomor yang sama dapat digunakan kembali setelah order sebelumnya tidak lagi aktif.

Endpoint menormalkan `customerNo` yang dikirim sebagai NIM maupun yang sudah berprefix KODE BPI. `virtualAccountNo` tetap divalidasi terhadap kedua bentuk request SmartBilling tersebut, sedangkan response selalu dikembalikan dalam format kanonis KODE BPI + NIM tanpa penggandaan prefix.

Menu **Konfig BSI** menyediakan tab **Log Messaging** untuk menelusuri event, kode respons, validitas signature, durasi, IP, serta detail request/response. Tab **Rekonsiliasi** menampilkan checksum dan hasil pencocokan laporan BSI dengan transaksi SIMKEU. Halaman pembayaran BSI operasional tidak menampilkan transaksi bertanda `data_test=true`; data tersebut hanya tampil pada Simulasi Pembayaran.

Rotasi Client Secret langsung membuat signature dengan secret lama tidak valid. Koordinasikan rotasi dengan tim BSI dan segera perbarui portal SmartBilling.

Admin dapat menguji pembuatan payment order melalui tab **Simulasi Pembayaran** pada menu yang sama. Nominal per tagihan dapat diubah sampai batas `tersedia` (sisa resmi dikurangi reservasi BSI). Simulasi membuat transaksi `pending` nyata dan menyediakan tombol pembatalan untuk melepaskan reservasinya. Tabel riwayat pada tab ini hanya menampilkan transaksi dengan `data_test=true`.

## API SIAKAD

Payment order SIAKAD disimpan sebagai transaksi BSI standalone. Status `success` berarti pembayaran sudah dikonfirmasi BSI; `transferred=true` berarti transaksi sudah disinkronkan ke ledger pembayaran resmi SIMKEU.

SIAKAD hanya mengirim `request_id`, `nim`, dan `items`. Nilai `production`, `data_test`, KODE BPI/nomor pembayaran, expiry, mode Open/Close Payment, biaya admin, dan transfer otomatis selalu berasal dari **Konfig BSI SIMKEU**. Field tambahan pada body atau item ditolak dengan HTTP 422.

Header request:

| Header | Endpoint | Status | Nilai |
|---|---|---|---|
| `X-SIAKAD-API-KEY` | Semua | Wajib | API key dari Konfig BSI |
| `Accept` | Semua | Wajib | `application/json` |
| `Content-Type` | POST payment order | Wajib | `application/json` |
| `User-Agent` | Semua | Opsional | Identitas aplikasi/versi SIAKAD |

API SIAKAD tidak memakai Bearer token maupun header signature BI-SNAP.

Contoh header umum:

```http
Accept: application/json
X-SIAKAD-API-KEY: <api-key-yang-dibuat-di-Konfig-BSI>
```

### Ambil tagihan

```http
GET /api/v1/integrations/siakad/bsi/bills/{nim}
```

Parameter path `nim` wajib. Endpoint ini tidak menerima query parameter maupun body.

```bash
curl --request GET \
  --url 'https://simkeuapp.uiidalwa.web.id/api/v1/integrations/siakad/bsi/bills/20240001' \
  --header 'Accept: application/json' \
  --header 'X-SIAKAD-API-KEY: GANTI_DENGAN_API_KEY'
```

### Ambil riwayat pembayaran

```http
GET /api/v1/integrations/siakad/bsi/payment-history/{nim}
```

Parameter path `nim` wajib. Endpoint ini hanya membaca ledger resmi
`keuangan_pembayaran`; payment order dari tabel standalone BSI tidak ikut ditampilkan.
Karena satu transaksi dapat tersimpan sebagai beberapa baris pembayaran, baris dengan
`keuangan_nota.nota` yang sama dibundel menjadi satu riwayat. Baris lama tanpa nota
menjadi bundel tersendiri dengan `nomor` pembayaran sebagai pengganti nota.

```bash
curl --request GET \
  --url 'https://simkeuapp.uiidalwa.web.id/api/v1/integrations/siakad/bsi/payment-history/20240001' \
  --header 'Accept: application/json' \
  --header 'X-SIAKAD-API-KEY: GANTI_DENGAN_API_KEY'
```

```json
{
  "status": true,
  "data": {
    "nim": "20240001",
    "total_transaksi": 1,
    "total_pembayaran": 350000,
    "riwayat": [
      {
        "nota": "130826-00001-L-123",
        "tanggal": "2026-08-13 09:00:00",
        "nim": "2024.0001",
        "total": 350000,
        "jumlah_item": 2,
        "items": [
          {
            "pembayaran_id": 101,
            "nomor": "PAY-001",
            "th_akademik_id": 25,
            "tagihan_id": 10,
            "semester": 5,
            "jumlah_sks": 1,
            "jumlah": 250000
          }
        ]
      }
    ]
  }
}
```

### Buat payment order

`request_id` harus unik dan stabil. Pengiriman ulang payload yang sama bersifat idempoten. Pengiriman ulang `request_id` yang sama dengan isi berbeda ditolak.

| Field body | Tipe | Status | Aturan |
|---|---|---|---|
| `request_id` | string | Wajib | Maksimal 255 karakter, unik, dan stabil |
| `nim` | string | Wajib | Setelah titik/spasi dihapus harus 5–12 digit |
| `items` | array | Wajib | 1–100 item |
| `items[].tagihan_id` | integer | Wajib | Unik dan berasal dari endpoint bills |
| `items[].jumlah` | number | Wajib | Minimal 0,01 dan maksimal nilai `tersedia` |

Tidak ada field body opsional. Field konfigurasi seperti `data_test`, `production`, `payment_mode`, expiry, biaya admin, status, nomor VA, dan `cara_bayar` tidak boleh dikirim SIAKAD.

```http
POST /api/v1/integrations/siakad/bsi/payment-orders
Content-Type: application/json
```

```json
{
  "request_id": "SIAKAD-20260808-000001",
  "nim": "20240001",
  "items": [
    { "tagihan_id": 10, "jumlah": 250000 },
    { "tagihan_id": 12, "jumlah": 100000 }
  ]
}
```

Respons sukses berisi:

```json
{
  "status": true,
  "created": true,
  "data": {
    "request_id": "SIAKAD-20260808-000001",
    "reference_no": "BSI-20260808-00000001",
    "customer_no": "123456789012",
    "bsi_payment_number": "5090123456789012",
    "interbank_va_number": "9005090123456789012",
    "total": "350000.00",
    "admin_fee_bearer": "institution",
    "admin_fee_amount": 2500,
    "payable_total": 350000,
    "expected_settlement_total": 347500,
    "currency": "IDR",
    "status": "pending",
    "data_test": false,
    "production": true,
    "transferred": false,
    "expired_at": "2026-08-09T10:00:00+07:00"
  }
}
```

### Cek status

```http
GET /api/v1/integrations/siakad/bsi/payment-orders/{request_id}
```

Parameter path `request_id` wajib. Endpoint ini tidak menerima query parameter maupun body.

Status penting:

- `pending`: menunggu pembayaran.
- `success`: Payment BSI valid dan sudah dikonfirmasi pada data standalone BSI.
- `expired`: nomor pembayaran kedaluwarsa.
- `cancelled`: dibatalkan sebelum dibayar.
- `needs_review`: perlu pemeriksaan staf keuangan.
- `rejected`: ditolak staf keuangan.

Gunakan `status=success` bersama `transferred=true` jika SIAKAD perlu memastikan transaksi sudah masuk ke ledger resmi SIMKEU.

### Batalkan payment order

```http
POST /api/v1/integrations/siakad/bsi/payment-orders/{request_id}/cancel
```

Parameter path `request_id` wajib dan endpoint ini tidak menerima body. Hanya transaksi `pending` yang dapat dibatalkan. Pembatalan ulang transaksi `cancelled` bersifat idempoten.

## Endpoint BSI BI-SNAP

Berikan URL berikut kepada tim BSI:

| Operasi | Method | Endpoint |
|---|---|---|
| Auth | POST | `/api/bpi-bi-snap/auth` |
| Inquiry | POST | `/api/bpi-bi-snap/inquiry` |
| Payment | POST | `/api/bpi-bi-snap/payment` |
| Advice | POST | `/api/bpi-bi-snap/advice` |
| Reconciliation | POST | `/api/bpi-bi-snap/reconciliation` |

### Auth signature

```text
stringToSign = CLIENT_ID + "|" + X-TIMESTAMP
X-SIGNATURE  = Base64(RSA-SHA256(stringToSign, BSI_PRIVATE_KEY))
```

Token berlaku 900 detik dan dapat dipakai berulang selama masih valid.

### Transaction signature

Untuk Inquiry, Payment, dan Advice:

```text
bodyHash     = lowercase(hex(SHA256(minified-json-body)))
stringToSign = UPPERCASE(METHOD) + ":" + ENDPOINT-URL + ":" + ACCESS-TOKEN + ":" + bodyHash + ":" + X-TIMESTAMP
X-SIGNATURE  = Base64(HMAC-SHA512(stringToSign, CLIENT_SECRET))
```

`ENDPOINT-URL` harus sama persis dengan path request, misalnya `/api/bpi-bi-snap/inquiry`.

Header transaksi wajib adalah `Authorization`, `X-TIMESTAMP`, `X-SIGNATURE`, `X-PARTNER-ID`, `CHANNEL-ID`, `X-EXTERNAL-ID`, `Endpoint-Url`, dan `Content-Type: application/json`. Channel v3.5 yang diterima: `6011`, `6014`, `6017`, `6027`, `6099`, dan `6199`.

Sesuai perubahan versi 3.5, implementasi tidak menghasilkan `additionalInfo`. Contoh `additionalInfo` yang masih tertinggal pada beberapa halaman PDF dianggap artefak versi sebelumnya.

### Nomor pembayaran

- BSI: `KODE_BPI` (4 digit) + `customer_no` (maks. 12 digit), maksimal 16 digit.
- Antarbank: `900` + nomor pembayaran BSI, maksimal 19 digit.
- Pada payload BI-SNAP, `partnerServiceId` dipad kiri menjadi 8 karakter dengan spasi.

### Rekonsiliasi

Checksum per item diverifikasi dengan:

```text
SHA1(nomorPembayaran + secretKey + wktRekonsiliasi + totalPembayaran + idRekon + kodeFT)
```

Dokumen BSI v3.5 mewajibkan `allChecksum`, tetapi tidak mendefinisikan rumus pembentukannya. Implementasi mewajibkan format SHA-1 untuk field tersebut, menyimpan payload lengkap untuk audit, dan menentukan `rc` dari checksum per item serta kecocokan transaksi/nominal.

Webservice rekonsiliasi tersedia di `POST /api/bpi-bi-snap/reconciliation`. Request divalidasi terhadap kode bank `451`, KODE BPI, format waktu, nominal, status rekonsiliasi, checksum per item, dan transaksi sukses di SIMKEU. Respons selalu berupa array sesuai spesifikasi, misalnya `[ {"rc":true,"idRekon":"123456"} ]`. BSI hanya memanggil otomatis satu kali; pengiriman ulang harus dilakukan manual dari SmartBilling.

## Kode respons BI-SNAP v3.5

Semua `responseCode` dikirim sebagai string. Advice menggunakan kode dan bentuk respons Payment.

Untuk kompatibilitas SmartBilling BSI, endpoint Inquiry, Payment, dan Advice mengirim seluruh
respons yang berhasil diproses melalui HTTP `200`. Hasil bisnis tetap ditentukan oleh
`responseCode` pada body JSON, misalnya `4042412` untuk Bill not found. Endpoint Auth tetap
menggunakan HTTP status autentikasi sesuai tabel.

### Auth

| HTTP | Kode | Pesan |
|---:|---|---|
| 200 | `2000000` / `2007300` | Success |
| 400 | `4007300` | Bad Request |
| 400 | `4007302` | Invalid Field Format |
| 401 | `4017300` | Unauthorized Client |
| 401 | `4017301` | Unauthorized stringToSign |
| 404 | `4047311` | Unauthorized Signature |
| 404 | `4047312` | Invalid Token |
| 500 | `5007399` | DB Error |
| 504 | `5007300` | Timeout |

### Inquiry

| HTTP | Kode | Pesan |
|---:|---|---|
| 200 | `2002400` | Success |
| 400 | `4002401` | Invalid Field Format |
| 400 | `4002402` | Field {xyz} is not exists |
| 401 | `4012400` | Unauthorized Access |
| 401 | `4012401` | Invalid Token {accessToken} |
| 404 | `4042411` | Invalid data |
| 404 | `4042412` | Bill not found |
| 404 | `4042414` | Bill already paid |
| 404 | `4042419` | Invalid Bill number format / Bill Expired |
| 404 | `4042420` | Bill Expired |
| 500 | `5002400` | General Error |
| 500 | `5002499` | DB Error |
| 504 | `5042400` / `5042468` | Timeout |

### Payment dan Advice

| HTTP | Kode | Pesan |
|---:|---|---|
| 200 | `2002500` | Success |
| 400 | `4002501` | Invalid Field Format |
| 400 | `4002502` | Field {xyz} is not exists |
| 401 | `4012500` | Unauthorized Access |
| 401 | `4012501` | Invalid Token {accessToken} |
| 404 | `4042511` | Invalid data |
| 404 | `4042512` | Bill not found |
| 404 | `4042513` | Payment Amount not valid |
| 404 | `4042514` | Bill already paid |
| 404 | `4042519` | Invalid Bill number format |
| 500 | `5002500` | General Error |
| 500 | `5002599` | DB Error |
| 504 | `5042500` / `5042568` | Timeout |

## Operasional dan keamanan

- Jangan menaruh API key SIAKAD di browser; panggil SIMKEU dari backend SIAKAD.
- Aktifkan integrasi hanya setelah validasi konfigurasi berhasil.
- Terapkan rate limiting/WAF dan whitelist IP BSI di edge.
- Pastikan waktu server sinkron (NTP).
- Pantau log SNAP dan hasil rekonsiliasi pada detail transaksi BSI.
- Rotasi API key SIAKAD atau secret bila terindikasi bocor.

## Artefak

- OpenAPI: `docs/integrations/bsi-snap-v3.5.openapi.yaml`
- Postman collection: `docs/integrations/bsi-snap-v3.5.postman_collection.json`
- Postman environment: `docs/integrations/bsi-snap-v3.5.postman_environment.json`

## Deployment

```bash
php artisan migrate --path=database/migrations/update-21 --force
php artisan optimize:clear
```

Setelah frontend dibangun/dideploy, buka **Pengaturan > Konfig BSI**, isi kredensial, buat API key SIAKAD, tekan **Validasi**, lalu aktifkan integrasi.
