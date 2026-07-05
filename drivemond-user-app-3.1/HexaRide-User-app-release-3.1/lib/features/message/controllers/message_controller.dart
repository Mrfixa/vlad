import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_message_screen.dart';
import 'package:ride_sharing_user_app/features/message/domain/services/message_service_interface.dart';
import 'package:ride_sharing_user_app/features/message/screens/message_screen.dart';
import 'package:ride_sharing_user_app/features/message/domain/models/channel_model.dart';
import 'package:ride_sharing_user_app/features/message/domain/models/message_model.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/helper/file_validation_helper.dart';
import 'package:ride_sharing_user_app/helper/pusher_helper.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';



class MessageController extends GetxController implements GetxService{
  final MessageServiceInterface messageServiceInterface;
  MessageController({required this.messageServiceInterface});

  List <XFile>? _pickedImageFiles =[];
  List <XFile>? get pickedImageFile => _pickedImageFiles;
  bool isLoading = false;
  // Distinguishes "first page loading" from "loaded but empty/failed" so the
  // conversation screen shows a shimmer while loading and a retry state on error
  // (instead of an endless shimmer when the fetch fails).
  bool isConversationLoading = true;

  FilePickerResult? _otherFile;
  FilePickerResult? get otherFile => _otherFile;

  File? _file;
  PlatformFile? objFile;
  File? get file=> _file;

  List<MultipartBody> _selectedImageList = [];
  List<MultipartBody> get selectedImageList => _selectedImageList;

  final List<dynamic> _conversationList=[];
  List<dynamic> get conversationList => _conversationList;

  final bool _paginationLoading = true;
  bool get paginationLoading => _paginationLoading;

  bool _isOtherUserTyping = false;
  bool get isOtherUserTyping => _isOtherUserTyping;
  void setOtherUserTyping(bool value) {
    _isOtherUserTyping = value;
    update();
  }

  // GOJEK-GRADE FIX: Track stream subscriptions for proper cleanup
  StreamSubscription? _martChatSubscription;
  StreamSubscription? _rideChatSubscription;




  var conversationController = TextEditingController();
  final GlobalKey<FormState> conversationKey  = GlobalKey<FormState>();

  @override
  void onInit(){
    super.onInit();
    conversationController.text = '';
  }

  bool isImagePicked = false;

  void pickMultipleImage(bool isRemove,{int? index}) async {
    if(isRemove) {
      if(index != null){
        _pickedImageFiles!.removeAt(index);
        _selectedImageList.removeAt(index);
      }
    }else {
      isImagePicked = true;
      Future.delayed(const Duration(seconds: 1)).then((value) {
        update();
      });
      _pickedImageFiles = await FileValidationHelper.validateAndPickMultipleImages();
      if (_pickedImageFiles != null) {
        for(int i =0; i< _pickedImageFiles!.length; i++){
          _selectedImageList.add(MultipartBody('files[$i]',_pickedImageFiles![i]));
        }
      }
      isImagePicked = false;
    }
    update();
  }

  bool permissionGranted = false;

  Future getStoragePermission() async {
    if (await Permission.storage.request().isGranted) {
      permissionGranted = true;

    } else if (await Permission.storage.request().isPermanentlyDenied) {
      await openAppSettings();
    } else if (await Permission.storage.request().isDenied) {
      await openAppSettings();
      Permission.storage.request();
    }
    update();
  }


  void pickOtherFile(bool isRemove) async {
    if(isRemove){
      _otherFile=null;
      _file = null;
    }else{
      _otherFile = (await FilePicker.platform.pickFiles(
        type: FileType.custom,
        withReadStream: true,
        allowedExtensions: AppConstants.allowedImageExtensionsForFile,
      ))!;
      if (_otherFile != null) {
        if(await FileValidationHelper.validatePlatformFileSizeAsync(file: _otherFile!.files.single)){
          objFile = _otherFile!.files.single;
        }
      }
    }
    update();
  }

  void removeFile() async {
    _otherFile=null;
    update();
  }

  void cleanOldData(){
    _pickedImageFiles = [];
    _selectedImageList = [];
    _otherFile = null;
    _file = null;
  }



  ChannelModel? channelModel;

  Future<void> getChannelList(int offset) async{
    Response response = await messageServiceInterface.getChannelList(offset);
    if(response.statusCode == 200){
      if(offset == 1 ){
        channelModel = ChannelModel.fromJson(response.body);
      }else{
        channelModel!.totalSize =  ChannelModel.fromJson(response.body).totalSize;
        channelModel!.offset =  ChannelModel.fromJson(response.body).offset;
        channelModel!.data!.addAll(ChannelModel.fromJson(response.body).data!);
      }
      isLoading = false;
    }else{
      ApiChecker.checkApi(response);
    }
    update();
  }

