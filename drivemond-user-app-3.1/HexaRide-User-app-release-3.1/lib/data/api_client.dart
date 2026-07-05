import 'dart:async';
import 'dart:convert';
import 'dart:developer';
import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get_connect/http/src/request/request.dart';
import 'package:ride_sharing_user_app/data/error_response.dart';
import 'package:path/path.dart';
import 'package:ride_sharing_user_app/features/address/domain/models/address_model.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;

/// GOJEK-GRADE FIX: Circuit breaker states
enum CircuitState { closed, open, halfOpen }

/// GOJEK-GRADE FIX: Circuit breaker for API calls
class CircuitBreaker {
  CircuitState _state = CircuitState.closed;
  int _failureCount = 0;
  DateTime? _lastFailureTime;
  
  static const int _failureThreshold = 5;
  static const Duration _openDuration = Duration(seconds: 30);
  
  CircuitState get state => _state;
  
  bool canExecute() {
    if (_state == CircuitState.closed) return true;
    
    if (_state == CircuitState.open) {
      if (_lastFailureTime != null && 
          DateTime.now().difference(_lastFailureTime!) > _openDuration) {
        _state = CircuitState.halfOpen;
        return true;
      }
      return false;
    }
    
    // halfOpen state - allow one test request
    return true;
  }
  
  void recordSuccess() {
    _failureCount = 0;
    _state = CircuitState.closed;
  }
  
  void recordFailure() {
    _failureCount++;
    _lastFailureTime = DateTime.now();
    
    if (_failureCount >= _failureThreshold) {
      _state = CircuitState.open;
    }
  }
}

class ApiClient extends GetxService {
  final String appBaseUrl;
  final SharedPreferences sharedPreferences;
  static final String noInternetMessage = 'connection_to_api_server_failed'.tr;
  static final String circuitOpenMessage = 'service_temporarily_unavailable'.tr;
  final int timeoutInSeconds = 30;

  late String token;
  late Map<String, String> _mainHeaders;
  
  // GOJEK-GRADE FIX: Circuit breaker instance
  final CircuitBreaker _circuitBreaker = CircuitBreaker();

  ApiClient({required this.appBaseUrl, required this.sharedPreferences, String initialToken = ''}) {
    token = initialToken.isNotEmpty ? initialToken : (sharedPreferences.getString(AppConstants.token) ?? '');
    if (kDebugMode) debugPrint('Token: $token');
    Address? address;
    try {
      address = Address.fromJson(jsonDecode(sharedPreferences.getString(AppConstants.userAddress)!));
      if (kDebugMode) debugPrint(address.toJson().toString());
    // ignore: empty_catches
    }catch(e) {}
    updateHeader(token, address);
  }

  void updateHeader(String token, Address? address, {String? zoneId}) {
    Map<String, String> header = {};
    if(address != null) {
      header.addAll({'zoneId': address.zoneId.toString()});
    }
    if(zoneId != null){
      header.addAll({'zoneId': zoneId});
    }
    header.addAll({
      'Content-Type': 'application/json; charset=UTF-8',
      'Accept' : 'application/json',
      AppConstants.localization: sharedPreferences.getString(AppConstants.languageCode) ?? AppConstants.languages[0].languageCode,
      'Authorization': 'Bearer $token',
    });
    if (kDebugMode) {
      debugPrint('====> API Call: Zone: ${address?.zoneId ?? ''}');
    }

    _mainHeaders = header;
  }

  /// Clears the in-memory token and rebuilds headers without an Authorization
  /// bearer, so a logged-out / 401 session does not keep reusing a dead token.
  void clearToken() {
    token = '';
    updateHeader('', null);
  }

  // M8: retries transient network errors up to 2 times with exponential back-off.
  // Only SocketException (no network) and TimeoutException trigger a retry;
  // business-logic errors (4xx/5xx) are returned immediately.
  Future<T> _withRetry<T>(Future<T> Function() fn) async {
    int attempt = 0;
    while (true) {
      try {
        return await fn();
      } catch (e) {
        if ((e is SocketException || e is TimeoutException) && attempt < 2) {
          attempt++;
          await Future.delayed(Duration(milliseconds: 500 * attempt));
          continue;
        }
        rethrow;
      }
    }
  }
  
  // GOJEK-GRADE FIX: Check circuit breaker before API call
  Response _checkCircuitBreaker() {
    if (!_circuitBreaker.canExecute()) {
      return Response(
        statusCode: 503,
        statusText: circuitOpenMessage,
        body: {'message': circuitOpenMessage},
      );
    }
    return Response(statusCode: -1); // Indicates no error
  }
  
  // GOJEK-GRADE FIX: Record success or failure for circuit breaker
  void _recordCircuitBreakerResult(bool success) {
    if (success) {
      _circuitBreaker.recordSuccess();
    } else {
      _circuitBreaker.recordFailure();
    }
  }

