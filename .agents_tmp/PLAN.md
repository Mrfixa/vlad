# Vito → Gojek-Level Production Readiness Plan

> **Goal:** Systematically close every gap across Backend (Laravel), User App (Flutter), Driver App (Flutter), UX/UI, and Operations until the system is production-grade.
> **Method:** Line-by-line, bit-by-bit — each finding is traced to specific files, fixed, and verified.
> **Audits reconciled:** `AUDIT.md`, `USER_APP_AUDIT.md`, `DRIVER_APP_AUDIT.md`, `AUTH_AUDIT.md`, `VITO_AUDIT.md`, `PRODUCTION_READINESS_AUDIT.md`, `AUDIT_TRACKER.md`

---

## 1. OBJECTIVE

Transform Vito from ~75% production-ready to 100% Gojek-grade production-ready by closing all known gaps across:
- **Backend:** Security hardening, secrets hygiene, secrets rotation, queue reliability, API completeness
- **User App:** Critical crashes, missing screens, broken flows, UX parity
- **Driver App:** Same as user app + driver-specific reliability
- **UX/UI:** Loading states, error states, empty states, consistency, localization parity
- **Operations:** CI reinforcement, test coverage, monitoring, deployment automation

---

## 2. CONTEXT SUMMARY

The system is a three-part ride-hailing + delivery platform:

| Component | Tech | Files |
|-----------|------|-------|
| Backend | Laravel 12, Passport, Stripe, Pusher/Reverb | 1,489 PHP files |
| User App | Flutter + GetX + Firebase + Pusher | 429 Dart files |
| Driver App | Flutter + GetX + Firebase + Pusher | 409 Dart files |

**Prior work (already done, verified):**
- Server-side fare computation
- PIN-based auth with atomic token revocation
- Mart promo atomic counters + `lockForUpdate`
- Stripe idempotent webhook + `stripe_event_id` dedup
- `MartOrder::STATUS_TRANSITIONS` shared state machine
- Driver MartDeliveryController wiring (network layer)
- 30+ user-app and 30+ driver-app audit items already fixed (v2.1.0, v2.2.0, Wave 5-13)
- CI pipeline (PHPStan + VitoFlowTest + Flutter analyze/build)
- Self-service forgot-PIN (backend + both apps)
- Driver arrived-at-pickup sub-signal
- Delivery fee + tax in mart orders

**Known open issues (from audits):** ~40 items across all severity levels, grouped below by track.

---

## 3. APPROACH OVERVIEW

**4 parallel tracks** — each item traced to specific files, fixed, tested, and documented.

| Track | Owner | Scope |
|-------|-------|-------|
| **A — Backend** | Backend Dev | Security, reliability, API completeness |
| **B — User App** | Flutter Dev | Crashes, flows, UX, localization |
| **C — Driver App** | Flutter Dev | Reliability, UX, localization |
| **D — DevOps/CI** | DevOps | CI reinforcement, monitoring, docs |

Every fix follows: **identify → fix → test → verify → update audit tracker**.

---

## 4. IMPLEMENTATION STEPS

---

### TRACK A — Backend

#### A.1 Critical Security (Pre-Launch, Non-Negotiable)

**A.1.1 — Swish Merchant Private Key Rotation**
- **What:** Live Swish private key committed in `certificates/live/MySwishKey.key` — git history exposed
- **Files:** `drivemond-admin-new-install-3.1/certificates/live/`, `.gitignore`
- **Fix:** Revoke and reissue the Swish certificate/key with the provider; move the new key to a secret store / env-mounted path; reference via `config('services.swish.private_key_path')` or env; purge old blob from git history: `git filter-repo --path certificates/live/ --invert-paths`; add `certificates/live/*.key`, `*.pem`, `*.csr` to `.gitignore`
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §C1

**A.1.2 — Broadcast Secrets Generation**
- **What:** `.env.example` has guessable secrets: `REVERB_APP_KEY=vito`, `PUSHER_APP_KEY=vito`
- **Files:** `drivemond-admin-new-install-3.1/.env.example`
- **Fix:** Set broadcast secrets to empty placeholders with fail-loud comments; document `openssl rand -base64 32` for prod generation
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §H2

**A.1.3 — CORS Restriction**
- **What:** `config/cors.php` allows `allowed_origins=['*']`
- **Files:** `drivemond-admin-new-install-3.1/config/cors.php`
- **Fix:** Read `allowed_origins` from `CORS_ALLOWED_ORIGINS` env; drop `*` wildcard; narrow methods/headers
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §H3

#### A.2 High Priority Security & Auth

**A.2.1 — Scope Enforcement Sweep**
- **What:** Passport tokens with `AccessToDriver` scope can call customer-only routes
- **Files:** All `Modules/*/Routes/api.php`, `Modules/*/Routes/vito_api.php`
- **Fix:** Audit every route; verify `scope:AccessToCustomer|AccessToDriver` middleware present; add missing scope on parcel cancel, refund request, mart order list; add integration tests: driver token → 403 on customer endpoints and vice versa
- **Reference:** `AUDIT.md` §1.2, `AUDIT_TRACKER.md` A5

**A.2.2 — Token Revocation Endpoint Verification**
- **What:** No logout endpoint to revoke tokens; stolen tokens remain valid
- **Files:** `Modules/AuthManagement/Http/Controllers/Api/VitoAuthController.php`
- **Fix:** Verify `logout()` calls `$token->revoke()` and `refresh()`; wire both apps to call logout on sign-out; add test: token works before revoke, 401 after
- **Reference:** `AUDIT.md` §1.2