  Future<void> createChannel(String userId, String? tripId) async{
    isLoading = true;
    update();
    Response response = await messageServiceInterface.createChannel(userId,tripId!);
    if(response.statusCode == 200){
      isLoading = false;
      final data = response.body is Map ? response.body['data'] : null;
      final channel = data is Map ? data['channel'] : null;
      if (channel is! Map || channel['id'] == null) {
        update();
        showCustomSnackBar('channel_creation_failed'.tr, isError: true);
        return;
      }
      final user = data['user'] is Map ? data['user'] : {};
      Get.to(()=> MessageScreen(channelId : channel['id'].toString(), tripId: (channel['trip_id'] ?? tripId).toString(), userName:  '${user['first_name'] ?? ''} ${user['last_name'] ?? ''}'.trim()));
    }else{
      isLoading = false;
      ApiChecker.checkApi(response);
    }
    update();
  }

  Future<void> createMartChannel(String driverId, String orderId, String driverName) async {
    isLoading = true;
    update();
    Response response = await messageServiceInterface.createMartChannel(driverId, orderId);
    if (response.statusCode == 200) {
      isLoading = false;
      final channel = response.body['data']?['channel'];
      if (channel == null || channel['id'] == null) {
        update();
        showCustomSnackBar('channel_creation_failed'.tr, isError: true);
        return;
      }
      String channelId = channel['id'].toString();
      String oId = (channel['trip_id'] ?? orderId).toString();
      Get.to(() => MartMessageScreen(channelId: channelId, orderId: oId, userName: driverName));
    } else {
      isLoading = false;
      ApiChecker.checkApi(response);
    }
    update();
  }



  MessageModel? messageModel;
  Future<void> getConversation(String channelId, int offset) async{
    isLoading = true;
    if(offset == 1){
      isConversationLoading = true;
      update();
    }
    Response response = await messageServiceInterface.getConversation(channelId, offset);
    if(response.statusCode == 200){

      if(offset == 1 ){
        messageModel = MessageModel.fromJson(response.body);
      }else{
        messageModel!.totalSize =  MessageModel.fromJson(response.body).totalSize;
        messageModel!.offset =  MessageModel.fromJson(response.body).offset;
        messageModel!.data!.addAll(MessageModel.fromJson(response.body).data!);
      }
      isLoading = false;

    }else{
      isLoading = false;
      ApiChecker.checkApi(response);
    }
    isConversationLoading = false;
    update();
  }

  bool isSending = false;

