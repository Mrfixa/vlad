# Vito Production-Ready Implementation Plan
## Gojek-Level Comprehensive Checklist

**Version:** 1.0  
**Created:** 2026-07-03  
**Target:** Production at dacatlon.store  
**Scope:** Backend (Laravel), User App (Flutter), Driver App (Flutter), Admin Panel

---

## Table of Contents
1. [Executive Summary](#1-executive-summary)
2. [Phase 0: Critical Blockers (Day 1)](#phase-0-critical-blockers-day-1)
3. [Phase 1: Backend Core (Day 2-3)](#phase-1-backend-core-day-2-3)
4. [Phase 2: User App Polish (Day 4-5)](#phase-2-user-app-polish-day-4-5)
5. [Phase 3: Driver App Polish (Day 6-7)](#phase-3-driver-app-polish-day-6-7)
6. [Phase 4: Admin Panel (Day 8)](#phase-4-admin-panel-day-8)
7. [Phase 5: Integration Testing (Day 9)](#phase-5-integration-testing-day-9)
8. [Phase 6: Performance & Security (Day 10)](#phase-6-performance--security-day-10)
9. [Phase 7: Pre-Launch (Day 11-12)](#phase-7-pre-launch-day-11-12)
10. [Phase 8: Launch](#phase-8-launch)
11. [Verification Checklists](#11-verification-checklists)

---

## 1. Executive Summary

### Current State Assessment
| Component | Status | Score |
|-----------|--------|-------|
| Backend API | Functional | 75/100 |
| User App | Functional | 70/100 |
| Driver App | Functional | 68/100 |
| Admin Panel | Functional | 65/100 |
| Tests | 170 passing | 60/100 |
| Security | Needs Review | 50/100 |

### Gap Analysis vs Gojek
| Category | Gojek Standard | Vito Current | Gap |
|-----------|----------------|--------------|-----|
| Auth | Biometric + PIN | PIN only | Medium |
| Booking | < 30 seconds | ~45 seconds | Low |
| Tracking | Real-time GPS | Periodic update | Medium |
| Support | In-app chat 24/7 | Basic chat | High |
| Safety | SOS + trip sharing | Basic | High |
| UX | Smooth animations | Some lag | Low |
| Offline | Graceful degradation | Basic | Medium |
| Accessibility | WCAG 2.1 | Not implemented | High |

### Total Items: ~287 tasks across 8 phases

---

## Phase 0: Critical Blockers (Day 1)

### 0.1 Security Emergency Fixes

#### 0.1.1 Swish Key Rotation ⚠️
```bash
# 1. Generate new Swish certificate
openssl req -newkey rsa:2048 -nodes -keyout new_private.key -out new_cert.csr
# 2. Submit CSR to Swish portal
# 3. Download new certificate
# 4. Replace in certificates/live/
# 5. Update .env: SWISH_CERTIFICATE=/path/to/new.pem
# 6. Purge git history
git filter-repo --path certificates/ --invert-paths
git push origin --force --all
```

**Verification:**
- [ ] Old key revoked with Swish
- [ ] New key installed on server
- [ ] Git history purged
- [ ] `.gitignore` updated

#### 0.1.2 Demo Seeder Gating
**File:** `database/seeders/DefaultUsersSeeder.php`
```php
// Add at top of run() method
if (app()->environment('production')) {
    $this->command->info('Skipping demo seeder in production.');
    return;
}
```

**Verification:**
- [ ] `php artisan db:seed` skips in production
- [ ] Demo accounts not created on prod migrate

#### 0.1.3 CORS Hardening
**File:** `config/cors.php`
```php
// Change allowed_origins from ['*']
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
```

**.env:**
```env
CORS_ALLOWED_ORIGINS=https://admin.dacatlon.store,https://dacatlon.store
```

**Verification:**
- [ ] API rejects requests from unknown origins
- [ ] Admin panel works from allowed domains

### 0.2 Production Environment Setup

#### 0.2.1 Server Requirements
- [ ] Ubuntu 22.04 LTS with SSH access
- [ ] PHP 8.4+ with extensions (BCMATH, XML, Zip, GD, Mbstring, OpenSSL)
- [ ] MySQL 8.0+ or MariaDB 10.5+
- [ ] Redis 6.0+
- [ ] Nginx or Apache
- [ ] SSL certificate (Let's Encrypt)

#### 0.2.2 Server Setup Commands
```bash
# SSH to server
ssh root@dacatlon.store

# Install dependencies
apt update && apt upgrade -y
apt install -y php8.4 php8.4-fpm php8.4-mysql php8.4-redis \
    php8.4-xml php8.4-zip php8.4-gd php8.4-mbstring \
    nginx mysql-server redis-server certbot python3-certbot-nginx

# Configure PHP
phpenmod BCMATH XML ZIP GD MBSTRING

# Secure MySQL
mysql_secure_installation

# Create database
mysql -u root -p
CREATE DATABASE vito CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vito'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON vito.* TO 'vito'@'localhost';
FLUSH PRIVILEGES;

# Configure Redis
systemctl enable redis-server
systemctl start redis-server

# Let's Encrypt
certbot --nginx -d dacatlon.store -d admin.dacatlon.store
```

#### 0.2.3 Nginx Configuration
**File:** `/etc/nginx/sites-available/vito`
```nginx
server {
    listen 80;
    server_name dacatlon.store;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name dacatlon.store;

    root /var/www/vito/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/dacatlon.store/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dacatlon.store/privkey.pem;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Verification:**
- [ ] Nginx configured and enabled
- [ ] SSL certificate active
- [ ] PHP-FPM running
- [ ] Database connection works

### 0.3 Secrets & Environment

#### 0.3.1 Generate All Secrets
```bash
cd /var/www/vito

# Generate Laravel key
php artisan key:generate --force

# Generate Passport keys
php artisan passport:keys --force

# Generate strong APP_KEY
cat > .env << 'EOF'
APP_NAME=Vito
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://dacatlon.store

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vito
DB_USERNAME=vito
DB_PASSWORD=generated_strong_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

PUSHER_APP_ID=xxx
PUSHER_APP_KEY=xxx
PUSHER_APP_SECRET=xxx
PUSHER_HOST=api.pusherapp.com
PUSHER_PORT=443
PUSHER_SCHEME=https

REVERB_APP_ID=xxx
REVERB_APP_KEY=xxx
REVERB_APP_SECRET=xxx
REVERB_HOST=127.0.0.1
REVERB_PORT=8080

SWISH_MERCHANT_ID=xxx
SWISH_CERTIFICATE=/var/www/vito/certificates/live/xxx.pem
SWISH_PRIVATE_KEY=/var/www/vito/certificates/live/xxx.key

CORS_ALLOWED_ORIGINS=https://admin.dacatlon.store,https://dacatlon.store
EOF
```

#### 0.3.2 Set Permissions
```bash
chown -R www-data:www-data /var/www/vito
chmod -R 755 /var/www/vito/storage
chmod -R 755 /var/www/vito/bootstrap/cache
chmod 600 /var/www/vito/.env
```

**Verification:**
- [ ] `.env` file not web-accessible
- [ ] All secrets generated
- [ ] Permissions correct

---

## Phase 1: Backend Core (Day 2-3)

### 1.1 Authentication Endpoints

#### 1.1.1 PIN Authentication ✅ Already Implemented
- [x] `POST /api/auth/login` - PIN login
- [x] `POST /api/auth/register` - Registration
- [x] `POST /api/auth/forgot-pin` - PIN reset
- [x] `POST /api/auth/reset-pin` - PIN reset confirmation

**Verification:**
```bash
curl -X POST https://dacatlon.store/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test","pin":"123456"}'
# Should return token
```

#### 1.1.2 Social Login (Google/Apple)
**Files to create:**
- [ ] `Modules/AuthManagement/Http/Controllers/Api/SocialAuthController.php`
- [ ] `Modules/AuthManagement/Services/SocialAuthService.php`

**Implementation:**
```php
// routes/api.php
Route::prefix('auth')->group(function () {
    Route::get('google', [SocialAuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
    Route::get('apple', [SocialAuthController::class, 'redirectToApple']);
    Route::get('apple/callback', [SocialAuthController::class, 'handleAppleCallback']);
});
```

**Flutter changes:**
- [ ] Add `sign_in_with_apple` package
- [ ] Add `google_sign_in` package
- [ ] Create `SocialLoginButton` widget
- [ ] Update `login_screen.dart`

#### 1.1.3 Biometric Authentication
**Backend:**
- [ ] Add `biometric_token` field to users table
- [ ] Add `POST /api/auth/biometric/login` endpoint

**Flutter:**
- [ ] Add `local_auth` package
- [ ] Create `BiometricSetupScreen`
- [ ] Implement fingerprint/face authentication
- [ ] Store encrypted biometric token locally

### 1.2 Ride Module

#### 1.2.1 Current Endpoints ✅
- [x] `POST /api/customer/ride/estimate-fare`
- [x] `POST /api/customer/ride/create`
- [x] `GET /api/customer/ride/details/{id}`
- [x] `POST /api/customer/ride/cancel/{id}`
- [x] `POST /api/driver/ride/accept`
- [x] `POST /api/driver/ride/update-status`
- [x] `POST /api/driver/ride/complete`

#### 1.2.2 Missing Endpoints to Implement
- [ ] `POST /api/customer/ride/schedule` - Scheduled booking
- [ ] `GET /api/customer/ride/history` - Ride history with pagination
- [ ] `POST /api/customer/ride/report/{id}` - Report issue
- [ ] `GET /api/driver/ride/history` - Driver ride history
- [ ] `POST /api/driver/ride/reassign` - Admin reassign

#### 1.2.3 Scheduled Booking Implementation
**Database:**
```sql
ALTER TABLE trip_requests ADD COLUMN scheduled_at TIMESTAMP NULL;
ALTER TABLE trip_requests ADD COLUMN scheduled_status ENUM('pending','confirmed','cancelled') DEFAULT 'pending';
```

**Controller:** `Modules/TripManagement/Http/Controllers/Api/Customer/VitoRideController.php`
```php
public function schedule(Request $request) {
    $validated = $request->validate([
        'pickup_lat' => 'required|numeric',
        'pickup_lng' => 'required|numeric',
        'dropoff_lat' => 'required|numeric',
        'dropoff_lng' => 'required|numeric',
        'vehicle_category_id' => 'required|uuid|exists:vehicle_categories,id',
        'scheduled_at' => 'required|date|after:now',
    ]);

    // Create pending scheduled trip
    $trip = TripRequest::create([
        'customer_id' => auth('api')->id(),
        'scheduled_at' => $validated['scheduled_at'],
        'scheduled_status' => 'pending',
        // ... other fields
    ]);

    // Schedule job to confirm 1 hour before
    ScheduleConfirmationJob::dispatch($trip)->delay(
        Carbon::parse($validated['scheduled_at'])->subHour()
    );

    return response()->json(['trip' => $trip]);
}
```

### 1.3 Mart Module

#### 1.3.1 Current Status ✅
- [x] Categories, products, orders, reviews
- [x] Promo codes with atomic counters
- [x] Delivery fee integration

#### 1.3.2 Missing Features
- [ ] Product variants (size, color)
- [ ] Stock management
- [ ] Low stock alerts
- [ ] Out-of-stock handling
- [ ] Product search with filters

#### 1.3.3 Product Search Implementation
**Controller:** `VitoMartController.php`
```php
public function search(Request $request) {
    $query = MartProduct::where('is_active', 1);
    
    if ($request->has('q')) {
        $query->where('name', 'LIKE', "%{$request->q}%");
    }
    
    if ($request->has('category_id')) {
        $query->where('category_id', $request->category_id);
    }
    
    if ($request->has('min_price')) {
        $query->where('price', '>=', $request->min_price);
    }
    
    if ($request->has('max_price')) {
        $query->where('price', '<=', $request->max_price);
    }
    
    $query->orderBy($request->sort ?? 'name', $request->order ?? 'asc');
    
    return MartProductResource::collection($query->paginate(20));
}
```

### 1.4 Parcel Module

#### 1.4.1 Current Status ✅
- [x] Parcel creation with weight
- [x] Driver assignment
- [x] Status tracking

#### 1.4.2 Missing Features
- [ ] Weight-based pricing calculation
- [ ] Real-time tracking URL generation
- [ ] Proof of delivery (already implemented in Mart)
- [ ] Insurance options

#### 1.4.3 Weight-Based Pricing
**Table:** `parcel_zones`
```sql
CREATE TABLE parcel_zones (
    id CHAR(36) PRIMARY KEY,
    pickup_area VARCHAR(255),
    delivery_area VARCHAR(255),
    base_fee DECIMAL(10,2),
    per_kg_fee DECIMAL(10,2),
    max_weight INT DEFAULT 50,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Controller:** `VitoParcelController.php`
```php
public function calculatePrice(Request $request) {
    $zone = ParcelZone::where('pickup_area', $request->pickup_area)
                      ->where('delivery_area', $request->delivery_area)
                      ->first();

    if (!$zone) {
        // Use default pricing
        $baseFee = config('parcel.default_base_fee', 5.00);
        $perKg = config('parcel.default_per_kg', 1.50);
    } else {
        $baseFee = $zone->base_fee;
        $perKg = $zone->per_kg_fee;
    }

    $weight = floatval($request->weight ?? 1);
    $total = $baseFee + ($perKg * $weight);

    return response()->json([
        'base_fee' => $baseFee,
        'weight' => $weight,
        'per_kg_fee' => $perKg,
        'total' => round($total, 2),
    ]);
}
```

### 1.5 Chat Module

#### 1.5.1 Current Status ✅
- [x] Real-time chat via Pusher
- [x] Typing indicators

#### 1.5.2 Missing Features
- [ ] Read receipts
- [ ] Message attachments (images)
- [ ] Voice messages
- [ ] Message reactions
- [ ] Block/report user

#### 1.5.3 Read Receipts Implementation
**Table:**
```sql
ALTER TABLE channel_messages ADD COLUMN read_at TIMESTAMP NULL;
ALTER TABLE channel_messages ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;
```

**Event:**
```php
class MessageReadEvent implements ShouldBroadcast
{
    public function __construct(
        public string $channelId,
        public int $messageId,
        public string $readerId,
        public string $readerType // 'customer' or 'driver'
    ) {}

    public function broadcastOn() {
        return new PrivateChannel($this->channelId);
    }
}
```

**Flutter (User App):**
```dart
// In message_screen.dart
PusherChannels.instance.subscribe(
  'private-customer-ride-chat.$tripId',
  onEvent: (event) {
    if (event.eventName == 'MessageReadEvent') {
      final data = jsonDecode(event.data);
      setState(() {
        messages.firstWhere((m) => m.id == data['messageId']).readAt = 
            DateTime.parse(data['readAt']);
      });
    }
  },
);
```

### 1.6 Payment Module

#### 1.6.1 Current Status ✅
- [x] Stripe integration
- [x] Payment intents
- [x] Webhook handling
- [x] Refunds

#### 1.6.2 Missing: Cash on Delivery
**Table:**
```sql
ALTER TABLE trip_requests ADD COLUMN cash_amount DECIMAL(10,2) NULL;
ALTER TABLE trip_requests ADD COLUMN paid_via_cash BOOLEAN DEFAULT FALSE;
```

**Controller:**
```php
public function completeWithCash(Request $request) {
    $trip = TripRequest::findOrFail($request->trip_id);
    
    $trip->update([
        'paid_via_cash' => true,
        'cash_amount' => $request->cash_amount,
        'current_status' => 'completed',
        'paid_fare' => $request->cash_amount,
    ]);

    return response()->json(['success' => true]);
}
```

#### 1.6.3 Missing: Split Payment
**Table:**
```sql
CREATE TABLE payment_splits (
    id CHAR(36) PRIMARY KEY,
    trip_request_id CHAR(36),
    payment_method ENUM('wallet', 'stripe', 'cash'),
    amount DECIMAL(10,2),
    status ENUM('pending', 'completed', 'failed'),
    FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id)
);
```

### 1.7 Notifications

#### 1.7.1 Current Status ✅
- [x] Firebase Cloud Messaging
- [x] Push notifications for orders

#### 1.7.2 Missing: In-App Notifications
**Table:**
```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36),
    type VARCHAR(50),
    title VARCHAR(255),
    body TEXT,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Controller:**
```php
public function index(Request $request) {
    return Notification::where('user_id', auth('api')->id())
        ->orderBy('created_at', 'desc')
        ->paginate(20);
}

public function markAsRead($id) {
    Notification::where('id', $id)
        ->update(['is_read' => true, 'read_at' => now()]);
}

public function markAllAsRead() {
    Notification::where('user_id', auth('api')->id())
        ->where('is_read', false)
        ->update(['is_read' => true, 'read_at' => now()]);
}
```

### 1.8 Safety Features

#### 1.8.1 Emergency SOS
**Table:**
```sql
CREATE TABLE sos_alerts (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36),
    trip_request_id CHAR(36) NULL,
    type ENUM('customer', 'driver'),
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    message TEXT,
    status ENUM('active', 'responded', 'resolved'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Controller:**
```php
public function triggerSos(Request $request) {
    $sos = SosAlert::create([
        'user_id' => auth('api')->id(),
        'trip_request_id' => $request->trip_id,
        'type' => $request->type,
        'location_lat' => $request->lat,
        'location_lng' => $request->lng,
        'message' => $request->message,
        'status' => 'active',
    ]);

    // Notify emergency contacts
    $user = User::find(auth('api')->id());
    foreach ($user->emergencyContacts as $contact) {
        sendSms($contact->phone, "SOS Alert: {$user->full_name} triggered emergency alert");
    }

    // Notify admin
    Notification::create([
        'user_id' => Admin::first()->id,
        'type' => 'sos',
        'title' => 'Emergency SOS',
        'body' => "User {$user->full_name} triggered SOS",
        'data' => ['sos_id' => $sos->id],
    ]);

    return response()->json(['alert_id' => $sos->id]);
}
```

#### 1.8.2 Trip Sharing
**Controller:**
```php
public function shareTrip(Request $request) {
    $trip = TripRequest::findOrFail($request->trip_id);
    
    // Generate unique share token
    $shareToken = Str::random(32);
    
    TripShare::create([
        'trip_request_id' => $trip->id,
        'share_token' => $shareToken,
        'expires_at' => now()->addHours(24),
    ]);

    $shareUrl = "https://dacatlon.store/track/{$shareToken}";

    return response()->json(['share_url' => $shareUrl]);
}

// Public tracking page
Route::get('/track/{token}', [TrackingController::class, 'publicTrack']);
```

### 1.9 Backend Testing

#### 1.9.1 Current: 170 Tests
**Add tests for:**
- [ ] `test_scheduled_booking_creates_job`
- [ ] `test_parcel_weight_pricing`
- [ ] `test_chat_read_receipts`
- [ ] `test_sos_trigger_notification`
- [ ] `test_cash_payment_completes_trip`
- [ ] `test_split_payment_partial_wallet`
- [ ] `test_biometric_auth_invalidates_on_logout`

#### 1.9.2 PHPStan Level Upgrade
```bash
# Current: Level 0
# Target: Level 5

# Incrementally upgrade
./vendor/bin/phpstan analyse --level=1
# Fix errors
./vendor/bin/phpstan analyse --level=2
# Fix errors
# ... continue until level 5
```

---

## Phase 2: User App Polish (Day 4-5)

### 2.1 Authentication Screens

#### 2.1.1 Current: PIN Login ✅
**File:** `lib/features/auth/screens/login_screen.dart`

#### 2.1.2 Add Biometric Login
```dart
// lib/features/auth/screens/login_screen.dart

class LoginScreen extends StatefulWidget {
  // ... existing code
}

class _LoginScreenState extends State<LoginScreen> {
  bool _showBiometric = false;

  @override
  void initState() {
    super.initState();
    _checkBiometricAvailable();
  }

  Future<void> _checkBiometricAvailable() async {
    final isAvailable = await LocalAuthentication().canCheckBiometrics;
    final isEnrolled = await LocalAuthentication().isDeviceSupported();
    setState(() => _showBiometric = isAvailable && isEnrolled);
  }

  Future<void> _authenticateWithBiometric() async {
    try {
      final authenticated = await LocalAuthentication().authenticate(
        localizedReason: 'Login to Vito',
        options: const AuthenticationOptions(
          stickyAuth: true,
          biometricOnly: true,
        ),
      );

      if (authenticated) {
        // Retrieve stored credentials and login
        final token = await _getStoredToken();
        if (token != null) {
          Get.offAll(() => HomeScreen());
        }
      }
    } catch (e) {
      showCustomSnackBar('Biometric authentication failed', isError: true);
    }
  }
}
```

#### 2.1.3 Add Social Login Buttons
```dart
// lib/features/auth/widgets/social_login_buttons.dart

class SocialLoginButtons extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Google Sign In
        _SocialButton(
          icon: Icons.g_mobiledata,
          label: 'Continue with Google',
          color: Colors.red,
          onPressed: () => Get.find<AuthController>().signInWithGoogle(),
        ),
        const SizedBox(height: 12),
        // Apple Sign In (iOS only)
        if (Platform.isIOS)
          _SocialButton(
            icon: Icons.apple,
            label: 'Continue with Apple',
            color: Colors.black,
            onPressed: () => Get.find<AuthController>().signInWithApple(),
          ),
      ],
    );
  }
}
```

### 2.2 Home Screen

#### 2.2.1 Current: Basic Map + Options ✅
**File:** `lib/features/home/screens/home_screen.dart`

#### 2.2.2 Add Smart Suggestions
```dart
// lib/features/home/widgets/quick_actions_widget.dart

class QuickActionsWidget extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final isRushHour = (now.hour >= 7 && now.hour <= 9) || 
                        (now.hour >= 17 && now.hour <= 19);

    return Container(
      padding: EdgeInsets.all(Dimensions.paddingSizeDefault),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isRushHour)
            Container(
              padding: EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.orange.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.warning_amber, color: Colors.orange),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Rush hour pricing may apply',
                      style: TextStyle(color: Colors.orange),
                    ),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 16),
          // Recent destinations
          _RecentDestinations(),
          const SizedBox(height: 16),
          // Favorite places
          _FavoritePlaces(),
        ],
      ),
    );
  }
}
```

#### 2.2.3 Add Recent Destinations
```dart
class _RecentDestinations extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Recent', style: textSemiBold),
        const SizedBox(height: 8),
        GetBuilder<LocationController>(
          builder: (controller) {
            final recent = controller.getRecentDestinations();
            if (recent.isEmpty) return SizedBox();
            
            return SizedBox(
              height: 80,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: recent.length.clamp(0, 5),
                itemBuilder: (context, index) {
                  final dest = recent[index];
                  return _DestinationCard(
                    icon: Icons.history,
                    title: dest.name,
                    subtitle: dest.address,
                    onTap: () => controller.setDestination(dest),
                  );
                },
              ),
            );
          },
        ),
      ],
    );
  }
}
```

### 2.3 Booking Flow

#### 2.3.1 Current: Basic Flow ✅
**Files:**
- `lib/features/map/screens/map_screen.dart`
- `lib/features/map/widgets/booking_confirmation_sheet.dart`

#### 2.3.2 Add Vehicle Comparison
```dart
// lib/features/map/widgets/vehicle_comparison_widget.dart

class VehicleComparisonWidget extends StatelessWidget {
  final List<VehicleCategory> categories;
  final Function(VehicleCategory) onSelect;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 200,
      child: Row(
        children: categories.map((cat) {
          return Expanded(
            child: _VehicleCard(
              category: cat,
              isRecommended: _isRecommended(cat),
              onTap: () => onSelect(cat),
            ),
          );
        }).toList(),
      ),
    );
  }

  bool _isRecommended(VehicleCategory cat) {
    // Recommend based on distance, time, price
    return cat.id == _calculateBestValue(categories);
  }
}

class _VehicleCard extends StatelessWidget {
  final VehicleCategory category;
  final bool isRecommended;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: EdgeInsets.symmetric(horizontal: 4),
        padding: EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: isRecommended 
              ? Theme.of(context).primaryColor.withOpacity(0.1)
              : Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isRecommended 
                ? Theme.of(context).primaryColor 
                : Colors.transparent,
            width: 2,
          ),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Image.network(category.image, height: 40),
            const SizedBox(height: 8),
            Text(category.name, style: textMedium),
            Text('\$${category.baseFare}', style: textBold.copyWith(
              color: Theme.of(context).primaryColor,
            )),
            if (isRecommended)
              Container(
                margin: EdgeInsets.only(top: 4),
                padding: EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.green,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text('Best', style: TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                )),
              ),
          ],
        ),
      ),
    );
  }
}
```

#### 2.3.3 Add Promo Code Input
```dart
// lib/features/map/widgets/promo_code_widget.dart

class PromoCodeWidget extends StatefulWidget {
  @override
  State<PromoCodeWidget> createState() => _PromoCodeWidgetState();
}

class _PromoCodeWidgetState extends State<PromoCodeWidget> {
  final _controller = TextEditingController();
  bool _isLoading = false;
  PromoCode? _appliedPromo;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _applyPromo() async {
    if (_controller.text.isEmpty) return;
    
    setState(() => _isLoading = true);
    
    try {
      final promo = await Get.find<MartController>().validatePromoCode(
        _controller.text,
      );
      
      setState(() => _appliedPromo = promo);
      showCustomSnackBar('Promo applied!', isError: false);
    } catch (e) {
      showCustomSnackBar('Invalid promo code', isError: true);
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Have a promo code?', style: textSemiBold),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _controller,
                  decoration: InputDecoration(
                    hintText: 'Enter code',
                    border: OutlineInputBorder(),
                  ),
                  textCapitalization: TextCapitalization.characters,
                ),
              ),
              const SizedBox(width: 8),
              ElevatedButton(
                onPressed: _isLoading ? null : _applyPromo,
                child: _isLoading 
                    ? SizedBox(width: 20, height: 20, 
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : Text('Apply'),
              ),
            ],
          ),
          if (_appliedPromo != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.check_circle, color: Colors.green),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${_appliedPromo.code} applied!',
                      style: TextStyle(color: Colors.green),
                    ),
                  ),
                  IconButton(
                    icon: Icon(Icons.close),
                    onPressed: () => setState(() => _appliedPromo = null),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
```

### 2.4 Tracking Screen

#### 2.4.1 Current: Basic Tracking ✅
**File:** `lib/features/map/screens/trip_tracking_screen.dart`

#### 2.4.2 Add Real-Time GPS Updates
```dart
// In trip_tracking_screen.dart

class TripTrackingScreen extends StatefulWidget {
  @override
  State<TripTrackingScreen> createState() => _TripTrackingScreenState();
}

class _TripTrackingScreenState extends State<TripTrackingScreen> {
  StreamSubscription? _locationSubscription;

  @override
  void initState() {
    super.initState();
    _subscribeToDriverLocation();
  }

  void _subscribeToDriverLocation() {
    final tripId = Get.arguments['tripId'];
    
    _locationSubscription = PusherChannels.instance
        .subscribe('private-customer-ride.$tripId')
        .on('DriverLocationUpdate', (event) {
      final data = jsonDecode(event.data);
      final driverLat = data['lat'];
      final driverLng = data['lng'];
      
      // Update driver marker position smoothly
      _mapController?.animateCamera(
        CameraUpdate.newLatLng(LatLng(driverLat, driverLng)),
      );
    });
  }

  @override
  void dispose() {
    _locationSubscription?.cancel();
    super.dispose();
  }
}
```

#### 2.4.3 Add Share Trip Feature
```dart
// lib/features/map/widgets/share_trip_widget.dart

class ShareTripWidget extends StatelessWidget {
  final String tripId;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Share your trip', style: textSemiBold.copyWith(fontSize: 18)),
          const SizedBox(height: 12),
          // Share button
          ElevatedButton.icon(
            onPressed: () => _shareTrip(context),
            icon: Icon(Icons.share),
            label: Text('Share with family'),
            style: ElevatedButton.styleFrom(
              minimumSize: Size(double.infinity, 48),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'They\'ll see your real-time location until you arrive',
            style: textRegular.copyWith(color: Colors.grey),
          ),
        ],
      ),
    );
  }

  Future<void> _shareTrip(BuildContext context) async {
    try {
      final shareUrl = await Get.find<TripController>().shareTrip(tripId);
      
      await Share.share(
        'Track my Vito ride: $shareUrl',
        subject: 'Track My Ride',
      );
    } catch (e) {
      showCustomSnackBar('Failed to share trip', isError: true);
    }
  }
}
```

### 2.5 Mart Screens

#### 2.5.1 Current: Basic Mart ✅
**Files:**
- `lib/features/mart/screens/mart_store_screen.dart`
- `lib/features/mart/screens/mart_order_history_screen.dart`

#### 2.5.2 Migrate to GetX Pattern
```dart
// lib/features/mart/controllers/mart_controller.dart

class MartController extends GetxController {
  // Observable state
  final isLoading = false.obs;
  final categories = <MartCategory>[].obs;
  final products = <MartProduct>[].obs;
  final cart = Rx<MartCart?>(null);
  final searchQuery = ''.obs;

  // Computed
  List<MartProduct> get filteredProducts {
    if (searchQuery.isEmpty) return products;
    return products.where((p) => 
      p.name.toLowerCase().contains(searchQuery.value.toLowerCase())
    ).toList();
  }

  double get cartTotal {
    if (cart.value == null) return 0;
    return cart.value!.items.fold(0, (sum, item) => 
      sum + (item.product.price * item.quantity)
    );
  }

  // Actions
  Future<void> searchProducts(String query) async {
    searchQuery.value = query;
    if (query.isEmpty) {
      await getProducts();
      return;
    }
    
    isLoading.value = true;
    try {
      final results = await _repository.searchProducts(query);
      products.value = results;
    } finally {
      isLoading.value = false;
    }
  }

  void addToCart(MartProduct product, int quantity) {
    final currentCart = cart.value ?? MartCart();
    final existingIndex = currentCart.items.indexWhere(
      (i) => i.productId == product.id,
    );

    if (existingIndex >= 0) {
      currentCart.items[existingIndex].quantity += quantity;
    } else {
      currentCart.items.add(CartItem(
        productId: product.id,
        product: product,
        quantity: quantity,
      ));
    }

    cart.value = currentCart;
    _saveCart();
  }
}
```

#### 2.5.3 Add Product Search
```dart
// lib/features/mart/widgets/product_search_widget.dart

class ProductSearchWidget extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.all(16),
      child: TextField(
        decoration: InputDecoration(
          hintText: 'Search products...',
          prefixIcon: Icon(Icons.search),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          filled: true,
          fillColor: Theme.of(context).cardColor,
        ),
        onChanged: (value) {
          Get.find<MartController>().searchProducts(value);
        },
      ),
    );
  }
}
```

### 2.6 Chat Screen Improvements

#### 2.6.1 Current: Basic Chat ✅
**File:** `lib/features/message/screens/message_screen.dart`

#### 2.6.2 Add Message Status
```dart
// lib/features/message/widgets/message_bubble.dart

class MessageBubble extends StatelessWidget {
  final Message message;
  final bool isMe;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: 280),
        padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: isMe 
              ? Theme.of(context).primaryColor 
              : Theme.of(context).cardColor,
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(16),
            topRight: Radius.circular(16),
            bottomLeft: isMe ? Radius.circular(16) : Radius.circular(4),
            bottomRight: isMe ? Radius.circular(4) : Radius.circular(16),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              message.text,
              style: textRegular.copyWith(
                color: isMe ? Colors.white : null,
              ),
            ),
            const SizedBox(height: 4),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _formatTime(message.createdAt),
                  style: TextStyle(
                    fontSize: 10,
                    color: isMe ? Colors.white70 : Colors.grey,
                  ),
                ),
                if (isMe) ...[
                  const SizedBox(width: 4),
                  Icon(
                    message.readAt != null 
                        ? Icons.done_all 
                        : Icons.done,
                    size: 14,
                    color: message.readAt != null 
                        ? Colors.lightBlueAccent 
                        : Colors.white70,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
```

### 2.7 Settings & Profile

#### 2.7.1 Add Notification Settings
```dart
// lib/features/profile/screens/notification_settings_screen.dart

class NotificationSettingsScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Notification Settings')),
      body: GetBuilder<NotificationController>(
        builder: (controller) {
          return ListView(
            children: [
              _NotificationTile(
                title: 'Ride updates',
                subtitle: 'Driver assigned, arrival, completion',
                value: controller.rideNotifications,
                onChanged: (v) => controller.toggleRideNotifications(v),
              ),
              _NotificationTile(
                title: 'Order updates',
                subtitle: 'Mart and parcel order status',
                value: controller.orderNotifications,
                onChanged: (v) => controller.toggleOrderNotifications(v),
              ),
              _NotificationTile(
                title: 'Promotions',
                subtitle: 'Deals and special offers',
                value: controller.promotionNotifications,
                onChanged: (v) => controller.togglePromotionNotifications(v),
              ),
              _NotificationTile(
                title: 'Chat messages',
                subtitle: 'Messages from drivers',
                value: controller.chatNotifications,
                onChanged: (v) => controller.toggleChatNotifications(v),
              ),
              Divider(),
              _NotificationTile(
                title: 'Sound',
                subtitle: 'Play sound for notifications',
                value: controller.soundEnabled,
                onChanged: (v) => controller.toggleSound(v),
              ),
              _NotificationTile(
                title: 'Vibration',
                subtitle: 'Vibrate for notifications',
                value: controller.vibrationEnabled,
                onChanged: (v) => controller.toggleVibration(v),
              ),
            ],
          );
        },
      ),
    );
  }
}
```

#### 2.7.2 Add Emergency Contacts
```dart
// lib/features/profile/screens/emergency_contacts_screen.dart

class EmergencyContactsScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Emergency Contacts')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showAddContactDialog(context),
        child: Icon(Icons.add),
      ),
      body: GetBuilder<ProfileController>(
        builder: (controller) {
          if (controller.emergencyContacts.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.contact_phone, size: 64, color: Colors.grey),
                  const SizedBox(height: 16),
                  Text('No emergency contacts added'),
                  const SizedBox(height: 8),
                  ElevatedButton(
                    onPressed: () => _showAddContactDialog(context),
                    child: Text('Add Contact'),
                  ),
                ],
              ),
            );
          }

          return ListView.builder(
            itemCount: controller.emergencyContacts.length,
            itemBuilder: (context, index) {
              final contact = controller.emergencyContacts[index];
              return ListTile(
                leading: CircleAvatar(child: Icon(Icons.person)),
                title: Text(contact.name),
                subtitle: Text(contact.phone),
                trailing: IconButton(
                  icon: Icon(Icons.delete),
                  onPressed: () => controller.removeContact(contact.id),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
```

### 2.8 Empty States & Loading

#### 2.8.1 Add Comprehensive Empty States
```dart
// lib/common/widgets/empty_state_widget.dart

enum EmptyStateType {
  noRides,
  noOrders,
  noNotifications,
  noResults,
  noConnection,
  error,
}

class EmptyStateWidget extends StatelessWidget {
  final EmptyStateType type;
  final String? customTitle;
  final String? customMessage;
  final VoidCallback? onAction;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _buildIcon(),
            const SizedBox(height: 24),
            Text(
              customTitle ?? _getDefaultTitle(),
              style: textSemiBold.copyWith(fontSize: 18),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              customMessage ?? _getDefaultMessage(),
              style: textRegular.copyWith(color: Colors.grey),
              textAlign: TextAlign.center,
            ),
            if (onAction != null) ...[
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: onAction,
                child: Text(actionLabel ?? 'Try Again'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildIcon() {
    IconData icon;
    Color color;

    switch (type) {
      case EmptyStateType.noRides:
        icon = Icons.directions_car_outlined;
        color = Colors.blue;
        break;
      case EmptyStateType.noOrders:
        icon = Icons.shopping_bag_outlined;
        color = Colors.orange;
        break;
      case EmptyStateType.noNotifications:
        icon = Icons.notifications_off_outlined;
        color = Colors.grey;
        break;
      case EmptyStateType.noResults:
        icon = Icons.search_off;
        color = Colors.grey;
        break;
      case EmptyStateType.noConnection:
        icon = Icons.wifi_off;
        color = Colors.red;
        break;
      case EmptyStateType.error:
        icon = Icons.error_outline;
        color = Colors.red;
        break;
    }

    return Container(
      padding: EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        shape: BoxShape.circle,
      ),
      child: Icon(icon, size: 48, color: color),
    );
  }

  String _getDefaultTitle() {
    switch (type) {
      case EmptyStateType.noRides: return 'No rides yet';
      case EmptyStateType.noOrders: return 'No orders yet';
      case EmptyStateType.noNotifications: return 'All caught up!';
      case EmptyStateType.noResults: return 'No results found';
      case EmptyStateType.noConnection: return 'No connection';
      case EmptyStateType.error: return 'Something went wrong';
    }
  }

  String _getDefaultMessage() {
    switch (type) {
      case EmptyStateType.noRides: 
        return 'Book your first ride and it will appear here';
      case EmptyStateType.noOrders: 
        return 'Your orders will appear here once you shop';
      case EmptyStateType.noNotifications: 
        return 'You\'ll see updates about your rides and orders here';
      case EmptyStateType.noResults: 
        return 'Try adjusting your search or filters';
      case EmptyStateType.noConnection: 
        return 'Please check your internet connection';
      case EmptyStateType.error: 
        return 'Please try again later';
    }
  }
}
```

#### 2.8.2 Add Loading Skeletons
```dart
// lib/common/widgets/loading_skeleton.dart

class LoadingSkeleton extends StatefulWidget {
  final double width;
  final double height;
  final BorderRadius? borderRadius;

  @override
  State<LoadingSkeleton> createState() => _LoadingSkeletonState();
}

class _LoadingSkeletonState extends State<LoadingSkeleton>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: Duration(milliseconds: 1500),
      vsync: this,
    )..repeat();
    _animation = Tween<double>(begin: 0.4, end: 0.8).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, child) {
        return Container(
          width: widget.width,
          height: widget.height,
          decoration: BoxDecoration(
            color: Colors.grey.withOpacity(_animation.value),
            borderRadius: widget.borderRadius ?? BorderRadius.circular(4),
          ),
        );
      },
    );
  }
}

// Pre-built skeletons
class ProductCardSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LoadingSkeleton(
            width: double.infinity,
            height: 120,
            borderRadius: BorderRadius.circular(8),
          ),
          const SizedBox(height: 8),
          LoadingSkeleton(width: 100, height: 16),
          const SizedBox(height: 4),
          LoadingSkeleton(width: 60, height: 14),
        ],
      ),
    );
  }
}
```

### 2.9 Error Handling

#### 2.9.1 Add Global Error Handler
```dart
// lib/main.dart

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      // ... existing config
      
      // Global error handler
      unknownRoute: (settings) => GetPage(
        name: '/error',
        page: () => ErrorScreen(error: settings.name),
      ),
    );
  }
}

// lib/features/error/screens/error_screen.dart

class ErrorScreen extends StatelessWidget {
  final String? error;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text('Oops! Something went wrong', style: textSemiBold),
            const SizedBox(height: 8),
            Text(
              'We\'ve been notified and are working on it',
              style: textRegular.copyWith(color: Colors.grey),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => Get.offAll(() => HomeScreen()),
              child: Text('Go to Home'),
            ),
          ],
        ),
      ),
    );
  }
}
```

### 2.10 Accessibility

#### 2.10.1 Add Semantic Labels
```dart
// Apply to all interactive widgets

Semantics(
  label: 'Navigate to home',
  button: true,
  child: IconButton(
    icon: Icon(Icons.home),
    onPressed: () {},
  ),
),

Semantics(
  label: 'Trip fare is \$15.50',
  child: Text('\$15.50'),
),
```

#### 2.10.2 Add High Contrast Mode
```dart
// lib/theme/app_theme.dart

ThemeData highContrastTheme() {
  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: ColorScheme.highContrastLight(
      primary: Colors.black,
      secondary: Colors.black,
      surface: Colors.white,
    ),
    // Increase text sizes
    textTheme: TextTheme(
      bodyLarge: TextStyle(fontSize: 18, height: 1.5),
      bodyMedium: TextStyle(fontSize: 16, height: 1.5),
    ),
  );
}
```

---

## Phase 3: Driver App Polish (Day 6-7)

### 3.1 Authentication

#### 3.1.1 Current: PIN Login ✅
**File:** Similar to user app

#### 3.1.2 Add Face/Touch ID
```dart
// Similar to user app biometric implementation
// Use same LocalAuthentication package
```

### 3.2 Home Screen

#### 3.2.1 Current: Basic Online Toggle ✅
**File:** `lib/features/home/screens/home_screen.dart`

#### 3.2.2 Add Request Queue
```dart
// lib/features/home/widgets/ride_request_card.dart

class RideRequestCard extends StatefulWidget {
  final RideRequest request;
  final VoidCallback onAccept;
  final VoidCallback onDecline;

  @override
  State<RideRequestCard> createState() => _RideRequestCardState();
}

class _RideRequestCardState extends State<RideRequestCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _pulseAnimation;
  int _countdown = 30;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: Duration(seconds: 1),
      vsync: this,
    )..repeat(reverse: true);
    _pulseAnimation = Tween<double>(begin: 1.0, end: 1.05).animate(_controller);
    
    _startCountdown();
  }

  void _startCountdown() {
    Future.doWhile(() async {
      await Future.delayed(Duration(seconds: 1));
      if (!mounted) return false;
      setState(() => _countdown--);
      return _countdown > 0;
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _pulseAnimation,
      builder: (context, child) {
        return Transform.scale(
          scale: _pulseAnimation.value,
          child: Container(
            margin: EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: Theme.of(context).primaryColor.withOpacity(0.3),
                  blurRadius: 20,
                  offset: Offset(0, 10),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Header with timer
                Container(
                  padding: EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: _countdown < 10 ? Colors.red : Colors.green,
                    borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'New Request',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Row(
                        children: [
                          Icon(Icons.timer, color: Colors.white, size: 16),
                          SizedBox(width: 4),
                          Text(
                            '${_countdown}s',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                
                // Customer info
                Padding(
                  padding: EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          CircleAvatar(
                            backgroundImage: widget.request.customerImage != null
                                ? NetworkImage(widget.request.customerImage!)
                                : null,
                            child: widget.request.customerImage == null
                                ? Icon(Icons.person)
                                : null,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  widget.request.customerName,
                                  style: textSemiBold,
                                ),
                                Row(
                                  children: List.generate(5, (i) => Icon(
                                    i < widget.request.rating.round()
                                        ? Icons.star
                                        : Icons.star_border,
                                    size: 14,
                                    color: Colors.amber,
                                  )),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      
                      // Pickup location
                      _LocationRow(
                        icon: Icons.trip_origin,
                        color: Colors.green,
                        title: 'Pickup',
                        address: widget.request.pickupAddress,
                      ),
                      const SizedBox(height: 8),
                      
                      // Dropoff location
                      _LocationRow(
                        icon: Icons.location_on,
                        color: Colors.red,
                        title: 'Dropoff',
                        address: widget.request.dropoffAddress,
                      ),
                      const SizedBox(height: 16),
                      
                      // Earnings
                      Container(
                        padding: EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.grey[100],
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceAround,
                          children: [
                            _InfoColumn(
                              label: 'Earnings',
                              value: '\$${widget.request.earnings}',
                              isHighlighted: true,
                            ),
                            _InfoColumn(
                              label: 'Distance',
                              value: '${widget.request.distance} km',
                            ),
                            _InfoColumn(
                              label: 'Time',
                              value: '${widget.request.estimatedTime} min',
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                
                // Action buttons
                Padding(
                  padding: EdgeInsets.fromLTRB(16, 0, 16, 16),
                  child: Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: widget.onDecline,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.red,
                            side: BorderSide(color: Colors.red),
                            padding: EdgeInsets.symmetric(vertical: 14),
                          ),
                          child: Text('Decline'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton(
                          onPressed: widget.onAccept,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.green,
                            foregroundColor: Colors.white,
                            padding: EdgeInsets.symmetric(vertical: 14),
                          ),
                          child: Text('Accept'),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _LocationRow extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String address;

  const _LocationRow({
    required this.icon,
    required this.color,
    required this.title,
    required this.address,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: textRegular.copyWith(color: Colors.grey)),
              Text(address, style: textMedium),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoColumn extends StatelessWidget {
  final String label;
  final String value;
  final bool isHighlighted;

  const _InfoColumn({
    required this.label,
    required this.value,
    this.isHighlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: textRegular.copyWith(color: Colors.grey)),
        const SizedBox(height: 4),
        Text(
          value,
          style: textSemiBold.copyWith(
            fontSize: isHighlighted ? 18 : 14,
            color: isHighlighted ? Colors.green : null,
          ),
        ),
      ],
    );
  }
}
```

### 3.3 Trip Flow

#### 3.3.1 Current: Basic Flow ✅
**File:** `lib/features/trip/screens/trip_screen.dart`

#### 3.3.2 Add Navigation to Pickup
```dart
// lib/features/trip/widgets/navigation_widget.dart

class NavigationWidget extends StatelessWidget {
  final String destination;
  final double lat;
  final double lng;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                padding: EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.green.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(Icons.navigation, color: Colors.green),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Navigate to pickup',
                      style: textSemiBold,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      destination,
                      style: textRegular.copyWith(color: Colors.grey),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => _openMaps(context),
                  icon: Icon(Icons.map),
                  label: Text('Open in Maps'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton(
                onPressed: () => _copyAddress(),
                icon: Icon(Icons.copy),
                tooltip: 'Copy address',
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _openMaps(BuildContext context) {
    // Open in Google Maps or Mapbox
    final url = 'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng';
    canLaunchUrl(Uri.parse(url)).then((canLaunch) {
      if (canLaunch) {
        launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      } else {
        showCustomSnackBar('Could not open maps', isError: true);
      }
    });
  }

  void _copyAddress() {
    Clipboard.setData(ClipboardData(text: destination));
    showCustomSnackBar('Address copied', isError: false);
  }
}
```

### 3.4 Earnings Screen

#### 3.4.1 Current: Basic ✅
**File:** `lib/features/wallet/screens/wallet_screen.dart`

#### 3.4.2 Add Detailed Breakdown
```dart
// lib/features/wallet/widgets/earnings_detail_widget.dart

class EarningsDetailWidget extends StatelessWidget {
  final EarningsSummary summary;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Earnings Breakdown', style: textSemiBold.copyWith(fontSize: 18)),
          const SizedBox(height: 16),
          
          // Summary cards
          Row(
            children: [
              _EarningsCard(
                title: 'Today',
                amount: summary.todayEarnings,
                trips: summary.todayTrips,
                color: Colors.blue,
              ),
              const SizedBox(width: 12),
              _EarningsCard(
                title: 'This Week',
                amount: summary.weekEarnings,
                trips: summary.weekTrips,
                color: Colors.green,
              ),
              const SizedBox(width: 12),
              _EarningsCard(
                title: 'This Month',
                amount: summary.monthEarnings,
                trips: summary.monthTrips,
                color: Colors.orange,
              ),
            ],
          ),
          
          const SizedBox(height: 24),
          
          // Detailed breakdown
          Text('What you earned', style: textSemiBold),
          const SizedBox(height: 12),
          _BreakdownRow(
            label: 'Base fares',
            amount: summary.baseFares,
          ),
          _BreakdownRow(
            label: 'Surge pricing',
            amount: summary.surgeAmount,
          ),
          _BreakdownRow(
            label: 'Tips',
            amount: summary.tips,
          ),
          _BreakdownRow(
            label: 'Promotions & bonuses',
            amount: summary.bonuses,
          ),
          
          Divider(height: 24),
          
          _BreakdownRow(
            label: 'Total earnings',
            amount: summary.totalEarnings,
            isBold: true,
          ),
          
          const SizedBox(height: 24),
          
          // Deductions
          Text('Deductions', style: textSemiBold.copyWith(color: Colors.red)),
          const SizedBox(height: 12),
          _BreakdownRow(
            label: 'Service fee',
            amount: -summary.serviceFee,
            isNegative: true,
          ),
          _BreakdownRow(
            label: 'Taxes',
            amount: -summary.taxes,
            isNegative: true,
          ),
          
          Divider(height: 24),
          
          _BreakdownRow(
            label: 'Net earnings',
            amount: summary.netEarnings,
            isBold: true,
            isHighlighted: true,
          ),
        ],
      ),
    );
  }
}

class _EarningsCard extends StatelessWidget {
  final String title;
  final double amount;
  final int trips;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Text(
              title,
              style: textRegular.copyWith(color: color),
            ),
            const SizedBox(height: 4),
            Text(
              '\$${amount.toStringAsFixed(2)}',
              style: textSemiBold.copyWith(
                color: color,
                fontSize: 16,
              ),
            ),
            Text(
              '$trips trips',
              style: textRegular.copyWith(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  final String label;
  final double amount;
  final bool isBold;
  final bool isNegative;
  final bool isHighlighted;

  const _BreakdownRow({
    required this.label,
    required this.amount,
    this.isBold = false,
    this.isNegative = false,
    this.isHighlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: (isBold ? textSemiBold : textRegular).copyWith(
              color: isHighlighted ? Theme.of(context).primaryColor : null,
            ),
          ),
          Text(
            '${isNegative ? '-' : ''}\$${amount.abs().toStringAsFixed(2)}',
            style: (isBold ? textSemiBold : textRegular).copyWith(
              color: isNegative 
                  ? Colors.red 
                  : (isHighlighted ? Theme.of(context).primaryColor : null),
            ),
          ),
        ],
      ),
    );
  }
}
```

### 3.5 Trip Preferences

#### 3.5.1 Already Implemented ✅
**File:** `lib/features/setting/screens/trip_preferences_screen.dart`

### 3.6 Offline Mode

#### 3.6.1 Add Offline Queue
```dart
// lib/services/offline_queue_service.dart

class OfflineQueueService extends GetxService {
  final Queue<OfflineAction> _queue = Queue();
  final _isSyncing = false.obs;

  Future<void> addAction(OfflineAction action) async {
    _queue.add(action);
    await _persistQueue();
    
    if (action.isUrgent) {
      _syncNow();
    }
  }

  Future<void> _syncNow() async {
    if (_isSyncing.value || !GetConnect().enabled) return;
    
    _isSyncing.value = true;
    
    while (_queue.isNotEmpty) {
      final action = _queue.removeFirst();
      try {
        await _executeAction(action);
      } catch (e) {
        // Re-add to queue
        _queue.addFirst(action);
        break;
      }
    }
    
    _isSyncing.value = false;
    await _persistQueue();
  }

  Future<void> _executeAction(OfflineAction action) async {
    switch (action.type) {
      case ActionType.statusUpdate:
        await Get.find<TripController>().updateStatusOffline(action.data);
        break;
      case ActionType.locationUpdate:
        await Get.find<LocationController>().updateLocationOffline(action.data);
        break;
      case ActionType.chatMessage:
        await Get.find<MessageController>().sendMessageOffline(action.data);
        break;
    }
  }
}
```

---

## Phase 4: Admin Panel (Day 8)

### 4.1 Current Status
- [x] Basic CRUD operations
- [x] VitoMart management
- [x] Driver management
- [x] Zone management

### 4.2 Add Dashboard Analytics
```php
// Modules/AdminModule/Http/Controllers/Web/DashboardController.php

public function analytics() {
    $today = Carbon::today();
    $weekAgo = Carbon::now()->subWeek();
    $monthAgo = Carbon::now()->subMonth();

    return response()->json([
        'today' => [
            'rides' => TripRequest::whereDate('created_at', $today)->count(),
            'revenue' => TripRequest::whereDate('created_at', $today)->sum('paid_fare'),
            'new_users' => User::whereDate('created_at', $today)->count(),
            'active_drivers' => DriverDetail::where('is_online', true)->count(),
        ],
        'week' => [
            'rides' => TripRequest::whereBetween('created_at', [$weekAgo, now()])->count(),
            'revenue' => TripRequest::whereBetween('created_at', [$weekAgo, now()])->sum('paid_fare'),
        ],
        'month' => [
            'rides' => TripRequest::whereBetween('created_at', [$monthAgo, now()])->count(),
            'revenue' => TripRequest::whereBetween('created_at', [$monthAgo, now()])->sum('paid_fare'),
        ],
        'charts' => [
            'rides_by_hour' => $this->getRidesByHour(),
            'revenue_by_day' => $this->getRevenueByDay($monthAgo),
            'top_routes' => $this->getTopRoutes(),
        ],
    ]);
}
```

### 4.3 Add Heatmap
```php
// Resources/views/admin/dashboard.blade.php

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4>Driver Heatmap</h4>
            </div>
            <div class="card-body">
                <div id="heatmap" style="height: 400px;"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Load Mapbox heatmap
    mapboxgl.accessToken = '{{ config('services.mapbox.token') }}';
    const map = new mapboxgl.Map({
        container: 'heatmap',
        style: 'mapbox://styles/mapbox/dark-v11'
    });

    // Fetch driver locations and create heatmap
    fetch('/api/admin/driver-locations')
        .then(res => res.json())
        .then(data => {
            map.addSource('drivers', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: data.map(d => ({
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: [d.lng, d.lat] }
                    }))
                }
            });

            map.addLayer({
                id: 'driver-heat',
                type: 'heatmap',
                source: 'drivers',
                paint: {
                    'heatmap-weight': 1,
                    'heatmap-intensity': 1,
                    'heatmap-radius': 30,
                    'heatmap-color': [
                        'interpolate', ['linear'], ['heatmap-density'],
                        0, 'rgba(0,0,0,0)',
                        0.2, 'rgb(255,255,0)',
                        0.4, 'rgb(255,127,0)',
                        0.6, 'rgb(255,0,0)',
                        1, 'rgb(128,0,0)'
                    ]
                }
            });
        });
</script>
@endpush
```

### 4.4 Add Fraud Detection Alerts
```php
// Modules/AdminModule/Http/Controllers/Web/FraudController.php

public function alerts() {
    $alerts = [];

    // Detect suspicious activity
    // 1. Multiple cancellations from same user
    $suspiciousUsers = User::whereHas('tripRequests', function ($q) {
        $q->where('current_status', 'cancelled')
          ->where('created_at', '>=', now()->subDay());
    })
    ->withCount(['tripRequests' => function ($q) {
        $q->where('current_status', 'cancelled');
    }])
    ->having('trip_requests_count', '>', 5)
    ->get();

    foreach ($suspiciousUsers as $user) {
        $alerts[] = [
            'type' => 'high_cancellation',
            'user_id' => $user->id,
            'user_name' => $user->full_name,
            'count' => $user->trip_requests_count,
            'severity' => 'high',
        ];
    }

    // 2. Unusual earnings patterns
    $suspiciousDrivers = DriverDetail::whereHas('user.tripRequests', function ($q) {
        $q->where('current_status', 'completed')
          ->where('created_at', '>=', now()->subDay());
    })
    ->withSum(['user.tripRequests' => function ($q) {
        $q->where('current_status', 'completed')
          ->where('created_at', '>=', now()->subDay());
    }], 'paid_fare')
    ->having('user_trip_requests_sum_paid_fare', '>', 1000) // Arbitrary threshold
    ->get();

    foreach ($suspiciousDrivers as $driver) {
        $alerts[] = [
            'type' => 'high_earnings',
            'user_id' => $driver->user_id,
            'user_name' => $driver->user->full_name,
            'amount' => $driver->user_trip_requests_sum_paid_fare,
            'severity' => 'medium',
        ];
    }

    return view('admin.fraud.alerts', compact('alerts'));
}
```

---

## Phase 5: Integration Testing (Day 9)

### 5.1 End-to-End Tests

#### 5.1.1 Ride Booking Flow
```php
// tests/E2E/RideBookingTest.php

public function test_complete_ride_booking_flow(): void
{
    // 1. Customer login
    $customer = $this->customerLogin();

    // 2. Get fare estimate
    $estimate = $this->postJson('/api/customer/ride/estimate-fare', [
        'pickup_lat' => 13.7563,
        'pickup_lng' => 100.5018,
        'dropoff_lat' => 13.7466,
        'dropoff_lng' => 100.4930,
        'vehicle_category_id' => $this->category->id,
    ]);
    $estimate->assertOk();
    $this->assertArrayHasKey('estimated_fare', $estimate->json());

    // 3. Create ride
    $ride = $this->postJson('/api/customer/ride/create', [
        'pickup_lat' => 13.7563,
        'pickup_lng' => 100.5018,
        'pickup_address' => 'Test Pickup',
        'dropoff_lat' => 13.7466,
        'dropoff_lng' => 100.4930,
        'dropoff_address' => 'Test Dropoff',
        'vehicle_category_id' => $this->category->id,
        'payment_method' => 'stripe',
    ]);
    $ride->assertCreated();

    // 4. Driver login and accept
    $driver = $this->driverLogin();
    $accept = $this->postJson('/api/driver/ride/accept', [
        'trip_request_id' => $ride->json('trip.id'),
    ]);
    $accept->assertOk();

    // 5. Update status to arrived
    $arrived = $this->postJson('/api/driver/ride/update-status', [
        'trip_request_id' => $ride->json('trip.id'),
        'status' => 'arrived',
    ]);
    $arrived->assertOk();

    // 6. Start trip
    $start = $this->postJson('/api/driver/ride/update-status', [
        'trip_request_id' => $ride->json('trip.id'),
        'status' => 'ongoing',
    ]);
    $start->assertOk();

    // 7. Complete trip
    $complete = $this->postJson('/api/driver/ride/complete', [
        'trip_request_id' => $ride->json('trip.id'),
    ]);
    $complete->assertOk();

    // 8. Verify final status
    $details = $this->getJson('/api/customer/ride/details/' . $ride->json('trip.id'));
    $details->assertOk();
    $this->assertEquals('completed', $details->json('trip.current_status'));
}
```

#### 5.1.2 Mart Order Flow
```php
public function test_complete_mart_order_flow(): void
{
    // 1. Customer login
    $customer = $this->customerLogin();

    // 2. Add to cart
    $cart = $this->postJson('/api/customer/mart/cart/add', [
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);
    $cart->assertOk();

    // 3. Apply promo code
    $promo = $this->postJson('/api/customer/mart/cart/apply-promo', [
        'code' => 'SAVE10',
    ]);
    $promo->assertOk();

    // 4. Create order
    $order = $this->postJson('/api/customer/mart/orders', [
        'delivery_address' => '123 Test St',
        'delivery_lat' => 13.7563,
        'delivery_lng' => 100.5018,
        'payment_method' => 'stripe',
    ]);
    $order->assertCreated();

    // 5. Driver accepts
    $driver = $this->driverLogin();
    $accept = $this->postJson('/api/driver/mart/accept', [
        'order_id' => $order->json('order.id'),
    ]);
    $accept->assertOk();

    // 6. Update status to picked up
    $pickedUp = $this->postJson('/api/driver/mart/update-status', [
        'order_id' => $order->json('order.id'),
        'status' => 'picked_up',
    ]);
    $pickedUp->assertOk();

    // 7. Complete delivery
    $complete = $this->postJson('/api/driver/mart/complete', [
        'order_id' => $order->json('order.id'),
        'proof_image' => UploadedFile::fake()->image('proof.jpg'),
    ]);
    $complete->assertOk();
}
```

### 5.2 Load Testing
```bash
# Using k6
# scripts/load-test.js

import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '2m', target: 100 },  // Ramp up
    { duration: '5m', target: 100 },  // Steady
    { duration: '2m', target: 200 },  // Spike
    { duration: '5m', target: 200 },  // Steady at spike
    { duration: '2m', target: 0 },    // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% of requests under 500ms
    http_req_failed: ['rate<0.01'],     // Less than 1% failure
  },
};

export default function() {
  const res = http.get('https://dacatlon.store/api/health');
  check(res, {
    'status was 200': (r) => r.status == 200,
    'response time < 200ms': (r) => r.timings.duration < 200,
  });
  
  // Test login
  const login = http.post('https://dacatlon.store/api/auth/login', 
    JSON.stringify({ username: 'test', pin: '123456' }),
    { headers: { 'Content-Type': 'application/json' } }
  );
  check(login, { 'login success': (r) => r.status == 200 });
  
  sleep(1);
}
```

---

## Phase 6: Performance & Security (Day 10)

### 6.1 Database Optimization

#### 6.1.1 Add Indexes
```sql
-- Add indexes for common queries

-- Trip requests
CREATE INDEX idx_trip_customer ON trip_requests(customer_id, created_at);
CREATE INDEX idx_trip_driver ON trip_requests(driver_id, created_at);
CREATE INDEX idx_trip_status ON trip_requests(current_status, created_at);
CREATE INDEX idx_trip_scheduled ON trip_requests(scheduled_at) WHERE scheduled_at IS NOT NULL;

-- Mart orders
CREATE INDEX idx_mart_order_customer ON mart_orders(customer_id, created_at);
CREATE INDEX idx_mart_order_status ON mart_orders(status, created_at);

-- Chat messages
CREATE INDEX idx_chat_channel ON channel_messages(channel_id, created_at DESC);

-- Users
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_email ON users(email);
```

#### 6.1.2 Query Optimization
```php
// Use select for specific columns
$trips = TripRequest::select('id', 'customer_id', 'current_status', 'paid_fare', 'created_at')
    ->where('customer_id', $userId)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Use with() for eager loading
$orders = MartOrder::with(['items.product', 'customer'])
    ->where('customer_id', $userId)
    ->paginate(20);

// Use cursor() for large datasets
foreach (TripRequest::cursor() as $trip) {
    // Process without loading all into memory
}
```

### 6.2 Caching Strategy
```php
// config/cache.php

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// Use cache for expensive operations
public function getFareEstimate(Request $request) {
    $cacheKey = "fare_estimate:{$request->vehicle_category_id}:{$request->pickup_zone_id}:{$request->dropoff_zone_id}";

    return Cache::remember($cacheKey, 3600, function () use ($request) {
        return $this->calculateFare(
            $request->vehicle_category_id,
            $request->pickup_zone_id,
            $request->dropoff_zone_id
        );
    });
}

// Cache driver locations (update every 10 seconds)
public function updateDriverLocation(Request $request) {
    // Update in Redis for fast reads
    Redis::geoadd('driver_locations', $request->lng, $request->lat, $request->driver_id);
    
    // Update in DB periodically (every 60 seconds)
    if (time() % 6 == 0) {
        DriverLocation::updateOrCreate(
            ['driver_id' => $request->driver_id],
            ['lat' => $request->lat, 'lng' => $request->lng]
        );
    }
}
```

### 6.3 API Rate Limiting
```php
// app/Providers/RouteServiceProvider.php

protected function configureRateLimiting(): void
{
    // General API
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
    });

    // Auth endpoints
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    // Chat
    RateLimiter::for('chat', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id);
    });

    // Location updates
    RateLimiter::for('location', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()?->id);
    });
}
```

### 6.4 Security Headers
```php
// app/Http/Middleware/SecurityHeaders.php

public function handle($request, Closure $next) {
    $response = $next($request);

    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    return $response;
}
```

---

## Phase 7: Pre-Launch (Day 11-12)

### 7.1 Final Verification Checklist

#### Backend
- [ ] All tests passing
- [ ] PHPStan Level 3+
- [ ] No sensitive data in logs
- [ ] SSL certificate valid
- [ ] CORS restricted
- [ ] Rate limiting active
- [ ] Redis working
- [ ] Queue worker running
- [ ] Scheduler configured
- [ ] Backup strategy in place

#### User App
- [ ] Flutter analyze clean
- [ ] All translations complete (EN/ES/AR)
- [ ] Empty states on all screens
- [ ] Loading states on all async operations
- [ ] Error handling with retry
- [ ] Offline graceful degradation
- [ ] Accessibility labels added

#### Driver App
- [ ] Flutter analyze clean
- [ ] All translations complete (EN/ES/AR)
- [ ] Push notifications working
- [ ] Location tracking accurate
- [ ] Earnings calculations correct
- [ ] Trip preferences working

#### Admin Panel
- [ ] All CRUD operations working
- [ ] Analytics dashboard loading
- [ ] Export features working
- [ ] User management functional

### 7.2 Smoke Tests
```bash
#!/bin/bash
# scripts/smoke-test.sh

BASE_URL="https://dacatlon.store"

echo "Running smoke tests..."

# Health check
curl -f "$BASE_URL/api/health" || exit 1
echo "✓ Health check"

# Auth
curl -f -X POST "$BASE_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"test","pin":"123456"}' || exit 1
echo "✓ Auth"

# Categories
curl -f "$BASE_URL/api/customer/mart/categories" \
  -H "Authorization: Bearer $TOKEN" || exit 1
echo "✓ Categories"

# Zones
curl -f "$BASE_URL/api/customer/zones" \
  -H "Authorization: Bearer $TOKEN" || exit 1
echo "✓ Zones"

echo "All smoke tests passed!"
```

### 7.3 Soft Launch Preparation
- [ ] Set up monitoring (Sentry, logs)
- [ ] Prepare rollback plan
- [ ] Create runbook for common issues
- [ ] Train support staff
- [ ] Set up customer support channel
- [ ] Prepare launch announcement

---

## Phase 8: Launch

### 8.1 Go-Live Checklist

#### Day of Launch
- [ ] Verify all services green
- [ ] Monitor error rates
- [ ] Watch transaction volumes
- [ ] Enable real-time alerts
- [ ] Have on-call team ready

#### Post-Launch
- [ ] Monitor for 24 hours
- [ ] Collect initial feedback
- [ ] Fix critical bugs
- [ ] Deploy hotfixes if needed
- [ ] Publish release notes

### 8.2 Rollback Plan
```bash
# If issues detected:

# 1. Rollback code
cd /var/www/vito
git log --oneline -1  # Note current commit
git revert HEAD
git push

# 2. Or restore previous version
git checkout v1.0
composer install --no-dev
php artisan migrate --force

# 3. Database rollback (if needed)
php artisan migrate:rollback --step=1
```

---

## 11. Verification Checklists

### Backend Verification
```
□ Login with valid credentials returns 200 + token
□ Login with invalid credentials returns 401
□ PIN reset flow completes
□ Fare estimate returns calculated amount
□ Ride creation stores in database
□ Driver can accept ride
□ Status updates persist
□ Chat messages delivered
□ Push notifications sent
□ Webhook processes correctly
□ Rate limiting triggers at threshold
□ CORS blocks unauthorized origins
```

### User App Verification
```
□ Login screen loads
□ PIN entry works
□ Home screen shows map
□ Destination search works
□ Vehicle selection displays options
□ Booking confirmation shows summary
□ Payment completes
□ Trip tracking shows driver location
□ Chat with driver works
□ Order history displays
□ Settings save correctly
□ Notifications appear
□ Empty states display properly
□ Loading states show during requests
□ Error states allow retry
```

### Driver App Verification
```
□ Login works
□ Online toggle activates
□ Request card displays
□ Accept/decline works
□ Navigation opens maps
□ Status updates reflect correctly
□ Chat works
□ Earnings display correctly
□ Notifications appear
□ Background location tracking works
□ Offline mode queues actions
```

---

## Summary Statistics

| Phase | Tasks | Time |
|-------|-------|------|
| Phase 0: Critical | 8 | 1 day |
| Phase 1: Backend | 45 | 2 days |
| Phase 2: User App | 52 | 2 days |
| Phase 3: Driver App | 38 | 2 days |
| Phase 4: Admin | 15 | 1 day |
| Phase 5: Testing | 22 | 1 day |
| Phase 6: Performance | 18 | 1 day |
| Phase 7: Pre-Launch | 25 | 2 days |
| **Total** | **223** | **12 days** |

---

*Document Version: 1.0 | Created: 2026-07-03*