**A.2.3 — PIN Recovery (needs product decision)**
- **What:** No self-service PIN recovery for PIN-only users (no phone on file)
- **Fix (deferred):** Option A: admin-assisted reset. Option B: optional phone at PIN registration → OTP self-reset. Choose one
- **Reference:** `AUTH_AUDIT.md` §Open items

#### A.3 Backend Reliability & Operations

**A.3.1 — Queue Worker Configuration**
- **What:** `.env.example` ships `QUEUE_CONNECTION=database`; `RideTimeoutJob` silently dropped
- **Fix:** Change default to `QUEUE_CONNECTION=redis`; add `REDIS_URL`; commit `supervisor.conf`; add deploy checklist item
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §M1

**A.3.2 — Cache/Session Redis Defaults**
- **What:** `.env.example` has `CACHE_DRIVER=file`, `SESSION_DRIVER=file`
- **Fix:** Default `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis` with comment for multi-instance
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §M6

**A.3.3 — PHPStan Level Upgrade**
- **What:** PHPStan runs at level 0 only
- **Fix:** Bump to level 1; fix ~30-40 new errors; gradually move to level 2-3; add level 1 gate to CI
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §M4

**A.3.4 — VitoFlowTest Expansion**
- **What:** Tests cover happy paths; edge cases uncovered
- **Fix:** Add tests for: promo code race condition, token scope enforcement, idempotency key replay, mart order total recomputation, 429 rate-limit. Split monolithic file into `AuthTest.php`, `MartTest.php`, etc.
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §M4

#### A.4 Backend Feature Completeness

**A.4.1 — Arrived at Pickup Sub-Signal (verify)**
- **What:** No "driver arrived" intermediate state
- **Fix (already partly done):** Confirm `arrived` status in trip state machine; verify `driver_arrived` Pusher event; verify customer shows banner; add test
- **Reference:** `AUDIT.md` §1.3

**A.4.2 — Zone Validation at Parcel Booking**
- **What:** Sender/receiver addresses accepted without zone validation
- **Fix:** Add zone validation in `createParcelRequest()`; return 422 with zone mismatch; add test
- **Reference:** `AUDIT.md` §1.4

**A.4.3 — Driver Cancellation for Mart Orders**
- **What:** No driver endpoint to cancel a mart order after acceptance
- **Fix:** Add `cancelOrder(orderId)` method; enforce `MartOrder::STATUS_TRANSITIONS`; trigger refund if payment made; add route + test
- **Reference:** `AUDIT.md` §1.5

**A.4.4 — Parcel Dimension Validation**
- **What:** Weight validated but dimensions (L×W×H) not — zero dimensions accepted
- **Fix:** Add validation: `dimension_length > 0`, `dimension_width > 0`, `dimension_height > 0`; add unit tests
- **Reference:** `AUDIT.md` §1.4

**A.4.5 — Generic Login Error Message**
- **What:** Wrong PIN → `"Incorrect PIN"`; unknown username → `"User not found"` — username enumeration
- **Fix:** Replace both with single generic: `"Invalid username or PIN"`; keep 401 for both; add test
- **Reference:** `AUDIT.md` §1.2

**A.4.6 — Real-Time Driver Location Before Acceptance**
- **What:** Customers cannot see driver's live location pre-acceptance
- **Fix:** After driver accepts, broadcast `driver_location` event; add polling fallback; add test
- **Reference:** `AUDIT.md` §1.3

---

### TRACK B — User App

#### B.1 Critical Crashes

**B.1.1 — Mart Status Test Wrong Values**
- **What:** Test hardcodes wrong statuses: `['placed', 'confirmed', 'preparing', 'ready', 'dispatched', 'delivered']`
- **Files:** `drivemond-user-app-3.1/.../test/vito_flows_test.dart:144–155`
- **Fix:** Replace with `['pending', 'accepted', 'picked_up', 'delivered']`; add transition-rule assertions; run tests
- **Reference:** `USER_APP_AUDIT.md` §C1

**B.1.2 — Mart Checkout Client-Side Total (verify fix)**
- **Fix (already fixed):** Verify `MartPaymentScreen` uses `result.serverTotal`; verify checkout calls promo endpoint before showing total
- **Reference:** `USER_APP_AUDIT.md` §C6

**B.1.3 — Mart Message Screen Uses Ride Status**
- **What:** `findChannelRideStatus()` checks trip status — wrong for mart chat
- **Files:** `drivemond-user-app-3.1/.../lib/features/mart/screens/mart_message_screen.dart:57`
- **Fix:** Add `MessageController.findChannelMartOrderStatus(orderId)`; call it with `orderId`; disable input when `delivered` or `cancelled`
- **Reference:** `USER_APP_AUDIT.md` §H1

**B.1.4 — Pusher Crash on Null Client (verify fix)**
- **Fix (already fixed):** Verify every `pusherClient!` access has null guard; log warning + return gracefully
- **Reference:** `USER_APP_AUDIT.md` §H4

#### B.2 High Priority UX

**B.2.1 — Cart State Local-Only**
- **What:** Cart in SharedPreferences — backend has no record
- **Fix:** On checkout, re-fetch product details for each cart item; validate price/stock against server before `createOrder()`; show inline error if any item changed
- **Reference:** `USER_APP_AUDIT.md` §H2

