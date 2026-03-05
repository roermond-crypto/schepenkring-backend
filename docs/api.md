# NautiSecure API Documentation

Base URL: `http://localhost:8000/api`

Authentication: Most protected routes use `auth:sanctum` (Bearer token).

## Active Routes (routes/api.php)
### AI & Search
#### Create Search By Image
- Method: `POST`
- Path: `/api/search-by-image`
- Request Body (JSON):
```json
{
  "image": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "similar_boats": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "status": "error",
  "message": "Image search failed",
  "details": "string"
}
```

#### Ai - Chat
- Method: `POST`
- Path: `/api/ai/chat`
- Success Response:
```json
{
  "reply": "string",
  "voice_meta": "string"
}
```
- Error Response:
```json
{
  "error": "Concierge Offline: ' . $e->getMessage()"
}
```

#### Ai - Extract Boat
- Method: `POST`
- Path: `/api/ai/extract-boat`
- Request Body (JSON):
```json
{
  "images": [],
  "images.*": "sample",
  "hint_text": "sample"
}
```
- Success Response:
```json
{
  "success": true,
  "extracted": "string"
}
```
- Error Response:
```json
{
  "error": "GEMINI_API_KEY not configured"
}
```

### Activity Logs
#### List Activity Logs
- Method: `GET`
- Path: `/api/activity-logs`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "logs": [
    {
      "id": 1
    }
  ],
  "pagination": "string"
}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Activity Logs - Stats
- Method: `GET`
- Path: `/api/activity-logs/stats`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "id": 1
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Activity Logs - User
- Method: `GET`
- Path: `/api/activity-logs/user/{userId}`
- Middleware: `auth:sanctum`
- Path Params:
  - `userId` (string)
- Success Response:
```json
[
  {
    "log_type": "string",
    "user_id": 1,
    "action": "string",
    "description": "string",
    "ip_address": "string",
    "user_agent": "string",
    "metadata": {},
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "entity_type": "string",
    "entity_id": "string",
    "entity_name": "string",
    "severity": "string",
    "old_data": {},
    "new_data": {}
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Activity Logs - My Activity
- Method: `GET`
- Path: `/api/activity-logs/my-activity`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "log_type": "string",
    "user_id": 1,
    "action": "string",
    "description": "string",
    "ip_address": "string",
    "user_agent": "string",
    "metadata": {},
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "entity_type": "string",
    "entity_id": "string",
    "entity_name": "string",
    "severity": "string",
    "old_data": {},
    "new_data": {}
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Activity Logs - Clear Old
- Method: `DELETE`
- Path: `/api/activity-logs/clear-old`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "message": "Successfully cleared {$deleted} old logs",
  "deleted_count": {
    "log_type": "string",
    "user_id": 1,
    "action": "string",
    "description": "string",
    "ip_address": "string",
    "user_agent": "string",
    "metadata": {},
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "entity_type": "string",
    "entity_id": "string",
    "entity_name": "string",
    "severity": "string",
    "old_data": {},
    "new_data": {}
  }
}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

### Analytics
#### Analytics - Track
- Method: `POST`
- Path: `/api/analytics/track`
- Success Response:
```json
{
  "status": "synced"
}
```

#### Analytics - Summary
- Method: `GET`
- Path: `/api/analytics/summary`
- Success Response:
```json
[
  {
    "external_id": "string",
    "name": "string",
    "model": "string",
    "price": "string",
    "ref_code": "string",
    "url": "string",
    "ip_address": "string",
    "user_agent": "string",
    "raw_specs": {},
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```
- Error Response:
```json
{
  "error": "string"
}
```

### Auth
#### Login
- Method: `POST`
- Path: `/api/login`
- Request Body (JSON):
```json
{
  "email": "user@example.com",
  "password": "Password123!"
}
```
- Success Response:
```json
{
  "token": "token_example",
  "id": 1,
  "name": "Jane Doe",
  "email": "user@example.com",
  "userType": "string",
  "status": "success",
  "access_level": "string",
  "permissions": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "message": "Identity could not be verified. Check credentials."
}
```

#### Register User
- Method: `POST`
- Path: `/api/register`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "accept_terms": "sample"
}
```
- Success Response:
```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "user@example.com",
  "userType": "string",
  "verification_required": true
}
```

#### Register Partner
- Method: `POST`
- Path: `/api/register/partner`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "accept_terms": "sample"
}
```
- Success Response:
```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "user@example.com",
  "userType": "string",
  "verification_required": true
}
```

#### Register Partner
- Method: `POST`
- Path: `/api/register/partner`
- Success Response:
```json
{
  "userType": "Partner",
  "id": 1,
  "verification_required": true
}
```
- Error Response:
```json
{
  "message": "Direct Insert Failed: ' . $e->getMessage()"
}
```

#### Register User
- Method: `POST`
- Path: `/api/register`
- Success Response:
```json
{
  "userType": "Customer",
  "id": 1,
  "verification_required": true
}
```
- Error Response:
```json
{
  "message": "Direct Insert Failed: ' . $e->getMessage()"
}
```

#### Register Seller
- Method: `POST`
- Path: `/api/register/seller`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "token": "token_example"
}
```
- Success Response:
```json
{
  "user": {},
  "token": "token_example"
}
```

### Bids
#### Bids - History
- Method: `GET`
- Path: `/api/bids/{id}/history`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "bids": {
    "yacht_id": "string",
    "user_id": "string",
    "amount": 1.0,
    "status": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "finalized_at": "2024-01-01T00:00:00Z",
    "finalized_by": "string"
  },
  "highestBid": "string"
}
```

#### Bids - Place
- Method: `POST`
- Path: `/api/bids/place`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "yacht_id": "sample",
  "amount": 1000.0
}
```
- Success Response:
```json
{
  "message": "Bid placed successfully.",
  "bid": "string"
}
```
- Error Response:
```json
{
  "message": "Bidding is closed. Vessel is sold."
}
```

#### Bids - Accept
- Method: `POST`
- Path: `/api/bids/{id}/accept`
- Middleware: `auth:sanctum, permission:accept bids`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Bid accepted. Vessel marked as Sold."
}
```

#### Bids - Decline
- Method: `POST`
- Path: `/api/bids/{id}/decline`
- Middleware: `auth:sanctum, permission:accept bids`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Bid declined."
}
```

#### Delete Bids
- Method: `DELETE`
- Path: `/api/bids/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Bid deleted",
  "highestBid": "string"
}
```
- Error Response:
```json
{
  "message": "Unauthorized"
}
```

#### List Bids
- Method: `GET`
- Path: `/api/bids`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "yacht_id": "string",
    "user_id": "string",
    "amount": 1.0,
    "status": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "finalized_at": "2024-01-01T00:00:00Z",
    "finalized_by": "string"
  }
]
```
- Error Response:
```json
{
  "error": "string",
  "file": "string",
  "line": "string",
  "trace": "string"
}
```

### Blogs
#### List Blogs
- Method: `GET`
- Path: `/api/blogs`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "data": [
    {
      "id": 1
    }
  ],
  "meta": "string"
}
```

#### Create Blogs
- Method: `POST`
- Path: `/api/blogs`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "content": "sample",
  "author": "sample",
  "excerpt": "sample",
  "status": "success",
  "featured_image": "sample"
}
```
- Success Response:
```json
{
  "message": "Blog created successfully",
  "data": "string"
}
```
- Error Response:
```json
{
  "errors": [
    {
      "id": 1
    }
  ]
}
```

#### Get Blogs
- Method: `GET`
- Path: `/api/blogs/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "data": {
    "title": "string",
    "content": "string",
    "author": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "slug": "string",
    "excerpt": "string",
    "featured_image": "string",
    "status": "string",
    "views": 1,
    "user_id": "string"
  }
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

#### Update Blogs
- Method: `PUT`
- Path: `/api/blogs/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "content": "sample",
  "author": "sample",
  "excerpt": "sample",
  "status": "success",
  "featured_image": "sample"
}
```
- Success Response:
```json
{
  "message": "Blog updated successfully",
  "data": "string"
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

#### Delete Blogs
- Method: `DELETE`
- Path: `/api/blogs/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Blog deleted successfully"
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

#### Blogs - Slug
- Method: `GET`
- Path: `/api/blogs/slug/{slug}`
- Middleware: `auth:sanctum`
- Path Params:
  - `slug` (string)
- Success Response:
```json
{
  "data": {
    "title": "string",
    "content": "string",
    "author": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "slug": "string",
    "excerpt": "string",
    "featured_image": "string",
    "status": "string",
    "views": 1,
    "user_id": "string"
  }
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

#### Blogs - Featured
- Method: `GET`
- Path: `/api/blogs/featured`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "data": [
    {
      "title": "string",
      "content": "string",
      "author": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "slug": "string",
      "excerpt": "string",
      "featured_image": "string",
      "status": "string",
      "views": 1,
      "user_id": "string"
    }
  ]
}
```

#### Public Blogs - Blogs
- Method: `GET`
- Path: `/api/public/blogs`
- Success Response:
```json
{
  "data": [
    {
      "id": 1
    }
  ],
  "meta": "string"
}
```

#### Public Blogs - Blogs
- Method: `GET`
- Path: `/api/public/blogs/{id}`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "data": {
    "title": "string",
    "content": "string",
    "author": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "slug": "string",
    "excerpt": "string",
    "featured_image": "string",
    "status": "string",
    "views": 1,
    "user_id": "string"
  }
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

#### Public Blogs - Slug
- Method: `GET`
- Path: `/api/public/blogs/slug/{slug}`
- Path Params:
  - `slug` (string)
- Success Response:
```json
{
  "data": {
    "title": "string",
    "content": "string",
    "author": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "slug": "string",
    "excerpt": "string",
    "featured_image": "string",
    "status": "string",
    "views": 1,
    "user_id": "string"
  }
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

#### Public Blogs - Featured
- Method: `GET`
- Path: `/api/public/blogs/featured`
- Success Response:
```json
{
  "data": [
    {
      "title": "string",
      "content": "string",
      "author": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "slug": "string",
      "excerpt": "string",
      "featured_image": "string",
      "status": "string",
      "views": 1,
      "user_id": "string"
    }
  ]
}
```

#### Public Blogs - View
- Method: `POST`
- Path: `/api/public/blogs/{id}/view`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "View count incremented",
  "views": 1
}
```
- Error Response:
```json
{
  "message": "Blog not found"
}
```

### Deals & Payments
Action security applies to the endpoints below. It enforces risk level, OTP freshness, audit snapshots, and idempotency.

Common Headers:
- `Authorization: Bearer <token>`
- `Idempotency-Key: <uuid>` (required for action-secured endpoints)
- `X-Device-Id: <uuid>` (recommended for audit trails)
- `X-Request-Id: <uuid>` (optional)

Idempotency Behavior:
- Same `Idempotency-Key` + same payload returns the same response.
- Same `Idempotency-Key` + different payload returns `409`.

#### Deals - Generate
- Method: `POST`
- Path: `/api/deals/{dealId}/contract/generate`
- Middleware: `auth:sanctum`, `action:deal.contract.generate`
- Path Params:
  - `dealId` (string)
- Request Example:
```http
POST /api/deals/123/contract/generate
Authorization: Bearer <token>
Idempotency-Key: 7a0bba2b-2bb5-4a36-a4f3-6f5a9b9e6f2e
X-Device-Id: device-abc-123
```
- Success Response:
```json
{
  "message": "Contract generated",
  "contract_pdf_path": "string",
  "contract_sha256": "string"
}
```
- Error Response (fresh OTP required):
```json
{
  "message": "Recent verification required.",
  "step_up_required": true,
  "required_level": "high",
  "fresh_minutes": 30
}
```

#### Deals - Create
- Method: `POST`
- Path: `/api/deals/{dealId}/signhost/create`
- Middleware: `auth:sanctum`, `action:deal.signhost.create`
- Path Params:
  - `dealId` (string)
- Request Example:
```http
POST /api/deals/123/signhost/create
Authorization: Bearer <token>
Idempotency-Key: 3cc0d3a2-0f6a-4d65-84cc-78c7c5a8b7d1
X-Device-Id: device-abc-123
```
- Success Response:
```json
{
  "message": "Signhost transaction created",
  "transaction": {
    "deal_id": "string",
    "signhost_transaction_id": "string",
    "status": "string",
    "signing_url_buyer": "string",
    "signing_url_seller": "string",
    "signed_pdf_path": "string",
    "webhook_last_payload": {},
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```
- Error Response:
```json
{
  "message": "Contract not prepared"
}
```

#### Deals - Url
- Method: `GET`
- Path: `/api/deals/{dealId}/signhost/url`
- Middleware: `auth:sanctum`
- Path Params:
  - `dealId` (string)
- Success Response:
```json
{
  "url": "string"
}
```
- Error Response:
```json
{
  "message": "No signhost transaction"
}
```

#### Deals - Status
- Method: `GET`
- Path: `/api/deals/{dealId}/status`
- Middleware: `auth:sanctum`
- Path Params:
  - `dealId` (string)
- Success Response:
```json
{
  "seller_user_id": "string",
  "buyer_user_id": "string",
  "boat_id": "string",
  "contract_pdf_path": "string",
  "contract_sha256": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

#### Deals - Create
- Method: `POST`
- Path: `/api/deals/{dealId}/payments/deposit/create`
- Middleware: `auth:sanctum`, `action:deal.payments.deposit.create`
- Path Params:
  - `dealId` (string)
- Request Body (JSON):
```json
{
  "amount": 5000,
  "currency": "EUR",
  "redirect_url": "https://app.example.com/deals/123/return"
}
```
- Request Example:
```http
POST /api/deals/123/payments/deposit/create
Authorization: Bearer <token>
Idempotency-Key: 5b76f5d3-4b67-4c4f-8ed9-4f7c0f5f2a2a
X-Device-Id: device-abc-123
Content-Type: application/json
```
- Success Response:
```json
{
  "payment": {
    "id": 10,
    "deal_id": 123,
    "type": "deposit",
    "status": "open",
    "amount_currency": "EUR",
    "amount_value": "5000.00",
    "checkout_url": "https://payments.example/checkout/..."
  },
  "checkout_url": "https://payments.example/checkout/..."
}
```

#### Deals - Create
- Method: `POST`
- Path: `/api/deals/{dealId}/payments/platform-fee/create`
- Middleware: `auth:sanctum`, `action:deal.payments.platform_fee.create`
- Path Params:
  - `dealId` (string)
- Request Body (JSON):
```json
{
  "amount": 750,
  "currency": "EUR",
  "redirect_url": "https://app.example.com/deals/123/return"
}
```
- Request Example:
```http
POST /api/deals/123/payments/platform-fee/create
Authorization: Bearer <token>
Idempotency-Key: 8e3a1a27-6d24-4b9b-8a2d-1c3f4d5e6f7a
X-Device-Id: device-abc-123
Content-Type: application/json
```
- Success Response:
```json
{
  "payment": {
    "id": 11,
    "deal_id": 123,
    "type": "platform_fee",
    "status": "open",
    "amount_currency": "EUR",
    "amount_value": "750.00",
    "checkout_url": "https://payments.example/checkout/..."
  },
  "checkout_url": "https://payments.example/checkout/..."
}
```

#### Deals - Checkout Url
- Method: `GET`
- Path: `/api/deals/{dealId}/payments/{type}/checkout-url`
- Middleware: `auth:sanctum`
- Path Params:
  - `dealId` (string)
  - `type` (string)
- Success Response:
```json
{
  "checkout_url": "string"
}
```
- Error Response:
```json
{
  "message": "Payment not found"
}
```

### Wallet
#### Wallet - Top Up
- Method: `POST`
- Path: `/api/wallet/topup`
- Middleware: `auth:sanctum`, `action:wallet.topup.create`
- Request Body (JSON):
```json
{
  "amount": 100,
  "currency": "EUR",
  "redirect_url": "https://app.example.com/wallet/return"
}
```
- Request Example:
```http
POST /api/wallet/topup
Authorization: Bearer <token>
Idempotency-Key: 6c4a5b2d-1e9f-4f4f-9b6a-9dcb58d7b8a1
X-Device-Id: device-abc-123
Content-Type: application/json
```
- Success Response:
```json
{
  "topup": {
    "id": 55,
    "user_id": 7,
    "amount_currency": "EUR",
    "amount_value": "100.00",
    "status": "open",
    "checkout_url": "https://payments.example/checkout/..."
  },
  "checkout_url": "https://payments.example/checkout/..."
}
```

### FAQs
#### List Faqs
- Method: `GET`
- Path: `/api/faqs`
- Success Response:
```json
{
  "faqs": [
    {
      "id": 1
    }
  ],
  "categories": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ],
  "total_count": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ]
}
```

#### Get Faqs
- Method: `GET`
- Path: `/api/faqs/{id}`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "question": "string",
  "answer": "string",
  "category": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "views": 1,
  "helpful": 1,
  "not_helpful": 1,
  "embedding": "string"
}
```

#### Faqs - Ask Gemini
- Method: `POST`
- Path: `/api/faqs/ask-gemini`
- Request Body (JSON):
```json
{
  "question": "What yachts are available?"
}
```
- Success Response:
```json
{
  "answer": "Our AI assistant is temporarily unavailable. Please contact support.",
  "sources": 0,
  "timestamp": "string"
}
```

#### Faqs - Stats
- Method: `GET`
- Path: `/api/faqs/stats`
- Success Response:
```json
{
  "total_faqs": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ],
  "total_views": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ],
  "total_helpful": {
    "question": "string",
    "answer": "string",
    "category": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "views": 1,
    "helpful": 1,
    "not_helpful": 1,
    "embedding": "string"
  },
  "total_not_helpful": {
    "question": "string",
    "answer": "string",
    "category": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "views": 1,
    "helpful": 1,
    "not_helpful": 1,
    "embedding": "string"
  },
  "categories": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ],
  "popular_faqs": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ]
}
```

#### Faqs - Rate Helpful
- Method: `POST`
- Path: `/api/faqs/{id}/rate-helpful`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Thank you for your feedback!",
  "helpful_count": 1
}
```

#### Faqs - Rate Not Helpful
- Method: `POST`
- Path: `/api/faqs/{id}/rate-not-helpful`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Thank you for your feedback!",
  "not_helpful_count": 1
}
```

#### Create Faqs
- Method: `POST`
- Path: `/api/faqs`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "question": "What yachts are available?",
  "answer": "sample",
  "category": "sample"
}
```
- Success Response:
```json
{
  "message": "Faq added successfully",
  "faq": {
    "question": "string",
    "answer": "string",
    "category": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "views": 1,
    "helpful": 1,
    "not_helpful": 1,
    "embedding": "string"
  }
}
```

#### Update Faqs
- Method: `PUT`
- Path: `/api/faqs/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "question": "What yachts are available?",
  "answer": "sample",
  "category": "sample"
}
```
- Success Response:
```json
{
  "message": "Faq updated successfully",
  "faq": {
    "question": "string",
    "answer": "string",
    "category": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "views": 1,
    "helpful": 1,
    "not_helpful": 1,
    "embedding": "string"
  }
}
```

#### Delete Faqs
- Method: `DELETE`
- Path: `/api/faqs/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Faq deleted successfully"
}
```

#### Faqs - Train Gemini
- Method: `POST`
- Path: `/api/faqs/train-gemini`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "message": "Gemini training completed with {$faqCount} Faqs",
  "last_trained": "string",
  "faq_count": 0
}
```

