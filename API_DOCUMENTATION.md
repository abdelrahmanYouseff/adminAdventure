# API Documentation - Adventure World

## Orders API

### 1. إنشاء طلب جديد (Create Order)

**Endpoint:** `POST /api/orders`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "customer_name": "أحمد محمد",
    "customer_email": "ahmed@example.com",
    "customer_phone": "+966501234567",
    "total_amount": 150.00,
    "currency": "SAR",
    "payment_method": "credit_card",
    "payment_id": "pay_123456789",
    "status": "pending",
    "items": [
        {
            "name": "باقة المغامرة الكاملة",
            "quantity": 2,
            "price": 75.00
        }
    ],
    "notes": "ملاحظات إضافية",
    "user_id": 1
}
```

**Response (Success - 201):**
```json
{
    "success": true,
    "message": "تم إنشاء الطلب والفاتورة بنجاح",
    "data": {
        "order": {
            "id": 1,
            "order_number": "ORD-2024-001",
            "customer_name": "أحمد محمد",
            "customer_email": "ahmed@example.com",
            "customer_phone": "+966501234567",
            "total_amount": 150.00,
            "currency": "SAR",
            "payment_method": "credit_card",
            "payment_id": "pay_123456789",
            "status": "pending",
            "items": [
                {
                    "name": "باقة المغامرة الكاملة",
                    "quantity": 2,
                    "price": 75.00
                }
            ],
            "notes": "ملاحظات إضافية",
            "invoice_id": 1,
            "created_at": "2024-01-01T12:00:00.000000Z",
            "updated_at": "2024-01-01T12:00:00.000000Z",
            "user": {
                "id": 1,
                "name": "Admin User"
            },
            "invoice": {
                "id": 1,
                "invoice_number": "INV-2024-001",
                "amount": 150.00,
                "status": "pending",
                "payment_method": "credit_card"
            }
        },
        "invoice": {
            "id": 1,
            "invoice_number": "INV-2024-001",
            "amount": 150.00,
            "status": "pending",
            "payment_method": "credit_card",
            "issued_at": "2024-01-01T12:00:00.000000Z",
            "due_date": "2024-01-31T12:00:00.000000Z"
        }
    }
}
```

**Response (Validation Error - 422):**
```json
{
    "success": false,
    "message": "خطأ في البيانات المرسلة",
    "errors": {
        "customer_name": ["حقل اسم العميل مطلوب."],
        "total_amount": ["حقل المبلغ الإجمالي مطلوب."]
    }
}
```

**Response (Server Error - 500):**
```json
{
    "success": false,
    "message": "حدث خطأ أثناء إنشاء الطلب",
    "error": "Database connection failed"
}
```

### 2. جلب جميع الطلبات (Get All Orders)

**Endpoint:** `GET /api/orders`

**Query Parameters:**
- `search` (optional): البحث في رقم الطلب أو اسم العميل
- `status` (optional): فلترة حسب الحالة
- `payment_method` (optional): فلترة حسب طريقة الدفع
- `currency` (optional): فلترة حسب العملة
- `page` (optional): رقم الصفحة

**Example:**
```
GET /api/orders?search=أحمد&status=pending&page=1
```

**Response:**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "order_number": "ORD-2024-001",
                "customer_name": "أحمد محمد",
                "total_amount": 150.00,
                "status": "pending",
                "created_at": "2024-01-01T12:00:00.000000Z"
            }
        ],
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

### 3. جلب طلب محدد (Get Single Order)

**Endpoint:** `GET /api/orders/{id}`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "order_number": "ORD-2024-001",
        "customer_name": "أحمد محمد",
        "customer_email": "ahmed@example.com",
        "customer_phone": "+966501234567",
        "total_amount": 150.00,
        "currency": "SAR",
        "payment_method": "credit_card",
        "status": "pending",
        "items": [
            {
                "name": "باقة المغامرة الكاملة",
                "quantity": 2,
                "price": 75.00
            }
        ],
        "notes": "ملاحظات إضافية",
        "invoice_id": 1,
        "created_at": "2024-01-01T12:00:00.000000Z",
        "updated_at": "2024-01-01T12:00:00.000000Z",
        "user": {
            "id": 1,
            "name": "Admin User"
        },
        "invoice": {
            "id": 1,
            "invoice_number": "INV-2024-001",
            "amount": 150.00,
            "status": "pending"
        }
    }
}
```

### 4. تحديث حالة الطلب (Update Order Status)

**Endpoint:** `PATCH /api/orders/{id}/status`

**Request Body:**
```json
{
    "status": "paid"
}
```

**Response:**
```json
{
    "success": true,
    "message": "تم تحديث حالة الطلب بنجاح",
    "data": {
        "id": 1,
        "order_number": "ORD-2024-001",
        "status": "paid",
        "updated_at": "2024-01-01T13:00:00.000000Z"
    }
}
```

## Validation Rules

### Order Fields:
- `customer_name`: مطلوب، نص، أقصى 255 حرف
- `customer_email`: اختياري، بريد إلكتروني صحيح، أقصى 255 حرف
- `customer_phone`: اختياري، نص، أقصى 20 حرف
- `total_amount`: مطلوب، رقم، أكبر من أو يساوي 0
- `currency`: مطلوب، واحد من: SAR, USD, EUR
- `payment_method`: مطلوب، واحد من: credit_card, cash, bank_transfer, paypal, noon
- `payment_id`: اختياري، نص، أقصى 255 حرف
- `status`: اختياري، واحد من: pending, processing, paid, cancelled, refunded
- `items`: مطلوب، مصفوفة، أقصى عنصر واحد
- `items.*.name`: مطلوب، نص، أقصى 255 حرف
- `items.*.quantity`: مطلوب، رقم صحيح، أكبر من 0
- `items.*.price`: مطلوب، رقم، أكبر من أو يساوي 0
- `notes`: اختياري، نص، أقصى 1000 حرف
- `user_id`: اختياري، رقم صحيح موجود في جدول المستخدمين

## Error Codes

- `200`: نجح الطلب
- `201`: تم إنشاء الطلب بنجاح
- `422`: خطأ في البيانات المرسلة
- `500`: خطأ في الخادم

## Base URL

```
http://127.0.0.1:8000/api
```
