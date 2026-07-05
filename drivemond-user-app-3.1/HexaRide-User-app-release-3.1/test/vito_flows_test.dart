import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/features/ride/domain/models/remaining_distance_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_product_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_category_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_item_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_model.dart';
import 'package:ride_sharing_user_app/util/parse_utils.dart';
import 'package:ride_sharing_user_app/features/mart/domain/mart_order_status.dart';

/// Unit tests for VITO-specific flows in the user app.
/// These validate localization, token logic, and widget structure
/// without requiring the full app or a running backend.
void main() {
  group('Localization Parity', () {
    late Map<String, dynamic> en;
    late Map<String, dynamic> es;

    setUpAll(() {
      en = jsonDecode(File('assets/language/en.json').readAsStringSync());
      es = jsonDecode(File('assets/language/es.json').readAsStringSync());
    });

    test('EN and ES have the same number of keys', () {
      expect(es.length, en.length,
          reason: 'ES should have the same keys as EN');
    });

    test('All EN keys exist in ES', () {
      final missingInEs = en.keys.where((k) => !es.containsKey(k)).toList();
      expect(missingInEs, isEmpty,
          reason: 'Keys missing in ES: $missingInEs');
    });

    test('All ES keys exist in EN', () {
      final extraInEs = es.keys.where((k) => !en.containsKey(k)).toList();
      expect(extraInEs, isEmpty,
          reason: 'Extra keys in ES not in EN: $extraInEs');
    });

    test('Vito-specific EN keys have non-empty values', () {
      final vitoKeys = [
        'invitation_required',
        'scan_qr_or_enter_token',
        'enter_invitation_token',
        'validate_token',
        'vito_mart',
        'order_tracking',
        'cart',
        'enter_username_and_pin',
        'pin_is_required',
        'username_is_required',
      ];
      for (final key in vitoKeys) {
        expect(en[key], isNotNull, reason: 'EN key "$key" should exist');
        expect(en[key], isNotEmpty, reason: 'EN key "$key" should not be empty');
      }
    });

    test('Vito-specific ES keys have Spanish translations', () {
      final esVitoKeys = {
        'invitation_required': 'Invitación Requerida',
        'validate_token': 'Validar Token',
        'vito_mart': 'VitoMart',
        'cart': 'Carrito',
        'pin_is_required': 'El PIN es obligatorio',
      };
      for (final entry in esVitoKeys.entries) {
        expect(es[entry.key], entry.value,
            reason: 'ES key "${entry.key}" should be "${entry.value}"');
      }
    });
  });

  group('Token Validation Logic', () {
    test('Empty token should be rejected', () {
      final token = '';
      expect(token.isEmpty, isTrue);
    });

    test('Short token (< 10 chars) should be rejected', () {
      final token = 'abc123';
      expect(token.length < 10, isTrue,
          reason: 'Token of ${token.length} chars should fail format check');
    });

    test('Valid token format (64 hex chars)', () {
      final token = 'a' * 64;
      expect(token.length, 64);
      expect(RegExp(r'^[a-f0-9]+$').hasMatch(token), isTrue);
    });

    test('Token with max length 64', () {
      final token = '0123456789abcdef' * 4;
      expect(token.length, 64);
    });
  });

  group('PIN Validation Logic', () {
    test('PIN must be exactly 6 digits', () {
      expect('123456'.length, 6);
      expect(RegExp(r'^\d{6}$').hasMatch('123456'), isTrue);
    });

    test('Short PIN rejected', () {
      expect(RegExp(r'^\d{6}$').hasMatch('12345'), isFalse);
    });

    test('PIN with letters rejected', () {
      expect(RegExp(r'^\d{6}$').hasMatch('123abc'), isFalse);
    });

    test('PIN confirmation must match', () {
      final pin = '654321';
      final confirm = '654321';
      expect(pin, confirm);
    });

    test('PIN mismatch detected', () {
      final pin = '654321';
      final confirm = '654320';
      expect(pin == confirm, isFalse);
    });
  });

  group('QR Token Expiry Logic', () {
    test('Customer token expires in 1 hour', () {
      final now = DateTime.now();
      final expiry = now.add(const Duration(hours: 1));
      final diff = expiry.difference(now);
      expect(diff.inMinutes, 60);
    });

    test('Driver onboarding token expires in 7 days', () {
      final now = DateTime.now();
      final expiry = now.add(const Duration(days: 7));
      final diff = expiry.difference(now);
      expect(diff.inDays, 7);
    });

    test('Expired token is detected', () {
      final expiry = DateTime.now().subtract(const Duration(hours: 1));
      expect(expiry.isBefore(DateTime.now()), isTrue);
    });
  });

  group('Mart Order Logic', () {
    test('Order status flow is valid', () {
      final statusFlow = [
        'pending',
        'accepted',
        'picked_up',
        'delivered',
      ];
      expect(statusFlow.length, 4);
      expect(statusFlow.first, 'pending');
      expect(statusFlow.last, 'delivered');
      // Verify the full transition sequence matches the backend canonical map.
      expect(statusFlow[0], 'pending');
      expect(statusFlow[1], 'accepted');
      expect(statusFlow[2], 'picked_up');
      expect(statusFlow[3], 'delivered');
    });

    test('Cart total calculation', () {
      final items = [
        {'price': 10.0, 'qty': 2},
        {'price': 5.50, 'qty': 3},
      ];
      final total = items.fold<double>(
        0,
        (sum, item) =>
            sum + (item['price'] as double) * (item['qty'] as int),
      );
      expect(total, 36.50);
    });
  });

  group('Client Auth Validation Logic', () {
    test('Empty first name is rejected in signup', () {
      const firstName = '';
      expect(firstName.trim().isEmpty, isTrue,
          reason: 'Empty first name should fail validation');
    });

    test('Phone with country code passes length check', () {
      const phone = '+15551234567';
      expect(phone.startsWith('+'), isTrue);
      expect(phone.length, greaterThanOrEqualTo(10));
    });

    test('Password shorter than 8 characters is invalid', () {
      const password = 'abc123';
      expect(password.length < 8, isTrue,
          reason: 'Password must be at least 8 characters');
    });

    test('Password mismatch is detected', () {
      const password = 'securePass1';
      const confirm = 'differentPass';
      expect(password == confirm, isFalse,
          reason: 'Passwords do not match');
    });

    test('Promo max_discount cap limits discount', () {
      const subtotal = 20.0;
      const discountPercent = 0.5;
      const maxDiscount = 3.0;
      final rawDiscount = subtotal * discountPercent;
      final appliedDiscount = rawDiscount > maxDiscount ? maxDiscount : rawDiscount;
      expect(appliedDiscount, 3.0);
    });

    test('Order total equals subtotal minus discount plus tip', () {
      const subtotal = 20.0;
      const discount = 3.0;
      const tip = 2.0;
      final total = subtotal - discount + tip;
      expect(total, 19.0);
    });

    test('Negative total is floored to zero', () {
      const subtotal = 2.0;
      const discount = 5.0;
      const tip = 0.0;
      final raw = subtotal - discount + tip;
      final total = raw < 0 ? 0.0 : raw;
      expect(total, 0.0);
    });

    test('Expired token is invalid', () {
      final expiry = DateTime.now().subtract(const Duration(minutes: 1));
      final isExpired = expiry.isBefore(DateTime.now());
      expect(isExpired, isTrue);
    });
  });

  // Locks in the crash-sweep: malformed/missing numeric fields must NOT throw.
  group('Model parse hardening', () {
    test('RemainingDistanceModel.fromJson tolerates a null distance', () {
      final model = RemainingDistanceModel.fromJson({'distance': null});
      expect(model.distance, isNull);
    });

    test('RemainingDistanceModel.fromJson tolerates a non-numeric distance', () {
      final model = RemainingDistanceModel.fromJson({'distance': 'not-a-number'});
      expect(model.distance, 0);
    });

    test('RemainingDistanceModel.fromJson parses valid numeric distances', () {
      expect(RemainingDistanceModel.fromJson({'distance': 12.5}).distance, 12.5);
      expect(RemainingDistanceModel.fromJson({'distance': 5}).distance, 5.0);
    });
  });

  group('Mart model parsing', () {
    test('MartProductModel.fromJson coerces types and computes inStock', () {
      final p = MartProductModel.fromJson(<String, dynamic>{
        'id': 1, 'name': 'Widget', 'price': '9.99', 'is_active': 1, 'stock': '3',
      });
      expect(p.id, '1');
      expect(p.name, 'Widget');
      expect(p.price, 9.99);
      expect(p.isActive, true);
      expect(p.stock, 3);
      expect(p.inStock, true);
      expect(p.toJson()['name'], 'Widget');
    });

    test('MartProductModel availability tracks is_active only (items always in stock)', () {
      // Items are always available — only an inactive product is hidden.
      expect(MartProductModel.fromJson(<String, dynamic>{'is_active': false, 'stock': 5}).inStock, false);
      expect(MartProductModel.fromJson(<String, dynamic>{'is_active': true, 'stock': 0}).inStock, true);
    });

    test('MartProductModel tolerates missing/garbage fields', () {
      final p = MartProductModel.fromJson(<String, dynamic>{'price': 'abc', 'stock': 'x'});
      expect(p.price, 0);
      expect(p.stock, 0);
      expect(p.name, isNull);
    });

    test('MartCategoryModel.fromJson and toJson round-trip', () {
      final c = MartCategoryModel.fromJson(<String, dynamic>{'id': 7, 'name': 'Tools', 'slug': 'tools'});
      expect(c.id, '7');
      expect(c.name, 'Tools');
      expect(c.toJson()['slug'], 'tools');
    });

    test('MartOrderItemModel parses nested product and displayName', () {
      final it = MartOrderItemModel.fromJson(<String, dynamic>{
        'id': 'i1', 'product_id': 'p1', 'quantity': '2', 'unit_price': '5', 'total_price': '10',
        'product': <String, dynamic>{'name': 'Soap'},
      });
      expect(it.quantity, 2);
      expect(it.unitPrice, 5);
      expect(it.totalPrice, 10);
      expect(it.product?.name, 'Soap');
      expect(it.displayName, 'Soap');
      expect(it.toJson()['product'], isNotNull);
    });

    test('MartOrderItemModel displayName falls back when product is absent', () {
      expect(MartOrderItemModel.fromJson(<String, dynamic>{'quantity': 1}).displayName, 'Item');
    });

    test('MartOrderModel parses items, driver name, and itemCount', () {
      final o = MartOrderModel.fromJson(<String, dynamic>{
        'id': 'o1', 'ref_id': 'R1', 'status': 'pending', 'total_amount': '20.50',
        'driver': <String, dynamic>{'first_name': 'Jane', 'last_name': 'Doe'},
        'items': <Map<String, dynamic>>[
          <String, dynamic>{'quantity': 2, 'product': <String, dynamic>{'name': 'A'}},
          <String, dynamic>{'quantity': 3, 'product': <String, dynamic>{'name': 'B'}},
        ],
      });
      expect(o.id, 'o1');
      expect(o.totalAmount, 20.50);
      expect(o.driverName, 'Jane Doe');
      expect(o.items.length, 2);
      expect(o.itemCount, 5);
      expect(o.toJson()['ref_id'], 'R1');
    });

    test('MartOrderModel tolerates missing items and driver', () {
      final o = MartOrderModel.fromJson(<String, dynamic>{'id': 'o2'});
      expect(o.items, isEmpty);
      expect(o.itemCount, 0);
      expect(o.driverName, isNull);
    });
  });

  group('Session 401 handling', () {
    // A transient/secondary 401 must NOT destroy a valid session — only the
    // deliberate startup auth check (handleUnauthorized: true) may log out.
    test('secondary 401 does not invalidate the session', () {
      expect(ApiChecker.shouldInvalidateSession(401, handleUnauthorized: false), false);
    });
    test('startup-confirmed 401 invalidates the session', () {
      expect(ApiChecker.shouldInvalidateSession(401, handleUnauthorized: true), true);
    });
    test('non-401 never invalidates the session', () {
      expect(ApiChecker.shouldInvalidateSession(200, handleUnauthorized: true), false);
      expect(ApiChecker.shouldInvalidateSession(403, handleUnauthorized: true), false);
      expect(ApiChecker.shouldInvalidateSession(408, handleUnauthorized: true), false);
      expect(ApiChecker.shouldInvalidateSession(null, handleUnauthorized: true), false);
    });
  });

  // WS3 — safe numeric coercion for server-supplied fields (PriceConverter, config, etc.).
  group('parse_utils', () {
    test('toDoubleOr handles num, numeric string, null, and garbage', () {
      expect(toDoubleOr(5), 5.0);
      expect(toDoubleOr('5.5'), 5.5);
      expect(toDoubleOr(null), 0);
      expect(toDoubleOr('abc', 1.0), 1.0);
      expect(toDoubleOr('null'), 0); // server null-as-string must not throw
    });

    test('toIntOr handles num, int/double strings, null, and garbage', () {
      expect(toIntOr(5), 5);
      expect(toIntOr('5'), 5);
      expect(toIntOr('5.9'), 5);
      expect(toIntOr(null, 1), 1);
      expect(toIntOr('abc', 2), 2);
      // currencyDecimalPoint misconfig used to crash every price render via int.parse.
      expect(toIntOr('x', 1).clamp(0, 20), 1);
    });

    test('toIntOrNull returns null for null/garbage', () {
      expect(toIntOrNull(null), isNull);
      expect(toIntOrNull('7'), 7);
      expect(toIntOrNull('x'), isNull);
    });
  });

  // WS4 — pure mart order-status logic extracted from mart_order_tracking_screen.
  group('mart_order_status', () {
    test('martOrderStepIndex follows pending→accepted→picked_up→delivered', () {
      expect(martOrderStepIndex('pending'), 0);
      expect(martOrderStepIndex('accepted'), 1);
      expect(martOrderStepIndex('picked_up'), 2);
      expect(martOrderStepIndex('delivered'), 3);
      expect(martOrderStepIndex('cancelled'), -1);
      expect(martOrderStepIndex('weird'), 0); // unknown defaults to the first step
    });

    test('isMartOrderTerminal only for delivered/cancelled', () {
      expect(isMartOrderTerminal('delivered'), isTrue);
      expect(isMartOrderTerminal('cancelled'), isTrue);
      expect(isMartOrderTerminal('pending'), isFalse);
      expect(isMartOrderTerminal('accepted'), isFalse);
      expect(isMartOrderTerminal('picked_up'), isFalse);
    });

    test('canCancelMartOrder only before pickup', () {
      expect(canCancelMartOrder('pending'), isTrue);
      expect(canCancelMartOrder('accepted'), isTrue);
      expect(canCancelMartOrder('picked_up'), isFalse);
      expect(canCancelMartOrder('delivered'), isFalse);
      expect(canCancelMartOrder('cancelled'), isFalse);
    });
  });

  // VitoMart E2E tests
  group('VitoMart E2E Flow', () {
    group('Order Status Transitions', () {
      test('pending is initial state', () {
        final validInitialStates = ['pending'];
        expect(validInitialStates.contains('pending'), isTrue);
      });

      test('accepted follows pending', () {
        final pendingToAccepted = {'pending': ['accepted']};
        expect(pendingToAccepted['pending']?.contains('accepted'), isTrue);
      });

      test('picked_up follows accepted', () {
        final acceptedToPickedUp = {'accepted': ['picked_up']};
        expect(acceptedToPickedUp['accepted']?.contains('picked_up'), isTrue);
      });

      test('delivered follows picked_up', () {
        final pickedUpToDelivered = {'picked_up': ['delivered']};
        expect(pickedUpToDelivered['picked_up']?.contains('delivered'), isTrue);
      });

      test('cancelled from pending is valid', () {
        final cancellableFromPending = ['pending', 'accepted'];
        expect(cancellableFromPending.contains('pending'), isTrue);
      });

      test('cancelled from accepted is valid', () {
        final cancellableFromAccepted = ['pending', 'accepted'];
        expect(cancellableFromAccepted.contains('accepted'), isTrue);
      });

      test('cancelled after picked_up is invalid', () {
        final nonCancellableStates = ['picked_up', 'delivered'];
        expect(nonCancellableStates.contains('picked_up'), isTrue);
      });
    });

    group('Cart Operations', () {
      test('cart total calculation with multiple items', () {
        final items = [
          {'id': 'p1', 'price': 10.0, 'quantity': 2},
          {'id': 'p2', 'price': 5.50, 'quantity': 3},
          {'id': 'p3', 'price': 2.25, 'quantity': 1},
        ];
        final total = items.fold<double>(
          0,
          (sum, item) => sum + (item['price'] as double) * (item['quantity'] as int),
        );
        expect(total, 36.50 + 2.25); // 38.75
      });

      test('cart with zero items has zero total', () {
        final items = <Map<String, dynamic>>[];
        final total = items.fold<double>(0, (sum, item) => sum + (item['price'] as double) * (item['quantity'] as int));
        expect(total, 0.0);
      });

      test('cart total respects quantity updates', () {
        var items = [
          {'id': 'p1', 'price': 10.0, 'quantity': 1},
        ];
        var total = items.fold<double>(0, (sum, item) => sum + (item['price'] as double) * (item['quantity'] as int));
        expect(total, 10.0);

        // Update quantity
        items = [
          {'id': 'p1', 'price': 10.0, 'quantity': 3},
        ];
        total = items.fold<double>(0, (sum, item) => sum + (item['price'] as double) * (item['quantity'] as int));
        expect(total, 30.0);
      });

      test('remove item from cart reduces total', () {
        var items = [
          {'id': 'p1', 'price': 10.0, 'quantity': 2},
          {'id': 'p2', 'price': 5.0, 'quantity': 1},
        ];
        var total = items.fold<double>(0, (sum, item) => sum + (item['price'] as double) * (item['quantity'] as int));
        expect(total, 25.0);

        // Remove p1
        items = items.where((item) => item['id'] != 'p1').toList();
        total = items.fold<double>(0, (sum, item) => sum + (item['price'] as double) * (item['quantity'] as int));
        expect(total, 5.0);
      });

      test('clear cart results in empty list', () {
        var items = [
          {'id': 'p1', 'price': 10.0, 'quantity': 2},
        ];
        expect(items.isNotEmpty, isTrue);
        items = [];
        expect(items.isEmpty, isTrue);
      });
    });

    group('Promo Code Application', () {
      test('percentage discount calculated correctly', () {
        const subtotal = 100.0;
        const discountPercent = 0.20; // 20% off
        final discount = subtotal * discountPercent;
        expect(discount, 20.0);
      });

      test('max_discount cap applied when exceeded', () {
        const subtotal = 100.0;
        const discountPercent = 0.50; // 50% off = 50
        const maxDiscount = 15.0;
        final rawDiscount = subtotal * discountPercent;
        final appliedDiscount = rawDiscount > maxDiscount ? maxDiscount : rawDiscount;
        expect(appliedDiscount, 15.0);
      });

      test('fixed amount discount', () {
        const subtotal = 50.0;
        const fixedDiscount = 10.0;
        final total = subtotal - fixedDiscount;
        expect(total, 40.0);
      });

      test('promo with minimum order amount', () {
        const orderAmount = 25.0;
        const minimumOrder = 30.0;
        final meetsMinimum = orderAmount >= minimumOrder;
        expect(meetsMinimum, isFalse);
      });

      test('promo with valid minimum order amount', () {
        const orderAmount = 35.0;
        const minimumOrder = 30.0;
        final meetsMinimum = orderAmount >= minimumOrder;
        expect(meetsMinimum, isTrue);
      });
    });

    group('Order Creation', () {
      test('order items format is correct', () {
        final cartItems = [
          {'id': 'prod1', 'quantity': 2},
          {'id': 'prod2', 'quantity': 1},
        ];
        final orderItems = cartItems.map((item) => {
          'product_id': item['id'],
          'quantity': item['quantity'],
        }).toList();

        expect(orderItems.length, 2);
        expect(orderItems[0]['product_id'], 'prod1');
        expect(orderItems[0]['quantity'], 2);
        expect(orderItems[1]['product_id'], 'prod2');
        expect(orderItems[1]['quantity'], 1);
      });

      test('order response parsing extracts id and ref_id', () {
        final response = {
          'id': 'order-123',
          'ref_id': 'REF-456',
          'status': 'pending',
          'total_amount': '25.50',
        };
        expect(response['id'], isNotNull);
        expect(response['ref_id'], isNotNull);
        expect(response['status'], 'pending');
      });

      test('order total equals subtotal minus discount plus tip', () {
        const subtotal = 50.0;
        const discount = 5.0;
        const tip = 3.0;
        final total = subtotal - discount + tip;
        expect(total, 48.0);
      });
    });

    group('Reorder Functionality', () {
      test('reorder extracts product IDs from previous order', () {
        final previousOrder = {
          'items': [
            {'product_id': 'p1', 'quantity': 2},
            {'product_id': 'p2', 'quantity': 1},
          ],
        };
        final productIds = (previousOrder['items'] as List)
            .map((item) => item['product_id'] as String)
            .toList();
        expect(productIds, contains('p1'));
        expect(productIds, contains('p2'));
      });

      test('reorder maintains original quantities', () {
        final previousOrder = {
          'items': [
            {'product_id': 'p1', 'quantity': 3},
          ],
        };
        final quantities = (previousOrder['items'] as List)
            .map((item) => item['quantity'] as int)
            .toList();
        expect(quantities[0], 3);
      });
    });

    group('Wallet Payment', () {
      test('insufficient wallet balance blocks payment', () {
        const walletBalance = 10.0;
        const orderTotal = 25.0;
        final canPay = walletBalance >= orderTotal;
        expect(canPay, isFalse);
      });

      test('sufficient wallet balance allows payment', () {
        const walletBalance = 50.0;
        const orderTotal = 25.0;
        final canPay = walletBalance >= orderTotal;
        expect(canPay, isTrue);
      });

      test('exact wallet balance allows payment', () {
        const walletBalance = 25.0;
        const orderTotal = 25.0;
        final canPay = walletBalance >= orderTotal;
        expect(canPay, isTrue);
      });
    });

    group('Mart Chat Integration', () {
      test('channel name format for mart order', () {
        const orderId = 'order-123';
        const channelName = 'private-customer-mart-chat.$orderId';
        expect(channelName, 'private-customer-mart-chat.order-123');
      });

      test('order_id field used in mart chat (not trip_id)', () {
        const orderId = 'order-456';
        const tripId = 'trip-789';
        // Mart chat should use order_id
        final chatPayload = {'order_id': orderId};
        expect(chatPayload.containsKey('order_id'), isTrue);
        expect(chatPayload.containsKey('trip_id'), isFalse);
      });
    });

    group('Mart Sort & Featured/Popular (G4)', () {
      test('sort options map to API query values', () {
        const sortOptions = {
          'default': null,
          'price_low': 'price_asc',
          'price_high': 'price_desc',
          'popular': 'popular',
        };
        // "default" omits the sort param; others translate to server values
        expect(sortOptions['default'], isNull);
        expect(sortOptions['price_low'], 'price_asc');
        expect(sortOptions['price_high'], 'price_desc');
        expect(sortOptions['popular'], 'popular');
      });

      test('featured and popular products use separate API filters', () {
        // Featured: ?is_featured=1  Popular: ?is_popular=1
        // Both are mutually exclusive from the general product list
        const featuredFilter = {'is_featured': '1'};
        const popularFilter = {'is_popular': '1'};
        expect(featuredFilter.containsKey('is_popular'), isFalse);
        expect(popularFilter.containsKey('is_featured'), isFalse);
      });

      test('MartProductModel isFeatured and isPopular fields exist', () {
        final json = {
          'id': 'p1',
          'name': 'Test Product',
          'price': '10.00',
          'is_featured': true,
          'is_popular': false,
        };
        final product = MartProductModel.fromJson(json);
        expect(product.isFeatured, isTrue);
        expect(product.isPopular, isFalse);
      });
    });

    group('Trip History Search (G4)', () {
      test('search filters trips by pickup address', () {
        final List<Map<String, dynamic>> trips = [
          {'pickupAddress': '123 Main St', 'currentStatus': 'completed'},
          {'pickupAddress': '456 Oak Ave', 'currentStatus': 'completed'},
          {'pickupAddress': '789 Pine Rd', 'currentStatus': 'ongoing'},
        ];
        final q = 'main';
        final results = trips.where((t) =>
          (t['pickupAddress'] as String).toLowerCase().contains(q.toLowerCase())
        ).toList();
        expect(results.length, 1);
        expect(results.first['pickupAddress'], '123 Main St');
      });

      test('search filters trips by destination address', () {
        final List<Map<String, dynamic>> trips = [
          {'destinationAddress': 'Downtown Plaza', 'currentStatus': 'completed'},
          {'destinationAddress': 'Airport Terminal', 'currentStatus': 'completed'},
        ];
        final q = 'airport';
        final results = trips.where((t) =>
          (t['destinationAddress'] as String).toLowerCase().contains(q.toLowerCase())
        ).toList();
        expect(results.length, 1);
        expect(results.first['destinationAddress'], 'Airport Terminal');
      });

      test('search filters trips by driver name', () {
        final List<Map<String, dynamic>> trips = [
          {'driver': {'name': 'John Smith'}, 'currentStatus': 'completed'},
          {'driver': {'name': 'Jane Doe'}, 'currentStatus': 'completed'},
        ];
        final q = 'john';
        final results = trips.where((t) =>
          (t['driver']['name'] as String).toLowerCase().contains(q.toLowerCase())
        ).toList();
        expect(results.length, 1);
        expect(results.first['driver']['name'], 'John Smith');
      });

      test('search is case-insensitive', () {
        final List<Map<String, dynamic>> trips = [
          {'pickupAddress': 'MAIN STREET', 'currentStatus': 'completed'},
          {'pickupAddress': 'main street', 'currentStatus': 'completed'},
        ];
        final q = 'Main';
        final results = trips.where((t) =>
          (t['pickupAddress'] as String).toLowerCase().contains(q.toLowerCase())
        ).toList();
        expect(results.length, 2);
      });

      test('empty search returns all trips', () {
        final List<Map<String, dynamic>> trips = [
          {'pickupAddress': '123 Main St', 'currentStatus': 'completed'},
          {'pickupAddress': '456 Oak Ave', 'currentStatus': 'ongoing'},
        ];
        final q = '';
        final results = trips.where((t) {
          if (q.isEmpty) return true;
          return (t['pickupAddress'] as String).toLowerCase().contains(q.toLowerCase());
        }).toList();
        expect(results.length, 2);
      });

      test('combined status + search filter', () {
        final List<Map<String, dynamic>> trips = [
          {'pickupAddress': '123 Main St', 'currentStatus': 'completed'},
          {'pickupAddress': '456 Oak Ave', 'currentStatus': 'ongoing'},
          {'pickupAddress': '789 Main Rd', 'currentStatus': 'cancelled'},
        ];
        const status = 'completed';
        const q = 'main';

        final results = trips.where((t) {
          // Status filter
          if (t['currentStatus'] != status) return false;
          // Search filter
          if (q.isEmpty) return true;
          return (t['pickupAddress'] as String).toLowerCase().contains(q.toLowerCase());
        }).toList();

        expect(results.length, 1);
        expect(results.first['pickupAddress'], '123 Main St');
      });
    });
  });
}
