import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

/// Dialog prompting user to rate the app after completing a trip.
class AppRatingDialog extends StatefulWidget {
  final VoidCallback? onRateNow;
  final VoidCallback? onRemindLater;
  final VoidCallback? onSkip;

  const AppRatingDialog({
    super.key,
    this.onRateNow,
    this.onRemindLater,
    this.onSkip,
  });

  static Future<void> show({
    required BuildContext context,
    VoidCallback? onRateNow,
    VoidCallback? onRemindLater,
    VoidCallback? onSkip,
  }) {
    return showDialog(
      context: context,
      builder: (context) => AppRatingDialog(
        onRateNow: onRateNow,
        onRemindLater: onRemindLater,
        onSkip: onSkip,
      ),
    );
  }

  @override
  State<AppRatingDialog> createState() => _AppRatingDialogState();
}

class _AppRatingDialogState extends State<AppRatingDialog> {
  int _selectedStars = 0;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Dimensions.radiusDefault)),
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset(Images.logo, width: 80, height: 80),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            Text(
              'rate_your_experience'.tr,
              style: textSemiBold.copyWith(fontSize: Dimensions.fontSizeLarge),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Text(
              'how_was_your_trip'.tr,
              style: textRegular.copyWith(color: Theme.of(context).hintColor),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(5, (index) {
                final starIndex = index + 1;
                return IconButton(
                  icon: Icon(
                    _selectedStars >= starIndex ? Icons.star_rounded : Icons.star_border_rounded,
                    color: Colors.amber,
                    size: 36,
                  ),
                  onPressed: () => setState(() => _selectedStars = starIndex),
                );
              }),
            ),
            if (_selectedStars > 0) ...[
              const SizedBox(height: Dimensions.paddingSizeSmall),
              Text(
                _getRatingText(_selectedStars),
                style: textRegular.copyWith(color: Theme.of(context).hintColor),
              ),
            ],
            const SizedBox(height: Dimensions.paddingSizeDefault),
            ButtonWidget(
              buttonText: _selectedStars >= 4 ? 'rate_now'.tr : 'submit'.tr,
              onPressed: () {
                Navigator.of(context).pop();
                widget.onRateNow?.call();
              },
              width: double.infinity,
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                TextButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                    widget.onRemindLater?.call();
                  },
                  child: Text('remind_later'.tr, style: textRegular),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                    widget.onSkip?.call();
                  },
                  child: Text('skip'.tr, style: textRegular),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _getRatingText(int stars) {
    if (stars == 5) return 'excellent'.tr;
    if (stars == 4) return 'great'.tr;
    if (stars == 3) return 'good'.tr;
    if (stars == 2) return 'fair'.tr;
    return 'poor'.tr;
  }
}
