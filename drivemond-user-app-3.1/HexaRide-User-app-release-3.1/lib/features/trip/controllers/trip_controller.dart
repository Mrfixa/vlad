import 'package:flutter/cupertino.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/features/trip/domain/models/trip_cancelation_cause_list_model.dart';
import 'package:ride_sharing_user_app/features/trip/domain/models/trip_model.dart';
import 'package:ride_sharing_user_app/features/trip/domain/services/service_interface.dart';
import 'package:ride_sharing_user_app/features/ride/domain/models/trip_details_model.dart';

class TripController extends GetxController implements GetxService {
  final TripServiceInterface tripServiceInterface;
  TripController({required this.tripServiceInterface});

  final List<String> _filterList = ['all_time', 'today', 'previous_day', 'custom_date'];
  final List<String> _statusList = ['all', 'ongoing', 'cancelled', 'completed','returned'];
  int statusIndex = 0;
  int filterIndex = 0;
  bool _showCustomDate = false;
  String _filterStartDate = '';
  String _filterEndDate = '';
  TripModel? tripModel;

  // GAP-020: free-text search across origin, destination, driver name, trip ID
  String searchQuery = '';

  List<String> get filterList => _filterList;
  bool get showCustomDate => _showCustomDate;
  String get filterStartDate => _filterStartDate;
  String get filterEndDate => _filterEndDate;

  // GAP-020: trips filtered by active status tab AND free-text query.
  // Mirrors the client-side logic previously in TripScreen.tabBarBodyWidget.
  List<TripDetails> get filteredTrips {
    final trips = tripModel?.data ?? [];
    final q = searchQuery.trim().toLowerCase();
    return trips.where((trip) {
      // Status filter
      switch (_statusList[statusIndex]) {
        case 'ongoing':
          if (trip.currentStatus != 'accepted' && trip.currentStatus != 'started') return false;
          break;
        case 'cancelled':
          if (trip.currentStatus != 'cancelled') return false;
          break;
        case 'completed':
          if (trip.currentStatus != 'completed') return false;
          break;
        case 'returned':
          if (trip.currentStatus != 'returned') return false;
          break;
        default: // 'all'
          break;
      }
      // Search filter
      if (q.isEmpty) return true;
      return (trip.pickupAddress?.toLowerCase().contains(q) ?? false) ||
          (trip.destinationAddress?.toLowerCase().contains(q) ?? false) ||
          (trip.driver?.name?.toLowerCase().contains(q) ?? false) ||
          (trip.driver?.phone?.toLowerCase().contains(q) ?? false) ||
          (trip.currentStatus?.toLowerCase().contains(q) ?? false);
    }).toList();
  }

  void initData() {
    filterIndex = 0;
    statusIndex = 0;
    _showCustomDate = false;
    _filterStartDate = '';
    _filterEndDate = '';
    searchQuery = '';
  }

  void setStatusIndex(int index){
    statusIndex = index;
    getTripList(1,reload: true);
    update();
  }
  void setFilterTypeName(int index) {
    filterIndex = index;
    getTripList(1, reload: true);
    update();
  }

  Future<void> getTripList(int offset, {bool reload = false}) async {
    if(reload) {
      tripModel = null;
      update();
    }
    Response response = await tripServiceInterface.getTripList('ride_request', offset, _filterStartDate, _filterEndDate, _filterList[filterIndex], _statusList[statusIndex]);
    if (response.statusCode == 200 && response.body['data'] != []) {
      if(offset == 1) {
        tripModel = TripModel.fromJson(response.body);
      }else {
        tripModel?.data!.addAll(TripModel.fromJson(response.body).data!);
        tripModel?.offset = TripModel.fromJson(response.body).offset;
        tripModel?.totalSize = TripModel.fromJson(response.body).totalSize;
      }
    } else {
      ApiChecker.checkApi(response);
    }
    update();
  }

  void updateShowCustomDateState(bool state){
    _showCustomDate = state;
    update();
  }

  void setFilterDateRangeValue({String? start, String? end}) {
    filterIndex = _filterList.length - 1;
    _filterStartDate = start ?? '';
    _filterEndDate = end ?? '';
    getTripList(1);
    update();
  }


  TripCancellationCauseList? rideCancellationReasonList;
  TripCancellationCauseList? parcelCancellationReasonList;
  TextEditingController othersCancellationController = TextEditingController();

  int rideCancellationCauseCurrentIndex = 0;
  int parcelCancellationCauseCurrentIndex = 0;


  void getRideCancellationReasonList() async{
    rideCancellationReasonList = null;
    Response response = await tripServiceInterface.getRideCancellationReasonList();

    if(response.statusCode == 200){
      rideCancellationReasonList = TripCancellationCauseList.fromJson(response.body);
      if(!(rideCancellationReasonList?.data?.ongoingRide?.contains('other'.tr) ?? false)){
        rideCancellationReasonList?.data?.ongoingRide?.add('other'.tr);
      }
      if(!(rideCancellationReasonList?.data?.acceptedRide?.contains('other'.tr) ?? false)){
        rideCancellationReasonList?.data?.acceptedRide?.add('other'.tr);
      }
    }else{
      ApiChecker.checkApi(response);
    }
  }

  void getParcelCancellationReasonList() async{
    parcelCancellationReasonList = null;
    Response response = await tripServiceInterface.getParcelCancellationReasonList();

    if(response.statusCode == 200){
      parcelCancellationReasonList = TripCancellationCauseList.fromJson(response.body);
      if(!(parcelCancellationReasonList?.data?.ongoingRide?.contains('other'.tr) ?? false)){
        parcelCancellationReasonList?.data?.ongoingRide?.add('other'.tr);
      }
      if(!(parcelCancellationReasonList?.data?.acceptedRide?.contains('other'.tr) ?? false)){
        parcelCancellationReasonList?.data?.acceptedRide?.add('other'.tr);
      }

    }else{
      ApiChecker.checkApi(response);
    }
  }

  void setRideCancellationCurrentIndex(int index){
    rideCancellationCauseCurrentIndex = index;
  }

  void setParcelCancellationCurrentIndex(int index){
    parcelCancellationCauseCurrentIndex = index;
  }

}