import 'dart:convert';

import 'package:get/get.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/splash/domain/repositories/splash_repository_interface.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SplashRepository implements SplashRepositoryInterface{
  final ApiClient apiClient;
  final SharedPreferences sharedPreferences;
  const SplashRepository({required this.apiClient, required this.sharedPreferences});

  @override
  Future<Response> getConfigData() {
    return apiClient.getData(AppConstants.configUri);
  }

  @override
  Future<bool> initSharedData() {
    if(!sharedPreferences.containsKey(AppConstants.theme)) {
      return sharedPreferences.setBool(AppConstants.theme, false);
    }
    if(!sharedPreferences.containsKey(AppConstants.countryCode)) {
      return sharedPreferences.setString(AppConstants.countryCode, AppConstants.languages[0].countryCode);
    }

    return Future.value(true);
  }

  @override
  Future<bool> removeSharedData() async {
    // Clear the session (token) without wiping the saved language, theme or
    // intro flags, and reset the in-memory/secure token so a 401 does not leave
    // a stale token behind that re-triggers 401 on the next request.
    await Get.find<FlutterSecureStorage>().delete(key: AppConstants.token);
    await sharedPreferences.remove(AppConstants.token);
    apiClient.clearToken();
    return true;
  }

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
  Future getList({int? offset = 1}) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  Future update(Map<String, dynamic> body, int id) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  bool haveOngoingRides(){
    return sharedPreferences.getBool(AppConstants.haveOngoingRides) ?? false;
  }

  @override
  void saveOngoingRides(bool value) {
     sharedPreferences.setBool(AppConstants.haveOngoingRides, value);
  }

  @override
  void addLastReFoundData(Map<String,dynamic>? data) => sharedPreferences.setString(AppConstants.lastRefund, jsonEncode(data));

  @override
  Map<String, dynamic>? getLastRefundData() {
    final lastRefundString = sharedPreferences.getString(AppConstants.lastRefund);

    if (lastRefundString == null) {
      return null;
    }

    return jsonDecode(lastRefundString);
  }


}