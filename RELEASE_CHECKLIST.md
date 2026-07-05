# Vito Release Checklist

> **Last Updated:** 2026-07-05
> **Version:** 3.1 Production Release

This document provides step-by-step instructions for releasing Vito to production on Google Play Store and Apple App Store.

---

## Pre-Release Verification

### ✅ Backend Verification

- [ ] All PHPUnit tests pass: `php artisan test --filter=VitoFlowTest`
- [ ] PHPStan level 0 passes on Vito controllers
- [ ] No `.env` file in repository
- [ ] All secrets in `.env.example` are placeholders (no real keys)
- [ ] Stripe webhook endpoint verified and responding
- [ ] Redis connection verified for queue workers
- [ ] SSL certificate valid and not expiring within 90 days

### ✅ Flutter User App Verification

- [ ] `flutter analyze --no-fatal-infos` passes
- [ ] All unit tests pass: `flutter test test/vito_flows_test.dart`
- [ ] Debug APK builds successfully
- [ ] Release APK/OAB signed with production key
- [ ] API keys (Maps, Stripe) configured for production

### ✅ Flutter Driver App Verification

- [ ] `flutter analyze --no-fatal-infos` passes
- [ ] All unit tests pass: `flutter test test/vito_flows_test.dart`
- [ ] Debug APK builds successfully
- [ ] Release APK/OAB signed with production key

### ✅ CI/CD Verification

- [ ] GitHub Actions `vito-ci.yml` passes on `master` branch
- [ ] Coverage artifacts uploaded successfully
- [ ] APK artifacts available for download

---

## Google Play Store Release

### Step 1: Prepare Production Build

```bash
# User App
cd drivemond-user-app-3.1/HexaRide-User-app-release-3.1
flutter build appbundle --release \
  --dart-define=BASE_URL=https://api.yourdomain.com \
  --dart-define=MAPS_API_KEY=YOUR_PRODUCTION_MAPS_KEY \
  --dart-define=STRIPE_KEY=YOUR_PRODUCTION_STRIPE_KEY \
  --dart-define=MAPBOX_ACCESS_TOKEN=YOUR_MAPBOX_TOKEN \
  --dart-define=FIREBASE_API_KEY=YOUR_FIREBASE_KEY \
  --dart-define=FIREBASE_APP_ID=YOUR_FIREBASE_APP_ID \
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=YOUR_FIREBASE_SENDER_ID \
  --dart-define=FIREBASE_PROJECT_ID=YOUR_FIREBASE_PROJECT_ID

# Driver App
cd drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1
flutter build appbundle --release \
  --dart-define=BASE_URL=https://api.yourdomain.com \
  --dart-define=MAPS_API_KEY=YOUR_PRODUCTION_MAPS_KEY \
  --dart-define=STRIPE_KEY=YOUR_PRODUCTION_STRIPE_KEY \
  --dart-define=MAPBOX_ACCESS_TOKEN=YOUR_MAPBOX_TOKEN \
  --dart-define=FIREBASE_API_KEY=YOUR_FIREBASE_KEY \
  --dart-define=FIREBASE_APP_ID=YOUR_FIREBASE_APP_ID \
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=YOUR_FIREBASE_SENDER_ID \
  --dart-define=FIREBASE_PROJECT_ID=YOUR_FIREBASE_PROJECT_ID
```

### Step 2: Create Google Play Console Listing

