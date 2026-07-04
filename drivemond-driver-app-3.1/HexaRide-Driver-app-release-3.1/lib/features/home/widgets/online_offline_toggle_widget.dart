import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class OnlineOfflineToggleWidget extends StatelessWidget {
  const OnlineOfflineToggleWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return GetBuilder<ProfileController>(builder: (profileController) {
      final isOnline = profileController.isOnline == "1";
      
      return GestureDetector(
        onTap: () => _showToggleConfirmation(context, profileController, isOnline),
        child: Container(
          padding: const EdgeInsets.symmetric(
            horizontal: Dimensions.paddingSizeDefault,
            vertical: Dimensions.paddingSizeSmall,
          ),
          decoration: BoxDecoration(
            color: isOnline 
                ? Colors.green.withValues(alpha: 0.15)
                : Colors.red.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(Dimensions.radiusLarge),
            border: Border.all(
              color: isOnline ? Colors.green : Colors.red,
              width: 1.5,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isOnline ? Colors.green : Colors.red,
                  boxShadow: [
                    BoxShadow(
                      color: (isOnline ? Colors.green : Colors.red).withValues(alpha: 0.5),
                      blurRadius: 4,
                      spreadRadius: 1,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeSmall),
              Text(
                isOnline ? 'online'.tr : 'offline'.tr,
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: isOnline ? Colors.green : Colors.red,
                ),
              ),
            ],
          ),
        ),
      );
    });
  }

  void _showToggleConfirmation(
    BuildContext context, 
    ProfileController controller, 
    bool currentlyOnline
  ) {
    Get.dialog(
      AlertDialog(
        title: Text(currentlyOnline ? 'go_offline'.tr : 'go_online'.tr),
        content: Text(currentlyOnline 
            ? 'confirm_go_offline_message'.tr 
            : 'confirm_go_online_message'.tr),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: Text('cancel'.tr),
          ),
          ElevatedButton(
            onPressed: () {
              Get.back();
              controller.profileOnlineOffline(!currentlyOnline);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: currentlyOnline ? Colors.red : Colors.green,
            ),
            child: Text(
              currentlyOnline ? 'go_offline'.tr : 'go_online'.tr,
              style: const TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }
}