  Future<Response> getData(String uri, {Map<String, dynamic>? query, Map<String, String>? headers}) async {
    // GOJEK-GRADE FIX: Check circuit breaker
    final circuitCheck = _checkCircuitBreaker();
    if (circuitCheck.statusCode != -1) return circuitCheck;
    
    try {
      if(kDebugMode) {
        debugPrint('====> API Call: $uri\nHeader: $_mainHeaders');
      }
      http.Response response = await _withRetry(() => http.get(
        Uri.parse(appBaseUrl+uri),
        headers: headers ?? _mainHeaders,
      ).timeout(Duration(seconds: timeoutInSeconds)));
      _recordCircuitBreakerResult(response.statusCode == 200);
      return handleResponse(response, uri);
    } catch (e) {
      _recordCircuitBreakerResult(false);
      return Response(statusCode: 1, statusText: noInternetMessage);
    }
  }

  Future<Response> postData(String uri, dynamic body, {Map<String, String>? headers, String? idempotencyKey}) async {
    // GOJEK-GRADE FIX: Check circuit breaker
    final circuitCheck = _checkCircuitBreaker();
    if (circuitCheck.statusCode != -1) return circuitCheck;
    
    try {
      if(kDebugMode) {
        debugPrint('====> API Call: $uri\nHeader: $_mainHeaders');
        debugPrint('====> API Body: $body');
      }
      final effectiveHeaders = Map<String, String>.from(headers ?? _mainHeaders);
      if (idempotencyKey != null) effectiveHeaders['Idempotency-Key'] = idempotencyKey;
      http.Response response = await _withRetry(() => http.post(
        Uri.parse(appBaseUrl+uri),
        body: jsonEncode(body),
        headers: effectiveHeaders,
      ).timeout(Duration(seconds: timeoutInSeconds)));
      _recordCircuitBreakerResult(response.statusCode == 200);
      return handleResponse(response, uri);
    } catch (e) {
      _recordCircuitBreakerResult(false);
      return Response(statusCode: 1, statusText: noInternetMessage);
    }
  }

  Future<Response> postMultipartDataConversation(
      String? uri,
      Map<String, String> body,
      List<MultipartBody>? multipartBody,
      {Map<String, String>? headers,PlatformFile? otherFile}) async {

    http.MultipartRequest request = http.MultipartRequest('POST', Uri.parse(appBaseUrl+uri!));
    request.headers.addAll(headers ?? _mainHeaders);

    if(otherFile != null) {
      request.files.add(http.MultipartFile('files[${multipartBody!.length}]', otherFile.readStream!, otherFile.size, filename: basename(otherFile.name)));
    }
    if(multipartBody!=null){
      for(MultipartBody multipart in multipartBody) {
        Uint8List list = await multipart.file!.readAsBytes();
        request.files.add(http.MultipartFile(
          multipart.key, multipart.file!.readAsBytes().asStream(), list.length, filename:'${DateTime.now().toString()}.png',
        ));
      }
    }
    request.fields.addAll(body);
    http.Response response = await http.Response.fromStream(await request.send());
    return handleResponse(response, uri);
  }


  Future<Response> postMultipartData(String uri, Map<String, String> body, MultipartBody profile, List<MultipartBody> multipartBody, {Map<String, String>? headers}) async {
    // GOJEK-GRADE FIX: Check circuit breaker
    final circuitCheck = _checkCircuitBreaker();
    if (circuitCheck.statusCode != -1) return circuitCheck;
    
    try {
      if(kDebugMode) {
        debugPrint('====> API Call: $uri\nHeader: $_mainHeaders');
        debugPrint('====> API Body: $body with ${multipartBody.length} picture and ${profile.key}');
      }
      http.MultipartRequest request = http.MultipartRequest('POST', Uri.parse(appBaseUrl+uri));
      request.headers.addAll(headers ?? _mainHeaders);
      if(profile.file != null) {
        Uint8List list = await profile.file!.readAsBytes();
        request.files.add(http.MultipartFile(
          profile.key, profile.file!.readAsBytes().asStream(), list.length,
          filename: '${DateTime.now().toString()}.png',
        ));
      }

      for(MultipartBody multipart in multipartBody) {
        if(kDebugMode) log("Here-----${multipart.file}/${multipart.key}");
        if(multipart.file != null) {
         if(kDebugMode) log("Here----Inside-");
          Uint8List list = await multipart.file!.readAsBytes();
          request.files.add(http.MultipartFile(
            multipart.key, multipart.file!.readAsBytes().asStream(), list.length,
            filename: multipart.file?.path.split('/').last,
          ));
          if(kDebugMode) log("===ImageKey==>${multipart.key}/${multipart.file!.readAsBytes().asStream()}");
        }

      }
      request.fields.addAll(body);
      http.Response response = await http.Response.fromStream(await request.send());
      _recordCircuitBreakerResult(response.statusCode == 200);
      return handleResponse(response, uri);
    } catch (e) {
      _recordCircuitBreakerResult(false);
      return Response(statusCode: 1, statusText: noInternetMessage);
    }
  }