1. Go to [Google Play Console](https://play.google.com/console)
2. Select your organization
3. Create new app for User App
4. Create new app for Driver App

#### User App Listing:
- **App Name:** Vito - Ride & Delivery
- **Default Language:** English (en-US)
- **App Type:** Application
- **Category:** Travel and Local
- **Store Listing:** Fill in description, screenshots (phone, tablet), feature graphic

#### Driver App Listing:
- **App Name:** Vito Driver
- **Default Language:** English (en-US)
- **App Type:** Application
- **Category:** Business
- **Store Listing:** Fill in description, screenshots, feature graphic

### Step 3: Content Rating

1. Navigate to **Content rating** in Play Console
2. Complete the questionnaire honestly
3. Submit for rating

### Step 4: Pricing & Distribution

1. Navigate to **Pricing & distribution**
2. Set pricing (free or paid)
3. Select countries/regions
4. Agree to export compliance if applicable

### Step 5: Upload AAB

1. Navigate to **Production** track
2. Create release
3. Upload the `.aab` file from `build/app/outputs/bundle/release/`
4. Complete release notes
5. Review and publish

### Step 6: Submit for Review

- Review typically takes 1-3 days
- Monitor for any rejection emails
- Address any policy issues immediately

---

## Apple App Store Release

### Prerequisites

- Apple Developer Account ($99/year)
- Xcode installed with valid signing certificate
- App Store Connect access
- Required certificates and provisioning profiles

### Step 1: Prepare iOS Certificates

1. Go to [Apple Developer Portal](https://developer.apple.com)
2. Create **App Store Distribution** certificate
3. Create **Push Notification** certificate
4. Create **Provisioning Profile** for each app

### Step 2: Configure Xcode

1. Open `ios/Runner.xcworkspace` in Xcode
2. Set Team in Signing & Capabilities
3. Configure Bundle Identifier (unique per app)
4. Set version and build numbers

### Step 3: Build iOS Release

```bash
# User App
cd drivemond-user-app-3.1/HexaRide-User-app-release-3.1
flutter build ipa --release \
  --dart-define=BASE_URL=https://api.yourdomain.com \
  --dart-define=MAPS_API_KEY=YOUR_PRODUCTION_MAPS_KEY \
  --dart-define=STRIPE_KEY=YOUR_PRODUCTION_STRIPE_KEY \
  --dart-define=MAPBOX_ACCESS_TOKEN=YOUR_MAPBOX_TOKEN \
  --dart-define=FIREBASE_API_KEY=YOUR_FIREBASE_KEY \
  --dart-define=FIREBASE_APP_ID=YOUR_FIREBASE_APP_ID \
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=YOUR_FIREBASE_SENDER_ID \
  --dart-define=FIREBASE_PROJECT_ID=YOUR_FIREBASE_PROJECT_ID

# Driver App
cd drivemond-driver-app-3.1/HexaRide-Driver-app-release-3.1
flutter build ipa --release \
  --dart-define=BASE_URL=https://api.yourdomain.com \
  --dart-define=MAPS_API_KEY=YOUR_PRODUCTION_MAPS_KEY \
  --dart-define=STRIPE_KEY=YOUR_PRODUCTION_STRIPE_KEY \
  --dart-define=MAPBOX_ACCESS_TOKEN=YOUR_MAPBOX_TOKEN \
  --dart-define=FIREBASE_API_KEY=YOUR_FIREBASE_KEY \
  --dart-define=FIREBASE_APP_ID=YOUR_FIREBASE_APP_ID \
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=YOUR_FIREBASE_SENDER_ID \
  --dart-define=FIREBASE_PROJECT_ID=YOUR_FIREBASE_PROJECT_ID
```

### Step 4: Upload to App Store Connect

```bash
# Install Transporter from Mac App Store if not installed
# Upload the .ipa file
xcrun altool --upload-app -type ios -file build/ios/ipa/vito_user.ipa -u YOUR_APPLE_ID -p YOUR_APP_PASSWORD
```

### Step 5: Configure App Store Connect

1. Go to [App Store Connect](https://appstoreconnect.apple.com)
2. Select your app
3. Complete all required information:
   - **App Information:** Name, category, subcategory
   - **Pricing and Availability:** Set price tier and availability
   - **App Privacy:** Complete privacy nutrition labels
   - **Prepare for Submission:** Screenshots (all sizes), preview video, description, keywords, support URL

### Step 6: Submit for Review

1. Add TestFlight beta testers (recommended)
2. Complete export compliance information
3. Submit for Apple review
4. Review typically takes 24-48 hours

---

## GDPR Compliance Checklist

### Data Collection

- [ ] Privacy policy URL provided in app store listing
- [ ] In-app privacy policy screen implemented
- [ ] User consent for location services implemented
- [ ] User consent for notifications implemented
- [ ] Data retention policy defined

### User Rights

- [ ] User can delete account (implemented in `deleteAccount`)
- [ ] User can export data (consider implementing)
- [ ] User can revoke consent
- [ ] Privacy contact information available

### Required Disclosures

- [ ] Third-party SDKs disclosed (Firebase, Stripe, Maps)
- [ ] Data collection purposes explained
- [ ] International data transfers addressed

### Recommended Actions

1. Create comprehensive Privacy Policy document
2. Host Privacy Policy on a dedicated URL
3. Register with data protection authority if required
4. Implement cookie consent for web components

---

## Post-Release Verification

### Immediate (Day 1)

- [ ] Monitor Crashlytics for critical crashes
- [ ] Monitor Sentry for backend errors
- [ ] Verify app store listing is live
- [ ] Test login/registration flow
- [ ] Test basic ride booking flow

### First Week

- [ ] Monitor app store reviews
- [ ] Monitor API error rates
- [ ] Verify Stripe payments processing
- [ ] Verify push notifications working

### First Month

- [ ] Collect user feedback
- [ ] Monitor crash-free rates
- [ ] Review and respond to app store reviews
- [ ] Plan first update based on user feedback

---

## Rollback Procedures

### If Critical Bug Found:

**Google Play:**
1. Go to Play Console → Release → Production
2. Select the release
3. Click "Rollback"
4. Deploy hotfix

**App Store:**
1. App Store Connect → App Store → iOS App
2. Select the version
3. You cannot rollback - must submit new version immediately
4. Expedite review if critical

**Backend:**
```bash
cd /var/www/vito
git checkout PREVIOUS_COMMIT_SHA
cd drivemond-admin-new-install-3.1
php artisan config:cache && php artisan route:cache
sudo supervisorctl restart vito-worker:*
```

---

## Emergency Contacts

| Role | Contact | Responsibility |
|------|---------|----------------|
| Backend Lead | [TBD] | API/Server issues |
| Mobile Lead | [TBD] | App issues |
| DevOps | [TBD] | Infrastructure |
| Security | [TBD] | Security incidents |

---

## Support Resources

- **Firebase Console:** https://console.firebase.google.com
- **Stripe Dashboard:** https://dashboard.stripe.com
- **Google Play Console:** https://play.google.com/console
- **App Store Connect:** https://appstoreconnect.apple.com
- **Crashlytics Dashboard:** [Link]
- **Sentry Dashboard:** [Link]

---

*This checklist should be reviewed and updated before each release.*
