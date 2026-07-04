import 'package:get/get_connect/http/src/response/response.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/mart/domain/repositories/mart_repository_interface.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';

class MartRepository implements MartRepositoryInterface {
  final ApiClient apiClient;
  MartRepository({required this.apiClient});

  @override
  Future<Response> getProducts({String? category, String? search, String? sort, int limit = 50}) async {
    final params = <String, String>{'limit': '$limit'};
    if (category != null && category.isNotEmpty && category != 'all') {
      params['category'] = category;
    }
    if (search != null && search.isNotEmpty) {
      params['search'] = search;
    }
    if (sort != null && sort.isNotEmpty) {
      params['sort'] = sort;
    }
    final query = params.entries.map((e) => '${e.key}=${Uri.encodeComponent(e.value)}').join('&');
    return await apiClient.getData('${AppConstants.martProducts}?$query');
  }

  @override
  Future<Response> getCategories() async {
    return await apiClient.getData(AppConstants.martCategories);
  }

  @override
  Future<Response> getProductDetails(String id) async {
    return await apiClient.getData('${AppConstants.martProductDetails}$id');
  }

  @override
  Future<Response> getOrders({int limit = 20}) async {
    return await apiClient.getData('${AppConstants.martOrders}?limit=$limit');
  }

  @override
  Future<Response> getOrderDetails(String id) async {
    return await apiClient.getData('${AppConstants.martOrderDetails}$id');
  }

  @override
  Future<Response> cancelOrder(String id) async {
    return await apiClient.putData('${AppConstants.martCancelOrder}$id/cancel', {});
  }

  @override
  Future<Response> reviewOrder(String id, int rating, String? comment) async {
    return await apiClient.postData('${AppConstants.martReviewOrder}$id/review', {
      'rating': rating,
      if (comment != null && comment.isNotEmpty) 'comment': comment,
    });
  }

  @override
  Future<Response> createOrder(Map<String, dynamic> orderData, {String? idempotencyKey}) async {
    return await apiClient.postData(AppConstants.martCreateOrder, orderData, idempotencyKey: idempotencyKey);
  }

  @override
  Future<Response> applyPromoCode(String code, double orderTotal) async {
    return await apiClient.postData(AppConstants.martApplyPromo, {
      'code': code,
      'order_total': orderTotal,
    });
  }

  @override
  Future<Response> toggleFavorite(String productId) async {
    return await apiClient.postData(AppConstants.martFavoritesToggle, {'product_id': productId});
  }

  @override
  Future<Response> getFavorites() async {
    return await apiClient.getData(AppConstants.martFavorites);
  }

  @override
  Future add(value) => throw UnimplementedError();
  @override
  Future delete(String id) => throw UnimplementedError();
  @override
  Future get(String id) => throw UnimplementedError();
  @override
  Future getList({int? offset = 1}) => throw UnimplementedError();
  @override
  Future update(value, {int? id}) => throw UnimplementedError();
}
