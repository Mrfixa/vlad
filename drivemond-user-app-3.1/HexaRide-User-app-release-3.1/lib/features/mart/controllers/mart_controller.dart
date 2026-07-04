import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/data/offline_queue.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_category_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_product_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/services/mart_service_interface.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';

class MartController extends GetxController implements GetxService {
  final MartServiceInterface martServiceInterface;
  MartController({required this.martServiceInterface});

  bool isLoading = false;
  bool isActionLoading = false;

  // Idempotency key for order creation; regenerated after each failed attempt
  // so that retries are treated as new requests by the backend middleware.
  String _orderIdempotencyKey = OfflineQueue.generateIdempotencyKey();

  List<MartProductModel> products = [];
  List<MartCategoryModel> categories = [];
  String selectedCategory = 'all';

  // Catalog sort ('default' | price_asc | price_desc | popular) and the
  // Featured/Popular home shelves.
  String selectedSort = 'default';
  List<MartProductModel> featuredProducts = [];
  List<MartProductModel> popularProducts = [];

  List<MartOrderModel> orders = [];
  MartOrderModel? currentOrder;
  MartProductModel? productDetails;

  // Cart state - persisted to SharedPreferences
  static const String _cartKey = 'mart_cart_items';
  List<Map<String, dynamic>> _cartItems = [];
  List<Map<String, dynamic>> get cartItems => _cartItems;

  @override
  void onInit() {
    super.onInit();
    _loadCartFromStorage();
    getCategories();
  }

