import 'package:get/get_connect/http/src/response/response.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/notification/domain/repositories/notification_repository_interface.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';

class NotificationRepository implements NotificationRepositoryInterface{
  final ApiClient apiClient;

  NotificationRepository({required this.apiClient});


  @override
  Future add(value) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  Future delete(int id) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  Future get(String id) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  Future getList({int? offset = 1}) async{
    return await apiClient.getData('${AppConstants.notificationList}$offset');
  }

  @override
  Future update(Map<String, dynamic> body, int id) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  Future<Response> sendReadStatus(int notificationId) async {
    return await apiClient.putData(AppConstants.readNotification, {"notification_id" : notificationId});
  }

}