#### Faqs - Training Status
- Method: `GET`
- Path: `/api/faqs/training-status`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "last_trained": "string",
  "faq_count": 0,
  "total_faqs": [
    {
      "question": "string",
      "answer": "string",
      "category": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "views": 1,
      "helpful": 1,
      "not_helpful": 1,
      "embedding": "string"
    }
  ]
}
```

#### Faqs - Test Gemini
- Method: `GET`
- Path: `/api/faqs/test-gemini`

#### Faqs - Test Dummy
- Method: `POST`
- Path: `/api/faqs/test-dummy`
- Success Response:
```json
{
  "message": "Dummy FAQ added",
  "faq": {
    "question": "string",
    "answer": "string",
    "category": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "views": 1,
    "helpful": 1,
    "not_helpful": 1,
    "embedding": "string"
  }
}
```

### Inspections & Checklists
#### Inspections - Boat
- Method: `GET`
- Path: `/api/inspections/boat/{boatId}`
- Middleware: `auth:sanctum`
- Path Params:
  - `boatId` (string)
- Success Response:
```json
"string"
```

#### Inspections - Answers
- Method: `PUT`
- Path: `/api/inspections/{inspectionId}/answers/{answerId}`
- Middleware: `auth:sanctum`
- Path Params:
  - `inspectionId` (string)
  - `answerId` (string)
- Success Response:
```json
{
  "inspection_id": "string",
  "question_id": "string",
  "ai_answer": "string",
  "ai_confidence": 1.0,
  "human_answer": "string",
  "review_status": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "ai_evidence": "string"
}
```

#### List Boat Types
- Method: `GET`
- Path: `/api/boat-types`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "name": "string",
  "description": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

#### List Boat Checks
- Method: `GET`
- Path: `/api/boat-checks`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "question_text": "string",
    "type": "string",
    "required": true,
    "ai_prompt": "string",
    "evidence_sources": {},
    "weight": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "options": "string"
  }
]
```

#### Get Boat Checks
- Method: `GET`
- Path: `/api/boat-checks/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "question_text": "string",
  "type": "string",
  "required": true,
  "ai_prompt": "string",
  "evidence_sources": {},
  "weight": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "options": "string"
}
```

#### Create Inspections
- Method: `POST`
- Path: `/api/inspections`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "boat_id": "sample"
}
```
- Success Response:
```json
{
  "boat_id": "string",
  "user_id": "string",
  "status": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

#### Inspections - Answers
- Method: `POST`
- Path: `/api/inspections/{id}/answers`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "answers": [],
  "answers.*.question_id": "sample",
  "answers.*.answer": "sample"
}
```
- Success Response:
```json
{
  "message": "Answers saved successfully."
}
```

#### Inspections - Ai Analyze
- Method: `POST`
- Path: `/api/inspections/{id}/ai-analyze`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "AI analysis complete",
  "answers": [
    {
      "id": 1
    }
  ],
  "total_questions": 0
}
```
- Error Response:
```json
{
  "error": "No boat linked to this inspection"
}
```

#### Create Boat Checks
- Method: `POST`
- Path: `/api/boat-checks`
- Middleware: `auth:sanctum, permission:manage checklist questions`
- Success Response:
```json
{
  "question_text": "string",
  "type": "string",
  "required": true,
  "ai_prompt": "string",
  "evidence_sources": {},
  "weight": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "options": "string"
}
```

#### Update Boat Checks
- Method: `PUT`
- Path: `/api/boat-checks/{id}`
- Middleware: `auth:sanctum, permission:manage checklist questions`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "question_text": "string",
  "type": "string",
  "required": true,
  "ai_prompt": "string",
  "evidence_sources": {},
  "weight": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "options": "string"
}
```

#### Delete Boat Checks
- Method: `DELETE`
- Path: `/api/boat-checks/{id}`
- Middleware: `auth:sanctum, permission:manage checklist questions`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Checklist question deleted successfully."
}
```

### Misc
#### Create Sync Remaining
- Method: `POST`
- Path: `/api/sync-remaining`
- Success Response:
```json
{
  "status": "success",
  "message": "Sync process started. Missing images will be indexed shortly."
}
```

#### Create Analyze Boat
- Method: `POST`
- Path: `/api/analyze-boat`
- Request Body (JSON):
```json
{
  "query": "sample"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```
- Error Response:
```json
{
  "status": "error",
  "message": "Invalid response from analysis service"
}
```

#### List Search Boats
- Method: `GET`
- Path: `/api/search-boats`
- Request Body (JSON):
```json
{
  "query": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "results": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "status": "error",
  "message": "Search failed",
  "details": "string"
}
```

#### Create Upload Boat
- Method: `POST`
- Path: `/api/upload-boat`
- Request Body (JSON):
```json
{
  "image": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "message": "Image uploaded and embedding stored in database.",
  "data": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "status": "error",
  "message": "No image file detected."
}
```

#### Get Partner Fleet
- Method: `GET`
- Path: `/api/partner-fleet/{token}`
- Path Params:
  - `token` (string)
- Success Response:
```json
{
  "partner": "string",
  "yachts": [
    {
      "vessel_id": "string",
      "name": "string",
      "status": "string",
      "price": 1.0,
      "current_bid": 1.0,
      "year": 1,
      "length": "string",
      "main_image": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "make": "string",
      "model": "string",
      "beam": "string",
      "draft": "string",
      "engine_type": "string",
      "fuel_type": "string",
      "fuel_capacity": "string",
      "water_capacity": "string",
      "cabins": 1,
      "heads": 1,
      "description": "string",
      "location": "string",
      "brand_model": "string",
      "vat_status": "string",
      "reference_code": "string",
      "construction_material": "string",
      "dimensions": "string",
      "berths": 1,
      "hull_shape": "string",
      "hull_color": "string",
      "deck_color": "string",
      "clearance": "string",
      "displacement": "string",
      "steering": "string",
      "engine_brand": "string",
      "engine_model": "string",
      "engine_power": "string",
      "engine_hours": "string",
      "max_speed": "string",
      "fuel_consumption": "string",
      "voltage": "string",
      "interior_type": "string",
      "water_tank": "string",
      "water_system": "string",
      "navigation_electronics": "string",
      "exterior_equipment": "string",
      "trailer_included": true,
      "safety_equipment": "string",
      "user_id": "string",
      "boat_name": "string",
      "allow_bidding": true,
      "external_url": "string",
      "print_url": "string",
      "owners_comment": "string",
      "reg_details": "string",
      "known_defects": "string",
      "last_serviced": "2024-01-01T00:00:00Z",
      "loa": "string",
      "lwl": "string",
      "air_draft": "string",
      "passenger_capacity": 1,
      "designer": "string",
      "builder": "string",
      "where": "string",
      "hull_colour": "string",
      "hull_construction": "string",
      "hull_number": "string",
      "hull_type": "string",
      "super_structure_colour": "string",
      "super_structure_construction": "string",
      "deck_colour": "string",
      "deck_construction": "string",
      "cockpit_type": "string",
      "control_type": "string",
      "flybridge": true,
      "ballast": "string",
      "toilet": 1,
      "shower": 1,
      "bath": 1,
      "oven": true,
      "microwave": true,
      "fridge": true,
      "freezer": true,
      "heating": "string",
      "air_conditioning": true,
      "stern_thruster": true,
      "bow_thruster": true,
      "fuel": "string",
      "hours": "string",
      "cruising_speed": "string",
      "horse_power": "string",
      "engine_manufacturer": "string",
      "engine_quantity": "string",
      "tankage": "string",
      "gallons_per_hour": "string",
      "litres_per_hour": "string",
      "engine_location": "string",
      "gearbox": "string",
      "cylinders": "string",
      "propeller_type": "string",
      "starting_type": "string",
      "drive_type": "string",
      "cooling_system": "string",
      "navigation_lights": true,
      "compass": true,
      "depth_instrument": true,
      "wind_instrument": true,
      "autopilot": true,
      "gps": true,
      "vhf": true,
      "plotter": true,
      "speed_instrument": true,
      "radar": true,
      "life_raft": true,
      "epirb": true,
      "bilge_pump": true,
      "fire_extinguisher": true,
      "mob_system": true,
      "genoa": "string",
      "spinnaker": true,
      "tri_sail": "string",
      "storm_jib": "string",
      "main_sail": "string",
      "winches": "string",
      "battery": true,
      "battery_charger": true,
      "generator": true,
      "inverter": true,
      "television": true,
      "cd_player": true,
      "dvd_player": true,
      "anchor": true,
      "spray_hood": true,
      "bimini": true,
      "fenders": "string",
      "min_bid_amount": 1.0,
      "display_specs": {},
      "boat_type_id": "string",
      "advertising_channels": "string",
      "boat_type": "string",
      "boat_category": "string",
      "new_or_used": "string",
      "manufacturer": "string",
      "vessel_lying": "string",
      "location_city": "string",
      "location_lat": 1.0,
      "location_lng": 1.0,
      "short_description_nl": "string",
      "short_description_en": "string",
      "motorization_summary": "string",
      "advertise_as": "string",
      "ce_category": "string",
      "ce_max_weight": "string",
      "ce_max_motor": "string",
      "cvo": "string",
      "cbb": "string",
      "windows": "string",
      "open_cockpit": "string",
      "aft_cockpit": "string",
      "minimum_height": "string",
      "variable_depth": "string",
      "max_draft": "string",
      "min_draft": "string",
      "ballast_tank": "string",
      "steering_system": "string",
      "steering_system_location": "string",
      "remote_control": "string",
      "rudder": "string",
      "drift_restriction": "string",
      "drift_restriction_controls": "string",
      "trimflaps": "string",
      "stabilizer": "string",
      "saloon": "string",
      "headroom": "string",
      "separate_dining_area": "string",
      "engine_room": "string",
      "spaces_inside": "string",
      "upholstery_color": "string",
      "matrasses": "string",
      "cushions": "string",
      "curtains": "string",
      "berths_fixed": "string",
      "berths_extra": "string",
      "berths_crew": "string",
      "water_tank_material": "string",
      "water_tank_gauge": "string",
      "water_maker": "string",
      "waste_water_tank": "string",
      "waste_water_tank_material": "string",
      "waste_water_tank_gauge": "string",
      "waste_water_tank_drainpump": "string",
      "deck_suction": "string",
      "hot_water": "string",
      "sea_water_pump": "string",
      "deck_wash_pump": "string",
      "deck_shower": "string",
      "cooker": "string",
      "cooking_fuel": "string",
      "hot_air": "string",
      "stove": "string",
      "central_heating": "string",
      "satellite_reception": "string",
      "engine_serial_number": "string",
      "engine_year": "string",
      "reversing_clutch": "string",
      "transmission": "string",
      "propulsion": "string",
      "fuel_tanks_amount": "string",
      "fuel_tank_total_capacity": "string",
      "fuel_tank_material": "string",
      "range_km": "string",
      "fuel_tank_gauge": "string",
      "tachometer": "string",
      "oil_pressure_gauge": "string",
      "temperature_gauge": "string",
      "dynamo": "string",
      "accumonitor": "string",
      "voltmeter": "string",
      "shorepower": "string",
      "shore_power_cable": "string",
      "wind_generator": "string",
      "solar_panel": "string",
      "consumption_monitor": "string",
      "control_panel": "string",
      "log_speed": "string",
      "windvane_steering": "string",
      "charts_guides": "string",
      "rudder_position_indicator": "string",
      "fishfinder": "string",
      "turn_indicator": "string",
      "ais": "string",
      "ssb_receiver": "string",
      "shortwave_radio": "string",
      "short_band_transmitter": "string",
      "weatherfax_navtex": "string",
      "satellite_communication": "string",
      "sailplan_type": "string",
      "number_of_masts": "string",
      "spars_material": "string",
      "bowsprit": "string",
      "standing_rig": "string",
      "sail_surface_area": "string",
      "stabilizer_sail": "string",
      "sail_amount": "string",
      "sail_material": "string",
      "sail_manufacturer": "string",
      "furling_mainsail": "string",
      "mizzen": "string",
      "furling_mizzen": "string",
      "jib": "string",
      "roller_furling_foresail": "string",
      "genoa_reefing_system": "string",
      "flying_jib": "string",
      "halfwinder_bollejan": "string",
      "gennaker": "string",
      "electric_winches": "string",
      "manual_winches": "string",
      "hydraulic_winches": "string",
      "self_tailing_winches": "string",
      "anchor_connection": "string",
      "anchor_winch": "string",
      "stern_anchor": "string",
      "spud_pole": "string",
      "cockpit_tent": "string",
      "outdoor_cushions": "string",
      "covers": "string",
      "sea_rails": "string",
      "pushpit_pullpit": "string",
      "swimming_platform": "string",
      "swimming_ladder": "string",
      "sail_lowering_system": "string",
      "crutch": "string",
      "dinghy": "string",
      "dinghy_brand": "string",
      "outboard_engine": "string",
      "trailer": "string",
      "crane": "string",
      "davits": "string",
      "teak_deck": "string",
      "cockpit_table": "string",
      "oars_paddles": "string",
      "life_buoy": "string",
      "bilge_pump_manual": "string",
      "bilge_pump_electric": "string",
      "radar_reflector": "string",
      "flares": "string",
      "life_jackets": "string",
      "watertight_door": "string",
      "gas_bottle_locker": "string",
      "self_draining_cockpit": "string"
    }
  ]
}
```
- Error Response:
```json
{
  "message": "Invalid or expired link."
}
```

#### Create Verify Email
- Method: `POST`
- Path: `/api/verify-email`
- Request Body (JSON):
```json
{
  "email": "user@example.com",
  "code": "sample"
}
```
- Success Response:
```json
{
  "message": "Email verified successfully"
}
```
- Error Response:
```json
{
  "message": "User not found"
}
```

#### Create Resend Verification
- Method: `POST`
- Path: `/api/resend-verification`
- Request Body (JSON):
```json
{
  "email": "user@example.com"
}
```
- Success Response:
```json
{
  "message": "A confirmation code has been sent to your email. Please check your inbox."
}
```
- Error Response:
```json
{
  "message": "User not found"
}
```

#### Create Test Yacht Update
- Method: `POST`
- Path: `/api/test-yacht-update`

#### Delete Gallery
- Method: `DELETE`
- Path: `/api/gallery/{id}`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Image removed"
}
```

#### List My Yachts
- Method: `GET`
- Path: `/api/my-yachts`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "vessel_id": "string",
    "name": "string",
    "status": "string",
    "price": 1.0,
    "current_bid": 1.0,
    "year": 1,
    "length": "string",
    "main_image": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "make": "string",
    "model": "string",
    "beam": "string",
    "draft": "string",
    "engine_type": "string",
    "fuel_type": "string",
    "fuel_capacity": "string",
    "water_capacity": "string",
    "cabins": 1,
    "heads": 1,
    "description": "string",
    "location": "string",
    "brand_model": "string",
    "vat_status": "string",
    "reference_code": "string",
    "construction_material": "string",
    "dimensions": "string",
    "berths": 1,
    "hull_shape": "string",
    "hull_color": "string",
    "deck_color": "string",
    "clearance": "string",
    "displacement": "string",
    "steering": "string",
    "engine_brand": "string",
    "engine_model": "string",
    "engine_power": "string",
    "engine_hours": "string",
    "max_speed": "string",
    "fuel_consumption": "string",
    "voltage": "string",
    "interior_type": "string",
    "water_tank": "string",
    "water_system": "string",
    "navigation_electronics": "string",
    "exterior_equipment": "string",
    "trailer_included": true,
    "safety_equipment": "string",
    "user_id": "string",
    "boat_name": "string",
    "allow_bidding": true,
    "external_url": "string",
    "print_url": "string",
    "owners_comment": "string",
    "reg_details": "string",
    "known_defects": "string",
    "last_serviced": "2024-01-01T00:00:00Z",
    "loa": "string",
    "lwl": "string",
    "air_draft": "string",
    "passenger_capacity": 1,
    "designer": "string",
    "builder": "string",
    "where": "string",
    "hull_colour": "string",
    "hull_construction": "string",
    "hull_number": "string",
    "hull_type": "string",
    "super_structure_colour": "string",
    "super_structure_construction": "string",
    "deck_colour": "string",
    "deck_construction": "string",
    "cockpit_type": "string",
    "control_type": "string",
    "flybridge": true,
    "ballast": "string",
    "toilet": 1,
    "shower": 1,
    "bath": 1,
    "oven": true,
    "microwave": true,
    "fridge": true,
    "freezer": true,
    "heating": "string",
    "air_conditioning": true,
    "stern_thruster": true,
    "bow_thruster": true,
    "fuel": "string",
    "hours": "string",
    "cruising_speed": "string",
    "horse_power": "string",
    "engine_manufacturer": "string",
    "engine_quantity": "string",
    "tankage": "string",
    "gallons_per_hour": "string",
    "litres_per_hour": "string",
    "engine_location": "string",
    "gearbox": "string",
    "cylinders": "string",
    "propeller_type": "string",
    "starting_type": "string",
    "drive_type": "string",
    "cooling_system": "string",
    "navigation_lights": true,
    "compass": true,
    "depth_instrument": true,
    "wind_instrument": true,
    "autopilot": true,
    "gps": true,
    "vhf": true,
    "plotter": true,
    "speed_instrument": true,
    "radar": true,
    "life_raft": true,
    "epirb": true,
    "bilge_pump": true,
    "fire_extinguisher": true,
    "mob_system": true,
    "genoa": "string",
    "spinnaker": true,
    "tri_sail": "string",
    "storm_jib": "string",
    "main_sail": "string",
    "winches": "string",
    "battery": true,
    "battery_charger": true,
    "generator": true,
    "inverter": true,
    "television": true,
    "cd_player": true,
    "dvd_player": true,
    "anchor": true,
    "spray_hood": true,
    "bimini": true,
    "fenders": "string",
    "min_bid_amount": 1.0,
    "display_specs": {},
    "boat_type_id": "string",
    "advertising_channels": "string",
    "boat_type": "string",
    "boat_category": "string",
    "new_or_used": "string",
    "manufacturer": "string",
    "vessel_lying": "string",
    "location_city": "string",
    "location_lat": 1.0,
    "location_lng": 1.0,
    "short_description_nl": "string",
    "short_description_en": "string",
    "motorization_summary": "string",
    "advertise_as": "string",
    "ce_category": "string",
    "ce_max_weight": "string",
    "ce_max_motor": "string",
    "cvo": "string",
    "cbb": "string",
    "windows": "string",
    "open_cockpit": "string",
    "aft_cockpit": "string",
    "minimum_height": "string",
    "variable_depth": "string",
    "max_draft": "string",
    "min_draft": "string",
    "ballast_tank": "string",
    "steering_system": "string",
    "steering_system_location": "string",
    "remote_control": "string",
    "rudder": "string",
    "drift_restriction": "string",
    "drift_restriction_controls": "string",
    "trimflaps": "string",
    "stabilizer": "string",
    "saloon": "string",
    "headroom": "string",
    "separate_dining_area": "string",
    "engine_room": "string",
    "spaces_inside": "string",
    "upholstery_color": "string",
    "matrasses": "string",
    "cushions": "string",
    "curtains": "string",
    "berths_fixed": "string",
    "berths_extra": "string",
    "berths_crew": "string",
    "water_tank_material": "string",
    "water_tank_gauge": "string",
    "water_maker": "string",
    "waste_water_tank": "string",
    "waste_water_tank_material": "string",
    "waste_water_tank_gauge": "string",
    "waste_water_tank_drainpump": "string",
    "deck_suction": "string",
    "hot_water": "string",
    "sea_water_pump": "string",
    "deck_wash_pump": "string",
    "deck_shower": "string",
    "cooker": "string",
    "cooking_fuel": "string",
    "hot_air": "string",
    "stove": "string",
    "central_heating": "string",
    "satellite_reception": "string",
    "engine_serial_number": "string",
    "engine_year": "string",
    "reversing_clutch": "string",
    "transmission": "string",
    "propulsion": "string",
    "fuel_tanks_amount": "string",
    "fuel_tank_total_capacity": "string",
    "fuel_tank_material": "string",
    "range_km": "string",
    "fuel_tank_gauge": "string",
    "tachometer": "string",
    "oil_pressure_gauge": "string",
    "temperature_gauge": "string",
    "dynamo": "string",
    "accumonitor": "string",
    "voltmeter": "string",
    "shorepower": "string",
    "shore_power_cable": "string",
    "wind_generator": "string",
    "solar_panel": "string",
    "consumption_monitor": "string",
    "control_panel": "string",
    "log_speed": "string",
    "windvane_steering": "string",
    "charts_guides": "string",
    "rudder_position_indicator": "string",
    "fishfinder": "string",
    "turn_indicator": "string",
    "ais": "string",
    "ssb_receiver": "string",
    "shortwave_radio": "string",
    "short_band_transmitter": "string",
    "weatherfax_navtex": "string",
    "satellite_communication": "string",
    "sailplan_type": "string",
    "number_of_masts": "string",
    "spars_material": "string",
    "bowsprit": "string",
    "standing_rig": "string",
    "sail_surface_area": "string",
    "stabilizer_sail": "string",
    "sail_amount": "string",
    "sail_material": "string",
    "sail_manufacturer": "string",
    "furling_mainsail": "string",
    "mizzen": "string",
    "furling_mizzen": "string",
    "jib": "string",
    "roller_furling_foresail": "string",
    "genoa_reefing_system": "string",
    "flying_jib": "string",
    "halfwinder_bollejan": "string",
    "gennaker": "string",
    "electric_winches": "string",
    "manual_winches": "string",
    "hydraulic_winches": "string",
    "self_tailing_winches": "string",
    "anchor_connection": "string",
    "anchor_winch": "string",
    "stern_anchor": "string",
    "spud_pole": "string",
    "cockpit_tent": "string",
    "outdoor_cushions": "string",
    "covers": "string",
    "sea_rails": "string",
    "pushpit_pullpit": "string",
    "swimming_platform": "string",
    "swimming_ladder": "string",
    "sail_lowering_system": "string",
    "crutch": "string",
    "dinghy": "string",
    "dinghy_brand": "string",
    "outboard_engine": "string",
    "trailer": "string",
    "crane": "string",
    "davits": "string",
    "teak_deck": "string",
    "cockpit_table": "string",
    "oars_paddles": "string",
    "life_buoy": "string",
    "bilge_pump_manual": "string",
    "bilge_pump_electric": "string",
    "radar_reflector": "string",
    "flares": "string",
    "life_jackets": "string",
    "watertight_door": "string",
    "gas_bottle_locker": "string",
    "self_draining_cockpit": "string"
  }
]
```

