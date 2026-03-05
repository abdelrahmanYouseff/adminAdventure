# Orders API

Base URL: `/api` (مثلاً: `https://your-domain.com/api`)

---

## Endpoints

| Method | Endpoint | الوصف |
|--------|----------|--------|
| GET | `/api/orders` | قائمة الطلبات (مع فلترة وصفحات) |
| POST | `/api/orders` | إنشاء طلب جديد (وفاتورة) |
| GET | `/api/orders/{id}` | تفاصيل طلب واحد |
| PATCH | `/api/orders/{id}/status` | تحديث حالة الطلب |
| DELETE | `/api/orders/{id}` | حذف طلب |

---

## البيانات الإلزامية لإنشاء طلب (POST /api/orders)

يجب إرسال **كل** الحقول التالية (ما لم يُذكر اختياري):

| الحقل | النوع | إلزامي | الوصف |
|-------|--------|--------|--------|
| **customer_name** | string | ✅ نعم | اسم العميل (حد أقصى 255) |
| **total_amount** | number | ✅ نعم | إجمالي المبلغ (≥ 0) |
| **currency** | string | ✅ نعم | العملة: `SAR` أو `USD` أو `EUR` فقط |
| **payment_method** | string | ✅ نعم | طريقة الدفع: `credit_card` أو `cash` أو `bank_transfer` أو `paypal` أو `noon` |
| **items** أو **product_items** | array | ✅ نعم | تفاصيل المنتجات (واحد منهما يكفي) |

### تفاصيل `items` (بديل 1)

مصفوفة عناصر، كل عنصر:

| الحقل | إلزامي | النوع |
|-------|--------|--------|
| name | ✅ | string (اسم المنتج) |
| quantity | ✅ | integer ≥ 1 |
| price | ✅ | number ≥ 0 |

مثال:

```json
{
  "customer_name": "أحمد محمد",
  "total_amount": 3826.08,
  "currency": "SAR",
  "payment_method": "noon",
  "items": [
    { "name": "التحدي المعاكس - ايجار يومي", "quantity": 3, "price": 2869.56 },
    { "name": "طاولة جينجا - ايجار يومي", "quantity": 1, "price": 956.52 }
  ]
}
```

### تفاصيل `product_items` (بديل 2)

مصفوفة مرتبطة بجدول المنتجات، كل عنصر:

| الحقل | إلزامي | النوع |
|-------|--------|--------|
| product_id | ✅ | integer (موجود في جدول products) |
| quantity | ✅ | integer ≥ 1 |
| price | ✅ | number ≥ 0 |

مثال:

```json
{
  "customer_name": "أحمد محمد",
  "total_amount": 3826.08,
  "currency": "SAR",
  "payment_method": "noon",
  "product_items": [
    { "product_id": 1, "quantity": 3, "price": 2869.56 },
    { "product_id": 2, "quantity": 1, "price": 956.52 }
  ]
}
```

---

## البيانات الاختيارية (POST /api/orders)

| الحقل | النوع | الوصف |
|-------|--------|--------|
| customer_email | string (email) | بريد العميل |
| customer_phone | string | رقم الهاتف (حد أقصى 20) |
| address | string | العنوان |
| activity_date | string (date) | تاريخ الفعالية (YYYY-MM-DD) |
| payment_id | string | معرف الدفع من بوابة الدفع |
| status | string | `pending` \| `processing` \| `paid` \| `cancelled` \| `refunded` (افتراضي: pending) |
| notes | string | ملاحظات (حد أقصى 1000) |
| user_id | integer | معرف المستخدم في جدول users (إن وُجد؛ افتراضي: 1) |

---

## GET /api/orders — قائمة الطلبات

**Query (اختياري):**

| المعامل | الوصف |
|---------|--------|
| search | بحث في رقم الطلب، اسم العميل، payment_id، البريد |
| status | فلتر: pending, paid, cancelled, refunded, أو all |
| payment_method | فلتر: noon, cash, ... أو all |
| currency | فلتر: SAR, USD, EUR أو all |
| page | رقم الصفحة (تفعيل pagination) |

الاستجابة تحتوي على `data` (كائن pagination يحتوي على `data` للطلبات و `current_page`, `last_page`, إلخ).

---

## PATCH /api/orders/{id}/status — تحديث الحالة

**Body (JSON):**

```json
{
  "status": "paid"
}
```

القيم المسموحة: `pending`, `processing`, `paid`, `cancelled`, `refunded`.

---

## ملخص: الحد الأدنى لإنشاء طلب

أقل طلب صالح (بيانات إلزامية فقط):

- **customer_name**
- **total_amount**
- **currency** = `SAR` | `USD` | `EUR`
- **payment_method** = `credit_card` | `cash` | `bank_transfer` | `paypal` | `noon`
- **items** (مصفوفة: name, quantity, price) **أو** **product_items** (مصفوفة: product_id, quantity, price)

الباقي اختياري.
