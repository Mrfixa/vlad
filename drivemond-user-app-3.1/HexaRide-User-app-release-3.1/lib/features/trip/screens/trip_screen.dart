import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/custom_pop_scope_widget.dart';
import 'package:ride_sharing_user_app/features/trip/widgets/trip_item_view.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/features/trip/controllers/trip_controller.dart';
import 'package:ride_sharing_user_app/features/notification/widgets/notification_shimmer.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/body_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/no_data_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/paginated_list_widget.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class TripScreen extends StatefulWidget {
  final bool fromProfile;
  const TripScreen({super.key, required this.fromProfile});

  @override
  State<TripScreen> createState() => _TripScreenState();
}

class _TripScreenState extends State<TripScreen> with SingleTickerProviderStateMixin{
  late TabController tabController;
  final ScrollController scrollController = ScrollController();
  final TextEditingController searchController = TextEditingController();

  @override
  void initState() {
    tabController = TabController(length: 5, vsync: this);
    Get.find<TripController>().initData();
    Get.find<TripController>().getTripList(1);
    tabController.addListener((){
      if (!tabController.indexIsChanging){
        Get.find<TripController>().setStatusIndex(tabController.index);
      }
    });
    super.initState();
  }

  @override
  void dispose() {
    tabController.dispose();
    scrollController.dispose();
    searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: CustomPopScopeWidget(
        child: Scaffold(
          body: BodyWidget(
            appBar: AppBarWidget(title: 'trip_history'.tr, showBackButton: widget.fromProfile,centerTitle: true,showTripHistoryFilter: true),
            body: Padding(
              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
              child: GetBuilder<TripController>(builder: (tripController) {
                return Column(children: [
                  // GAP-020: free-text search bar
                  Padding(
                    padding: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
                    child: TextField(
                      controller: searchController,
                      onChanged: (value) {
                        tripController.searchQuery = value;
                        tripController.update();
                      },
                      decoration: InputDecoration(
                        hintText: 'search_trips'.tr,
                        prefixIcon: const Icon(Icons.search),
                        suffixIcon: searchController.text.isNotEmpty
                            ? IconButton(
                                icon: const Icon(Icons.clear),
                                onPressed: () {
                                  searchController.clear();
                                  tripController.searchQuery = '';
                                  tripController.update();
                                },
                              )
                            : null,
                        filled: true,
                        fillColor: Theme.of(context).cardColor,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                      ),
                    ),
                  ),
                  TabBar(
                    controller: tabController,
                    unselectedLabelColor: Colors.grey,
                    tabAlignment: TabAlignment.start,
                    isScrollable: true,
                    labelColor: Get.isDarkMode ? Colors.white.withValues(alpha:0.9) : Theme.of(context).primaryColor,
                    labelStyle: textSemiBold.copyWith(),
                    indicator: UnderlineTabIndicator(borderSide: BorderSide(color: Theme.of(context).primaryColor, width: 1)),
                    dividerHeight: 1,
                    dividerColor: Theme.of(context).primaryColor.withValues(alpha:0.15),
                    tabs: [
                      Tab(text: 'all_trip'.tr),
                      Tab(text: 'ongoing'.tr),
                      Tab(text: 'cancelled'.tr),
                      Tab(text: 'completed'.tr),
                      Tab(text: 'returned'.tr)
                    ],
                  ),

                  Expanded(child: TabBarView(
                      controller: tabController,
                      children: [
                        tabBarBodyWidget(tripController, 'all'),
                        tabBarBodyWidget(tripController, 'ongoing'),
                        tabBarBodyWidget(tripController, 'cancelled'),
                        tabBarBodyWidget(tripController, 'completed'),
                        tabBarBodyWidget(tripController, 'returned')
                      ]
                  ))

                ]);
              }),
            ),
          ),
        ),
      ),
    );
  }

  Widget tabBarBodyWidget (TripController tripController, String filter){
    // GAP-018/GAP-020: uses controller.filteredTrips (status + search)
    final trips = tripController.filteredTrips;

    if (tripController.tripModel != null) {
      if (trips.isNotEmpty) {
        return RefreshIndicator(
          onRefresh: () => Get.find<TripController>().getTripList(1),
          child: SingleChildScrollView(
            controller: scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            child: PaginatedListWidget(
              scrollController: scrollController,
              totalSize: trips.length,
              offset: null,
              onPaginate: (int? offset) async {
                await tripController.getTripList(offset ?? 1);
              },
              itemView: Padding(
                padding: const EdgeInsets.only(bottom: 70.0),
                child: ListView.separated(
                  itemCount: trips.length,
                  padding: const EdgeInsets.all(0),
                  physics: const NeverScrollableScrollPhysics(),
                  shrinkWrap: true,
                  itemBuilder: (BuildContext context, int index) {
                    return TripItemView(tripDetails: trips[index]);
                  },
                  separatorBuilder: (BuildContext context, int index) => Divider(color: Theme.of(context).highlightColor.withValues(alpha:0.15)),
                ),
              ),
            ),
          ),
        );
      } else {
        return const NoDataWidget(title: 'no_trip_found');
      }
    }
    return const NotificationShimmer();
  }
}