#### List User
- Method: `GET`
- Path: `/api/user`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "id": 1,
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "string",
  "status": "success",
  "access_level": "string",
  "partner_token": "string",
  "permissions": [
    {
      "id": 1
    }
  ]
}
```

#### List Permissions
- Method: `GET`
- Path: `/api/permissions`
- Middleware: `auth:sanctum, permission:manage users`
- Success Response:
```json
{
  "message": "Success"
}
```

#### List Roles
- Method: `GET`
- Path: `/api/roles`
- Middleware: `auth:sanctum, permission:manage users`
- Success Response:
```json
{
  "message": "Success"
}
```

#### Delete Boats
- Method: `DELETE`
- Path: `/api/boats/{filename}`
- Path Params:
  - `filename` (string)
- Success Response:
```json
{
  "message": "Boat deleted successfully"
}
```
- Error Response:
```json
{
  "message": "Delete failed: ' . $e->getMessage()"
}
```

#### List Advertising Channels
- Method: `GET`
- Path: `/api/advertising-channels`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "message": "Success"
}
```

### Notifications
#### List Notifications
- Method: `GET`
- Path: `/api/notifications`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "data": [
    {
      "id": 1
    }
  ],
  "meta": "string"
}
```
- Error Response:
```json
{
  "error": "Unauthorized",
  "message": "User not authenticated"
}
```

#### Notifications - Unread Count
- Method: `GET`
- Path: `/api/notifications/unread-count`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "count": {
    "user_id": "string",
    "notification_id": "string",
    "read": true,
    "read_at": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```
- Error Response:
```json
{
  "error": "Unauthorized",
  "message": "User not authenticated"
}
```

#### Notifications - Read
- Method: `POST`
- Path: `/api/notifications/{id}/read`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```
- Error Response:
```json
{
  "error": "Unauthorized",
  "message": "User not authenticated"
}
```

#### Notifications - Read All
- Method: `POST`
- Path: `/api/notifications/read-all`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "success": true,
  "message": "All notifications marked as read",
  "count": {
    "user_id": "string",
    "notification_id": "string",
    "read": true,
    "read_at": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```
- Error Response:
```json
{
  "error": "Unauthorized",
  "message": "User not authenticated"
}
```

#### Delete Notifications
- Method: `DELETE`
- Path: `/api/notifications/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "success": true,
  "message": "Notification deleted"
}
```
- Error Response:
```json
{
  "error": "Unauthorized",
  "message": "User not authenticated"
}
```

### System Logs
#### List System Logs
- Method: `GET`
- Path: `/api/system-logs`
- Success Response:
```json
{
  "data": [
    {
      "id": 1
    }
  ],
  "meta": "string"
}
```
- Error Response:
```json
{
  "error": "Internal server error",
  "message": "string"
}
```

#### System Logs - Summary
- Method: `GET`
- Path: `/api/system-logs/summary`
- Success Response:
```json
{
  "summary": "string",
  "recent_activity": {
    "event_type": "string",
    "entity_type": "string",
    "entity_id": 1,
    "user_id": "string",
    "old_data": {},
    "new_data": {},
    "changes": {},
    "description": "string",
    "ip_address": "string",
    "user_agent": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  },
  "daily_activity": {
    "event_type": "string",
    "entity_type": "string",
    "entity_id": 1,
    "user_id": "string",
    "old_data": {},
    "new_data": {},
    "changes": {},
    "description": "string",
    "ip_address": "string",
    "user_agent": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```
- Error Response:
```json
{
  "error": "Internal server error",
  "message": "string"
}
```

#### System Logs - Export
- Method: `GET`
- Path: `/api/system-logs/export`
- Success Response:
```json
{
  "csv_data": "string",
  "filename": "system_logs_export_' . date('Y-m-d_H-i-s') . '.csv"
}
```
- Error Response:
```json
{
  "error": "Internal server error",
  "message": "string"
}
```

#### System Logs - Health
- Method: `GET`
- Path: `/api/system-logs/health`
- Success Response:
```json
{
  "status": "healthy",
  "database": "connected",
  "total_logs": [
    {
      "event_type": "string",
      "entity_type": "string",
      "entity_id": 1,
      "user_id": "string",
      "old_data": {},
      "new_data": {},
      "changes": {},
      "description": "string",
      "ip_address": "string",
      "user_agent": "string",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "last_log_at": "string",
  "timestamp": "string"
}
```
- Error Response:
```json
{
  "status": "unhealthy",
  "database": "disconnected",
  "error": "string",
  "timestamp": "string"
}
```

#### Get System Logs
- Method: `GET`
- Path: `/api/system-logs/{id}`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "event_type": "string",
  "entity_type": "string",
  "entity_id": 1,
  "user_id": "string",
  "old_data": {},
  "new_data": {},
  "changes": {},
  "description": "string",
  "ip_address": "string",
  "user_agent": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```
- Error Response:
```json
{
  "error": "Log not found"
}
```

### Tasks & Appointments
#### List Tasks
- Method: `GET`
- Path: `/api/tasks`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "title": "string",
    "description": "string",
    "priority": "string",
    "status": "string",
    "assigned_to": "string",
    "yacht_id": "string",
    "due_date": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "created_by": "string",
    "user_id": "string",
    "type": "string",
    "assignment_status": "string",
    "appointment_id": "string"
  }
]
```

#### Tasks - My
- Method: `GET`
- Path: `/api/tasks/my`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "title": "string",
    "description": "string",
    "priority": "string",
    "status": "string",
    "assigned_to": "string",
    "yacht_id": "string",
    "due_date": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "created_by": "string",
    "user_id": "string",
    "type": "string",
    "assignment_status": "string",
    "appointment_id": "string"
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Tasks - Calendar
- Method: `GET`
- Path: `/api/tasks/calendar`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "id": 1
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Create Tasks
- Method: `POST`
- Path: `/api/tasks`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "due_date": "2024-01-01",
  "type": "sample",
  "assigned_to": 1,
  "yacht_id": 1
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "errors": [
    {
      "id": 1
    }
  ]
}
```

#### Get Tasks
- Method: `GET`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Update Tasks
- Method: `PUT`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "assigned_to": "sample",
  "yacht_id": "sample",
  "due_date": "2024-01-01"
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Tasks - Status
- Method: `PATCH`
- Path: `/api/tasks/{id}/status`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "status": "success"
}
```
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Delete Tasks
- Method: `DELETE`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Task deleted successfully"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Tasks - Accept
- Method: `PATCH`
- Path: `/api/tasks/{id}/accept`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```

#### Tasks - Reject
- Method: `PATCH`
- Path: `/api/tasks/{id}/reject`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```

#### Appointments - My
- Method: `GET`
- Path: `/api/appointments/my`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "yacht_id": "string",
    "start_at": "2024-01-01T00:00:00Z",
    "end_at": "2024-01-01T00:00:00Z",
    "status": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "user_id": "string",
    "seller_user_id": "string",
    "bid_id": "string",
    "deal_id": "string",
    "location": "string",
    "type": "string"
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Appointments - Admin
- Method: `GET`
- Path: `/api/appointments/admin`
- Middleware: `auth:sanctum`
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "error": "Forbidden"
}
```

#### Appointments - Boat
- Method: `GET`
- Path: `/api/appointments/boat/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
[
  {
    "yacht_id": "string",
    "start_at": "2024-01-01T00:00:00Z",
    "end_at": "2024-01-01T00:00:00Z",
    "status": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "user_id": "string",
    "seller_user_id": "string",
    "bid_id": "string",
    "deal_id": "string",
    "location": "string",
    "type": "string"
  }
]
```
- Error Response:
```json
{
  "error": "Forbidden"
}
```

### Users
#### Public Users - Employees
- Method: `GET`
- Path: `/api/public/users/employees`
- Success Response:
```json
[
  {}
]
```
- Error Response:
```json
{
  "error": "Failed to fetch users"
}
```

#### Users - Staff
- Method: `GET`
- Path: `/api/users/staff`
- Middleware: `auth:sanctum`
- Success Response:
```json
{}
```
- Error Response:
```json
{
  "error": "Failed to fetch staff"
}
```

#### List Users
- Method: `GET`
- Path: `/api/users`
- Middleware: `auth:sanctum, permission:manage users`
- Success Response:
```json
[
  {}
]
```

#### Create Users
- Method: `POST`
- Path: `/api/users`
- Middleware: `auth:sanctum, permission:manage users`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "role": "sample",
  "// Added Partner [cite: 6]\n            'status": "sample",
  "access_level": "sample"
}
```
- Success Response:
```json
{}
```

#### Get Users
- Method: `GET`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Users
- Method: `PUT`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "sample",
  "// Added Partner [cite: 13]\n            'status": "sample",
  "access_level": "sample",
  "password": "Password123!"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Users
- Method: `PATCH`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "sample",
  "// Added Partner [cite: 13]\n            'status": "sample",
  "access_level": "sample",
  "password": "Password123!"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Delete Users
- Method: `DELETE`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "User deleted successfully"
}
```
- Error Response:
```json
{
  "message": "Cannot terminate your own session."
}
```

#### Users - Impersonate
- Method: `POST`
- Path: `/api/users/{user}/impersonate`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Success Response:
```json
{
  "token": "token_example",
  "message": "Logged in as {$user->name}",
  "user": "string"
}
```
- Error Response:
```json
{
  "message": "Insufficient clearance for identity assumption."
}
```

#### Users - Toggle Status
- Method: `PATCH`
- Path: `/api/users/{user}/toggle-status`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Users - Toggle Permission
- Method: `POST`
- Path: `/api/users/{user}/toggle-permission`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Request Body (JSON):
```json
{
  "permission": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "current_permissions": [
    {
      "id": 1
    }
  ],
  "message": "Permission \" . ($status === 'attached' ? 'granted' : 'revoked')"
}
```

#### List Profile
- Method: `GET`
- Path: `/api/profile`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "message": "Success"
}
```

#### Profile - Update
- Method: `POST`
- Path: `/api/profile/update`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "// Existing fields\n            'name": "Jane Doe",
  "email": "user@example.com",
  "phone_number": "sample",
  "address": "sample",
  "city": "sample",
  "state": "sample",
  "postcode": "sample",
  "country": "sample",
  "profile_image": "sample",
  "// New personal fields\n            'relationNumber": "sample",
  "firstName": "sample",
  "lastName": "sample",
  "prefix": "sample",
  "initials": "sample",
  "title": "Sample Title",
  "salutation": "sample",
  "attentionOf": "sample",
  "identification": "sample",
  "dateOfBirth": "2024-01-01",
  "website": "http://localhost:8000/redirect",
  "mobile": "sample",
  "street": "sample",
  "houseNumber": "sample",
  "note": "sample",
  "claimHistoryCount": 1,
  "// Lockscreen & Security\n            'lockscreen_timeout": 1,
  "lockscreen_code": "sample",
  "// 4-digit PIN\n            'otp_enabled": true,
  "// YES/NO Option\n            'notifications_enabled": true,
  "email_notifications_enabled": true
}
```
- Success Response:
```json
{
  "message": "Profile updated successfully",
  "user": "string"
}
```

#### Profile - Change Password
- Method: `POST`
- Path: `/api/profile/change-password`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "current_password": "Password123!",
  "new_password": "Password123!"
}
```
- Success Response:
```json
{
  "message": "Password changed successfully"
}
```
- Error Response:
```json
{
  "message": "Current password is incorrect"
}
```

#### User - Authorizations
- Method: `GET`
- Path: `/api/user/authorizations/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
[
  {
    "user_id": "string",
    "operation_name": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

#### User - Sync
- Method: `POST`
- Path: `/api/user/authorizations/{id}/sync`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "operations": [],
  "operations.*": "sample"
}
```
- Success Response:
```json
{
  "message": "Permissions updated successfully"
}
```

#### List Page Permissions
- Method: `GET`
- Path: `/api/page-permissions`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "page_key": "string",
    "page_name": "string",
    "description": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

#### Users - Page Permissions
- Method: `GET`
- Path: `/api/users/{user}/page-permissions`
- Middleware: `auth:sanctum`
- Path Params:
  - `user` (string)
