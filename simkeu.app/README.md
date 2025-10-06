# SIMKEU API Documentation

## Authentication
Base URL: `/api/auth`

### Login
```http
POST /login
```
**Required Parameters:**
- `email` (string) - Email address
- `password` (string) - Password

### Register
```http
POST /register
```
**Required Parameters:**
- `name` (string) - Full name
- `email` (string) - Email address
- `password` (string) - Password (min: 8 characters)
- `password_confirmation` (string) - Password confirmation

## Admin Dashboard
Base URL: `/api/admin/dashboard`

### Get Dashboard Data
```http
GET /dashboard
```
**Optional Parameters:**
- `year` (integer) - Filter by year
- `month` (integer) - Filter by month

## Pemasukan Mahasiswa
Base URL: `/api/admin/pemasukan/mahasiswa`

### Jenis Pembayaran
```http
GET|POST|PUT|DELETE /jenis-pembayaran
```
**Required Parameters:**
- `nama` (string) - Payment type name
- `kode` (string) - Unique payment code
- `nominal` (decimal) - Payment amount

**Optional Parameters:**
- `keterangan` (string) - Description
- `status` (boolean) - Active status

### Tagihan
```http
GET|POST|PUT|DELETE /tagihan
```
**Required Parameters:**
- `mahasiswa_id` (integer) - Student ID
- `jenis_pembayaran_id` (integer) - Payment type ID
- `nominal` (decimal) - Bill amount
- `tgl_jatuh_tempo` (date) - Due date

**Optional Parameters:**
- `keterangan` (string) - Bill description
- `status` (enum) - Bill status

### Cek Tagihan
```http
GET|POST /cek-tagihan
```
**Required Parameters:**
- `nim` (string) - Student ID number
- `jenis_pembayaran_id` (integer) - Payment type ID

### Pembayaran
```http
GET|POST|PUT|DELETE /pembayaran
```
**Required Parameters:**
- `tagihan_id` (integer) - Bill ID
- `nominal` (decimal) - Payment amount
- `tgl_bayar` (date) - Payment date
- `metode_pembayaran` (enum) - Payment method

**Optional Parameters:**
- `bukti_pembayaran` (file) - Payment proof
- `keterangan` (string) - Payment notes

### Pemasukan Pengeluaran
```http
GET|POST|PUT|DELETE /pemasukan-pengeluaran
```
**Required Parameters:**
- `jenis` (enum) - Transaction type (income/expense)
- `nominal` (decimal) - Amount
- `tanggal` (date) - Transaction date

**Optional Parameters:**
- `keterangan` (string) - Transaction notes

## Saldo Management
Base URL: `/api/admin/saldo`

### Kategori
```http
GET|POST|PUT|DELETE /kategori
```
**Required Parameters:**
- `nama` (string) - Category name
- `jenis` (enum) - Category type

**Optional Parameters:**
- `keterangan` (string) - Category description

## User Management
Base URL: `/api/admin`

### Users
```http
GET|POST|PUT|DELETE /users
```
**Required Parameters:**
- `name` (string) - Full name
- `email` (string) - Email address
- `password` (string) - Password
- `role_id` (integer) - Role ID

### Roles
```http
GET|POST|PUT|DELETE /role
```
**Required Parameters:**
- `name` (string) - Role name
- `permissions` (array) - Permission list

### Mahasiswa
```http
GET|POST|PUT|DELETE /mahasiswa
```
**Required Parameters:**
- `nim` (string) - Student ID number
- `nama` (string) - Full name
- `prodi_id` (integer) - Study program ID
- `th_akademik_id` (integer) - Academic year ID

**Optional Parameters:**
- `email` (string) - Email address
- `no_hp` (string) - Phone number
- `alamat` (text) - Address

### Program Studi
```http
GET|POST|PUT|DELETE /prodi
```
**Required Parameters:**
- `nama` (string) - Program name
- `kode` (string) - Program code

### Tahun Akademik
```http
GET|POST|PUT|DELETE /th-akademik
```
**Required Parameters:**
- `tahun` (string) - Academic year
- `semester` (enum) - Semester (odd/even)
- `status` (boolean) - Active status

### Form Schedule
```http
GET|POST|PUT|DELETE /form-schedule
```
**Required Parameters:**
- `nama` (string) - Schedule name
- `tanggal_mulai` (datetime) - Start date
- `tanggal_selesai` (datetime) - End date

### Profil
```http
GET|PUT /profil
```
**Optional Parameters:**
- `name` (string) - Full name
- `email` (string) - Email address
- `password` (string) - New password
- `password_confirmation` (string) - Password confirmation

### References
```http
GET|POST|PUT|DELETE /ref
```
**Required Parameters:**
- `kategori` (string) - Reference category
- `nama` (string) - Reference name
- `nilai` (string) - Reference value

## Helper
Base URL: `/api/helper`

### Get Enum Values
```http
GET /get-enum-values
```
**Required Parameters:**
- `table` (string) - Table name
- `column` (string) - Column name

## Response Format
All API responses follow this structure:
```json
{
    "status": boolean,
    "message": string,
    "data": object|array|null
}
```

## Authentication
All endpoints except login/register require Bearer token:
```http
Authorization: Bearer <token>
```

## Error Codes
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error
