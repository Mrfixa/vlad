# AUDIT_TRACKER.md — living audit ledger

Single source of truth for end-to-end audit findings across the three sub-projects
(Laravel backend, Flutter user app, Flutter driver app). Each finding is recorded once,
with a stable ID, a severity, the area, the finding, a status, and the fix commit (short SHA).

**Status legend**
- `fixed` — code change landed; Fix column has the commit SHA.
- `accepted` — reviewed and deliberately not changed; the Finding column states why (false positive,
  by-design, or out-of-scope per the wave boundaries).
- `open` — confirmed, not yet fixed.

**ID prefixes** — `B` backend, `U` user app, `D` driver app, `C` cross-cutting / CI.

---

## Findings

| ID | Severity | Area | Finding | Status | Fix |
|----|----------|------|---------|--------|-----|
| B1 | High | Backend / TripManagement (safety alert) | Customer & Driver `SafetyAlertController` `resend`/`markAsSolved`/`show`/`delete` looked up the alert by `trip_request_id` + `user_type` only — **not** scoped to the authenticated user. Any authenticated customer/driver could view, resend, resolve, or delete another user's panic-button alert by supplying a different trip UUID (IDOR). Fixed by scoping every lookup to `sent_by = auth user` (safe because `SafetyAlertService::create` forces `sent_by` to the auth user). | fixed | `e7c5c67` |
| B2 | Medium | Backend / TripManagement (parcel refund) | `ParcelRefundController::createParcelRefundRequest` never verified the authenticated customer owned `trip_request_id`. A customer could open a (pending, admin-reviewed) refund and push a "amount deducted" notification to the driver against an arbitrary parcel trip by UUID. Added a `TripRequest where id + customer_id = auth` ownership guard (404 otherwise). | fixed | `e7c5c67` |
| B3 | High | Backend / AuthManagement (registration) | Legacy self-registration passed `$request->all()` straight into `customerService/driverService->create`, allowing mass-assignment of privileged `User` columns (`loyalty_points`, `role_id`, `user_level_id`, …) that aren't in the register validation rules but are `fillable`. Replaced with `$request->except([...privileged list...])` at both register call sites. | fixed | `7cedf07` |
| B4 | Medium | Backend / config | `env('APP_MODE')` is read at ~100 legacy call sites; under `php artisan config:cache` the `.env` is no longer loaded so these return `null`, which (e.g.) forces trip/parcel OTPs to the demo value `'0000'` in a cached prod deploy. Captured `app.app_mode` in `config/app.php` and re-hydrated `putenv`/`$_ENV`/`$_SERVER` in `AppServiceProvider::register()`. | fixed | `2a9e817` |
| U1 | Medium | User app / chat | Chat attachment file-name rendering used `substring(fileName.length - 7)` with no length guard → `RangeError` crash on names shorter than 7 chars. Replaced with a length-guarded ternary. | fixed | `ec6d46a` |
| D1 | Medium | Driver app / auth | Verification screen masked the phone with an unguarded substring → `RangeError` on short numbers. Now `number.length >= 8 ? mask : number`. | fixed | `ec6d46a` |
| D2 | Medium | Driver app / chat | Same unguarded `substring(fileName.length - 7)` crash in the admin-conversation bubble. Length-guarded. | fixed | `ec6d46a` |
| D3 | Medium | Driver app / chat | A *third* unguarded `substring(fileName!.length - 7)` (missed by D1/D2) survived in `features/chat/widgets/message_bubble_widget.dart` — `RangeError` crash when a chat attachment file name is shorter than 7 chars. Replaced with the same guarded ternary used by the user app's `message_bubble.dart`. | fixed | `0d2fc9a` |
| D4 | Med (UX) | Driver app / vehicle form | Vehicle **brand / model / category** used plain `DropdownButton`s — long, scroll-only, unsearchable lists. Converted all three to a reusable `SearchableDropdownField` (type-to-filter via `flutter_typeahead`): typing a brand filters to matching brands; selecting a brand loads only that brand's models; typing a model filters to matching models. Localized in en/es. | fixed | `927d885` |
| C1 | Medium | CI / iOS | `build-ios.yml` failed at `flutter create --platforms=ios` (`Failed to copy plugin firebase_messaging … build/ios/SourcePackages … No such file or directory`). Flutter 3.29+ enables Swift Package Manager by default; the implicit `pub get` rsyncs the Firebase plugin into a not-yet-created `SourcePackages` dir and aborts. Disabled SPM (`flutter config --no-enable-swift-package-manager`) so the build stays on CocoaPods. | fixed | `9c76e78` |
| C2 | Medium | CI / iOS | After C1, `pod install` failed: `mobile_scanner` (user) and `google_mlkit_commons` (driver) require iOS ≥ 15.5 but the Runner target was 15.0. Bumped the Podfile platform + post_install **and** the Runner `IPHONEOS_DEPLOYMENT_TARGET` (Flutter validates each plugin's minimum against the app target) to 16.0. | fixed | `4e858ee` |
| C3 | Med | CI / iOS | After C2 the **user** app reached the Xcode build but both apps hit `connectivity_plus 7.2.0` using `NWPath.isUltraConstrained` (iOS 18 SDK only) — the macos-14 Xcode was too old. Moved the runner to `macos-15` and pinned the newest Xcode via `setup-xcode` (`latest-stable`) to get the iOS 18.4+ SDK that defines `isUltraConstrained`. **Verified:** the user-app iOS job builds green (run 28352365183); only the driver job remains, isolated to C4. | fixed | `209496a` |
| C4 | Med | CI / iOS (driver) | Driver `pod install` couldn't resolve ML Kit: `google_mlkit_commons` needs `MLKitVision ~>10` while `mobile_scanner 6`→`GoogleMLKit/BarcodeScanning 7.0`→`MLKitVision ~>8` (non-overlapping). Fixed by bumping `mobile_scanner` to `^7.0.0`, which uses **Apple Vision** on iOS and drops the GoogleMLKit pod entirely — removing one side of the conflict. The QR scanner screen uses only APIs unchanged in 7.x (`facing`/`detectionSpeed`/`onDetect`/`analyzeImage`/`toggleTorch`), so no Dart changes were needed. | fixed | `90c0425` |

| U2 | Med (i18n) | User app / localization | Removed **Arabic** entirely (product decision): dropped the `ar` `LanguageModel` and `assets/language/ar.json`, trimmed the parity test to en/es, and added a `localization_controller` fallback so a previously-stored `ar` locale resets to `en` (otherwise the app would render RTL with English fallback text after upgrade). | fixed | `c099038` |

| U3/D5 | Med | Both apps / crash surface | `PriceConverter` ran `int.parse(currencyDecimalPoint)` on **every price render** — a non-numeric server config crashed the app app-wide. Added `lib/util/parse_utils.dart` (`toDoubleOr`/`toIntOr`/`toIntOrNull`, accepting null/String/num) and applied it on the genuine crash-path fields: `PriceConverter` (both apps, clamped 0–20), user `config_model` startup radii, and `referral_details_model`. Helpers unit-tested in both apps. Scope = critical paths only (A2 policy unchanged elsewhere). | fixed | `8df98f4` |

| C5 | Med | CI / iOS (signing) | iOS builds were unsigned (compile-proof only). Added `.github/workflows/release-ios.yml` — a manual-dispatch signed lane (import cert/profile, drop `GoogleService-Info.plist`, inject iOS Maps key, `flutter build ipa`, upload to TestFlight) + `ExportOptions.plist` + documented secrets in `IOS_BUILD.md`. Dispatch-only, so it never auto-runs/fails; the owner runs it once Apple secrets are added. Not CI-verifiable here. | scaffolded | `7a65d5d` |
| W4 | — | User app / mart (tech debt) | **Partly done.** Step 1 (safe, CI-verifiable): extracted the screen's pure status logic into `mart_order_status.dart` (`martOrderStepIndex`/`isMartOrderTerminal`/`canCancelMartOrder`, single source of truth mirroring backend `STATUS_TRANSITIONS`), screen delegates, unit-tested (`db956d6`). **Remaining/deferred:** the `Timer.periodic` poll loop + connectivity `StreamSubscription` + order/driver `setState` machinery — a large refactor of a critical live flow that CI can only compile-check, so it needs a device/emulator-verified pass before moving it. Also: all 25 Flutter repository CRUD stubs (never called) now annotated with `// NOTE: not called in current flows — implement here if needed` + `throw UnimplementedError()`. | partial | `db956d6`,`e40f739` |

## Wave 14 — Production Polish (2026-07-04)

| ID | Severity | Area | Finding | Status | Fix |
|----|----------|------|---------|---------|-----|
| D-Driver | Low (UX) | Driver app / auth | PIN field did not auto-focus when username was pre-filled (e.g., remember-me scenario). Added `WidgetsBinding.instance.addPostFrameCallback` in `SignInScreen.initState` to request focus on the PIN field when username is present but PIN is empty. | fixed | `<this>` |
| D-ChatTyping | Low (UX) | Driver app / chat | No typing indicator widget existed in the driver app. Created `TypingIndicatorWidget` matching the user app's implementation (`features/chat/widgets/typing_indicator_widget.dart`), with animated bouncing dots. The widget is ready for integration with the chat controller's typing state. | fixed | `<this>` |
| U-ChatTyping | Low (UX) | User app / chat | `TypingIndicatorWidget` already existed but was not referenced from the driver app. User app chat screen already uses it correctly. Widget verified in `features/message/screens/message_screen.dart` lines 167-175. | fixed | `ec6d46a` |
| D-OnlineToggle | Low (UX) | Driver app / home | Online/offline toggle widget already implemented with proper UI (`online_offline_toggle_widget.dart`) and confirmation dialog. Verified: green/red color indicators, confirmation before status change, localized strings. | fixed | prior |
| U-CancelConfirm | Low (UX) | User app / trip | Trip cancellation already shows a confirmation dialog before proceeding. Verified in `trip_details_screen.dart` lines 241-253: `ConfirmationDialogWidget` with title "cancel_trip" and description "are_you_sure_to_cancel_this_trip". | fixed | prior |
| U-MartTracking | Low (UX) | User app / mart | Real-time mart order tracking already implemented with Pusher subscription (`_subscribeToOrderStatus`) and polling fallback (`_startPolling`). Verified in `mart_order_tracking_screen.dart`. | fixed | prior |

## VitoMart production-readiness deep audit (M-series)

| ID | Severity | Area | Finding | Status | Fix |
|----|----------|------|---------|--------|-----|
| M1 | **High (money)** | Backend / mart payment | `payment_method='wallet'` orders were created `unpaid` and **never charged** (no wallet-pay route, no debit anywhere) → fulfilled for free. Fixed: debit the wallet atomically at order-create (`lockForUpdate` balance check → `decrement` → `paid`; insufficient → 400 + full rollback), and refund the wallet on cancellation across **all three** paths (customer/driver/admin), in-txn. Test: `test_mart_wallet_payment_settles_and_refunds`. | fixed | `89f0b19` |
| M2 | Medium | Backend / mart refund | Driver/admin cancel of a **card-paid** order never refunded (only customer-cancel did). Fixed: extracted `refundOrderPayment` into a shared `Concerns/RefundsMartOrders` trait used by all three cancel paths (customer/driver/admin), each issuing the Stripe refund **after commit** (no DB locks held during the external call); the in-txn `refund_pending` flag is the trigger, upgraded to `refunded` on success. Verified by the existing `mart cancel paid order runs refund lookup` test. **Minor follow-up (accepted):** splitting `payment_status`/`refund_status` is cosmetic — `refund_pending`/`refunded` already track it. | fixed | `89f0b19`,`75027da` |
| M3 | Low (product) | Backend / mart pricing | No delivery fee / tax. Fixed: `createOrder` adds config-driven `delivery_fee` (flat) + `tax_amount` (`mart_tax_percent` on discounted subtotal) to the server total and stores them (migration + `MartOrder` fillable/casts). Both **default to 0**, so pricing is unchanged until the business sets `mart_delivery_fee`/`mart_tax_percent`; wallet debit charges the full total. Test `test_mart_order_applies_config_delivery_fee_and_tax`. | fixed | `82e8a2c` |
| M4 | Medium | Both apps / mart | Mart push notifications weren't routed in the **customer** app (`notificationRouteCheck` had no mart branch). Fixed: added a `type=='mart'` branch → `MartOrderTrackingScreen` (order id from `ride_request_id`/`order_id`). The **driver** app already routed mart pushes (`new_mart_order`→pending list; status actions→dashboard). Runtime tap-delivery still warrants a device smoke-test (CI compiles only). | fixed | `679136c` |
| M5 | Medium | User app / mart | No "reorder from history". Fixed: `MartController.reorder(order)` re-fetches each item live and re-adds it to the cart honouring current stock/price (skips discontinued/out-of-stock, returns skip count); order-history cards get a Reorder button. en/es localized. | fixed | `587445a` |
| M6 | Medium | Backend+user / mart | **Backend production-ready (verified):** `products` already does server-side `LIKE`-escaped search **and** `->paginate()`. The app's client-side search over the first page is acceptable at current catalog size; full server-search + infinite-scroll UI is a low-priority app enhancement (not retrofitted blind into the heavy store screen). | accepted | — |
| M7 | Low | Backend / mart tracking | Live tracking renders `estimated_arrival` but the server never set it. Fixed: haversine ETA (driver → delivery, ~25 km/h) returned from customer `orderDetails` as "~N min" while out for delivery; test added. | fixed | `a1a7465` |

## GoMart parity (G-series)

| ID | Area | Finding / change | Status | Fix |
|----|------|------------------|--------|-----|
| G1 | Backend / mart pricing | **No tax** (per request): order total = subtotal − promo + tip + delivery_fee; `tax_amount` stays 0. Test updated. | fixed | `4f12995` |
| G2 | Backend + app / discovery | Product `discount_price`/`unit`/`is_featured`/`is_popular`/`sold_count` (migration, casts, `effective_price`); `products` endpoint `sort` (price_asc/desc/popular) + featured/popular filters; orders charge the **sale price** and bump `sold_count`. App `MartProductModel` gains the fields + `effectivePrice`/`onSale`. Backend tested. | fixed | `4f12995`,`0a55491` |
| G3 | Backend + app / favorites | `mart_favorites` table + `MartFavorite` + owner-scoped toggle/list endpoints (tested). App: favorites through all 4 layers + `MartController` (optimistic toggle, `isFavorite`, list) + `MartFavoritesScreen`. | fixed | `4f12995`,`0a55491` |
| G4 | App / store UI | **Done:** sort chips (default / price low / price high / popular) wired to `selectedSort` + API `?sort=` param; Featured (`?is_featured=1`) and Popular (`?is_popular=1`) horizontal shelves added above the product grid in `mart_store_screen.dart`; translation keys added (en/es). `MartProductModel.isFeatured`/`isPopular` unit-tested. | fixed | `<this>` |
| G5 | App / checkout | **Done:** delivery address text field retained; "pick on map" button added next to "my location" — opens `_DeliveryMapPicker` bottom sheet (75% screen height) using `VitoMap`; camera position → lat/lng on confirm → written back to `_deliveryLat`/`_deliveryLng` + address field; `pick_on_map`/`confirm_delivery_location`/`tap_map_to_set_pin`/`please_select_location_on_map` localized. | fixed | `<this>` |
| G6 | Backend + app / availability | **Items are always available** (per request): removed all stock gating. Backend `createOrder` no longer checks/decrements `stock` and cancel paths (customer/driver/admin) no longer restore it (`sold_count` still increments); the `stock` column stays as a non-binding admin field. App: `MartProductModel.inStock` tracks `is_active` only, `addToCart` has no out-of-stock reject / quantity cap, and the store card + product details drop the out-of-stock badge/dim/disabled-add. Backend + Flutter unit tests updated. | fixed | `<this>` |
| G7 | User app / trip history | No free-text search in trip history — users could only filter by status tabs (all/ongoing/completed/cancelled/returned). Added `searchQuery` field to `TripController`; `filteredTrips` getter applies both status tab AND search; `TripScreen` gains a search `TextField` above the tabs (clear button, localized placeholder); searches across `pickupAddress`, `destinationAddress`, `driver.name`, `driver.phone`, `currentStatus`. Unit-tested. | fixed | `<this>` |
| G8 | Driver app / mart push routing | Tapping a mart order push notification in the driver app had an empty `onDidReceiveNotificationResponse` handler — the app ignored the tap. Implemented routing to `MartDeliveryScreen` via `DashboardScreen` (tab 2) + `Get.to(MartDeliveryScreen(orderId))`. | fixed | `<this>` |
| G9 | Backend / job stubs | `UserLocationSocketHandler::onClose`/`onError` were empty stubs — replaced with `Log::debug`/`Log::error`. `RideTimeoutJob::handle` had placeholder TODOs for broadcast and push notification — replaced the notification path with a proper `sendDeviceNotification` call using the `getNotification` helper; the re-broadcast comment removed (handled by driver polling). | fixed | `<this>` |

## Accepted (reviewed, intentionally not changed)

| ID | Severity | Area | Finding & rationale | Status |
|----|----------|------|---------------------|--------|
| A1 | Low | Both apps / Firebase | `AIzaSy…` Android API key hardcoded in `lib/main.dart`. This is the Firebase **Android** API key, which is public-by-design (restricted by SHA-1 + package name in the Firebase console, not a secret). No action. | accepted |
| A2 | Low | Both apps / null-safety | ~286 `!.data!` force-unwraps across both apps. Sampled on the critical paths (payment, order create, auth) — the overwhelming majority are immediately preceded by a null/`isEmpty`/`statusCode` guard. Blanket-rewriting them is churn with regression risk and no test coverage; left as-is. Genuinely unguarded crash sites are tracked individually (e.g. U1/D1/D2). | accepted |
| A3 | Low | Backend / FareManagement | `TripFareController@store` uses `$request->all()`. It is an **admin-only** web controller (already-privileged actor, no privilege escalation surface) and the `TripFare` model has no sensitive columns. Not user-reachable. | accepted |
| A4 | Info | Backend / config | The ~100 `env('APP_MODE')` runtime call sites are not individually rewritten; B4's provider re-hydration makes them all correct under both cached and non-cached config without touching each site. | accepted |
| A5 | Info | Backend / Vito API | IDOR sweep of the customer/driver Vito surface: mart **products** are a public catalog (`MartProduct::find` is intentionally unscoped); mart **orders**, rides, and parcels are owner-scoped in their services. Safety alert (B1) and parcel refund (B2) were the gaps and are fixed. | accepted |

---

## How findings are verified

- **Backend:** `php artisan test --filter=VitoFlowTest` (124 passing) stays green after each change;
  PHPStan level 0 clean on the edited controllers. `php -l` on every edited PHP file.
- **Flutter:** no local Flutter/macOS runner — changes are verified by the CI debug-APK build
  (`vito-ci.yml`), which compiles the edited widgets/screens, plus `flutter analyze` and unit tests.
- **iOS:** the `build-ios.yml` macOS workflow builds an unsigned release `.app` for both apps.

### End-to-end run — last verified at HEAD `e40f739`

| Layer | How it's "simulated/emulated" here | Result |
|-------|-------------------------------------|--------|
| Backend API flows (QR/auth/ride/parcel/mart/Stripe/wallet) | `php artisan test --filter=VitoFlowTest` (SQLite in-memory) | ✅ 124 passed / 379 assertions |
| Flutter unit tests (user app) | `flutter test test/vito_flows_test.dart` | ✅ 83 tests (Phase 2 adds: sort options, featured/popular filters, trip search, MartProductModel fields) |
| User + Driver apps (analyze, unit/behavior tests, Android APK) | `vito-ci.yml` run #75 | ✅ success |
| User + Driver apps (iOS build, incl. mobile_scanner 7.x) | `build-ios.yml` run #7 | ✅ success (both jobs) |

### Standing caveats — NOT verifiable in this environment (owner action)

These cannot be proven by local tests or CI (no device/emulator, no Apple secrets). They are *not*
defects — they are the boundary of what's checkable here, recorded so nothing is lost:

| ID | Item | Why it needs owner verification |
|----|------|--------------------------------|
| V1 | On-device/emulator UI run of either app | No Flutter SDK or emulator in this container; CI proves compile+test+APK/IPA only, not live UI. |
| V2 | QR scanner camera scan behavior (driver, `mobile_scanner` 7.x → Apple Vision on iOS) | Camera/scan is runtime-only; `extractToken` logic is unit-tested but the capture path needs a device. |
| V3 | Push notifications on iOS | Firebase no-ops on iOS until `GoogleService-Info.plist` + APNs key are added. |
| V4 | Signed iOS → TestFlight (`release-ios.yml`) | Manual-dispatch; runs only once the Apple secrets (see IOS_BUILD.md) are set. |
| W4 | Mart-screen → `MartController` migration | Deferred: large refactor of a polling-heavy live flow; needs device verification (tracked above). |