- Success Response:
```json
[
  {
    "user_id": "string",
    "page_permission_id": "string",
    "permission_value": 1,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

#### Users - Update
- Method: `POST`
- Path: `/api/users/{user}/page-permissions/update`
- Middleware: `auth:sanctum`
- Path Params:
  - `user` (string)
- Request Body (JSON):
```json
{
  "page_key": "sample",
  "permission_value": 1
}
```
- Success Response:
```json
{
  "message": "Permission updated successfully",
  "permission": {
    "user_id": "string",
    "page_permission_id": "string",
    "permission_value": 1,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```
- Error Response:
```json
{
  "error": "Page not found"
}
```

#### Users - Bulk Update
- Method: `POST`
- Path: `/api/users/{user}/page-permissions/bulk-update`
- Middleware: `auth:sanctum`
- Path Params:
  - `user` (string)
- Request Body (JSON):
```json
{
  "permissions": [],
  "permissions.*.page_key": "sample",
  "permissions.*.permission_value": 1
}
```
- Success Response:
```json
{
  "message": "Permissions updated successfully"
}
```

#### Users - Reset
- Method: `POST`
- Path: `/api/users/{user}/page-permissions/reset`
- Middleware: `auth:sanctum`
- Path Params:
  - `user` (string)
- Success Response:
```json
{
  "message": "All permissions reset to default"
}
```

#### Partner Users - Users
- Method: `GET`
- Path: `/api/partner/users`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {
    "id": 1
  }
]
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Partner Users - Users
- Method: `POST`
- Path: `/api/partner/users`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "role": "sample",
  "status": "success",
  "access_level": "sample"
}
```
- Success Response:
```json
{}
```

#### Partner Users - Users
- Method: `GET`
- Path: `/api/partner/users/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Partner Users - Users
- Method: `PUT`
- Path: `/api/partner/users/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "role": "sample",
  "status": "success",
  "access_level": "sample"
}
```
- Success Response:
```json
{}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Partner Users - Users
- Method: `DELETE`
- Path: `/api/partner/users/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "User deleted"
}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Partner Users - Users
- Method: `GET`
- Path: `/api/partner/users`
- Middleware: `auth:sanctum`
- Success Response:
```json
[
  {}
]
```

### Video
#### Video - Music Tracks
- Method: `GET`
- Path: `/api/video/music-tracks`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "message": "Success"
}
```

#### Get Video Jobs
- Method: `GET`
- Path: `/api/video-jobs/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "id": 1,
  "status": "string",
  "progress": 1,
  "video_url": "https://example.com",
  "duration_seconds": 1,
  "file_size_bytes": 1,
  "image_count": 1,
  "error_log": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### Webhooks
#### Webhooks - Mollie
- Method: `POST`
- Path: `/api/webhooks/mollie`
- Success Response:
```json
{
  "message": "ok"
}
```

#### Webhooks - Signhost
- Method: `POST`
- Path: `/api/webhooks/signhost`
- Success Response:
```json
{
  "message": "ok"
}
```

### Yachts
#### Yachts - Recognize Boat
- Method: `POST`
- Path: `/api/yachts/recognize-boat`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "image": "sample"
}
```
- Success Response:
```json
{
  "matched": false,
  "message": "Recognition service error: ' . $e->getMessage()"
}
```

#### Yachts - Generate Embedding
- Method: `POST`
- Path: `/api/yachts/{id}/generate-embedding`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Embedding generated successfully for \"' . $yacht->boat_name . ",
  "embedding_id": 1,
  "description": "string"
}
```
- Error Response:
```json
{
  "message": "Yacht has no main image to generate embedding from."
}
```

#### List Yachts
- Method: `GET`
- Path: `/api/yachts`
- Success Response:
```json
[
  {
    "vessel_id": "string",
    "name": "string",
    "status": "string",
    "price": 1.0,
    "current_bid": 1.0,
    "year": 1,
    "length": "string",
    "main_image": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "make": "string",
    "model": "string",
    "beam": "string",
    "draft": "string",
    "engine_type": "string",
    "fuel_type": "string",
    "fuel_capacity": "string",
    "water_capacity": "string",
    "cabins": 1,
    "heads": 1,
    "description": "string",
    "location": "string",
    "brand_model": "string",
    "vat_status": "string",
    "reference_code": "string",
    "construction_material": "string",
    "dimensions": "string",
    "berths": 1,
    "hull_shape": "string",
    "hull_color": "string",
    "deck_color": "string",
    "clearance": "string",
    "displacement": "string",
    "steering": "string",
    "engine_brand": "string",
    "engine_model": "string",
    "engine_power": "string",
    "engine_hours": "string",
    "max_speed": "string",
    "fuel_consumption": "string",
    "voltage": "string",
    "interior_type": "string",
    "water_tank": "string",
    "water_system": "string",
    "navigation_electronics": "string",
    "exterior_equipment": "string",
    "trailer_included": true,
    "safety_equipment": "string",
    "user_id": "string",
    "boat_name": "string",
    "allow_bidding": true,
    "external_url": "string",
    "print_url": "string",
    "owners_comment": "string",
    "reg_details": "string",
    "known_defects": "string",
    "last_serviced": "2024-01-01T00:00:00Z",
    "loa": "string",
    "lwl": "string",
    "air_draft": "string",
    "passenger_capacity": 1,
    "designer": "string",
    "builder": "string",
    "where": "string",
    "hull_colour": "string",
    "hull_construction": "string",
    "hull_number": "string",
    "hull_type": "string",
    "super_structure_colour": "string",
    "super_structure_construction": "string",
    "deck_colour": "string",
    "deck_construction": "string",
    "cockpit_type": "string",
    "control_type": "string",
    "flybridge": true,
    "ballast": "string",
    "toilet": 1,
    "shower": 1,
    "bath": 1,
    "oven": true,
    "microwave": true,
    "fridge": true,
    "freezer": true,
    "heating": "string",
    "air_conditioning": true,
    "stern_thruster": true,
    "bow_thruster": true,
    "fuel": "string",
    "hours": "string",
    "cruising_speed": "string",
    "horse_power": "string",
    "engine_manufacturer": "string",
    "engine_quantity": "string",
    "tankage": "string",
    "gallons_per_hour": "string",
    "litres_per_hour": "string",
    "engine_location": "string",
    "gearbox": "string",
    "cylinders": "string",
    "propeller_type": "string",
    "starting_type": "string",
    "drive_type": "string",
    "cooling_system": "string",
    "navigation_lights": true,
    "compass": true,
    "depth_instrument": true,
    "wind_instrument": true,
    "autopilot": true,
    "gps": true,
    "vhf": true,
    "plotter": true,
    "speed_instrument": true,
    "radar": true,
    "life_raft": true,
    "epirb": true,
    "bilge_pump": true,
    "fire_extinguisher": true,
    "mob_system": true,
    "genoa": "string",
    "spinnaker": true,
    "tri_sail": "string",
    "storm_jib": "string",
    "main_sail": "string",
    "winches": "string",
    "battery": true,
    "battery_charger": true,
    "generator": true,
    "inverter": true,
    "television": true,
    "cd_player": true,
    "dvd_player": true,
    "anchor": true,
    "spray_hood": true,
    "bimini": true,
    "fenders": "string",
    "min_bid_amount": 1.0,
    "display_specs": {},
    "boat_type_id": "string",
    "advertising_channels": "string",
    "boat_type": "string",
    "boat_category": "string",
    "new_or_used": "string",
    "manufacturer": "string",
    "vessel_lying": "string",
    "location_city": "string",
    "location_lat": 1.0,
    "location_lng": 1.0,
    "short_description_nl": "string",
    "short_description_en": "string",
    "motorization_summary": "string",
    "advertise_as": "string",
    "ce_category": "string",
    "ce_max_weight": "string",
    "ce_max_motor": "string",
    "cvo": "string",
    "cbb": "string",
    "windows": "string",
    "open_cockpit": "string",
    "aft_cockpit": "string",
    "minimum_height": "string",
    "variable_depth": "string",
    "max_draft": "string",
    "min_draft": "string",
    "ballast_tank": "string",
    "steering_system": "string",
    "steering_system_location": "string",
    "remote_control": "string",
    "rudder": "string",
    "drift_restriction": "string",
    "drift_restriction_controls": "string",
    "trimflaps": "string",
    "stabilizer": "string",
    "saloon": "string",
    "headroom": "string",
    "separate_dining_area": "string",
    "engine_room": "string",
    "spaces_inside": "string",
    "upholstery_color": "string",
    "matrasses": "string",
    "cushions": "string",
    "curtains": "string",
    "berths_fixed": "string",
    "berths_extra": "string",
    "berths_crew": "string",
    "water_tank_material": "string",
    "water_tank_gauge": "string",
    "water_maker": "string",
    "waste_water_tank": "string",
    "waste_water_tank_material": "string",
    "waste_water_tank_gauge": "string",
    "waste_water_tank_drainpump": "string",
    "deck_suction": "string",
    "hot_water": "string",
    "sea_water_pump": "string",
    "deck_wash_pump": "string",
    "deck_shower": "string",
    "cooker": "string",
    "cooking_fuel": "string",
    "hot_air": "string",
    "stove": "string",
    "central_heating": "string",
    "satellite_reception": "string",
    "engine_serial_number": "string",
    "engine_year": "string",
    "reversing_clutch": "string",
    "transmission": "string",
    "propulsion": "string",
    "fuel_tanks_amount": "string",
    "fuel_tank_total_capacity": "string",
    "fuel_tank_material": "string",
    "range_km": "string",
    "fuel_tank_gauge": "string",
    "tachometer": "string",
    "oil_pressure_gauge": "string",
    "temperature_gauge": "string",
    "dynamo": "string",
    "accumonitor": "string",
    "voltmeter": "string",
    "shorepower": "string",
    "shore_power_cable": "string",
    "wind_generator": "string",
    "solar_panel": "string",
    "consumption_monitor": "string",
    "control_panel": "string",
    "log_speed": "string",
    "windvane_steering": "string",
    "charts_guides": "string",
    "rudder_position_indicator": "string",
    "fishfinder": "string",
    "turn_indicator": "string",
    "ais": "string",
    "ssb_receiver": "string",
    "shortwave_radio": "string",
    "short_band_transmitter": "string",
    "weatherfax_navtex": "string",
    "satellite_communication": "string",
    "sailplan_type": "string",
    "number_of_masts": "string",
    "spars_material": "string",
    "bowsprit": "string",
    "standing_rig": "string",
    "sail_surface_area": "string",
    "stabilizer_sail": "string",
    "sail_amount": "string",
    "sail_material": "string",
    "sail_manufacturer": "string",
    "furling_mainsail": "string",
    "mizzen": "string",
    "furling_mizzen": "string",
    "jib": "string",
    "roller_furling_foresail": "string",
    "genoa_reefing_system": "string",
    "flying_jib": "string",
    "halfwinder_bollejan": "string",
    "gennaker": "string",
    "electric_winches": "string",
    "manual_winches": "string",
    "hydraulic_winches": "string",
    "self_tailing_winches": "string",
    "anchor_connection": "string",
    "anchor_winch": "string",
    "stern_anchor": "string",
    "spud_pole": "string",
    "cockpit_tent": "string",
    "outdoor_cushions": "string",
    "covers": "string",
    "sea_rails": "string",
    "pushpit_pullpit": "string",
    "swimming_platform": "string",
    "swimming_ladder": "string",
    "sail_lowering_system": "string",
    "crutch": "string",
    "dinghy": "string",
    "dinghy_brand": "string",
    "outboard_engine": "string",
    "trailer": "string",
    "crane": "string",
    "davits": "string",
    "teak_deck": "string",
    "cockpit_table": "string",
    "oars_paddles": "string",
    "life_buoy": "string",
    "bilge_pump_manual": "string",
    "bilge_pump_electric": "string",
    "radar_reflector": "string",
    "flares": "string",
    "life_jackets": "string",
    "watertight_door": "string",
    "gas_bottle_locker": "string",
    "self_draining_cockpit": "string"
  }
]
```

#### Get Yachts
- Method: `GET`
- Path: `/api/yachts/{id}`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```
- Error Response:
```json
{
  "message": "Vessel not found"
}
```

#### Yachts - Available Slots
- Method: `GET`
- Path: `/api/yachts/{id}/available-slots`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "timeSlots": [
    {
      "id": 1
    }
  ]
}
```

#### Yachts - Available Dates
- Method: `GET`
- Path: `/api/yachts/{id}/available-dates`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "availableDates": [
    {
      "id": 1
    }
  ]
}
```

#### Create Yachts
- Method: `POST`
- Path: `/api/yachts`
- Middleware: `auth:sanctum, permission:manage yachts`
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Yachts - Yachts
- Method: `POST`
- Path: `/api/yachts/{id}`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Yachts - Gallery
- Method: `POST`
- Path: `/api/yachts/{id}/gallery`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "images.*": "sample",
  "category": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "message": "No images detected"
}
```

#### Delete Yachts
- Method: `DELETE`
- Path: `/api/yachts/{id}`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Vessel removed from fleet."
}
```

#### Yachts - Ai Classify
- Method: `POST`
- Path: `/api/yachts/ai-classify`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "images.*": "sample"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Yachts
- Method: `PUT`
- Path: `/api/yachts/{id}`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Yachts - Like
- Method: `POST`
- Path: `/api/yachts/{id}/like`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "liked": true,
  "yacht": {
    "vessel_id": "string",
    "name": "string",
    "status": "string",
    "price": 1.0,
    "current_bid": 1.0,
    "year": 1,
    "length": "string",
    "main_image": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "make": "string",
    "model": "string",
    "beam": "string",
    "draft": "string",
    "engine_type": "string",
    "fuel_type": "string",
    "fuel_capacity": "string",
    "water_capacity": "string",
    "cabins": 1,
    "heads": 1,
    "description": "string",
    "location": "string",
    "brand_model": "string",
    "vat_status": "string",
    "reference_code": "string",
    "construction_material": "string",
    "dimensions": "string",
    "berths": 1,
    "hull_shape": "string",
    "hull_color": "string",
    "deck_color": "string",
    "clearance": "string",
    "displacement": "string",
    "steering": "string",
    "engine_brand": "string",
    "engine_model": "string",
    "engine_power": "string",
    "engine_hours": "string",
    "max_speed": "string",
    "fuel_consumption": "string",
    "voltage": "string",
    "interior_type": "string",
    "water_tank": "string",
    "water_system": "string",
    "navigation_electronics": "string",
    "exterior_equipment": "string",
    "trailer_included": true,
    "safety_equipment": "string",
    "user_id": "string",
    "boat_name": "string",
    "allow_bidding": true,
    "external_url": "string",
    "print_url": "string",
    "owners_comment": "string",
    "reg_details": "string",
    "known_defects": "string",
    "last_serviced": "2024-01-01T00:00:00Z",
    "loa": "string",
    "lwl": "string",
    "air_draft": "string",
    "passenger_capacity": 1,
    "designer": "string",
    "builder": "string",
    "where": "string",
    "hull_colour": "string",
    "hull_construction": "string",
    "hull_number": "string",
    "hull_type": "string",
    "super_structure_colour": "string",
    "super_structure_construction": "string",
    "deck_colour": "string",
    "deck_construction": "string",
    "cockpit_type": "string",
    "control_type": "string",
    "flybridge": true,
    "ballast": "string",
    "toilet": 1,
    "shower": 1,
    "bath": 1,
    "oven": true,
    "microwave": true,
    "fridge": true,
    "freezer": true,
    "heating": "string",
    "air_conditioning": true,
    "stern_thruster": true,
    "bow_thruster": true,
    "fuel": "string",
    "hours": "string",
    "cruising_speed": "string",
    "horse_power": "string",
    "engine_manufacturer": "string",
    "engine_quantity": "string",
    "tankage": "string",
    "gallons_per_hour": "string",
    "litres_per_hour": "string",
    "engine_location": "string",
    "gearbox": "string",
    "cylinders": "string",
    "propeller_type": "string",
    "starting_type": "string",
    "drive_type": "string",
    "cooling_system": "string",
    "navigation_lights": true,
    "compass": true,
    "depth_instrument": true,
    "wind_instrument": true,
    "autopilot": true,
    "gps": true,
    "vhf": true,
    "plotter": true,
    "speed_instrument": true,
    "radar": true,
    "life_raft": true,
    "epirb": true,
    "bilge_pump": true,
    "fire_extinguisher": true,
    "mob_system": true,
    "genoa": "string",
    "spinnaker": true,
    "tri_sail": "string",
    "storm_jib": "string",
    "main_sail": "string",
    "winches": "string",
    "battery": true,
    "battery_charger": true,
    "generator": true,
    "inverter": true,
    "television": true,
    "cd_player": true,
    "dvd_player": true,
    "anchor": true,
    "spray_hood": true,
    "bimini": true,
    "fenders": "string",
    "min_bid_amount": 1.0,
    "display_specs": {},
    "boat_type_id": "string",
    "advertising_channels": "string",
    "boat_type": "string",
    "boat_category": "string",
    "new_or_used": "string",
    "manufacturer": "string",
    "vessel_lying": "string",
    "location_city": "string",
    "location_lat": 1.0,
    "location_lng": 1.0,
    "short_description_nl": "string",
    "short_description_en": "string",
    "motorization_summary": "string",
    "advertise_as": "string",
    "ce_category": "string",
    "ce_max_weight": "string",
    "ce_max_motor": "string",
    "cvo": "string",
    "cbb": "string",
    "windows": "string",
    "open_cockpit": "string",
    "aft_cockpit": "string",
    "minimum_height": "string",
    "variable_depth": "string",
    "max_draft": "string",
    "min_draft": "string",
    "ballast_tank": "string",
    "steering_system": "string",
    "steering_system_location": "string",
    "remote_control": "string",
    "rudder": "string",
    "drift_restriction": "string",
    "drift_restriction_controls": "string",
    "trimflaps": "string",
    "stabilizer": "string",
    "saloon": "string",
    "headroom": "string",
    "separate_dining_area": "string",
    "engine_room": "string",
    "spaces_inside": "string",
    "upholstery_color": "string",
    "matrasses": "string",
    "cushions": "string",
    "curtains": "string",
    "berths_fixed": "string",
    "berths_extra": "string",
    "berths_crew": "string",
    "water_tank_material": "string",
    "water_tank_gauge": "string",
    "water_maker": "string",
    "waste_water_tank": "string",
    "waste_water_tank_material": "string",
    "waste_water_tank_gauge": "string",
    "waste_water_tank_drainpump": "string",
    "deck_suction": "string",
    "hot_water": "string",
    "sea_water_pump": "string",
    "deck_wash_pump": "string",
    "deck_shower": "string",
    "cooker": "string",
    "cooking_fuel": "string",
    "hot_air": "string",
    "stove": "string",
    "central_heating": "string",
    "satellite_reception": "string",
    "engine_serial_number": "string",
    "engine_year": "string",
    "reversing_clutch": "string",
    "transmission": "string",
    "propulsion": "string",
    "fuel_tanks_amount": "string",
    "fuel_tank_total_capacity": "string",
    "fuel_tank_material": "string",
    "range_km": "string",
    "fuel_tank_gauge": "string",
    "tachometer": "string",
    "oil_pressure_gauge": "string",
    "temperature_gauge": "string",
    "dynamo": "string",
    "accumonitor": "string",
    "voltmeter": "string",
    "shorepower": "string",
    "shore_power_cable": "string",
    "wind_generator": "string",
    "solar_panel": "string",
    "consumption_monitor": "string",
    "control_panel": "string",
    "log_speed": "string",
    "windvane_steering": "string",
    "charts_guides": "string",
    "rudder_position_indicator": "string",
    "fishfinder": "string",
    "turn_indicator": "string",
    "ais": "string",
    "ssb_receiver": "string",
    "shortwave_radio": "string",
    "short_band_transmitter": "string",
    "weatherfax_navtex": "string",
    "satellite_communication": "string",
    "sailplan_type": "string",
    "number_of_masts": "string",
    "spars_material": "string",
    "bowsprit": "string",
    "standing_rig": "string",
    "sail_surface_area": "string",
    "stabilizer_sail": "string",
    "sail_amount": "string",
    "sail_material": "string",
    "sail_manufacturer": "string",
    "furling_mainsail": "string",
    "mizzen": "string",
    "furling_mizzen": "string",
    "jib": "string",
    "roller_furling_foresail": "string",
    "genoa_reefing_system": "string",
    "flying_jib": "string",
    "halfwinder_bollejan": "string",
    "gennaker": "string",
    "electric_winches": "string",
    "manual_winches": "string",
    "hydraulic_winches": "string",
    "self_tailing_winches": "string",
    "anchor_connection": "string",
    "anchor_winch": "string",
    "stern_anchor": "string",
    "spud_pole": "string",
    "cockpit_tent": "string",
    "outdoor_cushions": "string",
    "covers": "string",
    "sea_rails": "string",
    "pushpit_pullpit": "string",
    "swimming_platform": "string",
    "swimming_ladder": "string",
    "sail_lowering_system": "string",
    "crutch": "string",
    "dinghy": "string",
    "dinghy_brand": "string",
    "outboard_engine": "string",
    "trailer": "string",
    "crane": "string",
    "davits": "string",
    "teak_deck": "string",
    "cockpit_table": "string",
    "oars_paddles": "string",
    "life_buoy": "string",
    "bilge_pump_manual": "string",
    "bilge_pump_electric": "string",
    "radar_reflector": "string",
    "flares": "string",
    "life_jackets": "string",
    "watertight_door": "string",
    "gas_bottle_locker": "string",
    "self_draining_cockpit": "string"
  }
}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Yachts - Liked
- Method: `GET`
- Path: `/api/yachts/liked`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "data": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Partner Yachts - Yachts
- Method: `POST`
- Path: `/api/partner/yachts`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Partner Yachts - Gallery
- Method: `POST`
- Path: `/api/partner/yachts/{id}/gallery`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "images.*": "sample",
  "category": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "message": "No images detected"
}
```

#### Partner Yachts - Ai Classify
- Method: `POST`
- Path: `/api/partner/yachts/ai-classify`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "images.*": "sample"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Yachts - Book
- Method: `POST`
- Path: `/api/yachts/{id}/book`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "start_at": "2024-01-01"
}
```
- Success Response:
```json
{
  "yacht_id": "string",
  "start_at": "2024-01-01T00:00:00Z",
  "end_at": "2024-01-01T00:00:00Z",
  "status": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "user_id": "string",
  "seller_user_id": "string",
  "bid_id": "string",
  "deal_id": "string",
  "location": "string",
  "type": "string"
}
```
- Error Response:
```json
{
  "error": "Unauthorized"
}
```

#### Yachts - Inspection Report
- Method: `GET`
- Path: `/api/yachts/{id}/inspection-report`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "has_report": true,
  "yacht": "string",
  "inspection": "string",
  "items": [
    {
      "id": 1
    }
  ],
  "summary": "string"
}
```

#### Yachts - Pdf
- Method: `GET`
- Path: `/api/yachts/{id}/inspection-report/pdf`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response: `200 application/pdf`
```
<binary pdf>
```

#### Yachts - Syndicate
- Method: `POST`
- Path: `/api/yachts/{id}/syndicate`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "yacht_id": 1,
  "channels": [
    {
      "id": 1
    }
  ]
}
```

