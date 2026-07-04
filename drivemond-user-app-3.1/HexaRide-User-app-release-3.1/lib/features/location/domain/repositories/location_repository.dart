import 'dart:convert';

import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/location/domain/repositories/location_repository_interface.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/features/address/domain/models/address_model.dart';
import 'package:shared_preferences/shared_preferences.dart';


class LocationRepository implements LocationRepositoryInterface{
  final ApiClient apiClient;
  final SharedPreferences sharedPreferences;
  LocationRepository({required this.apiClient, required this.sharedPreferences});

  @override
  Future<Response> getZone(String lat, String lng) async {
    return await apiClient.getData('${AppConstants.getZone}?lat=$lat&lng=$lng');
  }

  @override
  Future<Response> getAddressFromGeocode(LatLng? latLng) async {
    return await apiClient.getData('${AppConstants.geoCodeURI}?lat=${latLng!.latitude}&lng=${latLng.longitude}');
  }

  @override
  Future<Response> searchLocation(String text) async {
    return await apiClient.getData('${AppConstants.searchLocationUri}?search_text=${text.replaceAll('#', '')}');
  }

  @override
  Future<Response> getPlaceDetails(String placeID) async {
    return await apiClient.getData('${AppConstants.placeApiDetails}?placeid=$placeID');
  }

  @override
  Future<bool> saveUserAddress(Address? address) async {
    apiClient.updateHeader(
      sharedPreferences.getString(AppConstants.token) ?? '', address,
    );
    if(address == null) {
      if(sharedPreferences.containsKey(AppConstants.userAddress)) {
        return await sharedPreferences.remove(AppConstants.userAddress);
      }else {
        return true;
      }
    }else {
      return await sharedPreferences.setString(AppConstants.userAddress, jsonEncode(address.toJson()));
    }
  }

  @override
  String? getUserAddress() {
    return sharedPreferences.getString(AppConstants.userAddress);
  }

  @override
  Future add(value) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

  @override
  Future delete(String id) {
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
  Future update(value, {int? id}) {
    // NOTE: not called in current flows — implement here if needed
    throw UnimplementedError();
  }

}