  Future<void> _loadCartFromStorage() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final cartJson = prefs.getString(_cartKey);
      if (cartJson != null) {
        final List<dynamic> decoded = jsonDecode(cartJson);
        _cartItems = decoded.cast<Map<String, dynamic>>();
        update();
      }
    } catch (e) {
      debugPrint('Failed to load cart from storage: $e');
      _cartItems = [];
    }
  }

  Future<void> _saveCartToStorage() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_cartKey, jsonEncode(_cartItems));
    } catch (e) {
      debugPrint('Failed to save cart to storage: $e');
    }
  }

  // Cart total calculation
  double get cartTotal {
    double total = 0.0;
    for (final item in _cartItems) {
      final price = double.tryParse(item['price']?.toString() ?? '0') ?? 0.0;
      final qty = item['quantity'] as int? ?? 1;
      if (price > 0 && qty > 0) {
        total += price * qty;
      }
    }
    return total.clamp(0.0, 999999.99);
  }

  int get cartItemCount => _cartItems.length;

  /// Delivery fee from backend config (defaults to 0.0 if not set)
  double get deliveryFee {
    try {
      final config = Get.find<ConfigController>().config;
      return config?.martDeliveryFee ?? 0.0;
    } catch (_) {
      return 0.0;
    }
  }

  /// Adds [product] to the cart. Items are always available, so this only
  /// increments the quantity or appends a new line. Always returns true.
  bool addToCart(Map<String, dynamic> product, {int quantity = 1}) {
    final existingIndex = _cartItems.indexWhere(
      (item) => item['id'] == product['id'],
    );

    if (existingIndex >= 0) {
      final existing = Map<String, dynamic>.from(_cartItems[existingIndex]);
      final newQty = (existing['quantity'] as int? ?? 1) + quantity;
      existing['quantity'] = newQty;
      _cartItems[existingIndex] = existing;
    } else {
      _cartItems.add({
        'id': product['id'],
        'name': product['name'],
        'price': product['price'],
        'image': product['image'],
        'quantity': quantity,
      });
    }

    _saveCartToStorage();
    update();
    return true;
  }

  /// M5: re-add a past order's items to the cart using the *current* catalog
  /// price (each item is re-fetched live, so prices are honoured and items that
  /// no longer exist / are inactive are skipped). Returns the number of items
  /// that could not be re-added so the UI can inform the user.
  Future<int> reorder(MartOrderModel order) async {
    int unavailable = 0;
    for (final item in order.items) {
      final id = item.productId;
      if (id == null || id.isEmpty) {
        unavailable++;
        continue;
      }
      final product = await getProductDetails(id);
      if (product == null || !product.isActive) {
        unavailable++;
        continue;
      }
      addToCart({
        'id': product.id,
        'name': product.name,
        'price': product.effectivePrice,
        'image': product.image,
      }, quantity: item.quantity > 0 ? item.quantity : 1);
    }
    update();
    return unavailable;
  }

  void updateCartItemQuantity(String productId, int quantity) {
    if (quantity <= 0) {
      removeFromCart(productId);
      return;
    }

    final index = _cartItems.indexWhere((item) => item['id'] == productId);
    if (index >= 0) {
      final item = Map<String, dynamic>.from(_cartItems[index]);
      item['quantity'] = quantity;
      _cartItems[index] = item;
      _saveCartToStorage();
      update();
    }
  }

  void removeFromCart(String productId) {
    _cartItems.removeWhere((item) => item['id'] == productId);
    _saveCartToStorage();
    update();
  }

  void clearCart() {
    _cartItems = [];
    _saveCartToStorage();
    update();
  }

  // Get categories as string list for UI
  List<String> get categoryList {
    final list = ['all'];
    for (final cat in categories) {
      if (cat.name != null && cat.name!.isNotEmpty) {
        list.add(cat.name!);
      }
    }
    return list.isEmpty ? ['all', 'food', 'drinks', 'snacks', 'essentials'] : list;
  }

  /// Helper to extract a list payload that may be a plain list or a Laravel
  /// paginator ({data: {data: [...]}}).
  List<dynamic> _extractList(dynamic body) {
    final data = body is Map ? body['data'] : null;
    if (data is List) return data;
    if (data is Map && data['data'] is List) return data['data'];
    return const [];
  }

  Future<void> getProducts({String? category, String? search, bool notify = true}) async {
    isLoading = true;
    if (notify) update();
    final response = await martServiceInterface.getProducts(
        category: category ?? selectedCategory,
        search: search,
        sort: selectedSort == 'default' ? null : selectedSort);
    if (response.statusCode == 200) {
      products = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartProductModel.fromJson)
          .toList();
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    if (notify) update();
  }

  Future<void> getCategories({bool notify = true}) async {
    final response = await martServiceInterface.getCategories();
    if (response.statusCode == 200) {
      categories = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartCategoryModel.fromJson)
          .toList();
    }
    if (notify) update();
  }

  void setCategory(String category) {
    selectedCategory = category;
    update();
    getProducts(category: category);
  }

  void setSort(String sort) {
    if (selectedSort == sort) return;
    selectedSort = sort;
    update();
    getProducts();
  }

  /// Loads the Featured and Popular shelves shown at the top of the store.
  Future<void> getShelves({bool notify = true}) async {
    final responses = await Future.wait([
      martServiceInterface.getProducts(isFeatured: true, limit: 10),
      martServiceInterface.getProducts(isPopular: true, limit: 10),
    ]);
    if (responses[0].statusCode == 200) {
      featuredProducts = _extractList(responses[0].body)
          .whereType<Map<String, dynamic>>()
          .map(MartProductModel.fromJson)
          .toList();
    }
    if (responses[1].statusCode == 200) {
      popularProducts = _extractList(responses[1].body)
          .whereType<Map<String, dynamic>>()
          .map(MartProductModel.fromJson)
          .toList();
    }
    if (notify) update();
  }

  Future<MartProductModel?> getProductDetails(String id) async {
    isLoading = true;
    update();
    productDetails = null;
    final response = await martServiceInterface.getProductDetails(id);
    if (response.statusCode == 200 && response.body['data'] != null) {
      productDetails = MartProductModel.fromJson(response.body['data']);
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    update();
    return productDetails;
  }

  // GoMart favorites / wishlist.
  final Set<String> favoriteIds = {};
  List<MartProductModel> favorites = [];

  bool isFavorite(String? id) => id != null && favoriteIds.contains(id);

  Future<void> getFavorites({bool notify = true}) async {
    final response = await martServiceInterface.getFavorites();
    if (response.statusCode == 200 && response.body['data'] != null) {
      favorites = (response.body['data'] as List).map((e) => MartProductModel.fromJson(e)).toList();
      favoriteIds
        ..clear()
        ..addAll(favorites.map((p) => p.id ?? ''));
    }
    if (notify) update();
  }

  /// Optimistic toggle; reverts on API failure.
  Future<void> toggleFavorite(String productId) async {
    final wasFav = favoriteIds.contains(productId);
    wasFav ? favoriteIds.remove(productId) : favoriteIds.add(productId);
    update();
    final response = await martServiceInterface.toggleFavorite(productId);
    if (response.statusCode != 200) {
      wasFav ? favoriteIds.add(productId) : favoriteIds.remove(productId);
      update();
    }
  }

  Future<void> getOrders({bool notify = true}) async {
    isLoading = true;
    if (notify) update();
    final response = await martServiceInterface.getOrders();
    if (response.statusCode == 200) {
      orders = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartOrderModel.fromJson)
          .toList();
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    if (notify) update();
  }

  Future<MartOrderModel?> getOrderDetails(String id, {bool notify = true}) async {
    final response = await martServiceInterface.getOrderDetails(id);
    if (response.statusCode == 200 && response.body['data'] != null) {
      currentOrder = MartOrderModel.fromJson(response.body['data']);
      if (notify) update();
      return currentOrder;
    }
    ApiChecker.checkApi(response);
    return null;
  }

  Future<bool> cancelOrder(String id) async {
    isActionLoading = true;
    update();
    final response = await martServiceInterface.cancelOrder(id);
    isActionLoading = false;
    update();
    if (response.statusCode == 200) {
      return true;
    }
    ApiChecker.checkApi(response);
    return false;
  }

  Future<bool> reviewOrder(String id, int rating, String? comment) async {
    isActionLoading = true;
    update();
    final response = await martServiceInterface.reviewOrder(id, rating, comment);
    isActionLoading = false;
    update();
    if (response.statusCode == 200) {
      return true;
    }
    ApiChecker.checkApi(response);
    return false;
  }

  /// Creates a mart order through the service layer.
  /// Returns a tuple of (success, orderId, serverTotal, errorMessage)
  Future<({bool success, String? orderId, double serverTotal, String? error})> createOrder({
    required List<Map<String, dynamic>> items,
    required String deliveryAddress,
    String? notes,
    required String paymentMethod,
    double? deliveryLat,
    double? deliveryLng,
    double? tipAmount,
    String? promoCode,
  }) async {
    isActionLoading = true;
    update();

    final body = <String, dynamic>{
      'items': items,
      'delivery_address': deliveryAddress,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      'payment_method': paymentMethod,
      if (deliveryLat != null) 'delivery_lat': deliveryLat,
      if (deliveryLng != null) 'delivery_lng': deliveryLng,
      if (tipAmount != null && tipAmount > 0) 'tip_amount': tipAmount,
      if (promoCode != null && promoCode.isNotEmpty) 'promo_code': promoCode,
    };

    final response = await martServiceInterface.createOrder(body, idempotencyKey: _orderIdempotencyKey);
    isActionLoading = false;
    update();

    if (response.statusCode == 200 || response.statusCode == 201) {
      final data = response.body['data'];
      final orderId = (data?['id'] ?? data?['order_id'] ?? '').toString();
      if (orderId.isEmpty) {
        // Rotate the key so a retry is a new request.
        _orderIdempotencyKey = OfflineQueue.generateIdempotencyKey();
        return (success: false, orderId: null, serverTotal: 0.0, error: 'invalid_order_response'.tr);
      }
      // FIX 1: extract the backend-computed total so callers never use a client-computed value.
      final serverTotal = double.tryParse(data?['total_amount']?.toString() ?? '') ?? 0.0;
      // Success: rotate so the next distinct order uses a fresh key.
      _orderIdempotencyKey = OfflineQueue.generateIdempotencyKey();
      return (success: true, orderId: orderId, serverTotal: serverTotal, error: null);
    }

    // Rotate the key on failure so a retry is not treated as a duplicate.
    _orderIdempotencyKey = OfflineQueue.generateIdempotencyKey();

    // Extract error message
    String? errorMsg;
    try {
      final errors = response.body['errors'];
      if (errors is List && errors.isNotEmpty) {
        final first = errors.first;
        if (first is Map && first['message'] != null) {
          errorMsg = first['message'].toString();
        }
      }
      if (errorMsg == null && response.body['message'] is String) {
        errorMsg = response.body['message'];
      }
    } catch (_) {}
    return (success: false, orderId: null, serverTotal: 0.0, error: errorMsg ?? 'order_failed'.tr);
  }

  String? appliedPromoCode;
  double promoDiscount = 0.0;
  bool isApplyingPromo = false;

  Future<void> applyPromo(String code, double orderTotal) async {
    if (code.trim().isEmpty) return;
    isApplyingPromo = true;
    update();
    final response = await martServiceInterface.applyPromoCode(code.trim(), orderTotal);
    isApplyingPromo = false;
    if (response.statusCode == 200) {
      final data = response.body;
      appliedPromoCode = code.trim();
      promoDiscount = double.tryParse(data?['discount']?.toString() ?? '0') ?? 0.0;
      showCustomSnackBar('promo_applied'.tr, isError: false);
    } else {
      appliedPromoCode = null;
      promoDiscount = 0.0;
      final body = response.body;
      String? msg;
      try { msg = body['message'] as String?; } catch (_) {}
      if (msg != null && msg.contains('expired')) {
        showCustomSnackBar('promo_expired'.tr);
      } else if (msg != null && msg.contains('limit')) {
        showCustomSnackBar('promo_usage_limit'.tr);
      } else if (msg != null && msg.contains('minimum')) {
        showCustomSnackBar('promo_min_spend'.tr);
      } else {
        showCustomSnackBar(msg ?? 'promo_invalid'.tr);
      }
    }
    update();
  }
}