#### Yachts - Generate Video
- Method: `POST`
- Path: `/api/yachts/{id}/generate-video`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "music_track": "sample",
  "voiceover_path": "sample"
}
```
- Success Response:
```json
{
  "message": "Video rendering queued",
  "job": {
    "yacht_id": "string",
    "user_id": "string",
    "status": "string",
    "video_path": "string",
    "error_log": "string",
    "duration_seconds": 1,
    "file_size_bytes": 1,
    "music_track": "string",
    "has_voiceover": true,
    "voiceover_path": "string",
    "image_count": 1,
    "progress": 1,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```
- Error Response:
```json
{
  "message": "A video is already being rendered for this yacht",
  "job": {
    "yacht_id": "string",
    "user_id": "string",
    "status": "string",
    "video_path": "string",
    "error_log": "string",
    "duration_seconds": 1,
    "file_size_bytes": 1,
    "music_track": "string",
    "has_voiceover": true,
    "voiceover_path": "string",
    "image_count": 1,
    "progress": 1,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```

#### Yachts - Videos
- Method: `GET`
- Path: `/api/yachts/{id}/videos`
- Middleware: `auth:sanctum`
- Path Params:
  - `id` (string)
- Success Response:
```json
[
  {
    "yacht_id": "string",
    "user_id": "string",
    "status": "string",
    "video_path": "string",
    "error_log": "string",
    "duration_seconds": 1,
    "file_size_bytes": 1,
    "music_track": "string",
    "has_voiceover": true,
    "voiceover_path": "string",
    "image_count": 1,
    "progress": 1,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```

## Legacy (routes/api copy.php) Routes (routes/api copy.php)
### Tasks & Appointments
#### List Tasks
- Method: `GET`
- Path: `/api/tasks`
- Middleware: `auth:sanctum, permission:manage tasks`
- Success Response:
```json
[
  {
    "title": "string",
    "description": "string",
    "priority": "string",
    "status": "string",
    "assigned_to": "string",
    "yacht_id": "string",
    "due_date": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "created_by": "string",
    "user_id": "string",
    "type": "string",
    "assignment_status": "string",
    "appointment_id": "string"
  }
]
```

#### Create Tasks
- Method: `POST`
- Path: `/api/tasks`
- Middleware: `auth:sanctum, permission:manage tasks`
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "due_date": "2024-01-01",
  "type": "sample",
  "assigned_to": 1,
  "yacht_id": 1
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "errors": [
    {
      "id": 1
    }
  ]
}
```

#### Get Tasks
- Method: `GET`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Update Tasks
- Method: `PUT`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "assigned_to": "sample",
  "yacht_id": "sample",
  "due_date": "2024-01-01"
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Update Tasks
- Method: `PATCH`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "assigned_to": "sample",
  "yacht_id": "sample",
  "due_date": "2024-01-01"
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Delete Tasks
- Method: `DELETE`
- Path: `/api/tasks/{id}`
- Middleware: `auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Task deleted successfully"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Tasks - Status
- Method: `PATCH`
- Path: `/api/tasks/{id}/status`
- Middleware: `auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "status": "success"
}
```
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

### Users
#### List Users
- Method: `GET`
- Path: `/api/users`
- Middleware: `auth:sanctum, permission:manage users`
- Success Response:
```json
[
  {}
]
```

#### Create Users
- Method: `POST`
- Path: `/api/users`
- Middleware: `auth:sanctum, permission:manage users`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "role": "sample",
  "// Added Partner [cite: 6]\n            'status": "sample",
  "access_level": "sample"
}
```
- Success Response:
```json
{}
```

#### Get Users
- Method: `GET`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Users
- Method: `PUT`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "sample",
  "// Added Partner [cite: 13]\n            'status": "sample",
  "access_level": "sample",
  "password": "Password123!"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Users
- Method: `PATCH`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "sample",
  "// Added Partner [cite: 13]\n            'status": "sample",
  "access_level": "sample",
  "password": "Password123!"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Delete Users
- Method: `DELETE`
- Path: `/api/users/{id}`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "User deleted successfully"
}
```
- Error Response:
```json
{
  "message": "Cannot terminate your own session."
}
```

#### Users - Toggle Status
- Method: `PATCH`
- Path: `/api/users/{user}/toggle-status`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Users - Toggle Permission
- Method: `POST`
- Path: `/api/users/{user}/toggle-permission`
- Middleware: `auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Request Body (JSON):
```json
{
  "permission": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "current_permissions": [
    {
      "id": 1
    }
  ],
  "message": "Permission \" . ($status === 'attached' ? 'granted' : 'revoked')"
}
```

### Yachts
#### List Yachts
- Method: `GET`
- Path: `/api/yachts`
- Success Response:
```json
[
  {
    "vessel_id": "string",
    "name": "string",
    "status": "string",
    "price": 1.0,
    "current_bid": 1.0,
    "year": 1,
    "length": "string",
    "main_image": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "make": "string",
    "model": "string",
    "beam": "string",
    "draft": "string",
    "engine_type": "string",
    "fuel_type": "string",
    "fuel_capacity": "string",
    "water_capacity": "string",
    "cabins": 1,
    "heads": 1,
    "description": "string",
    "location": "string",
    "brand_model": "string",
    "vat_status": "string",
    "reference_code": "string",
    "construction_material": "string",
    "dimensions": "string",
    "berths": 1,
    "hull_shape": "string",
    "hull_color": "string",
    "deck_color": "string",
    "clearance": "string",
    "displacement": "string",
    "steering": "string",
    "engine_brand": "string",
    "engine_model": "string",
    "engine_power": "string",
    "engine_hours": "string",
    "max_speed": "string",
    "fuel_consumption": "string",
    "voltage": "string",
    "interior_type": "string",
    "water_tank": "string",
    "water_system": "string",
    "navigation_electronics": "string",
    "exterior_equipment": "string",
    "trailer_included": true,
    "safety_equipment": "string",
    "user_id": "string",
    "boat_name": "string",
    "allow_bidding": true,
    "external_url": "string",
    "print_url": "string",
    "owners_comment": "string",
    "reg_details": "string",
    "known_defects": "string",
    "last_serviced": "2024-01-01T00:00:00Z",
    "loa": "string",
    "lwl": "string",
    "air_draft": "string",
    "passenger_capacity": 1,
    "designer": "string",
    "builder": "string",
    "where": "string",
    "hull_colour": "string",
    "hull_construction": "string",
    "hull_number": "string",
    "hull_type": "string",
    "super_structure_colour": "string",
    "super_structure_construction": "string",
    "deck_colour": "string",
    "deck_construction": "string",
    "cockpit_type": "string",
    "control_type": "string",
    "flybridge": true,
    "ballast": "string",
    "toilet": 1,
    "shower": 1,
    "bath": 1,
    "oven": true,
    "microwave": true,
    "fridge": true,
    "freezer": true,
    "heating": "string",
    "air_conditioning": true,
    "stern_thruster": true,
    "bow_thruster": true,
    "fuel": "string",
    "hours": "string",
    "cruising_speed": "string",
    "horse_power": "string",
    "engine_manufacturer": "string",
    "engine_quantity": "string",
    "tankage": "string",
    "gallons_per_hour": "string",
    "litres_per_hour": "string",
    "engine_location": "string",
    "gearbox": "string",
    "cylinders": "string",
    "propeller_type": "string",
    "starting_type": "string",
    "drive_type": "string",
    "cooling_system": "string",
    "navigation_lights": true,
    "compass": true,
    "depth_instrument": true,
    "wind_instrument": true,
    "autopilot": true,
    "gps": true,
    "vhf": true,
    "plotter": true,
    "speed_instrument": true,
    "radar": true,
    "life_raft": true,
    "epirb": true,
    "bilge_pump": true,
    "fire_extinguisher": true,
    "mob_system": true,
    "genoa": "string",
    "spinnaker": true,
    "tri_sail": "string",
    "storm_jib": "string",
    "main_sail": "string",
    "winches": "string",
    "battery": true,
    "battery_charger": true,
    "generator": true,
    "inverter": true,
    "television": true,
    "cd_player": true,
    "dvd_player": true,
    "anchor": true,
    "spray_hood": true,
    "bimini": true,
    "fenders": "string",
    "min_bid_amount": 1.0,
    "display_specs": {},
    "boat_type_id": "string",
    "advertising_channels": "string",
    "boat_type": "string",
    "boat_category": "string",
    "new_or_used": "string",
    "manufacturer": "string",
    "vessel_lying": "string",
    "location_city": "string",
    "location_lat": 1.0,
    "location_lng": 1.0,
    "short_description_nl": "string",
    "short_description_en": "string",
    "motorization_summary": "string",
    "advertise_as": "string",
    "ce_category": "string",
    "ce_max_weight": "string",
    "ce_max_motor": "string",
    "cvo": "string",
    "cbb": "string",
    "windows": "string",
    "open_cockpit": "string",
    "aft_cockpit": "string",
    "minimum_height": "string",
    "variable_depth": "string",
    "max_draft": "string",
    "min_draft": "string",
    "ballast_tank": "string",
    "steering_system": "string",
    "steering_system_location": "string",
    "remote_control": "string",
    "rudder": "string",
    "drift_restriction": "string",
    "drift_restriction_controls": "string",
    "trimflaps": "string",
    "stabilizer": "string",
    "saloon": "string",
    "headroom": "string",
    "separate_dining_area": "string",
    "engine_room": "string",
    "spaces_inside": "string",
    "upholstery_color": "string",
    "matrasses": "string",
    "cushions": "string",
    "curtains": "string",
    "berths_fixed": "string",
    "berths_extra": "string",
    "berths_crew": "string",
    "water_tank_material": "string",
    "water_tank_gauge": "string",
    "water_maker": "string",
    "waste_water_tank": "string",
    "waste_water_tank_material": "string",
    "waste_water_tank_gauge": "string",
    "waste_water_tank_drainpump": "string",
    "deck_suction": "string",
    "hot_water": "string",
    "sea_water_pump": "string",
    "deck_wash_pump": "string",
    "deck_shower": "string",
    "cooker": "string",
    "cooking_fuel": "string",
    "hot_air": "string",
    "stove": "string",
    "central_heating": "string",
    "satellite_reception": "string",
    "engine_serial_number": "string",
    "engine_year": "string",
    "reversing_clutch": "string",
    "transmission": "string",
    "propulsion": "string",
    "fuel_tanks_amount": "string",
    "fuel_tank_total_capacity": "string",
    "fuel_tank_material": "string",
    "range_km": "string",
    "fuel_tank_gauge": "string",
    "tachometer": "string",
    "oil_pressure_gauge": "string",
    "temperature_gauge": "string",
    "dynamo": "string",
    "accumonitor": "string",
    "voltmeter": "string",
    "shorepower": "string",
    "shore_power_cable": "string",
    "wind_generator": "string",
    "solar_panel": "string",
    "consumption_monitor": "string",
    "control_panel": "string",
    "log_speed": "string",
    "windvane_steering": "string",
    "charts_guides": "string",
    "rudder_position_indicator": "string",
    "fishfinder": "string",
    "turn_indicator": "string",
    "ais": "string",
    "ssb_receiver": "string",
    "shortwave_radio": "string",
    "short_band_transmitter": "string",
    "weatherfax_navtex": "string",
    "satellite_communication": "string",
    "sailplan_type": "string",
    "number_of_masts": "string",
    "spars_material": "string",
    "bowsprit": "string",
    "standing_rig": "string",
    "sail_surface_area": "string",
    "stabilizer_sail": "string",
    "sail_amount": "string",
    "sail_material": "string",
    "sail_manufacturer": "string",
    "furling_mainsail": "string",
    "mizzen": "string",
    "furling_mizzen": "string",
    "jib": "string",
    "roller_furling_foresail": "string",
    "genoa_reefing_system": "string",
    "flying_jib": "string",
    "halfwinder_bollejan": "string",
    "gennaker": "string",
    "electric_winches": "string",
    "manual_winches": "string",
    "hydraulic_winches": "string",
    "self_tailing_winches": "string",
    "anchor_connection": "string",
    "anchor_winch": "string",
    "stern_anchor": "string",
    "spud_pole": "string",
    "cockpit_tent": "string",
    "outdoor_cushions": "string",
    "covers": "string",
    "sea_rails": "string",
    "pushpit_pullpit": "string",
    "swimming_platform": "string",
    "swimming_ladder": "string",
    "sail_lowering_system": "string",
    "crutch": "string",
    "dinghy": "string",
    "dinghy_brand": "string",
    "outboard_engine": "string",
    "trailer": "string",
    "crane": "string",
    "davits": "string",
    "teak_deck": "string",
    "cockpit_table": "string",
    "oars_paddles": "string",
    "life_buoy": "string",
    "bilge_pump_manual": "string",
    "bilge_pump_electric": "string",
    "radar_reflector": "string",
    "flares": "string",
    "life_jackets": "string",
    "watertight_door": "string",
    "gas_bottle_locker": "string",
    "self_draining_cockpit": "string"
  }
]
```

#### Get Yachts
- Method: `GET`
- Path: `/api/yachts/{id}`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```
- Error Response:
```json
{
  "message": "Vessel not found"
}
```

#### Create Yachts
- Method: `POST`
- Path: `/api/yachts`
- Middleware: `auth:sanctum, permission:manage yachts`
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Yachts - Gallery
- Method: `POST`
- Path: `/api/yachts/{id}/gallery`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "images.*": "sample",
  "category": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "message": "No images detected"
}
```

#### Update Yachts
- Method: `PUT`
- Path: `/api/yachts/{id}`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Update Yachts
- Method: `PATCH`
- Path: `/api/yachts/{id}`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Delete Yachts
- Method: `DELETE`
- Path: `/api/yachts/{id}`
- Middleware: `auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Vessel removed from fleet."
}
```

## Legacy (routes/api copy 2.php) Routes (routes/api copy 2.php)
### AI & Search
#### Ai - Chat
- Method: `POST`
- Path: `/api/ai/chat`
- Middleware: `cors.public`
- Success Response:
```json
{
  "reply": "string",
  "voice_meta": "string"
}
```
- Error Response:
```json
{
  "error": "Concierge Offline: ' . $e->getMessage()"
}
```

### Analytics
#### Analytics - Track
- Method: `POST`
- Path: `/api/analytics/track`
- Middleware: `cors.public`
- Success Response:
```json
{
  "status": "synced"
}
```

#### Analytics - Summary
- Method: `GET`
- Path: `/api/analytics/summary`
- Middleware: `cors.public`
- Success Response:
```json
[
  {
    "external_id": "string",
    "name": "string",
    "model": "string",
    "price": "string",
    "ref_code": "string",
    "url": "string",
    "ip_address": "string",
    "user_agent": "string",
    "raw_specs": {},
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
]
```
- Error Response:
```json
{
  "error": "string"
}
```

### Auth
#### Login
- Method: `POST`
- Path: `/api/login`
- Middleware: `cors.public`
- Request Body (JSON):
```json
{
  "email": "user@example.com",
  "password": "Password123!"
}
```
- Success Response:
```json
{
  "token": "token_example",
  "id": 1,
  "name": "Jane Doe",
  "email": "user@example.com",
  "userType": "string",
  "status": "success",
  "access_level": "string",
  "permissions": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "message": "Identity could not be verified. Check credentials."
}
```

#### Register Partner
- Method: `POST`
- Path: `/api/register/partner`
- Middleware: `cors.public`
- Success Response:
```json
{
  "userType": "Partner",
  "id": 1,
  "verification_required": true
}
```
- Error Response:
```json
{
  "message": "Direct Insert Failed: ' . $e->getMessage()"
}
```

#### Register User
- Method: `POST`
- Path: `/api/register`
- Middleware: `cors.public`
- Success Response:
```json
{
  "userType": "Customer",
  "id": 1,
  "verification_required": true
}
```
- Error Response:
```json
{
  "message": "Direct Insert Failed: ' . $e->getMessage()"
}
```

### Bids
#### Bids - History
- Method: `GET`
- Path: `/api/bids/{id}/history`
- Middleware: `cors.public`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "bids": {
    "yacht_id": "string",
    "user_id": "string",
    "amount": 1.0,
    "status": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "finalized_at": "2024-01-01T00:00:00Z",
    "finalized_by": "string"
  },
  "highestBid": "string"
}
```

#### Bids - Place
- Method: `POST`
- Path: `/api/bids/place`
- Middleware: `cors.private, auth:sanctum`
- Request Body (JSON):
```json
{
  "yacht_id": "sample",
  "amount": 1000.0
}
```
- Success Response:
```json
{
  "message": "Bid placed successfully.",
  "bid": "string"
}
```
- Error Response:
```json
{
  "message": "Bidding is closed. Vessel is sold."
}
```

#### Bids - Accept
- Method: `POST`
- Path: `/api/bids/{id}/accept`
- Middleware: `cors.private, auth:sanctum, permission:accept bids`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Bid accepted. Vessel marked as Sold."
}
```

#### Bids - Decline
- Method: `POST`
- Path: `/api/bids/{id}/decline`
- Middleware: `cors.private, auth:sanctum, permission:accept bids`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Bid declined."
}
```

### Misc
#### Delete Gallery
- Method: `DELETE`
- Path: `/api/gallery/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Image removed"
}
```

#### List Permissions
- Method: `GET`
- Path: `/api/permissions`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Success Response:
```json
{
  "message": "Success"
}
```

#### List Roles
- Method: `GET`
- Path: `/api/roles`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Success Response:
```json
{
  "message": "Success"
}
```

### Tasks & Appointments
#### List Tasks
- Method: `GET`
- Path: `/api/tasks`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Success Response:
```json
[
  {
    "title": "string",
    "description": "string",
    "priority": "string",
    "status": "string",
    "assigned_to": "string",
    "yacht_id": "string",
    "due_date": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "created_by": "string",
    "user_id": "string",
    "type": "string",
    "assignment_status": "string",
    "appointment_id": "string"
  }
]
```

#### Create Tasks
- Method: `POST`
- Path: `/api/tasks`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "due_date": "2024-01-01",
  "type": "sample",
  "assigned_to": 1,
  "yacht_id": 1
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "errors": [
    {
      "id": 1
    }
  ]
}
```

#### Get Tasks
- Method: `GET`
- Path: `/api/tasks/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Update Tasks
- Method: `PUT`
- Path: `/api/tasks/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "assigned_to": "sample",
  "yacht_id": "sample",
  "due_date": "2024-01-01"
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Update Tasks
- Method: `PATCH`
- Path: `/api/tasks/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "title": "Sample Title",
  "description": "Sample description",
  "priority": "sample",
  "status": "success",
  "assigned_to": "sample",
  "yacht_id": "sample",
  "due_date": "2024-01-01"
}
```
- Success Response:
```json
"string"
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Delete Tasks
- Method: `DELETE`
- Path: `/api/tasks/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Task deleted successfully"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

#### Tasks - Status
- Method: `PATCH`
- Path: `/api/tasks/{id}/status`
- Middleware: `cors.private, auth:sanctum, permission:manage tasks`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "status": "success"
}
```
- Success Response:
```json
{
  "title": "string",
  "description": "string",
  "priority": "string",
  "status": "string",
  "assigned_to": "string",
  "yacht_id": "string",
  "due_date": "2024-01-01T00:00:00Z",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "created_by": "string",
  "user_id": "string",
  "type": "string",
  "assignment_status": "string",
  "appointment_id": "string"
}
```
- Error Response:
```json
{
  "error": "Task not found"
}
```

### Users
#### List Users
- Method: `GET`
- Path: `/api/users`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Success Response:
```json
[
  {}
]
```

#### Create Users
- Method: `POST`
- Path: `/api/users`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "password": "Password123!",
  "role": "sample",
  "// Added Partner [cite: 6]\n            'status": "sample",
  "access_level": "sample"
}
```
- Success Response:
```json
{}
```

#### Get Users
- Method: `GET`
- Path: `/api/users/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Users
- Method: `PUT`
- Path: `/api/users/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "sample",
  "// Added Partner [cite: 13]\n            'status": "sample",
  "access_level": "sample",
  "password": "Password123!"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Update Users