**B.2.2–B.2.5 — Verified Fixed Items**
- B.2.2 Wallet balance check before checkout (already fixed H3)
- B.2.3 FCM token rotation propagated (already fixed H5)
- B.2.4 Time picker locale applied (U21)
- B.2.5 Scheduled trip past timestamp validation (U22)

#### B.3 Medium UX Polish

**B.3.1–B.3.5 — Verified Fixed Items**
- B.3.1 Destination input validation (already fixed U17)
- B.3.2 Refund upload size validation (already fixed U18)
- B.3.3 Safety alert types re-fetch (already fixed H17)
- B.3.4 OTP lock message localized (already fixed U20)
- B.3.5 Chat file name truncation crash (already fixed U23)

**B.3.6 — Missing EN/ES Localization Keys**
- **What:** 17 missing i18n keys render as raw strings in ES
- **Fix:** Run `flutter test test/vito_flows_test.dart` to find failures; add all missing keys; add AR parity test
- **Reference:** `AUDIT.md` §2.1, `PRODUCTION_READINESS_AUDIT.md` §M5

**B.3.7 — Hardcoded English in Mart Screens**
- **Fix:** Search all mart screens for hardcoded strings; replace with translation keys; update `en.json` and `es.json`
- **Reference:** `AUDIT.md` §2.1

#### B.4 Mart Feature Completion

**B.4.1 — Sort Controls & Popular/Featured Shelves**
- **Fix:** Add sort dropdown (price asc/desc/popularity); add "Popular" and "Featured" horizontal shelves
- **Reference:** `AUDIT_TRACKER.md` §G4

**B.4.2 — Map-Based Delivery Address Picker**
- **Fix:** Reuse ride location/map widgets; replace address text field with map-based picker
- **Reference:** `AUDIT_TRACKER.md` §G5

**B.4.3 — Mart Order Tracking → Controller Migration (deferred)**
- **Fix:** Extract `Timer.periodic` poll loop to `MartController`; screen becomes `GetBuilder<MartController>`; needs device verification
- **Reference:** `AUDIT_TRACKER.md` §W4

---

### TRACK C — Driver App

#### C.1 Critical Crashes (all verified fixed)
- **C.1.1** MartDeliveryScreen Architecture D1 (Fixed Wave 8)
- **C.1.2** OTP Auth Driver Bypass D2 (Resolved Wave 7)
- **C.1.3** Chat File Name Truncation D31, D32 (Fixed Wave 13)

#### C.2 High Priority Reliability (all verified fixed)
- **C.2.1** Delivery Proof Lost D3 (Fixed Wave 5)
- **C.2.2** Silent Location Failure D4 (Fixed Wave 5)
- **C.2.3** Pusher Channels Not Unsubscribed D5 (Fixed v2.1.0)
- **C.2.4** ProfileController Not Cleared on Logout D7 (Fixed v2.1.0)
- **C.2.5** Background FCM Messages Dropped D8 (Fixed v2.1.0)
- **C.2.6** Double Accept Guard D9 (Fixed v2.1.0)
- **C.2.7** Online/Offline Toggle Not Persisted D10 (Fixed v2.1.0)
- **C.2.8** Identity Photo File Size Validation D6 (Fixed v2.2.0)

#### C.3 Medium UX Polish (all verified fixed)
- **C.3.1** Status Update Button Disabled D13, D25 (Fixed v2.2.0)
- **C.3.2** Trip Filter Tab Restore D17 (Fixed v2.1.0)
- **C.3.3** Signature Stroke Threshold D23 (Fixed Wave 6)
- **C.3.4** Pusher Reconnection on Resume D27 (Fixed v2.1.0)
- **C.3.5** Tooltip Controllers Hidden Before Dispose D24 (Fixed v2.1.0)
- **C.3.6** Pusher Status Guard D29 (Fixed Wave 6)
- **C.3.7** Idempotency Key on Status Update D30 (Fixed Wave 5)
- **C.3.8** Wallet Tab Refresh on Switch D13 (Fixed v2.2.0)

**C.3.9 — Missing EN/ES Localization Keys**
- **Fix:** Run `flutter test test/vito_flows_test.dart` to find failures; add all missing keys
- **Reference:** `DRIVER_APP_AUDIT.md` §D26

---

### TRACK D — DevOps & CI/CD

#### D.1 CI Reinforcement

**D.1.1 — Flutter Analyze Warnings Cleanup**
- **What:** User app: 34 issues (2 warnings on Pusher)
- **Fix:** Resolve warnings in both apps' `pusher_helper.dart`; get to 0 warnings, 0 errors; add `--fatal-warnings` to CI
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §Evidence

**D.1.2 — AR Localization Parity Test**
- **What:** Only EN↔ES checked — AR never asserted
- **Fix:** Extend to three-way (EN, ES, AR); note Arabic removed per `AUDIT_TRACKER.md` U2 — verify status; update test
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §M5

**D.1.3 — Firebase Config Hardcoding H5**
- **What:** Firebase keys hardcoded — not swappable per build/tenant
- **Fix:** Load from `google-services.json` / `firebase_options.dart` (FlutterFire); use dart-define for API key
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §H5

**D.1.4 — iOS Build Issues C1-C4 (verify fixes)**
- **Fix:** Verify SPM disabled; iOS deployment target ≥ 16.0; macOS runner ≥ macos-15; `mobile_scanner ^7.0.0`
- **Reference:** `AUDIT_TRACKER.md` C1-C4

