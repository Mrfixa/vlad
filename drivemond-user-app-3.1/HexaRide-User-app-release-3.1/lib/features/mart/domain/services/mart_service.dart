import 'package:ride_sharing_user_app/features/mart/domain/repositories/mart_repository_interface.dart';
import 'package:ride_sharing_user_app/features/mart/domain/services/mart_service_interface.dart';

class MartService implements MartServiceInterface {
  final MartRepositoryInterface martRepositoryInterface;
  MartService({required this.martRepositoryInterface});

  @override
  Future getProducts({String? category, String? search, int limit = 50,
      String? sort, bool? isFeatured, bool? isPopular}) async =>
      await martRepositoryInterface.getProducts(category: category, search: search, limit: limit,
          sort: sort, isFeatured: isFeatured, isPopular: isPopular);

  @override
  Future getCategories() async => await martRepositoryInterface.getCategories();

  @override
  Future getProductDetails(String id) async => await martRepositoryInterface.getProductDetails(id);

  @override
  Future getOrders({int limit = 20}) async => await martRepositoryInterface.getOrders(limit: limit);

  @override
  Future getOrderDetails(String id) async => await martRepositoryInterface.getOrderDetails(id);

  @override
  Future cancelOrder(String id) async => await martRepositoryInterface.cancelOrder(id);

  @override
  Future reviewOrder(String id, int rating, String? comment) async =>
      await martRepositoryInterface.reviewOrder(id, rating, comment);

  @override
  Future createOrder(Map<String, dynamic> orderData, {String? idempotencyKey}) async =>
      await martRepositoryInterface.createOrder(orderData, idempotencyKey: idempotencyKey);

  @override
  Future applyPromoCode(String code, double orderTotal) async =>
      await martRepositoryInterface.applyPromoCode(code, orderTotal);

  @override
  Future toggleFavorite(String productId) async => await martRepositoryInterface.toggleFavorite(productId);

  @override
  Future getFavorites() async => await martRepositoryInterface.getFavorites();
}
