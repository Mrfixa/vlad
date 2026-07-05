import 'dart:async';
import 'dart:convert';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/dashboard/screens/dashboard_screen.dart';
import 'package:ride_sharing_user_app/features/map/controllers/map_controller.dart';
import 'package:ride_sharing_user_app/features/map/screens/map_screen.dart';
import 'package:ride_sharing_user_app/features/mart/controllers/mart_controller.dart';
import 'package:ride_sharing_user_app/features/parcel/controllers/parcel_controller.dart';
import 'package:ride_sharing_user_app/features/payment/screens/payment_screen.dart';
import 'package:ride_sharing_user_app/features/payment/screens/review_screen.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/ride/widgets/confirmation_trip_dialog.dart';
import 'package:ride_sharing_user_app/features/safety_setup/controllers/safety_alert_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';

class PusherHelper {
  static PusherChannelsClient?  pusherClient;
  
  // GOJEK-GRADE FIX: Track all stream subscriptions for proper cleanup
  static final List<StreamSubscription> _activeSubscriptions = [];
  
  // Track all bound event handlers for cleanup
  static final Map<String, StreamSubscription> _eventSubscriptions = {};

  /// Safely decode event data, returning null if parsing fails or data is null
  static Map<String, dynamic>? _safeDecodeData(String? data) {
    if (data == null || data.isEmpty) return null;
    try {
      return jsonDecode(data) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  /// GOJEK-GRADE FIX: Cancel all active subscriptions to prevent memory leaks
  static void dispose() {
    _cancelAllSubscriptions();
  }

  /// GOJEK-GRADE FIX: Unsubscribe from all ride channels
  static void unsubscribeFromRideChannels() {
    _cancelRideSubscriptions();
  }
  
  /// GOJEK-GRADE FIX: Cancel ride-related subscriptions only
  static void _cancelRideSubscriptions() {
    // Cancel and remove ride event subscriptions
    final rideEventKeys = _eventSubscriptions.keys
        .where((k) => k.contains('trip') || k.contains('driver') || k.contains('payment'))
        .toList();
    for (final key in rideEventKeys) {
      _eventSubscriptions[key]?.cancel();
      _eventSubscriptions.remove(key);
    }
    _currentRideChannel?.unsubscribe();
    _currentRideChannel = null;
  }

  static PrivateChannel? _currentRideChannel;

  static void initializePusher() async{
    final config = Get.find<ConfigController>().config;
    // Config may be null if the config API failed on splash; don't crash.
    if(config == null) {
      return;
    }
    try {
      PusherChannelsOptions testOptions = PusherChannelsOptions.fromHost(
        host: config.webSocketUrl ?? '',
        scheme: config.websocketScheme == 'https' ? 'wss' : 'ws',
        key: config.webSocketKey ?? '',
        port: int.tryParse(config.webSocketPort ?? '6001') ?? 6001,
      );

      pusherClient = PusherChannelsClient.websocket(
        options: testOptions,
        connectionErrorHandler: (exception, trace, refresh) async {
          Get.find<ConfigController>().setPusherStatus('Disconnected');
          refresh();
        },
      );

      await pusherClient?.connect();
    } catch (_) {
      Get.find<ConfigController>().setPusherStatus('Disconnected');
      return;
    }

    String? pusherChannelId =  pusherClient?.channelsManager.channelsConnectionDelegate.socketId;
    if(pusherChannelId != null){
      Get.find<ConfigController>().setPusherStatus('Connected');
    }


    pusherClient?.lifecycleStream.listen((event) {
      Get.find<ConfigController>().setPusherStatus('Disconnected');
    });

  }
  
  static void _cancelAllSubscriptions() {
    for (final sub in _activeSubscriptions) {
      sub.cancel();
    }
    _activeSubscriptions.clear();
    for (final sub in _eventSubscriptions.values) {
      sub.cancel();
    }
    _eventSubscriptions.clear();
  }

  late PrivateChannel pusherDriverAccepted;
  late PrivateChannel driverTripStarted;
  late PrivateChannel driverTripCancelled;
  late PrivateChannel driverTripCompleted;
  late PrivateChannel driverPaymentReceived;
  late PrivateChannel martOrderStatus;
  PrivateChannel? _currentMartOrderChannel;

  void pusherDriverStatus(String tripId) async {
    if (pusherClient == null) return;
    
    // GOJEK-GRADE FIX: Cancel previous ride subscriptions before creating new ones
    _cancelRideSubscriptions();

    if (Get.find<ConfigController>().pusherConnectionStatus != null || Get.find<ConfigController>().pusherConnectionStatus == 'Connected'){
      pusherDriverAccepted = pusherClient!.privateChannel("private-driver-trip-accepted.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<ConfigController>().config!.websocketScheme ?? 'https'}://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));
      _currentRideChannel = pusherDriverAccepted;

      if(pusherDriverAccepted.currentStatus ==  null){
        pusherDriverAccepted.subscribe();
        // GOJEK-GRADE FIX: Track subscription for cleanup
        _eventSubscriptions['driver-trip-accepted-$tripId'] = pusherDriverAccepted.bind("driver-trip-accepted.$tripId").listen((event) {
          final data = _safeDecodeData(event.data);
          if (data == null) return;
          Get.find<RideController>().getRideDetails(data['id']?.toString() ?? tripId).then((value){
            if(value.statusCode == 200){
              if(data['type'] == AppConstants.parcel){
                Get.find<ParcelController>().updateParcelState(ParcelDeliveryState.acceptRider);
                Get.find<RideController>().startLocationRecord();
                Get.find<MapController>().notifyMapController();
                Get.offAll(() => const MapScreen(fromScreen: MapScreenType.parcel));
              }else{
                Get.find<RideController>().updateRideCurrentState(RideState.outForPickup);
                Get.find<RideController>().startLocationRecord();
                Get.find<MapController>().notifyMapController();
                Get.offAll(() => const MapScreen(fromScreen: MapScreenType.splash));
              }
            }
          });
        });
      }



      driverTripStarted = pusherClient!.privateChannel("private-driver-trip-started.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<ConfigController>().config!.websocketScheme ?? 'https'}://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(driverTripStarted.currentStatus == null){
        driverTripStarted.subscribe();
        // GOJEK-GRADE FIX: Track subscription for cleanup
        _eventSubscriptions['driver-trip-started-$tripId'] = driverTripStarted.bind("driver-trip-started.$tripId").listen((event) {
          final data = _safeDecodeData(event.data);
          if (data == null) return;
          Get.find<RideController>().startLocationRecord();
          if(data['type'] == AppConstants.parcel){
            Get.find<MapController>().getPolyline();
            Get.find<ParcelController>().updateParcelState(ParcelDeliveryState.parcelOngoing);

            if(Get.find<RideController>().tripDetails == null ){
              Get.find<RideController>().getRideDetails(data['id']?.toString() ?? tripId).then((value) {
                if (Get.find<RideController>().tripDetails!.parcelInformation!.payer == 'sender') {
                  Get.find<RideController>().getFinalFare(data['id']?.toString() ?? tripId).then((value) {
                    if (value.statusCode == 200) {
                      Get.find<MapController>().notifyMapController();
                      Get.off(() => const PaymentScreen(fromParcel: true,));
                    }
                  });
                }
              });
            }else{
              if (Get.find<RideController>().tripDetails!.parcelInformation!.payer == 'sender') {
                Get.find<RideController>().getFinalFare(data['id']?.toString() ?? tripId).then((value) {
                  if (value.statusCode == 200) {
                    Get.find<MapController>().notifyMapController();
                    Get.off(() => const PaymentScreen(fromParcel: true,));
                  }
                });
              }
            }

          }else{
            Get.find<RideController>().updateRideCurrentState(RideState.ongoingRide);
            Get.find<SafetyAlertController>().checkDriverNeedSafety();
            Get.to(() => const MapScreen(fromScreen: MapScreenType.splash));
          }
        });
      }


      driverTripCancelled = pusherClient!.privateChannel("private-driver-trip-cancelled.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<ConfigController>().config!.websocketScheme ?? 'https'}://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(driverTripCancelled.currentStatus == null){
        driverTripCancelled.subscribe();
        // GOJEK-GRADE FIX: Track subscription for cleanup
        _eventSubscriptions['driver-trip-cancelled-$tripId'] = driverTripCancelled.bind("driver-trip-cancelled.$tripId").listen((event) async{
          Get.find<RideController>().stopLocationRecord();
          Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
          Get.offAll(const DashboardScreen());
        });
      }



      driverTripCompleted = pusherClient!.privateChannel("private-driver-trip-completed.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<ConfigController>().config!.websocketScheme ?? 'https'}://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(driverTripCompleted.currentStatus ==  null){
        driverTripCompleted.subscribe();
        // GOJEK-GRADE FIX: Track subscription for cleanup
        _eventSubscriptions['driver-trip-completed-$tripId'] = driverTripCompleted.bind("driver-trip-completed.$tripId").listen((event) {
          final data = _safeDecodeData(event.data);
          if (data == null) return;
          if(data['type'] == AppConstants.parcel){
            Get.find<RideController>().clearRideDetails();
            if(Get.find<ConfigController>().config!.reviewStatus!) {
              Get.off(()=> ReviewScreen(tripId: data['id']?.toString() ?? tripId));
            }else{
              Get.offAll(const DashboardScreen());
            }
          }else{
            Get.dialog(const ConfirmationTripDialog(isStartedTrip: false,), barrierDismissible: false);
            Get.find<RideController>().getFinalFare(data['id']?.toString() ?? tripId).then((value) {
              if(value.statusCode == 200){
                Get.find<RideController>().updateRideCurrentState(RideState.completeRide);
                Get.find<MapController>().notifyMapController();
                Get.find<RideController>().stopLocationRecord();
                Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
                Get.off(()=>const PaymentScreen());
              }
            });
          }
        });
      }



      driverPaymentReceived = pusherClient!.privateChannel("private-driver-payment-received.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<ConfigController>().config!.websocketScheme ?? 'https'}://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));
      if(driverPaymentReceived.currentStatus == null){
        driverPaymentReceived.subscribe();
        // GOJEK-GRADE FIX: Track subscription for cleanup
        _eventSubscriptions['driver-payment-received-$tripId'] = driverPaymentReceived.bind("driver-payment-received.$tripId").listen((event) {
          final data = _safeDecodeData(event.data);
          if (data == null) return;
          if (data['type'] == 'ride_request') {
            if(Get.find<ConfigController>().config!.reviewStatus!){
              Get.off(()=> ReviewScreen(tripId: data['id']?.toString() ?? tripId));
              Get.find<RideController>().tripDetails = null;
            }else{
              Get.offAll(() => const DashboardScreen());
              Get.find<RideController>().tripDetails = null;
            }

          } else {
            Get.find<RideController>().getRideDetails(data['id']?.toString() ?? tripId).then((_){
              if(Get.find<RideController>().tripDetails?.parcelInformation?.payer == 'sender'){
                Get.find<ParcelController>().updateParcelState(ParcelDeliveryState.parcelOngoing);
                Get.find<RideController>().startLocationRecord();
                Get.offAll(() => const MapScreen(fromScreen: MapScreenType.parcel));
              }else{
                Get.offAll(() => const DashboardScreen());
                Get.find<RideController>().tripDetails = null;
              }
            });
          }
        });
      }
    }

  }

  // GAP-009: Subscribe to mart order status updates
  void subscribeMartOrderStatus(String orderId) async {
    if (pusherClient == null) return;
    _currentMartOrderChannel?.unsubscribe();

    if (Get.find<ConfigController>().pusherConnectionStatus != null || Get.find<ConfigController>().pusherConnectionStatus == 'Connected') {
      martOrderStatus = pusherClient!.privateChannel("mart-order.$orderId",
        authorizationDelegate: EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
          authorizationEndpoint: Uri.parse('${Get.find<ConfigController>().config!.websocketScheme ?? 'https'}://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
          headers: {
            "Accept": "application/json",
            "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
            "Access-Control-Allow-Origin": "*",
            'Access-Control-Allow-Methods': "PUT, GET, POST, DELETE, OPTIONS"
          },
        )
      );
      _currentMartOrderChannel = martOrderStatus;

      if (martOrderStatus.currentStatus == null) {
        martOrderStatus.subscribe();
        // GOJEK-GRADE FIX: Track subscription for cleanup
        _eventSubscriptions['mart-order-status-$orderId'] = martOrderStatus.bind("mart.order.status.updated").listen((event) {
          final data = _safeDecodeData(event.data);
          if (data == null) return;
          // Trigger mart controller to refresh order details
          try {
            final martController = Get.find<MartController>();
            martController.getOrderDetails(orderId);
          } catch (_) {
            // MartController not registered, ignore
          }
        });
      }
    }
  }

  void unsubscribeMartOrderStatus() {
    // GOJEK-GRADE FIX: Cancel mart order subscription
    _eventSubscriptions.removeWhere((key, _) => key.contains('mart-order-status'));
    _currentMartOrderChannel?.unsubscribe();
    _currentMartOrderChannel = null;
  }

}