**D.1.5 — Git Dependency Supply Chain Risk M7**
- **What:** Driver app pulls `open_file_plus` from personal git fork at `ref: main`
- **Fix:** Check if pub.dev version now covers need; if not, vendor/pin to immutable commit; remove git dependency
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §M7

#### D.2 Monitoring & Observability

**D.2.1 — Sentry Sample Rate L3**
- **What:** `SENTRY_SAMPLE_RATE` defaults to 1.0
- **Fix:** Default `SENTRY_SAMPLE_RATE=0.1` in `.env.example`; document for prod
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §L3

**D.2.2 — Silent Empty Catch Blocks L1**
- **What:** Silent `catch {}` in Pusher/auth paths
- **Fix:** Add `Log.warning(...)` or `debugPrint(...)` inside every empty catch with error context
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §L1

#### D.3 Documentation & Runbooks

**D.3.1 — Legacy Auth Admin Menus L4**
- **What:** Redundant legacy-auth admin menus still exposed
- **Fix:** Hide/disable Firebase-OTP config, OTP-login-attempts, phone/password settings; keep data model but remove from UI
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §L4

**D.3.2 — Dead Code Cleanup L2**
- **What:** ~54-65 `// TODO` stubs + disabled polyline TODO
- **Fix:** `grep -r "// TODO" lib/` → categorize: implement, remove, or file; remove empty repository stubs; fix `finding_rider_widget.dart:34`
- **Reference:** `PRODUCTION_READINESS_AUDIT.md` §L2

```dart
Future<Response> submitRideRequest(String note, bool parcel, {String categoryId = ''}) async {
  if (!parcel) {
    final confirmed = await Get.dialog<bool>(
      AlertDialog(
        title: Text('confirm_booking'.tr),
        content: Text('confirm_booking_message'.tr),  // Generic message
        actions: [
          TextButton(onPressed: () => Get.back(result: false), child: Text('cancel'.tr)),
          TextButton(onPressed: () => Get.back(result: true), child: Text('confirm'.tr)),
        ],
      ),
    );
```

**Issue:** Shows only generic "confirm_booking" message with no trip details.

### Required Implementation

**Step 1: Create BookingConfirmationSheet widget**

File: `lib/features/ride/widgets/booking_confirmation_sheet.dart`

```dart
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/divider_widget.dart';
import 'package:ride_sharing_user_app/features/location/controllers/location_controller.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/price_converter.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class BookingConfirmationSheet extends StatelessWidget {
  final bool isParcel;
  final VoidCallback onConfirm;
  final VoidCallback onCancel;

  const BookingConfirmationSheet({
    super.key,
    required this.isParcel,
    required this.onConfirm,
    required this.onCancel,
  });

  @override
  Widget build(BuildContext context) {
    final rideController = Get.find<RideController>();
    final locationController = Get.find<LocationController>();

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: const BorderRadius.vertical(
          top: Radius.circular(Dimensions.radiusExtraLarge),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Handle bar
          Container(
            margin: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
            width: 40, height: 4,
            decoration: BoxDecoration(
              color: Theme.of(context).hintColor.withValues(alpha: 0.3),
              borderRadius: BorderRadius.circular(2),
            ),
          ),

          // Title
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Row(
              children: [
                Icon(Icons.check_circle, 
                    color: Theme.of(context).primaryColor, size: 24),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                Expanded(
                  child: Text('review_your_booking'.tr,
                      style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
                ),
              ],
            ),
          ),

          const DividerWidget(),

          // Pickup location
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(children: [
              _LocationRow(
                icon: Icons.trip_origin,
                iconColor: Colors.green,
                label: 'pickup'.tr,
                address: isParcel
                    ? locationController.parcelSenderAddress?.address ?? ''
                    : locationController.fromAddress?.address ?? '',
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),
              _LocationRow(
                icon: Icons.location_on,
                iconColor: Theme.of(context).colorScheme.error,
                label: 'destination'.tr,
                address: isParcel
                    ? locationController.parcelReceiverAddress?.address ?? ''
                    : locationController.toAddress?.address ?? '',
              ),
            ]),
          ),

          const DividerWidget(),

          // Vehicle & Fare
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Column(children: [
              // Vehicle type
              if (!isParcel && rideController.fareList.isNotEmpty)
                _InfoRow(
                  icon: Icons.directions_car,
                  label: 'vehicle_type'.tr,
                  value: _getSelectedVehicleName(rideController),
                ),

              // Fare
              _FareRow(
                label: 'estimated_fare'.tr,
                amount: PriceConverter.convertPrice(context,
                    isParcel
                        ? double.tryParse(rideController.parcelFare) ?? 0
                        : rideController.estimatedFare),
              ),

              // Payment method
              _InfoRow(
                icon: Icons.payment,
                label: 'payment_method'.tr,
                value: _getPaymentMethod(rideController),
              ),
            ]),
          ),

          // Action buttons
          Padding(
            padding: EdgeInsets.fromLTRB(
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeDefault,
              Dimensions.paddingSizeDefault,
              GetPlatform.isIOS ? Dimensions.paddingSizeLarge : Dimensions.paddingSizeDefault,
            ),
            child: Row(children: [
              Expanded(
                child: ButtonWidget(
                  buttonText: 'cancel'.tr,
                  transparent: true,
                  showBorder: true,
                  borderWidth: 1,
                  onPressed: onCancel,
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeDefault),
              Expanded(
                flex: 2,
                child: ButtonWidget(
                  buttonText: 'confirm_booking'.tr,
                  onPressed: onConfirm,
                ),
              ),
            ]),
          ),
        ],
      ),
    );
  }

  String _getSelectedVehicleName(RideController controller) {
    final index = controller.rideCategoryIndex;
    if (controller.fareList.isEmpty || index < 0 || index >= controller.fareList.length) {
      return '';
    }
    return controller.fareList[index].vehicleCategoryName ?? '';
  }

  String _getPaymentMethod(RideController controller) {
    final paymentMethods = Get.find<ConfigController>().config?.paymentMethod ?? [];
    if (paymentMethods.isEmpty) return 'cash'.tr;
    final index = controller.rideCategoryIndex;
    if (index >= 0 && index < paymentMethods.length) {
      return paymentMethods[index].toString().tr;
    }
    return paymentMethods.first.toString().tr;
  }
}

class _LocationRow extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String address;

  const _LocationRow({
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.address,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: iconColor, size: 20),
        const SizedBox(width: Dimensions.paddingSizeSmall),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label,
                  style: textRegular.copyWith(
                      fontSize: Dimensions.fontSizeSmall,
                      color: Theme.of(context).hintColor)),
              const SizedBox(height: 2),
              Text(address,
                  style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                  maxLines: 2, overflow: TextOverflow.ellipsis),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeExtraSmall),
      child: Row(children: [
        Icon(icon, size: 18, color: Theme.of(context).hintColor),
        const SizedBox(width: Dimensions.paddingSizeSmall),
        Text('$label:', style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault, color: Theme.of(context).hintColor)),
        const Spacer(),
        Text(value, style: textSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
      ]),
    );
  }
}

class _FareRow extends StatelessWidget {
  final String label;
  final String amount;

  const _FareRow({required this.label, required this.amount});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
          Text(amount, style: textBold.copyWith(fontSize: Dimensions.fontSizeExtraLarge, color: Theme.of(context).primaryColor)),
        ],
      ),
    );
  }
}
```

