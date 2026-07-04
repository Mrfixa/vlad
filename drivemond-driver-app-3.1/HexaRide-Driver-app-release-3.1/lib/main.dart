import 'dart:async';
import 'package:camera/camera.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_downloader/flutter_downloader.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/splash/screens/splash_screen.dart';
import 'package:ride_sharing_user_app/helper/notification_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/features/map/screens/map_screen.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/splash_controller.dart';
import 'package:ride_sharing_user_app/helper/di_container.dart' as di;
import 'package:ride_sharing_user_app/localization/localization_controller.dart';
import 'package:ride_sharing_user_app/localization/messages.dart';
import 'package:ride_sharing_user_app/theme/dark_theme.dart';
import 'package:ride_sharing_user_app/theme/light_theme.dart';
import 'package:ride_sharing_user_app/theme/theme_controller.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'features/map/controllers/map_controller.dart';
import 'package:ride_sharing_user_app/common_widgets/offline_banner_widget.dart';


final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();
late List<CameraDescription> cameras;

Future<void> main() async {
  // Run the whole app inside a guarded zone so uncaught async errors are
  // reported instead of crashing silently.
  runZonedGuarded(() async {
    SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
        statusBarIconBrightness: Brightness.dark, // dark text for status bar
        statusBarColor: Colors.transparent),
    );

    WidgetsFlutterBinding.ensureInitialized();

    // Firebase init must never take down app startup.
    try {
      if(GetPlatform.isAndroid) {
        await Firebase.initializeApp(
          options: FirebaseOptions(
            apiKey: const String.fromEnvironment('FIREBASE_API_KEY', defaultValue: 'AIzaSyCFGqSEiWMItei_AFIUgdM53PWrvyGmjFY'),
            appId: const String.fromEnvironment('FIREBASE_APP_ID', defaultValue: '1:76471554747:android:28346318a6d400326d0f9e'),
            messagingSenderId: const String.fromEnvironment('FIREBASE_MESSAGING_SENDER_ID', defaultValue: '76471554747'),
            projectId: const String.fromEnvironment('FIREBASE_PROJECT_ID', defaultValue: 'drivevalley-fdb7f'),
          ),
        );
      } else {
        await Firebase.initializeApp();
      }

      // Route all uncaught Flutter framework and async errors to Crashlytics.
      FlutterError.onError = FirebaseCrashlytics.instance.recordFlutterFatalError;
      WidgetsBinding.instance.platformDispatcher.onError = (error, stack) {
        FirebaseCrashlytics.instance.recordError(error, stack, fatal: true);
        return true;
      };
    } catch (_) {}

    try {
      cameras = await availableCameras();
    } catch (_) {
      cameras = [];
    }

    Map<String, Map<String, String>> languages = await di.init();

    RemoteMessage? remoteMessage;
    try {
      remoteMessage = await FirebaseMessaging.instance.getInitialMessage();
      await NotificationHelper.initialize(flutterLocalNotificationsPlugin);
      FirebaseMessaging.onBackgroundMessage(myBackgroundMessageHandler);
    } catch (_) {}

    await SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);

    try {
      await FlutterDownloader.initialize(debug: kDebugMode, ignoreSsl: false);
    } catch (_) {}

    runApp(MyApp(languages: languages, notificationData: remoteMessage?.data));
  }, (error, stack) {
    try {
      FirebaseCrashlytics.instance.recordError(error, stack, fatal: true);
    } catch (_) {}
  });
}

class MyApp extends StatelessWidget {
  final Map<String, Map<String, String>> languages;
  final Map<String,dynamic>? notificationData;
  const MyApp({super.key, required this.languages, this.notificationData});


  @override
  Widget build(BuildContext context) {
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.dark.copyWith(
        statusBarColor: Get.isDarkMode? const Color(0xFF053B35) : const Color(0xFF00A08D),
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.dark)
    );
    if(GetPlatform.isWeb) {
      Get.find<SplashController>().initSharedData();

    }

    return GetBuilder<ThemeController>(builder: (themeController) {
      return GetBuilder<LocalizationController>(builder: (localizeController) {
        return GetBuilder<SplashController>(builder: (configController) {
          return (GetPlatform.isWeb && configController.config == null) ? const SizedBox() : GetMaterialApp(
            title: AppConstants.appName,
            debugShowCheckedModeBanner: false,
            navigatorKey: Get.key,
            scrollBehavior: const MaterialScrollBehavior().copyWith(
              dragDevices: {PointerDeviceKind.mouse, PointerDeviceKind.touch},
            ),
            theme: themeController.darkTheme ? darkTheme : lightTheme,
            locale: localizeController.locale,
            home: SplashScreen(notificationData: notificationData),
            translations: Messages(languages: languages),
            fallbackLocale: Locale(AppConstants.languages[0].languageCode, AppConstants.languages[0].countryCode),
            defaultTransition: Transition.fade,
            transitionDuration: const Duration(milliseconds: 500),
              builder:(context,child){
                return MediaQuery(
                  data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(0.95)),
                  child: OfflineBannerWidget(child: SafeArea(
                    top: false,
                    child: GetBuilder<RideController>(builder: (rideController) {
                      return Stack(children: [
                          child!,
                          if(rideController.notSplashRoute)...[
                            if(!(Get.find<SplashController>().config!.maintenanceMode != null &&
                                Get.find<SplashController>().config!.maintenanceMode!.maintenanceStatus == 1 &&
                                Get.find<SplashController>().config!.maintenanceMode!.selectedMaintenanceSystem!.driverApp == 1) || Get.find<SplashController>().haveOngoingRides())...[
                              Positioned(top: Get.height * 0.3, right: 0,
                                  child: GestureDetector(
                                      onTap: () async{
                                        Response res = await rideController.getRideDetails(rideController.rideId ?? '1', fromHomeScreen: true);
                                        if(res.statusCode == 403 || rideController.tripDetail?.currentStatus == 'returning' || rideController.tripDetail?.currentStatus == 'returned'){
                                          Get.find<RiderMapController>().setRideCurrentState(RideState.initial);
                                        }
                                        Get.to(()=> const MapScreen());
                                      },
                                      onHorizontalDragEnd: (DragEndDetails details){
                                        _onHorizontalDrag(details);
                                        Get.to(()=> const MapScreen());
                                      },
                                      child: Stack(children: [
                                        SizedBox(width: Dimensions.iconSizeExtraLarge,
                                            child: Image.asset(Images.homeToMapIcon, color: Theme.of(context).primaryColor)),
                                        Positioned(top: 0, bottom: 0, left: 5, right: 5, child: SizedBox(width: 15,child: Image.asset(
                                            Images.map,
                                            color: Get.isDarkMode ?
                                            Theme.of(context).textTheme.bodyMedium!.color :
                                            Theme.of(context).colorScheme.shadow
                                        )))
                                      ]),
                                  ),
                              ),
                            ]
                          ]
                        ],
                      );
                    }),
                  )),
                );
              }
          );
        });
      });
    });
  }
  void _onHorizontalDrag(DragEndDetails details) {
    if(details.primaryVelocity == 0) return;

    if (details.primaryVelocity!.compareTo(0) == -1) {
      debugPrint('dragged from left');
    } else {
      debugPrint('dragged from right');
    }
  }
}

