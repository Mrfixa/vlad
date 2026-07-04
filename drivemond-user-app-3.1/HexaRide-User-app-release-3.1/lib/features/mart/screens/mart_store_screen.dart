import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:shimmer/shimmer.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_product_model.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_order_tracking_screen.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_order_history_screen.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_favorites_screen.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_product_details_screen.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_payment_screen.dart';
import 'package:ride_sharing_user_app/features/mart/controllers/mart_controller.dart';
import 'package:ride_sharing_user_app/util/app_colors.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/features/location/view/pick_map_screen.dart';
import 'package:ride_sharing_user_app/features/location/controllers/location_controller.dart';
import 'package:ride_sharing_user_app/features/address/controllers/address_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';

class MartStoreScreen extends StatefulWidget {
  const MartStoreScreen({super.key});

  @override
  State<MartStoreScreen> createState() => _MartStoreScreenState();
}

class _MartStoreScreenState extends State<MartStoreScreen> {
  final TextEditingController _searchController = TextEditingController();
  bool _isOffline = false;
  Timer? _searchDebounce;
  String _selectedCategory = 'all';
  Future<void> Function() _loadProducts = () async {};

  MartController get _martController => Get.find<MartController>();

  @override
  void initState() {
    super.initState();
    // Ensure categories and products are loaded
    if (_martController.categories.isEmpty) {
      _martController.getCategories();
    }
    _martController.getProducts();
    _martController.getShelves();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 300), () {
      if (mounted) setState(() {});
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'vito_mart', showLogo: true),
      body: _isOffline ? _buildOfflineBody(context) : _buildBody(context),
      floatingActionButton: GetBuilder<MartController>(
        builder: (controller) => controller.cartItems.isNotEmpty
            ? FloatingActionButton.extended(
                onPressed: () {
                  HapticFeedback.mediumImpact();
                  _navigateToCart();
                },
                backgroundColor: Theme.of(context).primaryColor,
                icon: Icon(Icons.shopping_cart, color: Theme.of(context).colorScheme.onPrimary),
                label: Text(
                  '${'cart'.tr} (${controller.cartItemCount}) • \$${controller.cartTotal.toStringAsFixed(2)}',
                  style: textMedium.copyWith(color: Theme.of(context).colorScheme.onPrimary),
                ),
              )
            : const SizedBox.shrink(),
      ),
    );
  }

  Widget _buildOfflineBody(BuildContext context) {
    return Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
          color: AppColors.offlineWarning,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.wifi_off, color: Colors.white, size: Dimensions.iconSizeMedium),
              const SizedBox(width: Dimensions.paddingSizeExtraSmall),
              Text('you_are_offline'.tr, style: textMedium.copyWith(color: Colors.white)),
            ],
          ),
        ),
        Expanded(child: _buildBody(context)),
      ],
    );
  }

  Widget _buildBody(BuildContext context) {
    return Column(
      children: [
        _buildSearchBar(context),
        _buildCategoryFilter(context),
        _buildSortChips(context),
        Expanded(
          child: _buildAnimatedContent(context), // B12: animated switcher with loading handled inside
        ),
      ],
    );
  }

  static const List<(String, String)> _sortOptions = [
    ('default', 'recommended'),
    ('price_asc', 'price_low_to_high'),
    ('price_desc', 'price_high_to_low'),
    ('popular', 'most_popular'),
  ];

  Widget _buildSortChips(BuildContext context) {
    return GetBuilder<MartController>(
      builder: (controller) => SizedBox(
        height: 40,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
          itemCount: _sortOptions.length,
          itemBuilder: (context, index) {
            final (value, labelKey) = _sortOptions[index];
            return Padding(
              padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall, top: Dimensions.paddingSizeExtraSmall),
              child: ChoiceChip(
                label: Text(labelKey.tr, style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
                selected: controller.selectedSort == value,
                onSelected: (_) {
                  HapticFeedback.selectionClick();
                  controller.setSort(value);
                },
                selectedColor: Theme.of(context).primaryColor.withValues(alpha: 0.2),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildSearchBar(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'search_products'.tr,
                prefixIcon: const Icon(Icons.search),
                suffixIcon: AnimatedOpacity(
                  opacity: _searchController.text.isEmpty ? 0.0 : 1.0,
                  duration: const Duration(milliseconds: 200),
                  child: IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: _searchController.text.isEmpty ? null : () {
                      _searchDebounce?.cancel();
                      _searchController.clear();
                      setState(() {});
                    },
                  ),
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeDefault,
                  vertical: Dimensions.paddingSizeSmall,
                ),
              ),
            ),
          ),
          const SizedBox(width: Dimensions.paddingSizeSmall),
          // Entry point to the customer's mart order history.
          IconButton(
            tooltip: 'favorites'.tr,
            onPressed: () => Get.to(() => const MartFavoritesScreen()),
            icon: Icon(Icons.favorite_border, color: Theme.of(context).primaryColor),
          ),
          IconButton(
            tooltip: 'mart_order_history'.tr,
            onPressed: () => Get.to(() => const MartOrderHistoryScreen()),
            icon: Icon(Icons.receipt_long, color: Theme.of(context).primaryColor),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryFilter(BuildContext context) {
    return GetBuilder<MartController>(
      builder: (controller) {
        final categories = controller.categoryList;
        return SizedBox(
          height: 40,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
            itemCount: categories.length,
            itemBuilder: (context, index) {
              final category = categories[index];
              final isSelected = category == _selectedCategory;
              return Padding(
                padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
                child: FilterChip(
                  label: Text(category.tr),
                  selected: isSelected,
                  onSelected: (selected) {
                    HapticFeedback.selectionClick();
                    setState(() => _selectedCategory = category);
                    controller.setCategory(category);
                  },
                  selectedColor: Theme.of(context).primaryColor.withValues(alpha: 0.2),
                  checkmarkColor: Theme.of(context).primaryColor,
                ),
              );
            },
          ),
        );
      },
    );
  }

  // B12: AnimatedSwitcher keyed by category + live search query
  Widget _buildAnimatedContent(BuildContext context) {
    return GetBuilder<MartController>(
      builder: (controller) {
        final query = _searchController.text.trim().toLowerCase();
        
        // Convert products from model to map for filtering
        var filtered = controller.products.map((p) => p.toJson()).toList();
        
        if (_selectedCategory != 'all') {
          filtered = filtered.where((p) => p['category'] == _selectedCategory).toList();
        }

        if (query.isNotEmpty) {
          filtered = filtered
              .where((p) => (p['name']?.toString().toLowerCase() ?? '').contains(query))
              .toList();
        }

        // Shelves only make sense on the unfiltered default view.
        final showShelves = _selectedCategory == 'all' && query.isEmpty && controller.selectedSort == 'default' &&
            (controller.featuredProducts.isNotEmpty || controller.popularProducts.isNotEmpty);

        final stateKey = '${_selectedCategory}_${query}_${controller.selectedSort}';
        return AnimatedSwitcher(
          duration: const Duration(milliseconds: 200),
          transitionBuilder: (child, animation) => FadeTransition(opacity: animation, child: child),
          child: controller.isLoading
              ? _buildShimmerGrid(context)
              : filtered.isEmpty
                  ? _buildEmptyState(context, key: ValueKey('empty_$stateKey'))
                  : _buildProductGrid(context, filtered, showShelves: showShelves, key: ValueKey('grid_$stateKey')),
        );
      },
    );
  }

  // B18: shimmer skeleton loading grid
  Widget _buildShimmerGrid(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return GridView.builder(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 0.75,
        crossAxisSpacing: Dimensions.paddingSizeSmall,
        mainAxisSpacing: Dimensions.paddingSizeSmall,
      ),
      itemCount: 6,
      itemBuilder: (ctx, index) => Shimmer.fromColors(
        baseColor: isDark ? AppColors.shimmerBaseDark : AppColors.shimmerBaseLight,
        highlightColor: isDark ? AppColors.shimmerHighlightDark : AppColors.shimmerHighlightLight,
        child: Container(
          decoration: BoxDecoration(
            color: isDark ? AppColors.shimmerBaseDark : AppColors.shimmerBaseLight,
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          ),
        ),
      ),
    );
  }

  // B20: error state with retry
  Widget _buildErrorState(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.error_outline, size: 48, color: Theme.of(context).colorScheme.error),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text('something_went_wrong'.tr,
              style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault)),
          const SizedBox(height: Dimensions.paddingSizeExtraSmall),
          TextButton(onPressed: _loadProducts, child: Text('retry'.tr)),
        ],
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context, {Key? key}) {
    return Center(
      key: key,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.store_outlined,
            size: 80,
            color: Theme.of(context).hintColor.withValues(alpha: 0.3),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text(
            'no_products_available'.tr,
            style: textMedium.copyWith(
              fontSize: Dimensions.fontSizeLarge,
              color: Theme.of(context).hintColor,
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text(
            'check_back_later'.tr,
            style: textRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProductGrid(BuildContext context, List<Map<String, dynamic>> filtered,
      {bool showShelves = false, Key? key}) {
    // B17: RefreshIndicator on the product grid
    return RefreshIndicator(
      onRefresh: _loadProducts,
      color: Theme.of(context).primaryColor,
      child: CustomScrollView(
        key: key,
        slivers: [
          if (showShelves) ...[
            _buildShelf(context, 'featured_products', _martController.featuredProducts),
            _buildShelf(context, 'popular_products', _martController.popularProducts),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(Dimensions.paddingSizeDefault,
                    Dimensions.paddingSizeSmall, Dimensions.paddingSizeDefault, 0),
                child: Text('all_products'.tr,
                    style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
              ),
            ),
          ],
          SliverPadding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            sliver: SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.75,
                crossAxisSpacing: Dimensions.paddingSizeSmall,
                mainAxisSpacing: Dimensions.paddingSizeSmall,
              ),
              delegate: SliverChildBuilderDelegate(
                (context, index) => _ProductCard(
                  product: filtered[index],
                  isOffline: _isOffline,
                  onAdd: _addToCart,
                ),
                childCount: filtered.length,
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// A horizontal product shelf (Featured / Popular) shown above the main grid.
  Widget _buildShelf(BuildContext context, String titleKey, List<MartProductModel> items) {
    if (items.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());
    return SliverToBoxAdapter(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(Dimensions.paddingSizeDefault,
                Dimensions.paddingSizeSmall, Dimensions.paddingSizeDefault, Dimensions.paddingSizeExtraSmall),
            child: Text(titleKey.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
          ),
          SizedBox(
            height: 230,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
              itemCount: items.length,
              itemBuilder: (context, index) => SizedBox(
                width: 160,
                child: Padding(
                  padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
                  child: _ProductCard(
                    product: items[index].toJson(),
                    isOffline: _isOffline,
                    onAdd: _addToCart,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _addToCart(Map<String, dynamic> product) {
    // Items are always available — addToCart always succeeds.
    _martController.addToCart(product);
    showCustomSnackBar('item_added_to_cart'.tr, isError: false);
  }

  void _navigateToCart() {
    Get.to(() => MartCartScreen(cartItems: _martController.cartItems));
  }
}

// B14: Stateful product card with AnimatedScale + B13: CachedNetworkImage + B15: out-of-stock + B21: offline disable
class _ProductCard extends StatefulWidget {
  final Map<String, dynamic> product;
  final bool isOffline;
  final void Function(Map<String, dynamic>) onAdd;

  const _ProductCard({
    required this.product,
    required this.isOffline,
    required this.onAdd,
  });

  @override
  State<_ProductCard> createState() => _ProductCardState();
}

class _ProductCardState extends State<_ProductCard> {
  bool _isAdding = false;

  void _handleAdd() {
    if (widget.isOffline) {
      Get.snackbar('warning'.tr, 'you_are_offline'.tr);
      return;
    }
    setState(() => _isAdding = true);
    HapticFeedback.mediumImpact();
    widget.onAdd(widget.product);
    Future.delayed(const Duration(milliseconds: 100), () {
      if (mounted) setState(() => _isAdding = false);
    });
  }

  void _openDetails() {
    final id = widget.product['id']?.toString();
    if (id == null || id.isEmpty) return;
    Get.to(() => MartProductDetailsScreen(
          productId: id,
          initialProduct: MartProductModel.fromJson(Map<String, dynamic>.from(widget.product)),
          onAddToCart: widget.isOffline ? null : (_) => widget.onAdd(widget.product),
        ));
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = widget.product['image'] as String?;
    final product = MartProductModel.fromJson(
        Map<String, dynamic>.from(widget.product));
    final unit = product.unit;

    Widget card = Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // B13: product image (tap to view details)
          Expanded(
            flex: 3,
            child: InkWell(
              onTap: _openDetails,
              child: ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(Dimensions.radiusDefault),
                topRight: Radius.circular(Dimensions.radiusDefault),
              ),
              child: imageUrl != null && imageUrl.isNotEmpty
                  ? CachedNetworkImage(
                      imageUrl: imageUrl,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: Theme.of(context).hintColor.withValues(alpha: 0.1)),
                      errorWidget: (_, __, ___) => Container(
                        color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                        child: Center(
                          child: Icon(
                            Icons.inventory_2_outlined,
                            size: 40,
                            color: Theme.of(context).hintColor,
                          ),
                        ),
                      ),
                    )
                  : Container(
                      color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                      child: Center(
                        child: Icon(
                          Icons.inventory_2_outlined,
                          size: 40,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                    ),
            ),
            ),
          ),
          Expanded(
            flex: 2,
            child: Padding(
              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.product['name'] ?? '',
                    style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (unit != null && unit.isNotEmpty)
                    Text(
                      unit,
                      style: textRegular.copyWith(
                        fontSize: Dimensions.fontSizeExtraSmall,
                        color: Theme.of(context).hintColor,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  const Spacer(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      // Effective (sale) price, with the original struck through when on sale.
                      Flexible(
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Flexible(
                              child: Text(
                                '\$${product.effectivePrice.toStringAsFixed(2)}',
                                style: textBold.copyWith(
                                  fontSize: Dimensions.fontSizeDefault,
                                  color: Theme.of(context).primaryColor,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (product.onSale) ...[
                              const SizedBox(width: 4),
                              Flexible(
                                child: Text(
                                  '\$${product.price.toStringAsFixed(2)}',
                                  style: textRegular.copyWith(
                                    fontSize: Dimensions.fontSizeExtraSmall,
                                    color: Theme.of(context).hintColor,
                                    decoration: TextDecoration.lineThrough,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      // B14: AnimatedScale add button (items are always available)
                      AnimatedScale(
                        scale: _isAdding ? 0.88 : 1.0,
                        duration: const Duration(milliseconds: 100),
                        child: InkWell(
                          onTap: _handleAdd,
                          child: Opacity(
                            opacity: widget.isOffline ? 0.5 : 1.0,
                            child: Container(
                              padding: const EdgeInsets.all(4),
                              decoration: BoxDecoration(
                                color: Theme.of(context).primaryColor,
                                borderRadius:
                                    BorderRadius.circular(Dimensions.radiusSmall),
                              ),
                              child: Icon(Icons.add, color: Theme.of(context).colorScheme.onPrimary, size: Dimensions.iconSizeSmall),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );

    return card;
  }
}

class MartCartScreen extends StatefulWidget {
  final List<Map<String, dynamic>> cartItems;

  const MartCartScreen({super.key, required this.cartItems});

  @override
  State<MartCartScreen> createState() => _MartCartScreenState();
}

class _MartCartScreenState extends State<MartCartScreen> {
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _promoController = TextEditingController();
  bool _isOrdering = false;
  bool _isApplyingPromo = false;
  double _discount = 0.0;
  String? _appliedPromoCode;
  double _tipAmount = 0.0;
  double? _deliveryLat;
  double? _deliveryLng;

  // B25: payment method state
  String _paymentMethod = 'cash';

  // B27: checkout error state
  String? _checkoutError;

  MartController get _martController => Get.find<MartController>();

  final List<double> _tipOptions = [0, 2, 5, 10];

  double get _subtotal {
    double total = 0;
    for (final item in widget.cartItems) {
      final price = double.tryParse(item['price']?.toString() ?? '0') ?? 0.0;
      total += price * (item['quantity'] as int? ?? 1);
    }
    return total;
  }

  double get _totalAmount => _subtotal - _discount + _tipAmount + _martController.deliveryFee;

  @override
  void dispose() {
    _addressController.dispose();
    _notesController.dispose();
    _promoController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'cart'.tr),
      body: widget.cartItems.isEmpty
          ? _buildEmptyCart(context)
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                    children: [
                      // B22: Dismissible cart items
                      ...List.generate(widget.cartItems.length,
                          (index) => _buildCartItem(context, index)),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      _buildPromoSection(context),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      _buildTipSection(context),
                    ],
                  ),
                ),
                _buildOrderSummary(context),
              ],
            ),
    );
  }

  Widget _buildEmptyCart(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.shopping_cart_outlined,
            size: 80,
            color: Theme.of(context).hintColor.withValues(alpha: 0.3),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text(
            'cart_is_empty'.tr,
            style: textMedium.copyWith(
              fontSize: Dimensions.fontSizeLarge,
              color: Theme.of(context).hintColor,
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          TextButton.icon(
            onPressed: () => Get.back(),
            icon: Icon(Icons.storefront_outlined, color: Theme.of(context).primaryColor),
            label: Text(
              'browse_products'.tr,
              style: textMedium.copyWith(color: Theme.of(context).primaryColor),
            ),
          ),
        ],
      ),
    );
  }

  // B22: Dismissible + product image in cart
  Widget _buildCartItem(BuildContext context, int index) {
    final item = widget.cartItems[index];
    final imageUrl = item['image'] as String?;

    return Dismissible(
      key: Key(item['id']?.toString() ?? item['product_id']?.toString() ?? '$index'),
      direction: DismissDirection.endToStart,
      background: Builder(
        builder: (ctx) => Container(
          alignment: Alignment.centerRight,
          padding: const EdgeInsets.only(right: 16),
          color: Theme.of(ctx).colorScheme.error,
          child: const Icon(Icons.delete_outline, color: Colors.white),
        ),
      ),
      confirmDismiss: (_) async {
        return await Get.dialog<bool>(
              AlertDialog(
                title: Text('remove_item'.tr),
                content: Text('remove_item_confirmation'.tr),
                actions: [
                  TextButton(onPressed: () => Get.back(result: false), child: Text('no'.tr)),
                  TextButton(onPressed: () => Get.back(result: true), child: Text('yes'.tr)),
                ],
              ),
            ) ??
            false;
      },
      onDismissed: (_) {
        setState(() {
          final id = item['id'];
          widget.cartItems.removeWhere((e) => e['id'] == id);
          _appliedPromoCode = null;
          _discount = 0.0;
          _promoController.clear();
        });
      },
      child: Card(
        margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
        child: ListTile(
          leading: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Theme.of(context).hintColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
            ),
            child: imageUrl != null && imageUrl.isNotEmpty
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    child: CachedNetworkImage(
                      imageUrl: imageUrl,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: Theme.of(context).hintColor.withValues(alpha: 0.1)),
                      errorWidget: (_, __, ___) =>
                          Icon(Icons.inventory_2_outlined, color: Theme.of(context).hintColor),
                    ),
                  )
                : Icon(Icons.inventory_2_outlined, color: Theme.of(context).hintColor),
          ),
          title: Text(item['name'] ?? '', style: textMedium),
          subtitle: Text(
            '\$${item['price'] ?? '0.00'}',
            style: textRegular.copyWith(color: Theme.of(context).primaryColor),
          ),
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              IconButton(
                onPressed: () {
                  setState(() {
                    if ((item['quantity'] ?? 1) > 1) {
                      item['quantity'] = (item['quantity'] ?? 1) - 1;
                    } else {
                      widget.cartItems.removeAt(index);
                    }
                    _appliedPromoCode = null;
                    _discount = 0.0;
                    _promoController.clear();
                  });
                },
                icon: const Icon(Icons.remove_circle_outline),
              ),
              Text('${item['quantity'] ?? 1}', style: textMedium),
              IconButton(
                onPressed: () {
                  final current = item['quantity'] as int? ?? 1;
                  if (current < 100) {
                    setState(() {
                      item['quantity'] = current + 1;
                      _appliedPromoCode = null;
                      _discount = 0.0;
                      _promoController.clear();
                    });
                  }
                },
                icon: const Icon(Icons.add_circle_outline),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPromoSection(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('promo_code'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            if (_appliedPromoCode != null) ...[
              Container(
                padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.tertiary.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                ),
                child: Row(
                  children: [
                    Icon(Icons.check_circle, color: Theme.of(context).colorScheme.tertiary, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${'promo_applied'.tr}: $_appliedPromoCode (-\$${_discount.toStringAsFixed(2)})',
                        style: textMedium.copyWith(
                            color: Theme.of(context).colorScheme.tertiary,
                            fontSize: Dimensions.fontSizeSmall),
                      ),
                    ),
                    IconButton(
                      onPressed: () {
                        setState(() {
                          _appliedPromoCode = null;
                          _discount = 0.0;
                          _promoController.clear();
                        });
                      },
                      icon: const Icon(Icons.close, size: 18),
                    ),
                  ],
                ),
              ),
            ] else ...[
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _promoController,
                      decoration: InputDecoration(
                        hintText: 'enter_promo_code'.tr,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: Dimensions.paddingSizeSmall,
                          vertical: Dimensions.paddingSizeSmall,
                        ),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: Dimensions.paddingSizeSmall),
                  SizedBox(
                    height: 40,
                    child: ElevatedButton(
                      onPressed: _isApplyingPromo ? null : _applyPromoCode,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Theme.of(context).primaryColor,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                        ),
                      ),
                      child: _isApplyingPromo
                          ? SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Theme.of(context).colorScheme.onPrimary))
                          : Text('apply'.tr,
                              style: textMedium.copyWith(
                                  color: Theme.of(context).colorScheme.onPrimary,
                                  fontSize: Dimensions.fontSizeSmall)),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildTipSection(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('tip_driver'.tr,
                style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Text(
              'show_appreciation'.tr,
              style: textRegular.copyWith(
                  color: Theme.of(context).hintColor,
                  fontSize: Dimensions.fontSizeSmall),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Row(
              children: _tipOptions.map((tip) {
                final isSelected = _tipAmount == tip;
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeThree),
                    child: GestureDetector(
                      onTap: () {
                        HapticFeedback.selectionClick();
                        setState(() => _tipAmount = tip);
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          color: isSelected
                              ? Theme.of(context).primaryColor
                              : Theme.of(context).primaryColor.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                          border: Border.all(
                            color: isSelected
                                ? Theme.of(context).primaryColor
                                : Theme.of(context).primaryColor.withValues(alpha: 0.3),
                          ),
                        ),
                        child: Center(
                          child: Text(
                            tip == 0 ? 'no_tip'.tr : '\$${tip.toInt()}',
                            style: textMedium.copyWith(
                              color:
                                  isSelected ? Theme.of(context).colorScheme.onPrimary : Theme.of(context).primaryColor,
                              fontSize: Dimensions.fontSizeSmall,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderSummary(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).hintColor.withValues(alpha: 0.12),
            blurRadius: 10,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  controller: _addressController,
                  readOnly: true,
                  onTap: _pickAddressOnMap,
                  decoration: InputDecoration(
                    hintText: 'delivery_address'.tr,
                    prefixIcon: const Icon(Icons.location_on_outlined),
                    suffixIcon: _deliveryLat != null
                        ? const Icon(Icons.gps_fixed, color: AppColors.successGreen, size: 18)
                        : const Icon(Icons.map_outlined, size: 18),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: Dimensions.paddingSizeSmall),
              SizedBox(
                height: 56,
                child: IconButton.outlined(
                  tooltip: 'saved_addresses'.tr,
                  icon: const Icon(Icons.bookmark_border),
                  onPressed: _pickSavedAddress,
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          TextField(
            controller: _notesController,
            decoration: InputDecoration(
              hintText: 'order_notes'.tr,
              prefixIcon: const Icon(Icons.notes),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),

          // B25: payment method selector
          Align(
            alignment: Alignment.centerLeft,
            child: Text('payment_method'.tr,
                style: textBold.copyWith(fontSize: 14)),
          ),
          const SizedBox(height: 4),
          ...['cash', 'card', 'wallet'].map((method) => RadioListTile<String>(
                value: method,
                groupValue: _paymentMethod,
                onChanged: (v) => setState(() => _paymentMethod = v!),
                title: Text(method == 'cash'
                    ? 'cash_on_delivery'.tr
                    : method == 'card'
                        ? 'card'.tr
                        : 'wallet'.tr),
                contentPadding: EdgeInsets.zero,
                dense: true,
              )),
          const SizedBox(height: Dimensions.paddingSizeSmall),

          // Price breakdown
          _buildPriceLine('subtotal'.tr, _subtotal),
          if (_discount > 0) _buildPriceLine('discount'.tr, -_discount, isDiscount: true),
          _buildPriceLine('delivery_fee'.tr, _martController.deliveryFee),
          if (_tipAmount > 0) _buildPriceLine('tip'.tr, _tipAmount),
          const Divider(),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('total'.tr,
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
              Text(
                '\$${_totalAmount.toStringAsFixed(2)}',
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge,
                  color: Theme.of(context).primaryColor,
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),

          // B27: checkout error banner
          if (_checkoutError != null)
            Container(
              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
              margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.error.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
              ),
              child: Row(
                children: [
                  Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error, size: 16),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _checkoutError!,
                      style: textRegular.copyWith(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: Dimensions.fontSizeSmall,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, size: 16),
                    onPressed: () => setState(() => _checkoutError = null),
                  ),
                ],
              ),
            ),

          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isOrdering
                  ? null
                  : () {
                      HapticFeedback.mediumImpact();
                      _placeOrder();
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: Theme.of(context).primaryColor,
                foregroundColor: Theme.of(context).colorScheme.onPrimary,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
              ),
              child: _isOrdering
                  ? SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Theme.of(context).colorScheme.onPrimary),
                    )
                  : Text('place_order'.tr,
                      style: textBold.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                          color: Theme.of(context).colorScheme.onPrimary)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPriceLine(String label, double amount, {bool isDiscount = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
          Text(
            '${isDiscount ? '-' : ''}\$${amount.abs().toStringAsFixed(2)}',
            style: textMedium.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: isDiscount ? Theme.of(context).colorScheme.tertiary : null,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _applyPromoCode() async {
    final code = _promoController.text.trim();
    if (code.isEmpty) return;

    setState(() => _isApplyingPromo = true);

    try {
      final response = await Get.find<ApiClient>().postData(
        AppConstants.martApplyPromo,
        {'code': code, 'subtotal': _subtotal},
      );

      if (!mounted) return;
      if (response.statusCode == 200 && response.body['data'] != null) {
        final rawDiscount = response.body['data']?['discount'];
        if (rawDiscount is! num || (rawDiscount as num) < 0) {
          Get.snackbar('error'.tr, 'invalid_promo_code'.tr);
          return;
        }
        setState(() {
          _discount = rawDiscount.toDouble();
          _appliedPromoCode = code;
          _promoController.clear();
        });
      } else {
        // Surface the backend reason (expired, min-spend, invalid) when present.
        Get.snackbar('error'.tr, _extractErrorMessage(response.body) == 'order_failed'.tr
            ? 'invalid_promo_code'.tr
            : _extractErrorMessage(response.body));
      }
    } catch (e) {
      debugPrint('Mart error: $e');
      Get.snackbar('error'.tr, 'promo_validation_failed'.tr);
    } finally {
      if (mounted) setState(() => _isApplyingPromo = false);
    }
  }

  /// Opens the same map picker the ride/address flows use (pin confirm +
  /// Places search + current-location FAB) and stores the confirmed
  /// human-readable address with its coordinates.
  void _pickAddressOnMap() {
    Get.to(() => PickMapScreen(
      type: LocationType.location,
      onLocationPicked: (Position position, String address) {
        // Reject the "null island" (0,0) fix — a GPS/emulator glitch, not a
        // real location — rather than silently sending it to the backend.
        if (position.latitude == 0.0 && position.longitude == 0.0) {
          Get.snackbar('error'.tr, 'location_fetch_failed'.tr);
          return;
        }
        if (mounted) {
          setState(() {
            _addressController.text = address;
            _deliveryLat = position.latitude;
            _deliveryLng = position.longitude;
          });
        }
      },
    ));
  }

  /// Quick pick from the customer's saved addresses (home/work/etc.).
  void _pickSavedAddress() {
    final addressController = Get.find<AddressController>();
    if (addressController.addressList == null) {
      addressController.getAddressList(1);
    }
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(Dimensions.radiusLarge)),
      ),
      builder: (ctx) => SafeArea(
        child: GetBuilder<AddressController>(
          builder: (controller) {
            final addresses = controller.addressList ?? [];
            if (addresses.isEmpty) {
              return Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
                child: Text('no_data_found'.tr, style: textRegular, textAlign: TextAlign.center),
              );
            }
            return ListView.separated(
              shrinkWrap: true,
              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
              itemCount: addresses.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (_, index) {
                final address = addresses[index];
                return ListTile(
                  leading: Icon(
                    address.addressLabel == 'home' ? Icons.home_outlined
                        : address.addressLabel == 'office' ? Icons.work_outline
                        : Icons.place_outlined,
                    color: Theme.of(context).primaryColor,
                  ),
                  title: Text((address.addressLabel ?? '').tr, style: textMedium),
                  subtitle: Text(address.address ?? '', maxLines: 2, overflow: TextOverflow.ellipsis, style: textRegular),
                  onTap: () {
                    setState(() {
                      _addressController.text = address.address ?? '';
                      _deliveryLat = address.latitude;
                      _deliveryLng = address.longitude;
                    });
                    Navigator.of(ctx).pop();
                  },
                );
              },
            );
          },
        ),
      ),
    );
  }

  Future<void> _placeOrder() async {
    if (widget.cartItems.isEmpty) {
      Get.snackbar('error'.tr, 'cart_is_empty'.tr);
      return;
    }
    if (_addressController.text.trim().isEmpty) {
      Get.snackbar('error'.tr, 'please_enter_delivery_address'.tr);
      return;
    }

    // FIX 2: check wallet balance before submitting a wallet order
    if (_paymentMethod == 'wallet') {
      final profileController = Get.find<ProfileController>();
      final walletBalance =
          profileController.profileModel?.data?.wallet?.walletBalance ?? 0.0;
      if (walletBalance < _totalAmount) {
        showCustomSnackBar('insufficient_wallet_balance'.tr);
        return;
      }
    }

    if (_isOrdering) return; // guard against double submit

    setState(() {
      _isOrdering = true;
      _checkoutError = null;
    });

    final items = widget.cartItems
        .map((item) => {
              'product_id': item['id'],
              'quantity': item['quantity'] ?? 1,
            })
        .toList();

    // Use service layer through MartController
    final result = await _martController.createOrder(
      items: items,
      deliveryAddress: _addressController.text,
      notes: _notesController.text,
      paymentMethod: _paymentMethod,
      deliveryLat: _deliveryLat,
      deliveryLng: _deliveryLng,
      tipAmount: _tipAmount > 0 ? _tipAmount : null,
      promoCode: _appliedPromoCode,
    );

    if (!mounted) return;

    if (result.success) {
      Get.back();
      // Clear the cart after successful order placement
      _martController.clearCart();
      Get.snackbar('success'.tr, 'order_placed_successfully'.tr);
      if (_paymentMethod == 'card') {
        // FIX 1: use the backend-computed total, not the locally computed one
        final paymentTotal = result.serverTotal > 0 ? result.serverTotal : _totalAmount;
        Get.to(() => MartPaymentScreen(orderId: result.orderId!, totalAmount: paymentTotal));
      } else {
        Get.to(() => MartOrderTrackingScreen(orderId: result.orderId!));
      }
    } else {
      setState(() => _checkoutError = result.error);
    }

    if (mounted) setState(() => _isOrdering = false);
  }

  // Pulls a human-readable message out of any backend error shape.
  String _extractErrorMessage(dynamic body) {
    try {
      if (body is Map) {
        final errors = body['errors'];
        if (errors is List && errors.isNotEmpty) {
          final first = errors.first;
          if (first is Map && first['message'] != null) {
            return first['message'].toString();
          }
        }
        if (body['message'] is String && (body['message'] as String).isNotEmpty) {
          return body['message'];
        }
      }
    } catch (_) {/* fall through to default */}
    return 'order_failed'.tr;
  }
}