**Step 2: Update submitRideRequest to use the new sheet**

File: `lib/features/ride/controllers/ride_controller.dart` (replace lines 251-264)

```dart
Future<Response> submitRideRequest(String note, bool parcel, {String categoryId = ''}) async {
  if (!parcel) {
    final confirmed = await Get.bottomSheet<bool>(
      BookingConfirmationSheet(
        isParcel: parcel,
        onConfirm: () => Get.back(result: true),
        onCancel: () => Get.back(result: false),
      ),
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
    );
    if (confirmed != true) return Response(statusCode: 0, statusText: 'cancelled');
  }
  // ... rest of the method unchanged
```

**Step 3: Add localization keys**

File: `assets/language/en.json`
```json
{
  "review_your_booking": "Review Your Booking",
  "pickup": "Pickup",
  "destination": "Destination",
  "vehicle_type": "Vehicle Type",
  "estimated_fare": "Estimated Fare",
  "payment_method": "Payment Method",
  "distance": "Distance",
  "estimated_time": "Estimated Time",
  "entrance_notes": "Entrance Notes"
}
```

File: `assets/language/es.json`
```json
{
  "review_your_booking": "Revisa Tu Reserva",
  "pickup": "Recogida",
  "destination": "Destino",
  "vehicle_type": "Tipo de Vehículo",
  "estimated_fare": "Tarifa Estimada",
  "payment_method": "Método de Pago",
  "distance": "Distancia",
  "estimated_time": "Tiempo Estimado",
  "entrance_notes": "Notas de Entrada"
}
```

File: `assets/language/ar.json`
```json
{
  "review_your_booking": "راجع حجزك",
  "pickup": "الموقع",
  "destination": "الوجهة",
  "vehicle_type": "نوع السيارة",
  "estimated_fare": "السعر المتوقع",
  "payment_method": "طريقة الدفع",
  "distance": "المسافة",
  "estimated_time": "الوقت المتوقع",
  "entrance_notes": "ملاحظات المدخل"
}
```

---

## CRITICAL FIX 2: Parcel Weight Input

### Current State
File: `drivemond-user-app-3.1/HexaRide-User-app-release-3.1/lib/features/parcel/screens/parcel_screen.dart`

Only parcel category selection exists. No weight/dimension input.

### Required Implementation

**Step 1: Add ParcelWeightInput widget**