- Method: `PATCH`
- Path: `/api/users/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "user@example.com",
  "role": "sample",
  "// Added Partner [cite: 13]\n            'status": "sample",
  "access_level": "sample",
  "password": "Password123!"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Delete Users
- Method: `DELETE`
- Path: `/api/users/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "User deleted successfully"
}
```
- Error Response:
```json
{
  "message": "Cannot terminate your own session."
}
```

#### Users - Toggle Status
- Method: `PATCH`
- Path: `/api/users/{user}/toggle-status`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

#### Users - Toggle Permission
- Method: `POST`
- Path: `/api/users/{user}/toggle-permission`
- Middleware: `cors.private, auth:sanctum, permission:manage users`
- Path Params:
  - `user` (string)
- Request Body (JSON):
```json
{
  "permission": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "current_permissions": [
    {
      "id": 1
    }
  ],
  "message": "Permission \" . ($status === 'attached' ? 'granted' : 'revoked')"
}
```

#### List Profile
- Method: `GET`
- Path: `/api/profile`
- Middleware: `cors.private, auth:sanctum`
- Success Response:
```json
{
  "message": "Success"
}
```

#### Profile - Update
- Method: `POST`
- Path: `/api/profile/update`
- Middleware: `cors.private, auth:sanctum`
- Request Body (JSON):
```json
{
  "// Existing fields\n            'name": "Jane Doe",
  "email": "user@example.com",
  "phone_number": "sample",
  "address": "sample",
  "city": "sample",
  "state": "sample",
  "postcode": "sample",
  "country": "sample",
  "profile_image": "sample",
  "// New personal fields\n            'relationNumber": "sample",
  "firstName": "sample",
  "lastName": "sample",
  "prefix": "sample",
  "initials": "sample",
  "title": "Sample Title",
  "salutation": "sample",
  "attentionOf": "sample",
  "identification": "sample",
  "dateOfBirth": "2024-01-01",
  "website": "http://localhost:8000/redirect",
  "mobile": "sample",
  "street": "sample",
  "houseNumber": "sample",
  "note": "sample",
  "claimHistoryCount": 1,
  "// Lockscreen & Security\n            'lockscreen_timeout": 1,
  "lockscreen_code": "sample",
  "// 4-digit PIN\n            'otp_enabled": true,
  "// YES/NO Option\n            'notifications_enabled": true,
  "email_notifications_enabled": true
}
```
- Success Response:
```json
{
  "message": "Profile updated successfully",
  "user": "string"
}
```

### Yachts
#### List Yachts
- Method: `GET`
- Path: `/api/yachts`
- Middleware: `cors.public`
- Success Response:
```json
[
  {
    "vessel_id": "string",
    "name": "string",
    "status": "string",
    "price": 1.0,
    "current_bid": 1.0,
    "year": 1,
    "length": "string",
    "main_image": "string",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z",
    "make": "string",
    "model": "string",
    "beam": "string",
    "draft": "string",
    "engine_type": "string",
    "fuel_type": "string",
    "fuel_capacity": "string",
    "water_capacity": "string",
    "cabins": 1,
    "heads": 1,
    "description": "string",
    "location": "string",
    "brand_model": "string",
    "vat_status": "string",
    "reference_code": "string",
    "construction_material": "string",
    "dimensions": "string",
    "berths": 1,
    "hull_shape": "string",
    "hull_color": "string",
    "deck_color": "string",
    "clearance": "string",
    "displacement": "string",
    "steering": "string",
    "engine_brand": "string",
    "engine_model": "string",
    "engine_power": "string",
    "engine_hours": "string",
    "max_speed": "string",
    "fuel_consumption": "string",
    "voltage": "string",
    "interior_type": "string",
    "water_tank": "string",
    "water_system": "string",
    "navigation_electronics": "string",
    "exterior_equipment": "string",
    "trailer_included": true,
    "safety_equipment": "string",
    "user_id": "string",
    "boat_name": "string",
    "allow_bidding": true,
    "external_url": "string",
    "print_url": "string",
    "owners_comment": "string",
    "reg_details": "string",
    "known_defects": "string",
    "last_serviced": "2024-01-01T00:00:00Z",
    "loa": "string",
    "lwl": "string",
    "air_draft": "string",
    "passenger_capacity": 1,
    "designer": "string",
    "builder": "string",
    "where": "string",
    "hull_colour": "string",
    "hull_construction": "string",
    "hull_number": "string",
    "hull_type": "string",
    "super_structure_colour": "string",
    "super_structure_construction": "string",
    "deck_colour": "string",
    "deck_construction": "string",
    "cockpit_type": "string",
    "control_type": "string",
    "flybridge": true,
    "ballast": "string",
    "toilet": 1,
    "shower": 1,
    "bath": 1,
    "oven": true,
    "microwave": true,
    "fridge": true,
    "freezer": true,
    "heating": "string",
    "air_conditioning": true,
    "stern_thruster": true,
    "bow_thruster": true,
    "fuel": "string",
    "hours": "string",
    "cruising_speed": "string",
    "horse_power": "string",
    "engine_manufacturer": "string",
    "engine_quantity": "string",
    "tankage": "string",
    "gallons_per_hour": "string",
    "litres_per_hour": "string",
    "engine_location": "string",
    "gearbox": "string",
    "cylinders": "string",
    "propeller_type": "string",
    "starting_type": "string",
    "drive_type": "string",
    "cooling_system": "string",
    "navigation_lights": true,
    "compass": true,
    "depth_instrument": true,
    "wind_instrument": true,
    "autopilot": true,
    "gps": true,
    "vhf": true,
    "plotter": true,
    "speed_instrument": true,
    "radar": true,
    "life_raft": true,
    "epirb": true,
    "bilge_pump": true,
    "fire_extinguisher": true,
    "mob_system": true,
    "genoa": "string",
    "spinnaker": true,
    "tri_sail": "string",
    "storm_jib": "string",
    "main_sail": "string",
    "winches": "string",
    "battery": true,
    "battery_charger": true,
    "generator": true,
    "inverter": true,
    "television": true,
    "cd_player": true,
    "dvd_player": true,
    "anchor": true,
    "spray_hood": true,
    "bimini": true,
    "fenders": "string",
    "min_bid_amount": 1.0,
    "display_specs": {},
    "boat_type_id": "string",
    "advertising_channels": "string",
    "boat_type": "string",
    "boat_category": "string",
    "new_or_used": "string",
    "manufacturer": "string",
    "vessel_lying": "string",
    "location_city": "string",
    "location_lat": 1.0,
    "location_lng": 1.0,
    "short_description_nl": "string",
    "short_description_en": "string",
    "motorization_summary": "string",
    "advertise_as": "string",
    "ce_category": "string",
    "ce_max_weight": "string",
    "ce_max_motor": "string",
    "cvo": "string",
    "cbb": "string",
    "windows": "string",
    "open_cockpit": "string",
    "aft_cockpit": "string",
    "minimum_height": "string",
    "variable_depth": "string",
    "max_draft": "string",
    "min_draft": "string",
    "ballast_tank": "string",
    "steering_system": "string",
    "steering_system_location": "string",
    "remote_control": "string",
    "rudder": "string",
    "drift_restriction": "string",
    "drift_restriction_controls": "string",
    "trimflaps": "string",
    "stabilizer": "string",
    "saloon": "string",
    "headroom": "string",
    "separate_dining_area": "string",
    "engine_room": "string",
    "spaces_inside": "string",
    "upholstery_color": "string",
    "matrasses": "string",
    "cushions": "string",
    "curtains": "string",
    "berths_fixed": "string",
    "berths_extra": "string",
    "berths_crew": "string",
    "water_tank_material": "string",
    "water_tank_gauge": "string",
    "water_maker": "string",
    "waste_water_tank": "string",
    "waste_water_tank_material": "string",
    "waste_water_tank_gauge": "string",
    "waste_water_tank_drainpump": "string",
    "deck_suction": "string",
    "hot_water": "string",
    "sea_water_pump": "string",
    "deck_wash_pump": "string",
    "deck_shower": "string",
    "cooker": "string",
    "cooking_fuel": "string",
    "hot_air": "string",
    "stove": "string",
    "central_heating": "string",
    "satellite_reception": "string",
    "engine_serial_number": "string",
    "engine_year": "string",
    "reversing_clutch": "string",
    "transmission": "string",
    "propulsion": "string",
    "fuel_tanks_amount": "string",
    "fuel_tank_total_capacity": "string",
    "fuel_tank_material": "string",
    "range_km": "string",
    "fuel_tank_gauge": "string",
    "tachometer": "string",
    "oil_pressure_gauge": "string",
    "temperature_gauge": "string",
    "dynamo": "string",
    "accumonitor": "string",
    "voltmeter": "string",
    "shorepower": "string",
    "shore_power_cable": "string",
    "wind_generator": "string",
    "solar_panel": "string",
    "consumption_monitor": "string",
    "control_panel": "string",
    "log_speed": "string",
    "windvane_steering": "string",
    "charts_guides": "string",
    "rudder_position_indicator": "string",
    "fishfinder": "string",
    "turn_indicator": "string",
    "ais": "string",
    "ssb_receiver": "string",
    "shortwave_radio": "string",
    "short_band_transmitter": "string",
    "weatherfax_navtex": "string",
    "satellite_communication": "string",
    "sailplan_type": "string",
    "number_of_masts": "string",
    "spars_material": "string",
    "bowsprit": "string",
    "standing_rig": "string",
    "sail_surface_area": "string",
    "stabilizer_sail": "string",
    "sail_amount": "string",
    "sail_material": "string",
    "sail_manufacturer": "string",
    "furling_mainsail": "string",
    "mizzen": "string",
    "furling_mizzen": "string",
    "jib": "string",
    "roller_furling_foresail": "string",
    "genoa_reefing_system": "string",
    "flying_jib": "string",
    "halfwinder_bollejan": "string",
    "gennaker": "string",
    "electric_winches": "string",
    "manual_winches": "string",
    "hydraulic_winches": "string",
    "self_tailing_winches": "string",
    "anchor_connection": "string",
    "anchor_winch": "string",
    "stern_anchor": "string",
    "spud_pole": "string",
    "cockpit_tent": "string",
    "outdoor_cushions": "string",
    "covers": "string",
    "sea_rails": "string",
    "pushpit_pullpit": "string",
    "swimming_platform": "string",
    "swimming_ladder": "string",
    "sail_lowering_system": "string",
    "crutch": "string",
    "dinghy": "string",
    "dinghy_brand": "string",
    "outboard_engine": "string",
    "trailer": "string",
    "crane": "string",
    "davits": "string",
    "teak_deck": "string",
    "cockpit_table": "string",
    "oars_paddles": "string",
    "life_buoy": "string",
    "bilge_pump_manual": "string",
    "bilge_pump_electric": "string",
    "radar_reflector": "string",
    "flares": "string",
    "life_jackets": "string",
    "watertight_door": "string",
    "gas_bottle_locker": "string",
    "self_draining_cockpit": "string"
  }
]
```

#### Get Yachts
- Method: `GET`
- Path: `/api/yachts/{id}`
- Middleware: `cors.public`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```
- Error Response:
```json
{
  "message": "Vessel not found"
}
```

#### Create Yachts
- Method: `POST`
- Path: `/api/yachts`
- Middleware: `cors.private, auth:sanctum, permission:manage yachts`
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Yachts - Yachts
- Method: `POST`
- Path: `/api/yachts/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "vessel_id": "string",
  "name": "string",
  "status": "string",
  "price": 1.0,
  "current_bid": 1.0,
  "year": 1,
  "length": "string",
  "main_image": "string",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "make": "string",
  "model": "string",
  "beam": "string",
  "draft": "string",
  "engine_type": "string",
  "fuel_type": "string",
  "fuel_capacity": "string",
  "water_capacity": "string",
  "cabins": 1,
  "heads": 1,
  "description": "string",
  "location": "string",
  "brand_model": "string",
  "vat_status": "string",
  "reference_code": "string",
  "construction_material": "string",
  "dimensions": "string",
  "berths": 1,
  "hull_shape": "string",
  "hull_color": "string",
  "deck_color": "string",
  "clearance": "string",
  "displacement": "string",
  "steering": "string",
  "engine_brand": "string",
  "engine_model": "string",
  "engine_power": "string",
  "engine_hours": "string",
  "max_speed": "string",
  "fuel_consumption": "string",
  "voltage": "string",
  "interior_type": "string",
  "water_tank": "string",
  "water_system": "string",
  "navigation_electronics": "string",
  "exterior_equipment": "string",
  "trailer_included": true,
  "safety_equipment": "string",
  "user_id": "string",
  "boat_name": "string",
  "allow_bidding": true,
  "external_url": "string",
  "print_url": "string",
  "owners_comment": "string",
  "reg_details": "string",
  "known_defects": "string",
  "last_serviced": "2024-01-01T00:00:00Z",
  "loa": "string",
  "lwl": "string",
  "air_draft": "string",
  "passenger_capacity": 1,
  "designer": "string",
  "builder": "string",
  "where": "string",
  "hull_colour": "string",
  "hull_construction": "string",
  "hull_number": "string",
  "hull_type": "string",
  "super_structure_colour": "string",
  "super_structure_construction": "string",
  "deck_colour": "string",
  "deck_construction": "string",
  "cockpit_type": "string",
  "control_type": "string",
  "flybridge": true,
  "ballast": "string",
  "toilet": 1,
  "shower": 1,
  "bath": 1,
  "oven": true,
  "microwave": true,
  "fridge": true,
  "freezer": true,
  "heating": "string",
  "air_conditioning": true,
  "stern_thruster": true,
  "bow_thruster": true,
  "fuel": "string",
  "hours": "string",
  "cruising_speed": "string",
  "horse_power": "string",
  "engine_manufacturer": "string",
  "engine_quantity": "string",
  "tankage": "string",
  "gallons_per_hour": "string",
  "litres_per_hour": "string",
  "engine_location": "string",
  "gearbox": "string",
  "cylinders": "string",
  "propeller_type": "string",
  "starting_type": "string",
  "drive_type": "string",
  "cooling_system": "string",
  "navigation_lights": true,
  "compass": true,
  "depth_instrument": true,
  "wind_instrument": true,
  "autopilot": true,
  "gps": true,
  "vhf": true,
  "plotter": true,
  "speed_instrument": true,
  "radar": true,
  "life_raft": true,
  "epirb": true,
  "bilge_pump": true,
  "fire_extinguisher": true,
  "mob_system": true,
  "genoa": "string",
  "spinnaker": true,
  "tri_sail": "string",
  "storm_jib": "string",
  "main_sail": "string",
  "winches": "string",
  "battery": true,
  "battery_charger": true,
  "generator": true,
  "inverter": true,
  "television": true,
  "cd_player": true,
  "dvd_player": true,
  "anchor": true,
  "spray_hood": true,
  "bimini": true,
  "fenders": "string",
  "min_bid_amount": 1.0,
  "display_specs": {},
  "boat_type_id": "string",
  "advertising_channels": "string",
  "boat_type": "string",
  "boat_category": "string",
  "new_or_used": "string",
  "manufacturer": "string",
  "vessel_lying": "string",
  "location_city": "string",
  "location_lat": 1.0,
  "location_lng": 1.0,
  "short_description_nl": "string",
  "short_description_en": "string",
  "motorization_summary": "string",
  "advertise_as": "string",
  "ce_category": "string",
  "ce_max_weight": "string",
  "ce_max_motor": "string",
  "cvo": "string",
  "cbb": "string",
  "windows": "string",
  "open_cockpit": "string",
  "aft_cockpit": "string",
  "minimum_height": "string",
  "variable_depth": "string",
  "max_draft": "string",
  "min_draft": "string",
  "ballast_tank": "string",
  "steering_system": "string",
  "steering_system_location": "string",
  "remote_control": "string",
  "rudder": "string",
  "drift_restriction": "string",
  "drift_restriction_controls": "string",
  "trimflaps": "string",
  "stabilizer": "string",
  "saloon": "string",
  "headroom": "string",
  "separate_dining_area": "string",
  "engine_room": "string",
  "spaces_inside": "string",
  "upholstery_color": "string",
  "matrasses": "string",
  "cushions": "string",
  "curtains": "string",
  "berths_fixed": "string",
  "berths_extra": "string",
  "berths_crew": "string",
  "water_tank_material": "string",
  "water_tank_gauge": "string",
  "water_maker": "string",
  "waste_water_tank": "string",
  "waste_water_tank_material": "string",
  "waste_water_tank_gauge": "string",
  "waste_water_tank_drainpump": "string",
  "deck_suction": "string",
  "hot_water": "string",
  "sea_water_pump": "string",
  "deck_wash_pump": "string",
  "deck_shower": "string",
  "cooker": "string",
  "cooking_fuel": "string",
  "hot_air": "string",
  "stove": "string",
  "central_heating": "string",
  "satellite_reception": "string",
  "engine_serial_number": "string",
  "engine_year": "string",
  "reversing_clutch": "string",
  "transmission": "string",
  "propulsion": "string",
  "fuel_tanks_amount": "string",
  "fuel_tank_total_capacity": "string",
  "fuel_tank_material": "string",
  "range_km": "string",
  "fuel_tank_gauge": "string",
  "tachometer": "string",
  "oil_pressure_gauge": "string",
  "temperature_gauge": "string",
  "dynamo": "string",
  "accumonitor": "string",
  "voltmeter": "string",
  "shorepower": "string",
  "shore_power_cable": "string",
  "wind_generator": "string",
  "solar_panel": "string",
  "consumption_monitor": "string",
  "control_panel": "string",
  "log_speed": "string",
  "windvane_steering": "string",
  "charts_guides": "string",
  "rudder_position_indicator": "string",
  "fishfinder": "string",
  "turn_indicator": "string",
  "ais": "string",
  "ssb_receiver": "string",
  "shortwave_radio": "string",
  "short_band_transmitter": "string",
  "weatherfax_navtex": "string",
  "satellite_communication": "string",
  "sailplan_type": "string",
  "number_of_masts": "string",
  "spars_material": "string",
  "bowsprit": "string",
  "standing_rig": "string",
  "sail_surface_area": "string",
  "stabilizer_sail": "string",
  "sail_amount": "string",
  "sail_material": "string",
  "sail_manufacturer": "string",
  "furling_mainsail": "string",
  "mizzen": "string",
  "furling_mizzen": "string",
  "jib": "string",
  "roller_furling_foresail": "string",
  "genoa_reefing_system": "string",
  "flying_jib": "string",
  "halfwinder_bollejan": "string",
  "gennaker": "string",
  "electric_winches": "string",
  "manual_winches": "string",
  "hydraulic_winches": "string",
  "self_tailing_winches": "string",
  "anchor_connection": "string",
  "anchor_winch": "string",
  "stern_anchor": "string",
  "spud_pole": "string",
  "cockpit_tent": "string",
  "outdoor_cushions": "string",
  "covers": "string",
  "sea_rails": "string",
  "pushpit_pullpit": "string",
  "swimming_platform": "string",
  "swimming_ladder": "string",
  "sail_lowering_system": "string",
  "crutch": "string",
  "dinghy": "string",
  "dinghy_brand": "string",
  "outboard_engine": "string",
  "trailer": "string",
  "crane": "string",
  "davits": "string",
  "teak_deck": "string",
  "cockpit_table": "string",
  "oars_paddles": "string",
  "life_buoy": "string",
  "bilge_pump_manual": "string",
  "bilge_pump_electric": "string",
  "radar_reflector": "string",
  "flares": "string",
  "life_jackets": "string",
  "watertight_door": "string",
  "gas_bottle_locker": "string",
  "self_draining_cockpit": "string"
}
```