  Future<void> sendMartMessage(String channelId, String orderId) async {
    isSending = true;
    update();
    Response response = await messageServiceInterface.sendMartMessage(
        conversationController.value.text, channelId, orderId, _selectedImageList, objFile);
    if (response.statusCode == 200) {
      isSending = false;
      getConversation(channelId, 1);
      conversationController.text = '';
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile = null;
      objFile = null;
      _file = null;
    } else if (response.statusCode == 400) {
      isSending = false;
      final errors = response.body['errors'];
      String message = (errors is List && errors.isNotEmpty && errors[0] is Map && errors[0]['message'] != null)
          ? errors[0]['message'].toString()
          : 'something_went_wrong';
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile = null;
      objFile = null;
      _file = null;
      showCustomSnackBar(message.tr);
    } else {
      isSending = false;
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile = null;
      objFile = null;
      _file = null;
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    update();
  }

  PrivateChannel? martChannel;

  void subscribeMartMessageChannel(String orderId) {
    final martChannelName = "private-customer-mart-chat.$orderId";
    if (_subscribedMartChannelName == martChannelName) return;

    if (_subscribedMartChannelName.isNotEmpty) {
      try { martChannel?.unsubscribe(); } catch (_) {}
    }
    _subscribedMartChannelName = martChannelName;
    id = orderId;

    if (PusherHelper.pusherClient == null) {
      debugPrint('Pusher client is null, cannot subscribe to mart channel');
      return;
    }

    if (Get.find<ConfigController>().pusherConnectionStatus != null &&
        Get.find<ConfigController>().pusherConnectionStatus == 'Connected') {
      try {
        martChannel = PusherHelper.pusherClient?.privateChannel(
            "private-customer-mart-chat.$orderId",
            authorizationDelegate: EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
              authorizationEndpoint: Uri.parse(
                  'https://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
              headers: {
                "Accept": "application/json",
                "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
                "Access-Control-Allow-Origin": "*",
                'Access-Control-Allow-Methods': "PUT, GET, POST, DELETE, OPTIONS"
              },
            ));
        if (martChannel != null && martChannel!.currentStatus == null) {
          martChannel!.subscribe();
          // GOJEK-GRADE FIX: Track subscription for cleanup
          _martChatSubscription?.cancel();
          _martChatSubscription = martChannel!.bind("customer-mart-chat.$orderId").listen((event) {
            if (event.data == null) return;
            try {
              final data = jsonDecode(event.data!) as Map<String, dynamic>;
              final eventOrderId = data['order_id'] ?? data['channel_conversation']?['channel']?['trip_id'];
              if (eventOrderId == orderId && messageModel?.data != null) {
                messageModel!.data!.insert(0, Message.fromJson(data['channel_conversation']));
                update();
              }
            } catch (e) {
              debugPrint('Failed to parse mart Pusher message: $e');
            }
          });
        }
      } catch (e) {
        debugPrint('Failed to subscribe to mart channel: $e');
      }
    }
  }

  Future<void> sendMessage(String channelId , String tripId) async{
    isSending = true;
    update();
    Response response = await messageServiceInterface.sendMessage(conversationController.value.text,
        channelId, tripId ,_selectedImageList, objFile);
    if(response.statusCode == 200){
      isSending = false;
      getConversation(channelId, 1);
      conversationController.text='';
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile=null;
      objFile =null;
      _file=null;
    }
    else if(response.statusCode == 400){
      isSending = false;
      final errors = response.body['errors'];
      String message = (errors is List && errors.isNotEmpty && errors[0] is Map && errors[0]['message'] != null)
          ? errors[0]['message'].toString()
          : 'something_went_wrong';
      if(message.contains("png  jpg  jpeg  csv  txt  xlx  xls  pdf")){
        message = "the_files_types_must_be";
      }
      if(message.contains("failed to upload")){
        message = "failed_to_upload";
      }
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile=null;
      objFile =null;
      _file=null;
      showCustomSnackBar(message.tr);
    }
    else{
      isSending = false;
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile=null;
      objFile =null;
      _file=null;
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    update();
  }

  PrivateChannel? channel;
  String id ="";
  String _subscribedRideChannelName = '';
  String _subscribedMartChannelName = '';

  void subscribeMessageChannel(String tripId){
    final channelName = "private-customer-ride-chat.$tripId";
    if (_subscribedRideChannelName == channelName) return;

    if (_subscribedRideChannelName.isNotEmpty) {
      try { channel?.unsubscribe(); } catch (_) {}
    }
    _subscribedRideChannelName = channelName;
    id = tripId;

    if (PusherHelper.pusherClient == null) {
      debugPrint('Pusher client is null, cannot subscribe');
      return;
    }

    if (Get.find<ConfigController>().pusherConnectionStatus != null && Get.find<ConfigController>().pusherConnectionStatus == 'Connected'){
      try {
        channel = PusherHelper.pusherClient!.privateChannel(channelName, authorizationDelegate:
        EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
          authorizationEndpoint: Uri.parse('https://${Get.find<ConfigController>().config!.webSocketUrl}/broadcasting/auth'),
          headers:  {
            "Accept": "application/json",
            "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
            "Access-Control-Allow-Origin": "*",
            'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
          },
        ));

        if(channel != null && channel!.currentStatus == null){
          channel!.subscribe();
          // GOJEK-GRADE FIX: Track subscription for cleanup
          _rideChatSubscription?.cancel();
          _rideChatSubscription = channel!.bind("customer-ride-chat.$id").listen((event) {
            if (event.data == null) return;
            try {
              final data = jsonDecode(event.data!) as Map<String, dynamic>;
              final eventTripId = data['channel_conversation']?['channel']?['trip_id'];
              if (id == eventTripId && messageModel?.data != null) {
                messageModel!.data!.insert(0, Message.fromJson(data['channel_conversation']));
                update();
              }
            } catch (e) {
              debugPrint('Failed to parse Pusher message: $e');
            }
          });
        }
      } catch (e) {
        debugPrint('Failed to subscribe to ride channel: $e');
      }
    }
  }

  /// Unsubscribe the active ride/mart chat channels when leaving a conversation
  /// screen. MessageController is a long-lived lazyPut singleton, so onClose does
  /// not fire on normal navigation — call this from the screen's dispose().
  void leaveConversation() {
    // GOJEK-GRADE FIX: Cancel subscriptions before unsubscribing
    _martChatSubscription?.cancel();
    _rideChatSubscription?.cancel();
    try { channel?.unsubscribe(); } catch (_) {}
    try { martChannel?.unsubscribe(); } catch (_) {}
    _subscribedRideChannelName = '';
    _subscribedMartChannelName = '';
    isConversationLoading = true;
  }

  @override
  void onClose() {
    // GOJEK-GRADE FIX: Cancel all subscriptions
    _martChatSubscription?.cancel();
    _rideChatSubscription?.cancel();
    try { channel?.unsubscribe(); } catch (_) {}
    try { martChannel?.unsubscribe(); } catch (_) {}
    super.onClose();
  }




  bool _channelRideStatus = true;
  bool get channelRideStatus => _channelRideStatus;
  void findChannelRideStatus(String channelId) async{
    Response response = await messageServiceInterface.findChannelRideStatus(channelId);
    if(response.body['data'] == "cancelled" || response.body['data'] == 'completed'){
      _channelRideStatus = false;
    }else{
      _channelRideStatus = true;
    }
    update();
  }

  bool _channelMartOrderStatus = true;
  bool get channelMartOrderStatus => _channelMartOrderStatus;
  void findChannelMartOrderStatus(String orderId) async {
    try {
      final response = await messageServiceInterface.findChannelMartOrderStatus(orderId);
      if (response.statusCode == 200) {
        final status = response.body['data']?['status']?.toString() ?? '';
        if (status == 'delivered' || status == 'cancelled') {
          _channelMartOrderStatus = false;
        } else {
          _channelMartOrderStatus = true;
        }
      }
    } catch (e) {
      debugPrint('Failed to check mart order status: $e');
    }
    update();
  }

}