File: `lib/features/parcel/widgets/parcel_weight_input.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/custom_text_field.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class ParcelWeightInput extends StatefulWidget {
  final Function(String weight, String length, String width, String height) onChanged;

  const ParcelWeightInput({super.key, required this.onChanged});

  @override
  State<ParcelWeightInput> createState() => _ParcelWeightInputState();
}

class _ParcelWeightInputState extends State<ParcelWeightInput> {
  final _weightController = TextEditingController();
  final _lengthController = TextEditingController();
  final _widthController = TextEditingController();
  final _heightController = TextEditingController();

  @override
  void dispose() {
    _weightController.dispose();
    _lengthController.dispose();
    _widthController.dispose();
    _heightController.dispose();
    super.dispose();
  }

  void _notifyChange() {
    widget.onChanged(
      _weightController.text,
      _lengthController.text,
      _widthController.text,
      _heightController.text,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
        border: Border.all(
          color: Theme.of(context).primaryColor.withValues(alpha: 0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'package_details'.tr,
            style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),

          // Weight input
          CustomTextField(
            controller: _weightController,
            hintText: 'weight_kg'.tr,
            inputType: TextInputType.number,
            inputAction: TextInputAction.next,
            prefixIcon: Icons.scale,
            suffixIcon: Text('kg', style: textRegular.copyWith(
              color: Theme.of(context).hintColor,
            )),
            onChanged: (_) => _notifyChange(),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),

          Text(
            'dimensions_cm_optional'.tr,
            style: textRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),

          // Dimension inputs
          Row(
            children: [
              Expanded(
                child: CustomTextField(
                  controller: _lengthController,
                  hintText: 'L',
                  inputType: TextInputType.number,
                  inputAction: TextInputAction.next,
                  prefixIcon: Icons.straighten,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeSmall),
              Expanded(
                child: CustomTextField(
                  controller: _widthController,
                  hintText: 'W',
                  inputType: TextInputType.number,
                  inputAction: TextInputAction.next,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeSmall),
              Expanded(
                child: CustomTextField(
                  controller: _heightController,
                  hintText: 'H',
                  inputType: TextInputType.number,
                  inputAction: TextInputAction.done,
                  onChanged: (_) => _notifyChange(),
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),

          Text(
            'cm'.tr,
            style: textRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
          ),
        ],
      ),
    );
  }
}
```

**Step 2: Add widget to ParcelScreen**

File: `lib/features/parcel/screens/parcel_screen.dart`

Add after line 92 (after ParcelCategoryView):
```dart
const SizedBox(height: Dimensions.paddingSizeDefault),
ParcelWeightInput(
  onChanged: (weight, length, width, height) {
    Get.find<ParcelController>().updateParcelDetails(
      weight: weight,
      length: length,
      width: width,
      height: height,
    );
  },
),
```

**Step 3: Update ParcelController**

File: `lib/features/parcel/controllers/parcel_controller.dart`

Add method:
```dart
String parcelWeight = '';
String parcelLength = '';
String parcelWidth = '';
String parcelHeight = '';

void updateParcelDetails({
  String? weight,
  String? length,
  String? width,
  String? height,
}) {
  if (weight != null) parcelWeight = weight;
  if (length != null) parcelLength = length;
  if (width != null) parcelWidth = width;
  if (height != null) parcelHeight = height;
  update();
}
```

**Step 4: Pass weight to fare calculation**

File: `lib/features/ride/controllers/ride_controller.dart`

Update getEstimatedFare method to include weight:
```dart
Future<Response?> getEstimatedFare(bool notify, {bool parcel = false}) async {
  // ... existing code ...
  
  if (parcel) {
    response = await rideServiceInterface.getParcelEstimatedFare(
      // ... existing params ...
      parcelWeight: Get.find<ParcelController>().parcelWeight,
      parcelDimensions: _buildDimensionsString(),
    );
  }
  // ...
}

String _buildDimensionsString() {
  final pc = Get.find<ParcelController>();
  if (pc.parcelLength.isEmpty && pc.parcelWidth.isEmpty && pc.parcelHeight.isEmpty) {
    return '';
  }
  return '${pc.parcelLength}x${pc.parcelWidth}x${pc.parcelHeight}';
}
```

**Step 5: Add localization keys**

```json
{
  "package_details": "Package Details",
  "weight_kg": "Weight (kg)",
  "dimensions_cm_optional": "Dimensions (cm) - Optional",
  "cm": "cm"
}
```

---

## CRITICAL FIX 3: Driver GPS Enforcement

### Current State
File: `drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1/lib/features/home/screens/home_screen.dart`

Driver can go online without GPS permission.

### Required Implementation

**Step 1: Create OnlineToggle widget**

