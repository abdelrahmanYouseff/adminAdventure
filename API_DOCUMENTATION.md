# Orders API Documentation

## Create Order API

### Endpoint
```
POST /api/orders
```

### Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body
```json
{
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "customer_phone": "+966501234567",
    "total_amount": 299.99,
    "currency": "SAR",
    "payment_method": "credit_card",
    "payment_id": "PAY_123456789",
    "status": "pending",
    "items": [
        {
            "name": "Camping Tent",
            "quantity": 1,
            "price": 149.99
        },
        {
            "name": "Sleeping Bag",
            "quantity": 2,
            "price": 79.99
        }
    ],
    "notes": "Customer requested early delivery",
    "user_id": 1
}
```

### Required Fields
- `customer_name` (string, max:255)
- `total_amount` (numeric, min:0)
- `currency` (string, in:SAR,USD,EUR)
- `payment_method` (string, in:credit_card,cash,bank_transfer,paypal,noon)
- `items` (array, min:1)
  - `items.*.name` (string, max:255)
  - `items.*.quantity` (integer, min:1)
  - `items.*.price` (numeric, min:0)

### Optional Fields
- `customer_email` (email, max:255)
- `customer_phone` (string, max:20)
- `payment_id` (string, max:255)
- `status` (string, in:pending,processing,paid,cancelled,refunded) - defaults to 'pending'
- `notes` (string, max:1000)
- `user_id` (integer, exists:users,id)

### Response (Success - 201)
```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "id": 1,
        "user_id": 1,
        "customer_name": "John Doe",
        "customer_email": "john@example.com",
        "customer_phone": "+966501234567",
        "order_number": "ORD-202410-0001",
        "total_amount": "299.99",
        "currency": "SAR",
        "payment_method": "credit_card",
        "payment_id": "PAY_123456789",
        "status": "pending",
        "items": [
            {
                "name": "Camping Tent",
                "quantity": 1,
                "price": 149.99
            },
            {
                "name": "Sleeping Bag",
                "quantity": 2,
                "price": 79.99
            }
        ],
        "notes": "Customer requested early delivery",
        "created_at": "2024-10-14T10:00:00.000000Z",
        "updated_at": "2024-10-14T10:00:00.000000Z",
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com"
        }
    }
}
```

### Response (Validation Error - 422)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "customer_name": [
            "The customer name field is required."
        ],
        "total_amount": [
            "The total amount must be a number."
        ],
        "items": [
            "The items field is required."
        ]
    }
}
```

## Get Orders API

### Endpoint
```
GET /api/orders
```

### Query Parameters
- `search` (optional) - Search by order number, customer name, or payment ID
- `status` (optional) - Filter by status (pending,processing,paid,cancelled,refunded)
- `payment_method` (optional) - Filter by payment method
- `currency` (optional) - Filter by currency
- `page` (optional) - Page number for pagination

### Example Request
```
GET /api/orders?search=John&status=pending&page=1
```

### Response (Success - 200)
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "user_id": 1,
                "customer_name": "John Doe",
                "customer_email": "john@example.com",
                "customer_phone": "+966501234567",
                "order_number": "ORD-202410-0001",
                "total_amount": "299.99",
                "currency": "SAR",
                "payment_method": "credit_card",
                "payment_id": "PAY_123456789",
                "status": "pending",
                "items": [...],
                "notes": "Customer requested early delivery",
                "created_at": "2024-10-14T10:00:00.000000Z",
                "updated_at": "2024-10-14T10:00:00.000000Z",
                "user": {...},
                "invoice": null
            }
        ],
        "first_page_url": "http://localhost:8000/api/orders?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/orders?page=1",
        "links": [...],
        "next_page_url": null,
        "path": "http://localhost:8000/api/orders",
        "per_page": 15,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

## Get Single Order API

### Endpoint
```
GET /api/orders/{id}
```

### Response (Success - 200)
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 1,
        "customer_name": "John Doe",
        "customer_email": "john@example.com",
        "customer_phone": "+966501234567",
        "order_number": "ORD-202410-0001",
        "total_amount": "299.99",
        "currency": "SAR",
        "payment_method": "credit_card",
        "payment_id": "PAY_123456789",
        "status": "pending",
        "items": [...],
        "notes": "Customer requested early delivery",
        "created_at": "2024-10-14T10:00:00.000000Z",
        "updated_at": "2024-10-14T10:00:00.000000Z",
        "user": {...},
        "invoice": null
    }
}
```

### Response (Not Found - 404)
```json
{
    "message": "No query results for model [App\\Models\\Order] 999"
}
```

## Testing the API

### Using cURL
```bash
# Create Order
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "total_amount": 299.99,
    "currency": "SAR",
    "payment_method": "credit_card",
    "items": [
        {
            "name": "Camping Tent",
            "quantity": 1,
            "price": 149.99
        }
    ]
  }'

# Get Orders
curl -X GET http://localhost:8000/api/orders \
  -H "Accept: application/json"

# Get Single Order
curl -X GET http://localhost:8000/api/orders/1 \
  -H "Accept: application/json"
```

### Using Postman
1. Set method to POST/GET
2. Set URL to `http://localhost:8000/api/orders`
3. Add headers: `Content-Type: application/json`
4. Add request body (for POST requests)
5. Send request

## Order Status Values
- `pending` - Order is waiting for processing
- `processing` - Order is being processed
- `paid` - Order is paid and confirmed
- `cancelled` - Order has been cancelled
- `refunded` - Order has been refunded

## Payment Method Values
- `credit_card` - Credit Card payment
- `cash` - Cash payment
- `bank_transfer` - Bank transfer
- `paypal` - PayPal payment
- `noon` - Noon payment

## Currency Values
- `SAR` - Saudi Riyal
- `USD` - US Dollar
- `EUR` - Euro
