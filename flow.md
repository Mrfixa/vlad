# Vito App Flow Documentation

**Version:** 1.1  
**Last Updated:** 2026-07-03  
**Domain:** dacatlon.store (Backend API)

---

## Table of Contents
1. [System Architecture](#1-system-architecture)
2. [Authentication Flow](#2-authentication-flow)
3. [Customer App Flows](#3-customer-app-flows)
4. [Driver App Flows](#4-driver-app-flows)
5. [Backend API Endpoints](#5-backend-api-endpoints)
6. [Real-time Communication](#6-real-time-communication)
7. [Payment Flows](#7-payment-flows)
8. [Push Notifications](#8-push-notifications)

---

## 1. System Architecture

### 1.1 Components
```
┌─────────────────────────────────────────────────────────────────┐
│                        VITO SYSTEM                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────┐  │
│  │ Customer App │    │  Driver App  │    │   Admin Panel     │  │
│  │   (Flutter) │    │   (Flutter) │    │   (Laravel Web)   │  │
│  └──────┬───────┘    └──────┬───────┘    └────────┬─────────┘  │
│         │                   │                       │             │
│         └───────────────────┼───────────────────────┘             │
│                             │                                     │
│                             ▼                                     │
│                   ┌─────────────────────┐                         │
│                   │   Laravel Backend   │                         │
│                   │   (API + Web)      │                         │
│                   │   dacatlon.store   │                         │
│                   └─────────┬───────────┘                         │
│                             │                                     │
│         ┌───────────────────┼───────────────────┐                │
│         │                   │                   │                │
│         ▼                   ▼                   ▼                │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐         │
│  │   MySQL     │    │    Redis   │    │  Stripe     │         │
│  │  Database   │    │  Cache/Q   │    │  Payments   │         │
│  └─────────────┘    └─────────────┘    └─────────────┘         │
│                                                                  │
│         ┌───────────────────┼───────────────────┐                │
│         │                   │                   │                │
│         ▼                   ▼                   ▼                │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐         │
│  │  Pusher/   │    │   Firebase  │    │   Maps API  │         │
│  │   Reverb   │    │   Cloud     │    │  (Mapbox)   │         │
│  └─────────────┘    └─────────────┘    └─────────────┘         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Technology Stack
| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 + PHP 8.4 |
| Database | MySQL |
| Cache/Queue | Redis |
| Auth | Laravel Passport (OAuth2) |
| Real-time | Pusher / Laravel Reverb |
| Payments | Stripe |
| Maps | Mapbox |
| Push | Firebase Cloud Messaging |

---

## 2. Authentication Flow

### 2.1 Customer Authentication (PIN-based)

```
┌─────────┐                           ┌─────────────┐                    ┌──────────┐
│ Customer│                           │  User App   │                    │  Backend │
└────┬────┘                           └──────┬──────┘                    └────┬─────┘
     │                                      │                                │
     │ 1. Enter username                    │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │ 2. Enter 6-digit PIN                │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 3. POST /api/auth/login       │
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 4. Validate username + PIN     │
     │                                      │                                │
     │                                      │ 5. Generate Passport token     │
     │                                      │    (1 hour expiry)            │
     │                                      │◄──────────────────────────────│
     │                                      │                                │
     │ 6. Token + User Profile             │                                │
     │◄─────────────────────────────────────│                                │
     │                                      │                                │
```

**Endpoint:** `POST /api/auth/login`
```json
Request:
{
  "username": "customer",
  "pin": "123456"
}

Response:
{
  "token": "eyJ...",
  "user": { "id": "...", "username": "...", "user_type": "customer" }
}
```

### 2.2 QR Token Registration Flow

```
┌─────────┐                           ┌─────────────┐                    ┌──────────┐
│ Customer│                           │  User App   │                    │  Backend │
└────┬────┘                           └──────┬──────┘                    └────┬─────┘
     │                                      │                                │
     │ 1. Scan QR Code                     │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 2. POST /api/auth/qr-token/validate
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 3. Validate token              │
     │                                      │    - Check not expired         │
     │                                      │    - Check not revoked        │
     │                                      │    - Mark as redeemed         │
     │                                      │◄──────────────────────────────│
     │                                      │                                │
     │ 4. Token Valid / Invalid            │                                │
     │◄─────────────────────────────────────│                                │
     │                                      │                                │
     │ 5. Enter username, PIN, phone        │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 6. POST /api/auth/register    │
     │                                      │───────────────────────────────►│
     │                                      │                                │
```

### 2.3 Driver Authentication

```
┌─────────┐                           ┌─────────────┐                    ┌──────────┐
│ Driver  │                           │  Driver App │                    │  Backend │
└────┬────┘                           └──────┬──────┘                    └────┬─────┘
     │                                      │                                │
     │ 1. Enter username                    │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │ 2. Enter 6-digit PIN                │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 3. POST /api/auth/login       │
     │                                      │    (scope: AccessToDriver)     │
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 4. Validate driver PIN        │
     │                                      │    (Pre-approved check)       │
     │                                      │                                │
     │                                      │ 5. Generate Passport token    │
     │                                      │    (7 day expiry for drivers) │
     │                                      │◄──────────────────────────────│
```

---

## 3. Customer App Flows

### 3.1 Ride Booking Flow

```
┌──────────────────────────────────────────────────────────────────────────┐
│                        RIDE BOOKING FLOW                                   │
└──────────────────────────────────────────────────────────────────────────┘

[Home Screen] ──► [Select Pickup/Dropoff] ──► [Select Vehicle] ──► [Confirm]
      │                  │                         │                  │
      │                  ▼                         │                  │
      │         [Map with markers]                  │                  │
      │                                             ▼                  │
      │                                    [Vehicle Comparison]         │
      │                                             │                  │
      │                                    ┌────────┼────────┐         │
      │                                    │Economy │Premium│Van  │    │
      │                                    │ $5.00  │$12.00 │$20 │
      │                                    │ 5 min  │ 8 min │15m │    │
      │                                    └────────┴────────┴─────┘    │
      │                                                          │
      ▼                                                          ▼
[Payment Method] ◄─────────────── [Apply Promo Code] ◄──────────┘
      │
      ▼
[Confirm Booking] ──► [Finding Driver...] ──► [Driver Assigned]
                                                   │
                                                   ▼
                                          [Driver on the way]
                                                   │
                              ┌────────────────────┼────────────────────┐
                              │                    │                    │
                              ▼                    ▼                    ▼
                        [Driver Arrives]    [Trip Started]    [Trip Complete]
                              │                    │                    │
                              │                    │                    ▼
                              │                    │            [Rate Driver]
                              │                    │                    │
                              ▼                    ▼                    ▼
                         [Trip Ongoing] ◄──────────┘            [Payment Done]
```

### 3.2 Mart Order Flow

```
┌──────────────────────────────────────────────────────────────────────────┐
│                        MART ORDER FLOW                                    │
└──────────────────────────────────────────────────────────────────────────┘

[Mart Home] ──► [Browse Products] ──► [Add to Cart] ──► [View Cart]
      │                                      │                   │
      │                                      ▼                   │
      │                              [Product Details]          │
      │                                      │                   │
      │                                      │                   ▼
      │                                      │            [Checkout]
      │                                      │                   │
      ▼                                      │                   ▼
[Categories] ◄──────────────────────────────┘           [Apply Promo Code]
      │
      ▼
[Order Summary] ──► [Payment] ──► [Order Confirmed]
                          │
                          ▼
                   [Processing] ──► [Driver Assigned]
                                        │
                                        ▼
                              [Driver Picking Up] ──► [Delivered]
                                                           │
                                                           ▼
                                                     [Rate Order]
```

### 3.3 Parcel Delivery Flow

```
┌──────────────────────────────────────────────────────────────────────────┐
│                      PARCEL DELIVERY FLOW                                 │
└──────────────────────────────────────────────────────────────────────────┘

[Parcel Tab] ──► [Sender Info] ──► [Receiver Info] ──► [Package Details]
      │              │                │                   │
      │              │                │                   ▼
      │              │                │            [Weight/Dimensions]
      │              │                │                   │
      │              │                │                   ▼
      │              │                │            [Delivery Notes]
      │              │                │                   │
      ▼              ▼                │                   ▼
[Pickup/Dropoff] ◄──┴────────────────┘           [Confirm Details]
      │
      ▼
[Select Vehicle] ──► [Estimate Fare] ──► [Confirm Booking]
                              │
                              ▼
                     [Finding Driver...] ──► [Driver Assigned]
                                                   │
                                                   ▼
                                    [Driver Picks Up] ──► [In Transit]
                                                           │
                                                           ▼
                                                     [Delivered]
```

### 3.4 Chat Flow

```
┌─────────┐                           ┌─────────────┐                    ┌──────────┐
│Customer │                           │  User App   │                    │  Backend │
└────┬────┘                           └──────┬──────┘                    └────┬─────┘
     │                                      │                                │
     │ 1. Open chat                        │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │ 2. Type message                     │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 3. POST /api/customer/chat/send
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 4. Store in DB              │
     │                                      │ 5. Broadcast via Pusher      │
     │                                      │◄──────────────────────────────│
     │                                      │                                │
     │ 6. Real-time message received        │                                │
     │◄─────────────────────────────────────│                                │
     │                                      │                                │
     │ 7. Typing indicator (optional)        │                                │
     │◄─────────────────────────────────────│                                │
```

**Pusher Channel:** `private-customer-ride-chat.{tripId}` or `private-customer-mart-chat.{orderId}`

---

## 4. Driver App Flows

### 4.1 Online/Offline Flow

```
┌─────────┐                           ┌─────────────┐                    ┌──────────┐
│ Driver  │                           │ Driver App  │                    │  Backend │
└────┬────┘                           └──────┬──────┘                    └────┬─────┘
     │                                      │                                │
     │ 1. Tap Online/Offline toggle         │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 2. Check location permission  │
     │                                      │                                │
     │                                      │ 3. POST /api/driver/status   │
     │                                      │    {is_online: true}         │
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 4. Update driver status      │
     │                                      │ 5. Broadcast availability    │
     │                                      │◄──────────────────────────────│
     │                                      │                                │
     │ 6. Online status shown              │                                │
     │◄─────────────────────────────────────│                                │
     │                                      │                                │
     │ 7. Receive ride requests             │                                │
     │◄─────────────────────────────────────│                                │
```

### 4.2 Ride Request Flow

```
┌──────────────────────────────────────────────────────────────────────────┐
│                      DRIVER RIDE REQUEST FLOW                              │
└──────────────────────────────────────────────────────────────────────────┘

[Online] ──► [New Request Alert] ──► [View Details] ──► [Accept/Decline]
    │              │                      │                 │
    │              ▼                      ▼                 │
    │      [Request Modal]        [Pickup/Dropoff]         │
    │      [Distance]             [Estimated Fare]         │
    │      [Pickup Location]       [Customer Rating]       │
    │                                                      │
    │  ┌──────────┐                                         │
    │  │  ACCEPT  │ ◄─────────────────────────────────┐    │
    │  └────┬─────┘                                  │    │
    │       │                                          │    │
    │       ▼                                          │    │
    │  [Navigate to Pickup]                           │    │
    │       │                                          │    │
    │       ▼                                          │    │
    │  [Tap "Arrived"] ───────────────────────────────┼────┘
    │       │                                             │
    │       ▼                                             │
    │  [Customer Boarded]                                │
    │       │                                             │
    │       ▼                                             │
    │  [Tap "Start Trip"] ───────────────────────────────┘
    │       │
    │       ▼
    │  [Trip In Progress] ──► [Navigate to Dropoff]
    │       │
    │       ▼
    │  [Tap "Complete Trip"]
    │       │
    │       ▼
    │  [Confirm Fare]
    │       │
    │       ▼
    │  [Payment Received] ──► [Rate Customer]
    │       │
    │       ▼
    │  [Back to Online]
```

### 4.3 Mart Delivery Flow

```
┌──────────────────────────────────────────────────────────────────────────┐
│                      MART DELIVERY FLOW                                   │
└──────────────────────────────────────────────────────────────────────────┘

[Online] ──► [New Mart Order Alert] ──► [View Order] ──► [Accept Order]
    │              │                        │               │
    │              ▼                        ▼               ▼
    │      [Order Modal]              [Items List]    [Store Location]
    │      [Delivery Fee]             [Total]         [Customer Location]
    │      [Distance]                                  │
    │                                                   │
    │  ┌──────────┐                                    │
    │  │  ACCEPT  │ ◄─────────────────────────────────┘
    │  └────┬─────┘
    │       │
    │       ▼
    │  [Navigate to Store]
    │       │
    │       ▼
    │  [Mark "Picked Up"]
    │       │
    │       ▼
    │  [Navigate to Customer]
    │       │
    │       ▼
    │  [Mark "Delivered"]
    │       │
    │       ▼
    │  [Upload Proof Photo]
    │       │
    │       ▼
    │  [Order Complete]
```

---

## 5. Backend API Endpoints

### 5.1 Authentication Endpoints

| Method | Endpoint | Scope | Description |
|--------|----------|-------|-------------|
| POST | `/api/auth/login` | Public | PIN-based login |
| POST | `/api/auth/register` | Public | User registration |
| POST | `/api/auth/qr-token/validate` | Public | Validate QR invite token |
| POST | `/api/auth/forgot-pin` | Public | Request PIN reset |
| POST | `/api/auth/reset-pin` | Public | Reset PIN with token |
| POST | `/api/auth/logout` | Auth | Invalidate token |

### 5.2 Customer Endpoints

#### Rides
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/customer/zones` | Get service zones |
| POST | `/api/customer/ride/estimate-fare` | Get fare estimate |
| POST | `/api/customer/ride/create` | Create ride request |
| GET | `/api/customer/ride/details/{id}` | Get ride details |
| POST | `/api/customer/ride/cancel/{id}` | Cancel ride |

#### Mart
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/customer/mart/categories` | Get categories |
| GET | `/api/customer/mart/products` | Get products |
| GET | `/api/customer/mart/products/{id}` | Get product details |
| POST | `/api/customer/mart/orders` | Create order |
| GET | `/api/customer/mart/orders` | List orders |
| GET | `/api/customer/mart/orders/{id}` | Get order details |
| POST | `/api/customer/mart/orders/{id}/cancel` | Cancel order |
| POST | `/api/customer/mart/orders/{id}/review` | Review order |

#### Parcel
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/customer/parcel/estimate` | Get delivery estimate |
| POST | `/api/customer/parcel/create` | Create parcel delivery |
| GET | `/api/customer/parcel/details/{id}` | Get parcel details |

### 5.3 Driver Endpoints

#### Status
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/driver/status` | Update online/offline |
| GET | `/api/driver/profile` | Get driver profile |

#### Ride Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/driver/ride/accept` | Accept ride request |
| POST | `/api/driver/ride/update-status` | Update ride status |
| POST | `/api/driver/ride/complete` | Complete ride |

#### Parcel Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/driver/parcel/accept` | Accept parcel delivery |
| POST | `/api/driver/parcel/update-status` | Update parcel status |
| POST | `/api/driver/parcel/complete` | Complete delivery |

#### Mart Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/driver/mart/pending-orders` | Get pending orders |
| POST | `/api/driver/mart/accept` | Accept order |
| POST | `/api/driver/mart/update-status` | Update order status |
| POST | `/api/driver/mart/complete` | Complete delivery |

### 5.4 Chat Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/customer/chat/send` | Send customer message |
| POST | `/api/driver/chat/send` | Send driver message |
| GET | `/api/customer/chat/history/{type}/{id}` | Get chat history |
| GET | `/api/driver/chat/history/{type}/{id}` | Get chat history |

---

## 6. Real-time Communication

### 6.1 Pusher Events

#### Customer Events
| Event | Channel | Description |
|-------|---------|-------------|
| `CustomerRideChatEvent` | `private-customer-ride-chat.{tripId}` | Ride chat message |
| `CustomerMartOrderChatEvent` | `private-customer-mart-chat.{orderId}` | Mart order chat |

#### Driver Events
| Event | Channel | Description |
|-------|---------|-------------|
| `DriverRideChatEvent` | `private-driver-ride-chat.{tripId}` | Ride chat message |
| `DriverMartOrderChatEvent` | `private-driver-mart-chat.{orderId}` | Mart order chat |

### 6.2 Status Update Events

| Event | Channel | Description |
|-------|---------|-------------|
| `RideStatusUpdate` | `private-customer-ride.{tripId}` | Ride status changed |
| `MartOrderStatusUpdate` | `private-customer-mart.{orderId}` | Mart order status changed |
| `NewRideRequest` | `private-driver-channel` | New ride request (driver) |

---

## 7. Payment Flows

### 7.1 Stripe Payment Flow

```
┌─────────┐                           ┌─────────────┐                    ┌──────────┐
│ Customer│                           │  User App   │                    │  Backend │
└────┬────┘                           └──────┬──────┘                    └────┬─────┘
     │                                      │                                │
     │ 1. Select payment                    │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 2. Create PaymentIntent       │
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 3. Stripe PaymentIntent       │
     │                                      │◄──────────────────────────────│
     │                                      │                                │
     │ 4. Stripe payment form              │                                │
     │◄─────────────────────────────────────│                                │
     │                                      │                                │
     │ 5. Payment complete                 │                                │
     │──────────────────────────────────────►                                │
     │                                      │                                │
     │                                      │ 6. Confirm payment            │
     │                                      │───────────────────────────────►│
     │                                      │                                │
     │                                      │ 7. Webhook: payment_succeeded │
     │                                      │◄──────────────────────────────│
```

### 7.2 Wallet Flow

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          WALLET FLOW                                       │
└──────────────────────────────────────────────────────────────────────────┘

[Add Money] ──► [Stripe Payment] ──► [Balance Updated]
     │                                    │
     │                                    ▼
     │                            [Webhook Received]
     │                                    │
     ▼                                    ▼
[Check Balance] ◄─────────── [Transaction Recorded]
```

---

## 8. Push Notifications

### 8.1 Notification Types

| Type | Trigger | Recipient |
|------|---------|-----------|
| `ride_requested` | Customer creates ride | Drivers in zone |
| `driver_assigned` | Driver accepts | Customer |
| `driver_arrived` | Driver marks arrived | Customer |
| `ride_completed` | Trip ends | Customer |
| `order_status_update` | Mart order status change | Customer |
| `new_message` | Chat message | Customer/Driver |

### 8.2 Firebase Cloud Messaging

```
Backend                          Firebase                          Device
    │                                  │                               │
    │ 1. Send device notification      │                               │
    │─────────────────────────────────►│                               │
    │                                  │ 2. FCM delivery               │
    │                                  │──────────────────────────────►│
```

---

## 9. Order Status State Machines

### 9.1 Mart Order Status

```
pending ──► accepted ──► picked_up ──► delivered
    │           │
    └───────────┴─────► cancelled
```

### 9.2 Parcel Status

```
pending ──► accepted ──► picked_up ──► in_transit ──► delivered
    │           │
    └───────────┴─────► cancelled
```

### 9.3 Ride Status

```
pending ──► accepted ──► arrived ──► ongoing ──► completed
    │           │
    └───────────┴─────► cancelled
```

---

## 10. Error Handling

### 10.1 API Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### 10.2 HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

---

## 11. Rate Limiting

| Endpoint Group | Limit |
|----------------|-------|
| Auth (login) | 5 per minute |
| Auth (register) | 3 per hour |
| Chat send | 60 per minute |
| QR token | 10 per minute |
| API General | 100 per minute |

---

## 12. Environment Configuration

### 12.1 Backend Environment Variables

```env
# App
APP_NAME=Vito
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dacatlon.store

# Database
DB_HOST=localhost
DB_DATABASE=vito
DB_USERNAME=vito_user
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Stripe
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Pusher
PUSHER_APP_ID=app-id
PUSHER_APP_KEY=key
PUSHER_APP_SECRET=secret
PUSHER_HOST=api.pusher.com

# Reverb
REVERB_APP_ID=app-id
REVERB_APP_KEY=key
REVERB_APP_SECRET=secret
REVERB_HOST=samehost.local
```

### 12.2 App Build Variables

```bash
# User App
--dart-define=MAPS_API_KEY=your_maps_key
--dart-define=STRIPE_PUBLISHABLE_KEY=pk_live_...
--dart-define=BASE_URL=https://dacatlon.store

# Driver App
--dart-define=MAPS_API_KEY=your_maps_key
--dart-define=BASE_URL=https://dacatlon.store
```

---

*Document Version: 1.1 | Last Updated: 2026-07-03*