#### Yachts - Gallery
- Method: `POST`
- Path: `/api/yachts/{id}/gallery`
- Middleware: `cors.private, auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Request Body (JSON):
```json
{
  "images.*": "sample",
  "category": "sample"
}
```
- Success Response:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1
    }
  ]
}
```
- Error Response:
```json
{
  "message": "No images detected"
}
```

#### Delete Yachts
- Method: `DELETE`
- Path: `/api/yachts/{id}`
- Middleware: `cors.private, auth:sanctum, permission:manage yachts`
- Path Params:
  - `id` (string)
- Success Response:
```json
{
  "message": "Vessel removed from fleet."
}
```

#### Yachts - Ai Classify
- Method: `POST`
- Path: `/api/yachts/ai-classify`
- Middleware: `cors.private, auth:sanctum, permission:manage yachts`
- Request Body (JSON):
```json
{
  "images.*": "sample"
}
```
- Success Response:
```json
[
  {
    "id": 1
  }
]
```

### Harbor Widget Monitoring
#### Harbor Widget Event (Public)
- Method: `POST`
- Path: `/api/harbor/widget/events`
- Request Body (JSON):
```json
{
  "partner_token": "partner_token_32chars",
  "event_type": "harbor_button_click",
  "ref_code": "harbor_17",
  "ga_client_id": "1234567890.1700000000",
  "placement": "footer",
  "url": "https://harbor-roermond.nl",
  "referrer": "https://google.com",
  "device_type": "mobile",
  "viewport_width": 390,
  "viewport_height": 844,
  "scroll_depth": 68,
  "time_on_page_before_click": 12,
  "widget_version": "1.0.0",
  "metadata": {
    "page_language": "nl"
  }
}
```
- Success Response:
```json
{
  "message": "Event stored",
  "event_id": 1201
}
```
- Error Response (Invalid token):
```json
{
  "message": "Invalid partner token."
}
```
- Error Response (Domain mismatch):
```json
{
  "message": "Domain mismatch for partner."
}
```

#### Harbor Widget Overview (Admin)
- Method: `GET`
- Path: `/api/admin/harbor-widget/overview`
- Auth: `auth:sanctum` + `admin.errors`
- Success Response:
```json
{
  "benchmark_ctr": 10,
  "harbors": [
    {
      "harbor": {
        "id": 17,
        "name": "Harbor Roermond",
        "email": "partner@harbor-roermond.nl",
        "status": "Active"
      },
      "settings": {
        "id": 4,
        "harbor_id": 17,
        "domain": "harbor-roermond.nl",
        "widget_version": "1.0.0",
        "placement_default": "footer",
        "widget_selector": "#harbor-widget",
        "active": true,
        "created_at": "2026-02-25T10:00:00Z",
        "updated_at": "2026-02-25T10:00:00Z"
      },
      "latest_metric": {
        "id": 11,
        "harbor_id": 17,
        "week_start": "2026-02-23",
        "impressions": 2400,
        "visible_rate": 62.5,
        "clicks": 130,
        "ctr": 5.42,
        "mobile_ctr": 2.1,
        "desktop_ctr": 7.8,
        "avg_scroll_before_click": 68,
        "avg_time_before_click": 12,
        "error_count": 1,
        "reliability_score": 98,
        "conversion_score": 57,
        "computed_at": "2026-02-26T02:30:00Z",
        "created_at": "2026-02-26T02:30:00Z",
        "updated_at": "2026-02-26T02:30:00Z"
      },
      "latest_advice": {
        "id": 6,
        "harbor_id": 17,
        "week_start": "2026-02-23",
        "issues": [
          "Button visibility is low (62.5%).",
          "Mobile CTR is significantly lower than desktop."
        ],
        "suggestions": [
          "Move the button higher on the page or ensure it is above the fold.",
          "Increase button size on mobile (min 44px) and reduce nearby distractions."
        ],
        "priority": "high",
        "user_message": "Your harbor button performance summary: CTR 5.42%, visibility 62.5%. Review the suggestions to improve clicks.",
        "created_at": "2026-02-26T03:00:00Z",
        "updated_at": "2026-02-26T03:00:00Z"
      },
      "latest_snapshot": {
        "id": 55,
        "harbor_id": 17,
        "domain": "harbor-roermond.nl",
        "desktop_screenshot_path": "harbor-snapshots/17/2026-02-26/desktop.png",
        "mobile_screenshot_path": "harbor-snapshots/17/2026-02-26/mobile.png",
        "widget_found": true,
        "widget_visible": true,
        "widget_clickable": true,
        "console_errors": [],
        "load_time_ms": 2134,
        "checked_at": "2026-02-26T02:12:00Z",
        "created_at": "2026-02-26T02:12:00Z",
        "updated_at": "2026-02-26T02:12:00Z",
        "desktop_screenshot_url": "/storage/harbor-snapshots/17/2026-02-26/desktop.png",
        "mobile_screenshot_url": "/storage/harbor-snapshots/17/2026-02-26/mobile.png"
      }
    }
  ]
}
```

#### Harbor Widget Weekly Metrics (Admin)
- Method: `GET`
- Path: `/api/admin/harbors/{harbor}/widget/weekly`
- Query Params: `limit` (default 12)
- Success Response:
```json
{
  "harbor": {
    "id": 17,
    "name": "Harbor Roermond",
    "email": "partner@harbor-roermond.nl"
  },
  "settings": {
    "id": 4,
    "harbor_id": 17,
    "domain": "harbor-roermond.nl",
    "widget_version": "1.0.0",
    "placement_default": "footer",
    "widget_selector": "#harbor-widget",
    "active": true,
    "created_at": "2026-02-25T10:00:00Z",
    "updated_at": "2026-02-25T10:00:00Z"
  },
  "weeks": [
    {
      "id": 11,
      "harbor_id": 17,
      "week_start": "2026-02-23",
      "impressions": 2400,
      "visible_rate": 62.5,
      "clicks": 130,
      "ctr": 5.42,
      "mobile_ctr": 2.1,
      "desktop_ctr": 7.8,
      "avg_scroll_before_click": 68,
      "avg_time_before_click": 12,
      "error_count": 1,
      "reliability_score": 98,
      "conversion_score": 57,
      "computed_at": "2026-02-26T02:30:00Z",
      "created_at": "2026-02-26T02:30:00Z",
      "updated_at": "2026-02-26T02:30:00Z",
      "advice": {
        "id": 6,
        "harbor_id": 17,
        "week_start": "2026-02-23",
        "issues": [
          "Button visibility is low (62.5%)."
        ],
        "suggestions": [
          "Move the button higher on the page or ensure it is above the fold."
        ],
        "priority": "high",
        "user_message": "Your harbor button performance summary: CTR 5.42%, visibility 62.5%. Review the suggestions to improve clicks.",
        "created_at": "2026-02-26T03:00:00Z",
        "updated_at": "2026-02-26T03:00:00Z"
      }
    }
  ]
}
```

#### Harbor Widget Snapshots (Admin)
- Method: `GET`
- Path: `/api/admin/harbors/{harbor}/widget/snapshots`
- Query Params: `from` (YYYY-MM-DD), `to` (YYYY-MM-DD)
- Success Response:
```json
{
  "harbor": {
    "id": 17,
    "name": "Harbor Roermond",
    "email": "partner@harbor-roermond.nl"
  },
  "snapshots": [
    {
      "id": 55,
      "harbor_id": 17,
      "domain": "harbor-roermond.nl",
      "desktop_screenshot_path": "harbor-snapshots/17/2026-02-26/desktop.png",
      "mobile_screenshot_path": "harbor-snapshots/17/2026-02-26/mobile.png",
      "widget_found": true,
      "widget_visible": true,
      "widget_clickable": true,
      "console_errors": [],
      "load_time_ms": 2134,
      "checked_at": "2026-02-26T02:12:00Z",
      "created_at": "2026-02-26T02:12:00Z",
      "updated_at": "2026-02-26T02:12:00Z",
      "desktop_screenshot_url": "/storage/harbor-snapshots/17/2026-02-26/desktop.png",
      "mobile_screenshot_url": "/storage/harbor-snapshots/17/2026-02-26/mobile.png"
    }
  ]
}
```

#### Harbor Widget Settings (Admin)
- Method: `GET`
- Path: `/api/admin/harbors/{harbor}/widget/settings`
- Success Response:
```json
{
  "harbor": {
    "id": 17,
    "name": "Harbor Roermond",
    "email": "partner@harbor-roermond.nl"
  },
  "settings": {
    "id": 4,
    "harbor_id": 17,
    "domain": "harbor-roermond.nl",
    "widget_version": "1.0.0",
    "placement_default": "footer",
    "widget_selector": "#harbor-widget",
    "active": true,
    "created_at": "2026-02-25T10:00:00Z",
    "updated_at": "2026-02-25T10:00:00Z"
  }
}
```

#### Harbor Widget Settings Upsert (Admin)
- Method: `POST` or `PUT`
- Path: `/api/admin/harbors/{harbor}/widget/settings`
- Request Body (JSON):
```json
{
  "domain": "harbor-roermond.nl",
  "widget_version": "1.0.0",
  "placement_default": "footer",
  "widget_selector": "#harbor-widget",
  "active": true
}
```
- Success Response:
```json
{
  "message": "Settings saved",
  "settings": {
    "id": 4,
    "harbor_id": 17,
    "domain": "harbor-roermond.nl",
    "widget_version": "1.0.0",
    "placement_default": "footer",
    "widget_selector": "#harbor-widget",
    "active": true,
    "created_at": "2026-02-25T10:00:00Z",
    "updated_at": "2026-02-25T10:00:00Z"
  }
}
```

### Harbor Partner Performance (GA4 + Ref Attribution)
Note: Ensure GA4 has a custom event-scoped dimension named `harbor_id` (or update `GA4_DIMENSION_HARBOR_ID`) so reports can be grouped correctly.
#### Harbor Funnel Event (Public)
- Method: `POST`
- Path: `/api/harbor/funnel/events`
- Allowed `event_name`: `boat_form_started`, `boat_submitted`, `auction_started`, `winning_bid_selected`, `deal_completed`
- Request Body (JSON):
```json
{
  "event_name": "boat_form_started",
  "boat_id": 107,
  "language": "nl"
}
```
- Success Response:
```json
{
  "message": "Event tracked"
}
```
- Error Response:
```json
{
  "message": "Invalid event name"
}
```

#### Harbor Performance (Admin)
- Method: `GET`
- Path: `/api/admin/harbors/performance`
- Auth: `auth:sanctum` + `admin.errors`
- Query Params (optional):
  - `from` (YYYY-MM-DD)
  - `to` (YYYY-MM-DD)
  - `range_days` (default 30)
  - `device` (mobile/desktop)
  - `country` (NL/DE/etc)
  - `source`, `medium`, `campaign`
- Success Response:
```json
{
  "range": {
    "from": "2026-01-27",
    "to": "2026-02-26"
  },
  "filters": {
    "device": "mobile"
  },
  "benchmark": {
    "avg_ctr": 5.4
  },
  "harbors": [
    {
      "harbor": {
        "id": 17,
        "name": "Harbor Roermond",
        "email": "partner@harbor-roermond.nl",
        "status": "Active"
      },
      "ga4": {
        "active_users": 982,
        "sessions": 1204,
        "users": 982,
        "button_impressions": 2400,
        "button_clicks": 130,
        "ctr": 5.42,
        "boat_form_started": 54,
        "boat_submitted": 22,
        "deal_completed": 6
      },
      "db": {
        "boats_submitted": 20,
        "deals_completed": 5,
        "commission_total": 750.0
      }
    }
  ]
}
```

#### Harbor Performance (Admin Single Harbor)
- Method: `GET`
- Path: `/api/admin/harbors/{harbor}/performance`
- Auth: `auth:sanctum` + `admin.errors`
- Success Response:
```json
{
  "range": {
    "from": "2026-01-27",
    "to": "2026-02-26"
  },
  "filters": {},
  "benchmark": {
    "avg_ctr": 5.4
  },
  "harbor": {
    "harbor": {
      "id": 17,
      "name": "Harbor Roermond",
      "email": "partner@harbor-roermond.nl",
      "status": "Active"
    },
    "ga4": {
      "active_users": 982,
      "sessions": 1204,
      "users": 982,
      "button_impressions": 2400,
      "button_clicks": 130,
      "ctr": 5.42,
      "boat_form_started": 54,
      "boat_submitted": 22,
      "deal_completed": 6
    },
    "db": {
      "boats_submitted": 20,
      "deals_completed": 5,
      "commission_total": 750.0
    }
  }
}
```

#### Harbor Performance (Partner)
- Method: `GET`
- Path: `/api/harbors/performance`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "range": {
    "from": "2026-01-27",
    "to": "2026-02-26"
  },
  "filters": {},
  "benchmark": {
    "avg_ctr": 5.4
  },
  "harbor": {
    "harbor": {
      "id": 17,
      "name": "Harbor Roermond",
      "email": "partner@harbor-roermond.nl",
      "status": "Active"
    },
    "ga4": {
      "active_users": 982,
      "sessions": 1204,
      "users": 982,
      "button_impressions": 2400,
      "button_clicks": 130,
      "ctr": 5.42,
      "boat_form_started": 54,
      "boat_submitted": 22,
      "deal_completed": 6
    },
    "db": {
      "boats_submitted": 20,
      "deals_completed": 5,
      "commission_total": 750.0
    }
  }
}
```

### FAQ (Multilingual + Semantic Search)
#### FAQ Search (Semantic)
- Method: `POST`
- Path: `/api/faq/search`
- Request Body (JSON):
```json
{
  "query": "Hoe werkt derdengelden?",
  "language": "nl",
  "namespace": "transaction",
  "top_k": 5
}
```
- Success Response:
```json
{
  "query": "Hoe werkt derdengelden?",
  "language": "nl",
  "results": [
    {
      "score": 0.89,
      "translation_id": "b4c1c5d6-5f5f-4c3a-9db9-2e7c3b9b2a11",
      "faq_id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
      "language": "nl",
      "namespace": "transaction",
      "category": "Betaling",
      "subcategory": "Derdengelden",
      "question": "Wat is een derdengeldenrekening?",
      "answer": "Een derdengeldenrekening is een veilige rekening waar betalingen tijdelijk worden vastgehouden."
    }
  ]
}
```
- Error Response:
```json
{
  "message": "The query field is required.",
  "errors": {
    "query": ["The query field is required."]
  }
}
```

#### FAQ Translation Detail
- Method: `GET`
- Path: `/api/faq/translations/{translationId}`
- Success Response:
```json
{
  "translation": {
    "id": "b4c1c5d6-5f5f-4c3a-9db9-2e7c3b9b2a11",
    "faq_id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
    "language": "nl",
    "question": "Wat is een derdengeldenrekening?",
    "answer": "Een derdengeldenrekening is een veilige rekening waar betalingen tijdelijk worden vastgehouden.",
    "long_description": "What it means: ...",
    "long_description_status": "ready",
    "needs_review": false,
    "source_language": "nl",
    "translated_from_translation_id": null,
    "indexed_at": "2026-02-26T10:00:00Z",
    "created_at": "2026-02-26T09:00:00Z",
    "updated_at": "2026-02-26T10:00:00Z"
  },
  "faq": {
    "id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
    "category": "Betaling",
    "subcategory": "Derdengelden",
    "namespace": "transaction",
    "slug": "transaction-betaling-derdengelden-wat-is-een-derdengeldenrekening",
    "is_active": true,
    "created_at": "2026-02-26T09:00:00Z",
    "updated_at": "2026-02-26T09:00:00Z"
  }
}
```

#### FAQ Detail by Slug (with language fallback)
- Method: `GET`
- Path: `/api/faq/by-slug/{slug}`
- Query Params: `language` (nl/en/de/fr)
- Success Response:
```json
{
  "translation": {
    "id": "b4c1c5d6-5f5f-4c3a-9db9-2e7c3b9b2a11",
    "faq_id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
    "language": "en",
    "question": "What is a third-party escrow account?",
    "answer": "A third-party escrow account temporarily holds payments safely.",
    "long_description_status": "ready"
  },
  "faq": {
    "id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
    "namespace": "transaction",
    "category": "Payments",
    "subcategory": "Escrow",
    "slug": "transaction-payments-escrow-what-is-escrow",
    "is_active": true
  }
}
```

#### Admin - List FAQ Translations
- Method: `GET`
- Path: `/api/admin/faq/translations`
- Query Params: `language`, `namespace`, `category`, `subcategory`, `needs_review`, `long_description_status`
- Auth: `auth:sanctum` + `admin.errors`
- Success Response:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": "b4c1c5d6-5f5f-4c3a-9db9-2e7c3b9b2a11",
      "faq_id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
      "language": "nl",
      "question": "Wat is een derdengeldenrekening?",
      "answer": "Een derdengeldenrekening is een veilige rekening waar betalingen tijdelijk worden vastgehouden.",
      "long_description_status": "pending",
      "needs_review": false,
      "indexed_at": null,
      "faq": {
        "id": "9a8b7c6d-1111-4444-9999-aaaaaaaaaaaa",
        "namespace": "transaction",
        "category": "Betaling",
        "subcategory": "Derdengelden",
        "slug": "transaction-betaling-derdengelden-wat-is-een-derdengeldenrekening",
        "is_active": true
      }
    }
  ],
  "first_page_url": "http://localhost:8000/api/admin/faq/translations?page=1",
  "from": 1,
  "last_page": 1,
  "per_page": 25,
  "to": 1,
  "total": 1
}
```

#### Admin - Import FAQ Spreadsheet
- Method: `POST`
- Path: `/api/admin/faq/import`
- Auth: `auth:sanctum` + `admin.errors`
- Request Body: `multipart/form-data`
  - `file`: Excel/CSV file
  - `language`: optional default language (e.g. `nl`)
  - `generate_long_descriptions`: optional boolean
  - `index_after_import`: optional boolean (indexes newly imported translations)
- Success Response:
```json
{
  "message": "FAQ import completed",
  "result": {
    "imported": 120,
    "updated": 0,
    "skipped": 2,
    "indexed": 120
  }
}
```

#### Admin - Reindex FAQ in Pinecone
- Method: `POST`
- Path: `/api/admin/faq/index`
- Auth: `auth:sanctum` + `admin.errors`
- Request Body (JSON):
```json
{
  "language": "nl",
  "force": false,
  "limit": 200
}
```
- Success Response:
```json
{
  "message": "FAQ Pinecone indexing complete",
  "indexed": 120
}
```

#### Admin - Generate Long Descriptions
- Method: `POST`
- Path: `/api/admin/faq/long-descriptions`
- Auth: `auth:sanctum` + `admin.errors`
- Request Body (JSON):
```json
{
  "language": "nl",
  "limit": 50
}
```
- Success Response:
```json
{
  "message": "Long description jobs queued",
  "queued": 50
}
```

#### Admin - Generate Translations
- Method: `POST`
- Path: `/api/admin/faq/translate`
- Auth: `auth:sanctum` + `admin.errors`
- Request Body (JSON):
```json
{
  "target_language": "en",
  "source_language": "nl",
  "force": false
}
```
- Success Response:
```json
{
  "message": "Translation jobs queued",
  "queued": 120
}
```

#### Admin - Approve Translation
- Method: `POST`
- Path: `/api/admin/faq/translations/{translationId}/approve`
- Auth: `auth:sanctum` + `admin.errors`
- Success Response:
```json
{
  "message": "Translation approved",
  "translation": {
    "id": "b4c1c5d6-5f5f-4c3a-9db9-2e7c3b9b2a11",
    "needs_review": false
  }
}
```