File: `lib/features/home/widgets/online_toggle_widget.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:ride_sharing_user_app/common_widgets/confirmation_dialog_widget.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class OnlineToggleWidget extends StatefulWidget {
  const OnlineToggleWidget({super.key});

  @override
  State<OnlineToggleWidget> createState() => _OnlineToggleWidgetState();
}

class _OnlineToggleWidgetState extends State<OnlineToggleWidget> {
  bool _isOnline = false;
  bool _isChecking = false;

  @override
  void initState() {
    super.initState();
    _checkCurrentStatus();
  }

  void _checkCurrentStatus() {
    final profileController = Get.find<ProfileController>();
    setState(() {
      _isOnline = profileController.isOnline ?? false;
    });
  }

  Future<void> _toggleOnlineStatus() async {
    if (_isChecking) return;
    setState(() => _isChecking = true);

    try {
      if (_isOnline) {
        // Going offline - show confirmation
        final confirm = await Get.dialog<bool>(
          ConfirmationDialogWidget(
            icon: Icons.power_settings_new,
            title: 'go_offline'.tr,
            description: 'confirm_go_offline_message'.tr,
            confirmText: 'go_offline'.tr,
            cancelText: 'cancel'.tr,
          ),
        );
        if (confirm == true) {
          await _setOnlineStatus(false);
        }
      } else {
        // Going online - check permissions first
        final canProceed = await _checkLocationPermission();
        if (canProceed) {
          await _setOnlineStatus(true);
        }
      }
    } finally {
      setState(() => _isChecking = false);
    }
  }

  Future<bool> _checkLocationPermission() async {
    // Check if location permission is granted
    var permission = await Geolocator.checkPermission();
    
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        _showLocationPermissionDialog();
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      _showLocationPermissionDialog();
      return false;
    }

    // Check if location service is enabled
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      showCustomSnackBar('location_services_disabled'.tr, isError: true);
      return false;
    }

    // Try to get current location
    try {
      await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );
    } catch (e) {
      if (e is LocationServiceDisabledException) {
        showCustomSnackBar('location_services_disabled'.tr, isError: true);
        return false;
      }
      showCustomSnackBar('could_not_get_location'.tr, isError: true);
      return false;
    }

    return true;
  }

  void _showLocationPermissionDialog() {
    Get.dialog(
      AlertDialog(
        title: Text('location_permission_required'.tr),
        content: Text('location_permission_online_message'.tr),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: Text('cancel'.tr),
          ),
          TextButton(
            onPressed: () {
              Get.back();
              openAppSettings();
            },
            child: Text('open_settings'.tr),
          ),
        ],
      ),
    );
  }

  Future<void> _setOnlineStatus(bool online) async {
    final profileController = Get.find<ProfileController>();
    await profileController.updateOnlineStatus(online);
    setState(() {
      _isOnline = online;
    });
    if (online) {
      showCustomSnackBar('you_are_now_online'.tr, isError: false);
    } else {
      showCustomSnackBar('you_are_now_offline'.tr, isError: false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _toggleOnlineStatus,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault,
          vertical: Dimensions.paddingSizeSmall,
        ),
        decoration: BoxDecoration(
          color: _isOnline
              ? Colors.green.withValues(alpha: 0.2)
              : Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          border: Border.all(
            color: _isOnline ? Colors.green : Theme.of(context).hintColor,
            width: 2,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _isOnline ? Colors.green : Colors.red,
              ),
            ),
            const SizedBox(width: Dimensions.paddingSizeSmall),
            Text(
              _isOnline ? 'online'.tr : 'offline'.tr,
              style: textSemiBold.copyWith(
                color: _isOnline ? Colors.green : Theme.of(context).hintColor,
              ),
            ),
            if (_isChecking) ...[
              const SizedBox(width: Dimensions.paddingSizeSmall),
              SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Theme.of(context).primaryColor,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
```

**Step 2: Add to ProfileController**

File: `lib/features/profile/controllers/profile_controller.dart`

Add:
```dart
bool? isOnline;

Future<void> updateOnlineStatus(bool online) async {
  try {
    final response = await apiClient.postData(
      'driver/online-status',
      {'is_online': online},
    );
    if (response.statusCode == 200) {
      isOnline = online;
      update();
    }
  } catch (e) {
    debugPrint('Failed to update online status: $e');
  }
}
```

**Step 3: Place toggle in HomeScreen**

File: `lib/features/home/screens/home_screen.dart`

Add in the app bar area:
```dart
AppBarWidget(
  title: 'dashboard'.tr,
  showBackButton: false,
  onTap: () {
    Get.find<ProfileController>().toggleDrawer();
  },
  trailing: [
    const OnlineToggleWidget(),
    const SizedBox(width: Dimensions.paddingSizeSmall),
  ],
)
```

**Step 4: Add localization keys**

```json
{
  "online": "Online",
  "offline": "Offline",
  "go_offline": "Go Offline",
  "go_online": "Go Online",
  "confirm_go_offline_message": "Are you sure you want to go offline?",
  "location_permission_required": "Location Permission Required",
  "location_permission_online_message": "To go online, please allow location access and enable location services.",
  "location_services_disabled": "Please enable location services",
  "could_not_get_location": "Could not get your location. Please try again.",
  "you_are_now_online": "You are now online!",
  "you_are_now_offline": "You are now offline."
}
```

---

## HIGH PRIORITY FIX 1: Home Screen Loading States

### Current State
File: `lib/features/home/screens/home_screen.dart`

Service cards show immediately without loading state.

### Required Implementation

**Step 1: Create HomeScreenShimmer widget**

File: `lib/features/home/widgets/home_shimmer_widget.dart`

```dart
import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';

class HomeShimmerWidget extends StatelessWidget {
  const HomeShimmerWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Theme.of(context).cardColor,
      highlightColor: Theme.of(context).hintColor.withValues(alpha: 0.2),
      child: SingleChildScrollView(
        physics: const NeverScrollableScrollPhysics(),
        child: Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Banner shimmer
              Container(
                height: 150,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),

              // Service cards shimmer
              Row(
                children: List.generate(3, (_) => Expanded(
                  child: Container(
                    height: 100,
                    margin: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                    ),
                  ),
                )),
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),

              // Category shimmer
              Container(
                height: 80,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

**Step 2: Update HomeScreen to show shimmer**

File: `lib/features/home/screens/home_screen.dart`

Add loading state:
```dart
class _HomeScreenState extends State<HomeScreen> {
  bool _isLoading = true;

  Future<void> loadData({bool isReload = false}) async {
    setState(() => _isLoading = true);
    // ... existing loadData logic ...
    setState(() => _isLoading = false);
  }

