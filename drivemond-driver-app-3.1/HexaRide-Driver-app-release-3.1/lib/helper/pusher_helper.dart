import 'dart:async';
import 'dart:convert';
import 'package:audioplayers/audioplayers.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/home/screens/ride_list_screen.dart';
import 'package:ride_sharing_user_app/features/map/controllers/map_controller.dart';
import 'package:ride_sharing_user_app/features/map/screens/map_screen.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/ride/screens/ride_request_list_screen.dart';
import 'package:ride_sharing_user_app/features/safety_setup/controllers/safety_alert_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/splash_controller.dart';
import 'package:ride_sharing_user_app/features/trip/screens/payment_received_screen.dart';
import 'package:ride_sharing_user_app/features/trip/screens/review_this_customer_screen.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import '../features/dashboard/screens/dashboard_screen.dart';


class PusherHelper{

  static PusherChannelsClient?  pusherClient;

  static void initializePusher() async{
    final config = Get.find<SplashController>().config;
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
        //log('=================$exception');
        Get.find<SplashController>().setPusherStatus('Disconnected');
        refresh();
      },
    );

     await pusherClient?.connect();
    } catch (e, s) {
      debugPrint('PusherHelper.initializePusher error: $e\n$s');
      Get.find<SplashController>().setPusherStatus('Disconnected');
      return;
    }

    String? pusherChannelId =  pusherClient?.channelsManager.channelsConnectionDelegate.socketId;
      if(pusherChannelId != null){
        Get.find<SplashController>().setPusherStatus('Connected');
      }


     pusherClient?.lifecycleStream.listen((event) {
       Get.find<SplashController>().setPusherStatus('Disconnected');
     });


  }


  late PrivateChannel driverTripSubscribe;
  void driverTripRequestSubscribe(String id){
    if (Get.find<SplashController>().pusherConnectionStatus == 'Connected' && pusherClient != null){
      driverTripSubscribe = pusherClient!.privateChannel("private-customer-trip-request.$id", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<SplashController>().config?.websocketScheme ?? 'https'}://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(driverTripSubscribe.currentStatus == null){
        driverTripSubscribe.subscribeIfNotUnsubscribed();
        driverTripSubscribe.bind("customer-trip-request.$id").listen((event) {
          Get.find<RideController>().ongoingTripList().then((value){
            if((Get.find<RideController>().ongoingTrip ?? []).isEmpty){
              try {
                AudioPlayer().play(AssetSource('notification.wav'));
              } catch (e) {
                debugPrint('PusherHelper: failed to play notification sound: $e');
              }
              Get.find<RideController>().getPendingRideRequestList(1);
              Get.find<RideController>().setRideId(jsonDecode(event.data!)['trip_id']);
              Get.find<RideController>().getRideDetailBeforeAccept(jsonDecode(event.data!)['trip_id']).then((value){
                if(value.statusCode == 200){
                  Get.find<RiderMapController>().getPickupToDestinationPolyline();
                  Get.find<RiderMapController>().setRideCurrentState(RideState.pending);
                  Get.find<RideController>().updateRoute(false, notify: true);
                  Get.to(()=> const MapScreen());
                }
              });

            }else{
              if(Get.currentRoute == '/MapScreen'){
                Get.find<RideController>().getPendingRideRequestList(1,limit: 100);
              }else{
                Get.to(()=> RideRequestScreen());
              }

            }
          });

          customerInitialTripCancel(jsonDecode(event.data!)['trip_id'], id);
          anotherDriverAcceptedTrip(jsonDecode(event.data!)['trip_id'], id);

        });
      }
    }

  }

  late PrivateChannel customerInitialTripCancelChannel;

  void customerInitialTripCancel(String tripId, String userId){
    if (Get.find<SplashController>().pusherConnectionStatus == 'Connected' && pusherClient != null){
      customerInitialTripCancelChannel = pusherClient!.privateChannel("private-customer-trip-cancelled.$tripId.$userId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<SplashController>().config?.websocketScheme ?? 'https'}://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(customerInitialTripCancelChannel.currentStatus == null){
        customerInitialTripCancelChannel.subscribe();
        customerInitialTripCancelChannel.bind("customer-trip-cancelled.$tripId.$userId").listen((event) {
          if(Get.find<RideController>().tripDetail?.id == jsonDecode(event.data!)['trip_id']){
            Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
            Get.find<RideController>().getPendingRideRequestList(1).then((value) {
              if (value.statusCode == 200) {
                Get.find<RiderMapController>().setRideCurrentState(RideState.initial);
                Get.offAll(() => const DashboardScreen());
              }
            });
          }else{
            Get.find<RideController>().ongoingTripList();
            Get.find<RideController>().getPendingRideRequestList(1,limit: 100);
          }

        });
      }
    }

  }


  late PrivateChannel anotherDriverAcceptedTripChannel;

  void anotherDriverAcceptedTrip(String tripId, String userId){
    if (Get.find<SplashController>().pusherConnectionStatus == 'Connected' && pusherClient != null){
      anotherDriverAcceptedTripChannel = pusherClient!.privateChannel("private-another-driver-trip-accepted.$tripId.$userId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<SplashController>().config?.websocketScheme ?? 'https'}://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(anotherDriverAcceptedTripChannel.currentStatus == null){
        anotherDriverAcceptedTripChannel.subscribe();
        anotherDriverAcceptedTripChannel.bind("another-driver-trip-accepted.$tripId.$userId").listen((event) {
          if(Get.find<RideController>().tripDetail?.id == jsonDecode(event.data!)['trip_id']){
            Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
            Get.find<RideController>().getPendingRideRequestList(1).then((value) {
              if (value.statusCode == 200) {
                Get.find<RiderMapController>().setRideCurrentState(RideState.initial);
                Get.offAll(() => const DashboardScreen());
              }
            });
          }else{
            Get.find<RideController>().ongoingTripList();
            Get.find<RideController>().getPendingRideRequestList(1,limit: 100);
          }
        });
      }
    }
  }

  PrivateChannel? tripCancelAfterOngoingChannel;
  StreamSubscription? _tripCancelAfterOngoingSub;

  void tripCancelAfterOngoing(String tripId){
    if (Get.find<SplashController>().pusherConnectionStatus == 'Connected' && pusherClient != null){
      // Cancel any previous binding/channel so handlers don't stack across trips.
      try { _tripCancelAfterOngoingSub?.cancel(); } catch (_) {}
      try { tripCancelAfterOngoingChannel?.unsubscribe(); } catch (_) {}
      tripCancelAfterOngoingChannel = pusherClient!.privateChannel("private-customer-trip-cancelled-after-ongoing.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<SplashController>().config?.websocketScheme ?? 'https'}://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(tripCancelAfterOngoingChannel!.currentStatus == null){
        tripCancelAfterOngoingChannel!.subscribe();
        _tripCancelAfterOngoingSub = tripCancelAfterOngoingChannel!.bind("customer-trip-cancelled-after-ongoing.$tripId").listen((event) {
          Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
          Get.find<RideController>().getRideDetails(jsonDecode(event.data!)['id']).then((value){
            if(value.statusCode == 200){
              if(Get.find<RideController>().tripDetail?.type == AppConstants.parcel){
                Get.offAll(() => const DashboardScreen());
              }else{
                Get.find<RideController>().getFinalFare(jsonDecode(event.data!)['id']).then((value){
                  if(value.statusCode == 200){
                    Get.find<RiderMapController>().setRideCurrentState(RideState.initial);
                    Get.to(()=> const PaymentReceivedScreen());
                  }
                });
              }
            }
          });
          // pusherClient!.unsubscribe('private-customer-trip-cancelled-after-ongoing.$tripId');
        });
      }
    }

  }

  PrivateChannel? tripPaymentSuccessfulChannel;
  StreamSubscription? _tripPaymentSuccessfulSub;

  void tripPaymentSuccessful(String tripId){
    if (Get.find<SplashController>().pusherConnectionStatus == 'Connected' && pusherClient != null){
      // Cancel any previous binding/channel so handlers don't stack across trips.
      try { _tripPaymentSuccessfulSub?.cancel(); } catch (_) {}
      try { tripPaymentSuccessfulChannel?.unsubscribe(); } catch (_) {}
      tripPaymentSuccessfulChannel = pusherClient!.privateChannel("private-customer-trip-payment-successful.$tripId", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('${Get.find<SplashController>().config?.websocketScheme ?? 'https'}://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));

      if(tripPaymentSuccessfulChannel!.currentStatus == null){
        tripPaymentSuccessfulChannel!.subscribe();
        _tripPaymentSuccessfulSub = tripPaymentSuccessfulChannel!.bind("customer-trip-payment-successful.$tripId").listen((event) {
          if(jsonDecode(event.data!)['type'] == 'parcel'){
            Get.find<RideController>().getRideDetails(jsonDecode(event.data!)['id']).then((value){
              if(value.statusCode == 200){
                Get.find<RideController>().getOngoingParcelList();
                Get.back();
              }
            });
          }else{

            Get.find<RideController>().ongoingTripList().then((value){
              if((Get.find<RideController>().ongoingTrip ?? []).isEmpty){
                Get.find<RideController>().getRideDetails(jsonDecode(event.data!)['id']).then((value){
                  if(value.statusCode == 200){
                    if(Get.find<SplashController>().config!.reviewStatus!){
                      Get.offAll(()=>  ReviewThisCustomerScreen(tripId: jsonDecode(event.data!)['id']));
                    }else{
                      Get.offAll(()=> const DashboardScreen());
                    }
                  }
                });
              }else{
                Get.offAll(()=> const RideListScreen());
              }
            });

          }

        });
      }
    }

  }




  void pusherDisconnectPusher(){
    // D5: unsubscribe all active channels before disconnecting
    try { driverTripSubscribe.unsubscribe(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    try { customerInitialTripCancelChannel.unsubscribe(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    try { anotherDriverAcceptedTripChannel.unsubscribe(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    try { _tripCancelAfterOngoingSub?.cancel(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    try { tripCancelAfterOngoingChannel?.unsubscribe(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    try { _tripPaymentSuccessfulSub?.cancel(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    try { tripPaymentSuccessfulChannel?.unsubscribe(); } catch (e) { debugPrint('pusherDisconnect: $e'); }
    pusherClient?.disconnect();
    PusherHelper.pusherClient = null;
  }


}