### Chat (Unified Inbox)
#### Widget Init
- Method: `POST`
- Path: `/api/chat/widget/init`
- Request Body (JSON):
```json
{
  "visitor_id": "optional-uuid",
  "harbor_id": 17
}
```
- Success Response:
```json
{
  "visitor_id": "a6cf9f6f-6a4d-4c0b-9f78-1c3c9f9a0001",
  "session_id": "8d7c1a20-3b61-4b6c-86b7-2f3d1b57e312",
  "session_jwt": "<encrypted-token>"
}
```

#### Create Conversation (Widget/Web)
- Method: `POST`
- Path: `/api/chat/conversations`
- Request Body (JSON):
```json
{
  "visitor_id": "a6cf9f6f-6a4d-4c0b-9f78-1c3c9f9a0001",
  "page_url": "https://nauticsecure.com/yachts/140",
  "channel_origin": "web_widget",
  "contact": {
    "name": "Jan de Vries",
    "email": "jan@example.com",
    "phone": "+31612345678"
  },
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "boat-search"
}
```
- Success Response:
```json
{
  "id": "2e83a4b2-1a8d-4c6f-9c5c-2b16c4b20f55",
  "harbor_id": 17,
  "boat_id": 140,
  "status": "open",
  "priority": "normal",
  "channel_origin": "web_widget",
  "ai_mode": "auto",
  "first_response_due_at": "2026-02-26T11:30:00Z",
  "created_at": "2026-02-26T11:00:00Z",
  "updated_at": "2026-02-26T11:00:00Z"
}
```

#### List Conversations (Staff)
- Method: `GET`
- Path: `/api/chat/conversations?status=open&limit=20`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "data": [
    {
      "id": "2e83a4b2-1a8d-4c6f-9c5c-2b16c4b20f55",
      "harbor_id": 17,
      "status": "open",
      "priority": "normal",
      "last_message_at": "2026-02-26T11:05:00Z"
    }
  ],
  "next_cursor": "2026-02-26T11:05:00Z"
}
```

#### Conversation Detail (Staff)
- Method: `GET`
- Path: `/api/chat/conversations/{id}`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "id": "2e83a4b2-1a8d-4c6f-9c5c-2b16c4b20f55",
  "harbor_id": 17,
  "status": "open",
  "messages": [
    {
      "id": "4d8f...",
      "sender_type": "visitor",
      "text": "Is this yacht still available?",
      "created_at": "2026-02-26T11:05:00Z"
    }
  ]
}
```

#### Send Message
- Method: `POST`
- Path: `/api/chat/conversations/{id}/messages`
- Guests should include `visitor_id` or `session_jwt` from `/chat/widget/init`.
- Request Body (JSON):
```json
{
  "text": "Thanks! Yes, it is available.",
  "channel": "web",
  "sender_type": "admin"
}
```
- Success Response:
```json
{
  "id": "b3d2...",
  "conversation_id": "2e83a4b2-1a8d-4c6f-9c5c-2b16c4b20f55",
  "sender_type": "admin",
  "text": "Thanks! Yes, it is available.",
  "created_at": "2026-02-26T11:06:00Z"
}
```

#### Update Conversation
- Method: `PATCH`
- Path: `/api/chat/conversations/{id}`
- Auth: `auth:sanctum`
- Request Body (JSON):
```json
{
  "status": "pending",
  "priority": "high",
  "assign_to": 5,
  "ai_mode": "assist"
}
```

#### Thumbs-up Training
- Method: `POST`
- Path: `/api/chat/messages/{id}/thumbs-up`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "message": "Saved for training",
  "chat_faq": {
    "id": "c1a2...",
    "thumbs_up_count": 1
  }
}
```

#### Adapter Inbound (WhatsApp)
- Method: `POST`
- Path: `/api/chat/adapters/whatsapp/inbound`
- Header: `X-Chat-Adapter-Secret`
- Request Body (JSON):
```json
{
  "external_thread_id": "wa-thread-123",
  "external_user_id": "wa-user-999",
  "external_message_id": "msg-abc",
  "text": "Hi, I want to book a viewing",
  "language": "en",
  "contact": {
    "name": "Alex",
    "phone": "+31600000000",
    "whatsapp_user_id": "wa-user-999"
  }
}
```

#### Adapter Inbound (Email)
- Method: `POST`
- Path: `/api/chat/adapters/email/inbound`
- Header: `X-Chat-Adapter-Secret`
- Request Body (JSON):
```json
{
  "external_thread_id": "email-thread-456",
  "external_user_id": "alex@example.com",
  "external_message_id": "msg-xyz",
  "text": "Is the boat still available?",
  "language": "en",
  "contact": {
    "name": "Alex",
    "email": "alex@example.com"
  }
}
```

## Onboarding (Users + Partners)
### Register User
- Method: `POST`
- Path: `/api/register`
- Request Body (JSON):
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "accept_terms": true
}
```
- Success Response:
```json
{
  "id": 123,
  "email": "jane@example.com",
  "role": "user",
  "status": "email_pending",
  "verification_required": true
}
```

### Register Partner (Google Places)
- Method: `POST`
- Path: `/api/partner/register`
- Request Body (JSON):
```json
{
  "name": "Alex Broker",
  "email": "alex@broker.com",
  "phone": "+31600000000",
  "password": "secret123",
  "password_confirmation": "secret123",
  "place_id": "ChIJN1t_tDeuEmsRUsoyG83frY4",
  "accept_terms": true
}
```
- Success Response:
```json
{
  "id": 456,
  "email": "alex@broker.com",
  "role": "partner",
  "status": "email_pending",
  "verification_required": true
}
```

### Verify Email (Token Info)
- Method: `GET`
- Path: `/api/verify-email/{token}`
- Success Response:
```json
{
  "email": "alex@broker.com",
  "role": "partner",
  "status": "email_pending",
  "expires_at": "2026-03-02T12:00:00Z",
  "locked_until": null
}
```

### Verify Email (Confirm Code)
- Method: `POST`
- Path: `/api/verify-email/{token}`
- Request Body (JSON):
```json
{
  "code": "123456"
}
```
- Success Response:
```json
{
  "message": "Email verified successfully",
  "status": "pending_agreement",
  "next_step": "/partner/agreement"
}
```
- Error Response:
```json
{
  "message": "Invalid code, please try again"
}
```

### Resend Verification Code
- Method: `POST`
- Path: `/api/verify-email/{token}/resend`
- Success Response:
```json
{
  "message": "A confirmation code has been sent to your email. Please check your inbox.",
  "verification_token": "new-token",
  "verification_url": "https://app.example.com/verify-email/new-token"
}
```

### Change Email During Verification
- Method: `POST`
- Path: `/api/verify-email/{token}/change-email`
- Request Body (JSON):
```json
{
  "email": "new-email@example.com"
}
```
- Success Response:
```json
{
  "message": "Email updated. Please check your inbox.",
  "verification_token": "new-token",
  "verification_url": "https://app.example.com/verify-email/new-token"
}
```

### Onboarding Status (Auth)
- Method: `GET`
- Path: `/api/onboarding/status`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "status": "pending_agreement",
  "next_step": "/partner/agreement"
}
```

### Partner Agreement (Auth)
- Method: `GET`
- Path: `/api/partner/agreement`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "agreement_version": "v1",
  "agreement_text": "Partner Agreement...",
  "status": "pending_agreement"
}
```

### Accept Partner Agreement (Auth)
- Method: `POST`
- Path: `/api/partner/agreement`
- Auth: `auth:sanctum`
- Request Body (JSON):
```json
{
  "accepted": true
}
```
- Success Response:
```json
{
  "message": "Agreement accepted",
  "status": "contract_pending",
  "next_step": "/partner/contract-signing"
}
```

### Partner Contract (Auth)
- Method: `POST`
- Path: `/api/partner/contract`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "message": "Contract signing started",
  "transaction_id": "signhost-transaction-id",
  "signing_url": "https://signhost.com/sign/...",
  "contract_pdf_path": "contracts/partner_456.pdf",
  "contract_sha256": "sha256-hash"
}
```

### Partner Contract Status (Auth)
- Method: `GET`
- Path: `/api/partner/contract`
- Auth: `auth:sanctum`
- Success Response:
```json
{
  "id": 1,
  "user_id": 456,
  "signhost_transaction_id": "signhost-transaction-id",
  "status": "signing",
  "contract_pdf_path": "contracts/partner_456.pdf",
  "contract_sha256": "sha256-hash",
  "signed_document_url": null,
  "audit_trail_url": null,
  "signed_at": null,
  "created_at": "2026-03-02T12:00:00Z",
  "updated_at": "2026-03-02T12:00:00Z"
}
```

### Admin Copilot Action Catalog
- Method: `GET`
- Path: `/api/admin/copilot/action-catalog`
- Middleware: `auth:sanctum`
- Success Response:
```json
{
  "generated_at": "2026-03-03T10:00:00Z",
  "count": 2,
  "actions": [
    {
      "action_id": "deal.contract.generate",
      "title": "Generate Contract",
      "short_description": "Generate a deal contract PDF",
      "description": "Creates and stores a contract PDF for a deal",
      "module": "deals",
      "required_role": "admin",
      "permission_key": "manage deals",
      "security_level": "high",
      "input_schema": {
        "type": "object",
        "required": ["deal_id"],
        "properties": {
          "deal_id": {"type": "integer"}
        }
      },
      "example_inputs": [{"deal_id": 123}],
      "example_prompts": ["Generate the contract for deal 123"],
      "side_effects": ["writes_db", "creates_pdf"],
      "idempotency_rules": ["Idempotency-Key required"],
      "rate_limit_class": "high",
      "fresh_auth_required_minutes": 30,
      "confirmation_required": true,
      "route_template": "/admin/deals/{deal_id}",
      "query_template": null,
      "required_params": ["deal_id"],
      "tags": ["deal", "contract"],
      "phrases": ["generate contract", "create contract"]
    }
  ]
}
```

### Admin Copilot Draft
- Method: `POST`
- Path: `/api/admin/copilot/draft`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "prompt": "Generate the contract for deal 123",
  "language": "en",
  "top_k": 5,
  "context": {"module": "deals"}
}
```
- Success Response:
```json
{
  "draft_id": "b1c9c5d6-17c5-4d69-9ef1-5c3c8a0c9f2a",
  "prompt": "Generate the contract for deal 123",
  "selected_action": {
    "action_id": "deal.contract.generate",
    "title": "Generate Contract",
    "params": {"deal_id": 123},
    "risk_level": "high",
    "confirmation_required": true,
    "input_schema": {
      "type": "object",
      "required": ["deal_id"],
      "properties": {"deal_id": {"type": "integer"}}
    },
    "example_inputs": [{"deal_id": 123}]
  },
  "candidates": [
    {"action_id": "deal.contract.generate", "title": "Generate Contract", "score": 0.92, "reason": "Matched phrase: generate contract"}
  ],
  "confidence": 0.88,
  "clarifying_question": null
}
```

### Admin Copilot Validate
- Method: `POST`
- Path: `/api/admin/copilot/validate`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "action_id": "deal.contract.generate",
  "payload": {"deal_id": 123}
}
```
- Success Response:
```json
{
  "validation_token": "<encrypted-token>",
  "action_id": "deal.contract.generate",
  "requires_confirmation": true,
  "payload": {"deal_id": 123}
}
```
- Error Response:
```json
{
  "message": "Validation failed",
  "errors": {"deal_id": ["Required"]}
}
```

### Admin Copilot Execute
- Method: `POST`
- Path: `/api/admin/copilot/execute`
- Middleware: `auth:sanctum`
- Request Body (JSON):
```json
{
  "validation_token": "<encrypted-token>",
  "confirm": true
}
```
- Success Response:
```json
{
  "status": "executed",
  "action_id": "deal.contract.generate",
  "payload": {"deal_id": 123},
  "execution": {
    "execution_type": "deeplink",
    "deeplink": "/admin/deals/123"
  }
}
```

### Sentry / Platform Errors (Admin)
These endpoints operate on `platform_errors` records (synced from Sentry). All require admin access.

#### List Errors
- Method: `GET`
- Path: `/api/admin/errors`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Query Params:
  - `status` (unresolved|resolved|ignored)
  - `level` (error|warning|info)
  - `source` (frontend|backend)
  - `environment` (string)
  - `release` (string)
  - `route` (string, partial match)
  - `user_id` (string)
  - `category` (string)
  - `subject` (string)
  - `from` (date)
  - `to` (date)
  - `sort_by` (last_seen_at|first_seen_at|occurrences_count|users_affected|level)
  - `sort_dir` (asc|desc)
  - `per_page` (int, default 25)
- Request Example:
```http
GET /api/admin/errors?status=unresolved&sort_by=last_seen_at&sort_dir=desc&per_page=25
Authorization: Bearer <token>
```
- Success Response:
```json
{
  "data": [
    {
      "id": 12,
      "title": "TypeError",
      "message": "Cannot read properties of undefined",
      "level": "error",
      "status": "unresolved",
      "sentry_issue_id": "1234567890",
      "project": "nautisecure-backend",
      "environment": "production",
      "release": "2026.03.03",
      "occurrences_count": 4,
      "users_affected": 2,
      "last_seen_at": "2026-03-03T09:40:00Z"
    }
  ],
  "links": {"next": null, "prev": null},
  "meta": {"current_page": 1, "per_page": 25, "total": 1}
}
```

#### Error Stats
- Method: `GET`
- Path: `/api/admin/errors/stats`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Success Response:
```json
{
  "errors_last_24h": 5,
  "critical": 1,
  "regressions": 2,
  "users_affected": 12
}
```

#### Get Error Detail
- Method: `GET`
- Path: `/api/admin/errors/{error}`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Query Params:
  - `include_reports` (boolean)
- Request Example:
```http
GET /api/admin/errors/12?include_reports=true
Authorization: Bearer <token>
```
- Success Response:
```json
{
  "error": {
    "id": 12,
    "title": "TypeError",
    "status": "unresolved",
    "last_event_sample_json": {"exception": {}}
  },
  "reports": []
}
```

#### Resolve Error
- Method: `POST`
- Path: `/api/admin/errors/{error}/resolve`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Success Response:
```json
{"status": "resolved"}
```

#### Ignore Error
- Method: `POST`
- Path: `/api/admin/errors/{error}/ignore`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Request Body (JSON):
```json
{
  "days": 7,
  "until_release": "2026.03.10"
}
```
- Success Response:
```json
{"status": "ignored"}
```

#### Add Internal Note
- Method: `POST`
- Path: `/api/admin/errors/{error}/note`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Request Body (JSON):
```json
{"note": "Investigating root cause"}
```
- Success Response:
```json
{"status": "noted"}
```

#### Assign Error
- Method: `POST`
- Path: `/api/admin/errors/{error}/assign`
- Middleware: `auth:sanctum`, `onboarding.active`, `admin.errors`
- Request Body (JSON):
```json
{"user_id": 7}
```
- Success Response:
```json
{"status": "assigned"}
```

### Sentry Webhook
- Method: `POST`
- Path: `/api/sentry/webhook`
- Auth: none (signature optional)
- Headers (optional):
  - `X-Sentry-Signature: <hmac-sha256>`
- Success Response:
```json
{"status": "ok"}
```
- Error Response:
```json
{"message": "Invalid signature"}
```

## Multilingual & Translations (Backend)
### Locales
#### List Supported Locales
- Method: `GET`
- Path: `/api/locales`
- Success Response:
```json
{
  "default": "nl",
  "supported": ["nl", "en", "de"],
  "fallbacks": {
    "de": ["en", "nl"],
    "en": ["nl"],
    "nl": ["en"]
  }
}
```

### Public Content (Locale-aware)
#### List Blogs (localized)
- Method: `GET`
- Path: `/api/public/blogs`
- Query: `locale=de`
- Success Response:
```json
{
  "requested_locale": "de",
  "data": [
    {
      "id": 12,
      "slug": "harbor-safety-guide",
      "title": "Sicherer Hafenbetrieb",
      "excerpt": "Kurzer Leitfaden...",
      "content": "...",
      "meta_title": "Hafensicherheit",
      "meta_description": "...",
      "author": "NautiSecure",
      "featured_image": null,
      "status": "published",
      "views": 120,
      "user": null,
      "locale": "de",
      "fallback_locale_used": null
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 1
  }
}
```

#### Blog By Slug (localized)
- Method: `GET`
- Path: `/api/public/blogs/slug/{slug}`
- Query: `locale=de`
- Success Response:
```json
{
  "requested_locale": "de",
  "data": {
    "id": 12,
    "slug": "harbor-safety-guide",
    "title": "Sicherer Hafenbetrieb",
    "content": "...",
    "locale": "de",
    "fallback_locale_used": null
  }
}
```

#### Harbor Page (localized)
- Method: `GET`
- Path: `/api/harbor-pages/{harborId}`
- Query: `locale=de`
- Success Response:
```json
{
  "harbor": {"id": 5, "name": "Marina Noord"},
  "page": {
    "locale": "de",
    "page_content": {
      "hero_title": "..."
    },
    "translation_status": "AI_DRAFT"
  },
  "requested_locale": "de",
  "fallback_locale_used": null
}
```

#### FAQ List (localized)
- Method: `GET`
- Path: `/api/faqs`
- Query: `locale=de`
- Success Response:
```json
{
  "faqs": {
    "data": [
      {
        "id": "uuid",
        "faq_id": "uuid",
        "language": "de",
        "question": "Wie funktioniert Escrow?",
        "answer": "..."
      }
    ]
  },
  "categories": ["payments", "security"],
  "total_count": 12,
  "requested_locale": "de",
  "fallback_locale_used": null
}
```

### Admin Translation Pipeline (AI Offline)
#### Queue Blog Translations
- Method: `POST`
- Path: `/api/admin/blog-translations/ai-generate`
- Request Body (JSON):
```json
{
  "blog_ids": [12, 15],
  "target_locale": "de",
  "force": false
}
```
- Success Response:
```json
{
  "message": "Blog translation jobs queued",
  "queued": 2,
  "target_locale": "de"
}
```

#### Update Blog Translation (manual edit)
- Method: `PATCH`
- Path: `/api/admin/blog-translations/{translationId}`
- Request Body (JSON):
```json
{
  "title": "Sicherer Hafenbetrieb",
  "content": "...",
  "status": "REVIEWED"
}
```
- Success Response:
```json
{
  "id": 88,
  "blog_id": 12,
  "locale": "de",
  "title": "Sicherer Hafenbetrieb",
  "status": "REVIEWED"
}
```

#### Approve Blog Translation
- Method: `POST`
- Path: `/api/admin/blog-translations/{translationId}/approve`
- Request Body (JSON):
```json
{ "legal": false }
```
- Success Response:
```json
{
  "message": "Translation approved",
  "translation": {
    "id": 88,
    "status": "REVIEWED"
  }
}
```

#### Queue Interaction Template Translations
- Method: `POST`
- Path: `/api/admin/interaction-template-translations/ai-generate`
- Request Body (JSON):
```json
{
  "template_ids": [3, 5],
  "target_locale": "de",
  "force": false
}
```
- Success Response:
```json
{
  "message": "Interaction template translation jobs queued",
  "queued": 2,
  "target_locale": "de"
}
```

#### Update Interaction Template Translation
- Method: `PATCH`
- Path: `/api/admin/interaction-template-translations/{translationId}`
- Request Body (JSON):
```json
{
  "subject": "Neues Angebot",
  "body": "Hallo {user_name}, ...",
  "status": "REVIEWED"
}
```
- Success Response:
```json
{
  "id": 44,
  "interaction_template_id": 3,
  "locale": "de",
  "status": "REVIEWED"
}
```