  Future<Response> putData(String uri, dynamic body, {Map<String, String>? headers}) async {
    // GOJEK-GRADE FIX: Check circuit breaker
    final circuitCheck = _checkCircuitBreaker();
    if (circuitCheck.statusCode != -1) return circuitCheck;
    
    try {
      if(kDebugMode) {
        debugPrint('====> API Call: $uri\nHeader: $_mainHeaders');
        debugPrint('====> API Body: $body');
      }
      http.Response response = await http.put(
        Uri.parse(appBaseUrl+uri),
        body: jsonEncode(body),
        headers: headers ?? _mainHeaders,
      ).timeout(Duration(seconds: timeoutInSeconds));
      _recordCircuitBreakerResult(response.statusCode == 200);
      return handleResponse(response, uri);
    } catch (e) {
      _recordCircuitBreakerResult(false);
      return Response(statusCode: 1, statusText: noInternetMessage);
    }
  }

  Future<Response> deleteData(String uri, {Map<String, String>? headers}) async {
    // GOJEK-GRADE FIX: Check circuit breaker
    final circuitCheck = _checkCircuitBreaker();
    if (circuitCheck.statusCode != -1) return circuitCheck;
    
    try {
      if(kDebugMode) {
        debugPrint('====> API Call: $uri\nHeader: $_mainHeaders');
      }
      http.Response response = await http.delete(
        Uri.parse(appBaseUrl+uri),
        headers: headers ?? _mainHeaders,
      ).timeout(Duration(seconds: timeoutInSeconds));
      _recordCircuitBreakerResult(response.statusCode == 200);
      return handleResponse(response, uri);
    } catch (e) {
      _recordCircuitBreakerResult(false);
      return Response(statusCode: 1, statusText: noInternetMessage);
    }
  }

  Response handleResponse(http.Response response, String uri) {
    dynamic body;
    try {
      body = jsonDecode(response.body);
    } catch (e) {
      // If JSON parsing fails and response is not 200, return error
      if (response.statusCode != 200) {
        return Response(
          body: null,
          bodyString: response.body.toString(),
          statusCode: 0,
          statusText: 'server_error'.tr,
        );
      }
      // For 200 responses that aren't JSON, return raw body
      body = response.body;
    }
    
    Response localResponse = Response(
      body: body, bodyString: response.body.toString(),
      request: Request(headers: response.request!.headers, method: response.request!.method, url: response.request!.url),
      headers: response.headers, statusCode: response.statusCode, statusText: response.reasonPhrase,
    );
    
    if(localResponse.statusCode != 200 && localResponse.body != null && localResponse.body is! String) {
      // S2: Handle 429 Too Many Requests specifically
      if (localResponse.statusCode == 429) {
        localResponse = Response(
          statusCode: localResponse.statusCode,
          body: localResponse.body,
          statusText: 'too_many_requests'.tr,
        );
      }
      // Prefer RFC 7807 `title`/`detail` when present (additive backend fields); fall back to legacy format.
      else if (localResponse.statusCode != 429) {
        final title = localResponse.body['title'];
        final detail = localResponse.body['detail'];
        if (title != null) {
          final text = (detail != null && detail.toString().isNotEmpty) ? detail.toString() : title.toString();
          localResponse = Response(statusCode: localResponse.statusCode, body: localResponse.body, statusText: text);
        } else if(localResponse.body.toString().startsWith('{errors: [{code:')) {
          ErrorResponse errorResponse = ErrorResponse.fromJson(localResponse.body);
          localResponse = Response(statusCode: localResponse.statusCode, body: localResponse.body, statusText: errorResponse.errors![0].message);
        }else if(localResponse.body.toString().startsWith('{message')) {
          localResponse = Response(statusCode: localResponse.statusCode, body: localResponse.body, statusText: localResponse.body['message']);
        } else {
          // Generic error message for other non-200 responses
          localResponse = Response(
            statusCode: localResponse.statusCode,
            body: localResponse.body,
            statusText: 'something_went_wrong'.tr,
          );
        }
      }
    }else if(localResponse.statusCode != 200 && localResponse.body == null) {
      localResponse = Response(statusCode: 0, statusText: noInternetMessage);
    }

    log('====> API Response: [${localResponse.statusCode}] $uri\n${localResponse.body}');

    return localResponse;
  }
}

class MultipartBody {
  String key;
  XFile? file;

  MultipartBody(this.key, this.file);
}
