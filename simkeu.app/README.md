# SIMKEU API Documentation

## Overview
SIMKEU (Sistem Keuangan) is a financial management system API.

## Base URL
```
http://your-domain.com/api
```

## Authentication (`AuthController`)

### Login
```http
POST /auth/login
```

**Request Body:**
```json
{
    "username": "johndoe",
    "password": "yourpassword"
}
```

**Success Response:**
```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "admin"
        }
    }
}
```

### Register
```http
POST /auth/register
```

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "user@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

## Dashboard APIs
Base URL: `/admin/dashboard`

### Get Dashboard Statistics
```http
GET /admin/dashboard
```

**Query Parameters:**
- `year` (optional) - Filter by year
- `month` (optional) - Filter by month

**Success Response:**
```json
{
    "status": true,
    "message": "Dashboard data retrieved successfully",
    "data": {
        "total_income": 50000000,
        "total_expense": 30000000,
        "total_students": 500,
        "recent_transactions": [...]
    }
}
```

## Pemasukan Mahasiswa APIs
Base URL: `/admin/pemasukan/mahasiswa`

### 1. Jenis Pembayaran

#### List Jenis Pembayaran
```http
GET /jenis-pembayaran
```

**Query Parameters:**
- `search` (optional) - Search by name
- `status` (optional) - Filter by status
- `page` (optional) - Page number
- `per_page` (optional) - Items per page

**Success Response:**
```json
{
    "status": true,
    "message": "Data retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "nama": "SPP",
                "kode": "SPP-001",
                "nominal": 1500000,
                "keterangan": "Biaya SPP Semester",
                "status": true
            }
        ],
        "total": 10,
        "per_page": 10
    }
}
```

#### Create Jenis Pembayaran
```http
POST /jenis-pembayaran
```

**Request Body:**
```json
{
    "nama": "SPP",
    "kode": "SPP-001",
    "nominal": 1500000,
    "keterangan": "Biaya SPP Semester",
    "status": true
}
```

### 2. Tagihan

#### List Tagihan
```http
GET /tagihan
```

**Query Parameters:**
- `mahasiswa_id` (optional) - Filter by student
- `status` (optional) - Filter by status
- `page` (optional) - Page number

**Success Response:**
```json
{
    "status": true,
    "message": "Data retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "mahasiswa_id": 1,
                "jenis_pembayaran_id": 1,
                "nominal": 1500000,
                "tgl_jatuh_tempo": "2025-12-31",
                "status": "pending",
                "mahasiswa": {
                    "id": 1,
                    "nim": "12345",
                    "nama": "John Doe"
                }
            }
        ],
        "total": 100,
        "per_page": 10
    }
}
```

#### Create Tagihan
```http
POST /tagihan
```

**Request Body:**
```json
{
    "mahasiswa_id": 1,
    "jenis_pembayaran_id": 1,
    "nominal": 1500000,
    "tgl_jatuh_tempo": "2025-12-31",
    "keterangan": "Tagihan SPP Semester Ganjil"
}
```

### 3. Pembayaran

#### Create Pembayaran
```http
POST /pembayaran
```

**Request Body:**
```json
{
    "tagihan_id": 1,
    "nominal": 1500000,
    "tgl_bayar": "2025-10-06",
    "metode_pembayaran": "transfer",
    "bukti_pembayaran": "[file]",
    "keterangan": "Pembayaran SPP"
}
```

## Saldo Management
Base URL: `/admin/saldo`

### Kategori

#### List Kategori
```http
GET /kategori
```

**Success Response:**
```json
{
    "status": true,
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "nama": "Operasional",
            "jenis": "pengeluaran",
            "keterangan": "Biaya operasional kampus"
        }
    ]
}
```

## User Management
Base URL: `/admin`

### 1. Users

#### List Users
```http
GET /users
```

**Query Parameters:**
- `search` (optional) - Search by name or email
- `role_id` (optional) - Filter by role
- `page` (optional) - Page number

### 2. Roles

#### List Roles
```http
GET /role
```

**Success Response:**
```json
{
    "status": true,
    "message": "Data retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Admin",
            "permissions": ["manage_users", "manage_finance"]
        }
    ]
}
```

### 3. Mahasiswa

#### List Mahasiswa
```http
GET /mahasiswa
```

**Query Parameters:**
- `search` (optional) - Search by NIM or name
- `prodi_id` (optional) - Filter by study program
- `th_akademik_id` (optional) - Filter by academic year
- `page` (optional) - Page number

## Helper APIs
Base URL: `/helper`

### Get Enum Values
```http
GET /get-enum-values
```

**Query Parameters:**
- `table` (required) - Table name
- `column` (required) - Column name

**Success Response:**
```json
{
    "status": true,
    "message": "Enum values retrieved successfully",
    "data": ["option1", "option2", "option3"]
}
```

## Common HTTP Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation error
- `500 Internal Server Error` - Server error

## Authentication

All API endpoints (except login and register) require authentication using Bearer token:

```http
Authorization: Bearer your-token-here
```

## Response Format

All API responses follow this standard format:

```json
{
    "status": true|false,
    "message": "Response message",
    "data": null|object|array
}
```

## Error Response

When an error occurs, the response will follow this format:

```json
{
    "status": false,
    "message": "Error message",
    "errors": {
        "field": ["Error description"]
    }
}
```

## Rate Limiting

API requests are limited to 60 requests per minute per IP address. When exceeded, you'll receive a 429 Too Many Requests response.

## Versioning

Current API Version: v1

## Support

For technical support or questions, please contact:
- Email: support@simkeu.com
- Documentation: https://docs.simkeu.com
