abstract class MartServiceInterface {
  Future<dynamic> getProducts({String? category, String? search, int limit,
      String? sort, bool? isFeatured, bool? isPopular});
  Future<dynamic> getCategories();
  Future<dynamic> getProductDetails(String id);
  Future<dynamic> getOrders({int limit});
  Future<dynamic> getOrderDetails(String id);
  Future<dynamic> cancelOrder(String id);
  Future<dynamic> reviewOrder(String id, int rating, String? comment);
  Future<dynamic> createOrder(Map<String, dynamic> orderData, {String? idempotencyKey});
  Future<dynamic> applyPromoCode(String code, double orderTotal);
  Future<dynamic> toggleFavorite(String productId);
  Future<dynamic> getFavorites();
}