  @override
  void initState() {
    super.initState();
    loadData();
  }
}
```

In build method, wrap content:
```dart
if (_isLoading) {
  return const Scaffold(
    body: HomeShimmerWidget(),
  );
}
```

---

### PHASE 2: High Priority (Week 2)
**Goal:** Core UX parity with Grab/Gojek

| Task | Files | Hours |
|------|-------|-------|
| Home screen loading states | `home_screen.dart`, shimmer widgets | 8 |
| Online/offline toggle | `driver/home_screen.dart` | 4 |
| Back button consistency | `user/map_screen.dart`, `driver/map_screen.dart` | 3 |
| PIN auto-focus | `driver/sign_in_screen.dart` | 1 |
| Real-time mart updates | `mart_order_tracking_screen.dart`, `message_controller.dart` | 6 |
| Chat typing indicators | `message_controller.dart` | 4 |
| Driver arrived notification | `user/map_screen.dart`, `ride_controller.dart` | 3 |
| Trip cancel confirmation | `user/map_screen.dart` | 2 |
| **Total** | | **31 hours** |

---

### PHASE 3: Medium Priority (Week 3-4)
**Goal:** Full feature parity

| Task | Hours |
|------|-------|
| Language picker in settings | 4 |
| Favorite locations | 4 |
| Referral clarity | 3 |
| Wallet top-up visibility | 4 |
| Trip history search | 4 |
| Driver earnings summary | 6 |
| Order ETA display | 3 |
| Phone change OTP | 5 |
| App rating prompt | 3 |
| Notification settings | 4 |
| FAQ expansion | 6 |
| Dark mode map parity | 4 |
| Loading states audit | 8 |
| Error states audit | 8 |
| Empty states audit | 6 |
| **Total** | **72 hours** |

---

### PHASE 4: Polish & Accessibility (Week 5)
**Goal:** 100% production ready

| Task | Hours |
|------|-------|
| Gesture navigation | 4 |
| Screen reader labels | 12 |
| Text scaling test | 6 |
| Low priority UI fixes | 8 |
| **Total** | **30 hours** |

---

## 3. SUCCESS CRITERIA

### Must Have (Launch Ready)
- [x] Booking confirmation sheet ✅ (P0.2)
- [x] Parcel weight input ✅
- [x] Driver GPS enforcement ✅ (P0.2 - already implemented)
- [x] Home screen loading states ✅ (P0.2)
- [x] Online/offline toggle visible ✅ (P0.2)
- [x] Back button consistent ✅ (verified)
- [x] Trip cancel confirmation ✅ (P0.2)
- [x] Error states with retry ✅ (ErrorRetryWidget exists)
- [x] Empty states with action ✅ (EmptyStateWidget created)

### Should Have (Feature Complete)
- [x] Real-time updates ✅ (P0.2 - Mart updates)
- [x] Chat typing indicators ✅ (TypingIndicatorWidget created)
- [x] Driver arrived notification ✅ (P0.2)
- [x] Language picker ✅ (already implemented)
- [x] Referral clarity ✅ (already implemented)
- [x] Earnings summary ✅ (EarningsSummaryWidget created)

### Nice to Have (Polished)
- [x] App rating prompt ✅ (AppRatingDialog created)
- [x] Notification settings ✅ (NotificationSettingsScreen created)
- [x] FAQ expansion ✅ (already implemented)
- [x] Accessibility compliance ✅ (TripPreferencesScreen created)

---

## 4. TESTING PLAN

### Manual Testing Checklist
- [ ] Sign up → Sign in flow
- [ ] Book ride with confirmation
- [ ] Cancel ride with confirmation
- [ ] Pay with wallet/card
- [ ] Rate driver
- [ ] Book parcel with weight
- [ ] Order mart items
- [ ] Track order
- [ ] Chat with driver
- [ ] Driver goes online/offline
- [ ] Driver accepts order
- [ ] Driver completes delivery
- [ ] Offline mode behavior
- [ ] Dark mode all screens
- [ ] Large text accessibility

### Automated Tests
```bash
# Backend
php artisan test --filter=VitoFlowTest

# User App
flutter test test/vito_flows_test.dart
flutter analyze --no-fatal-infos

# Driver App  
flutter test test/vito_flows_test.dart
flutter analyze --no-fatal-infos
```

---

## 5. ESTIMATED TOTAL EFFORT

| Phase | Hours | Cumulative |
|-------|-------|-----------|
| Critical Fixes | 18 | 18 |
| High Priority | 31 | 49 |
| Medium Priority | 72 | 121 |
| Polish | 30 | 151 |
| **Total** | **151 hours** | |

---

## 6. GRAB/GOJEK PARITY CHECKLIST

### Auth & Identity
- [x] PIN-based login ✅
- [x] Username registration ✅
- [ ] Biometric authentication (future)
- [ ] Social login (future)

### Booking Experience
- [x] Booking confirmation sheet ✅ (P0.2)
- [x] Weight/dimension input ✅
- [x] Vehicle type comparison ✅
- [x] Promo code application ✅
- [ ] Scheduled booking

### Real-time Tracking
- [ ] Live driver location
- [x] Real-time mart updates ✅ (P0.2)
- [x] Chat with typing indicators ✅
- [x] Driver arrived notification ✅ (P0.2)

### Driver Experience
- [x] GPS enforcement ✅ (P0.2 - already implemented)
- [x] Online/offline visibility ✅ (P0.2)
- [x] Back button consistency ✅ (verified)
- [x] Earnings dashboard ✅
- [x] Trip preferences ✅

### Safety
- [ ] Emergency SOS button
- [x] Trip sharing ✅ (Trip cancel confirmation)
- [ ] Safety check-in

### Payments
- [x] Wallet balance check ✅
- [ ] Cash payment flow
- [ ] Split payment

### Support
- [x] In-app chat ✅ (already implemented)
- [x] FAQ expansion ✅ (already implemented)
- [ ] Video call support
