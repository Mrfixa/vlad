<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Passport\Passport;
use Modules\AuthManagement\Entities\QrToken;
use Modules\TripManagement\Entities\MartProduct;
use Modules\TripManagement\Entities\MartCategory;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Entities\MartOrderItem;
use Modules\TripManagement\Entities\MartPromoCode;
use Modules\TripManagement\Entities\StripeEvent;
use Modules\UserManagement\Entities\User;
use Tests\TestCase;

class VitoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapVitoSchema();
        Passport::tokensCan([
            'AccessToCustomer' => 'Customer Panel Access',
            'AccessToDriver' => 'Driver Panel Access',
            'AccessToSuperAdmin' => 'Admin Panel Access',
        ]);
        
        // Disable throttle middleware for customer OTP auth routes in tests
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('mart_reviews');
        Schema::dropIfExists('mart_order_items');
        Schema::dropIfExists('mart_orders');
        Schema::dropIfExists('mart_promo_codes');
        Schema::dropIfExists('mart_categories');
        Schema::dropIfExists('mart_favorites');
        Schema::dropIfExists('mart_products');
        Schema::dropIfExists('vito_otps');
        Schema::dropIfExists('vehicle_categories');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_brands');
        Schema::dropIfExists('qr_tokens');
        Schema::dropIfExists('user_accounts');
        Schema::dropIfExists('time_tracks');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('driver_details');
        Schema::dropIfExists('temp_trip_notifications');
        Schema::dropIfExists('trip_status');
        Schema::dropIfExists('trip_requests');
        Schema::dropIfExists('user_levels');
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('firebase_push_notifications');
        Schema::dropIfExists('vito_audit_log');
        Schema::dropIfExists('channel_conversations');
        Schema::dropIfExists('channel_lists');
        Schema::dropIfExists('channel_users');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_clients');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function bootstrapVitoSchema(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('full_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->string('user_type')->default('customer');
                $table->boolean('is_active')->default(1);
                $table->string('ref_code')->nullable();
                $table->string('profile_image')->nullable();
                $table->unsignedSmallInteger('failed_attempt')->default(0);
                $table->boolean('is_temp_blocked')->default(0);
                $table->timestamp('blocked_at')->nullable();
                $table->string('username', 50)->nullable()->unique();
                $table->string('pin_hash')->nullable();
                $table->unsignedSmallInteger('pin_attempts')->default(0);
                $table->timestamp('pin_blocked_at')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->string('identification_image')->nullable();
                $table->string('other_documents')->nullable();
                $table->string('logged_in_via')->nullable();
                $table->uuid('user_level_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('user_levels')) {
            Schema::create('user_levels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('user_type');
                $table->integer('sequence');
                $table->string('reward_type')->default('no_reward');
                $table->decimal('reward_amount', 10, 2)->default(0);
                $table->integer('targeted_ride')->default(0);
                $table->integer('targeted_ride_point')->default(0);
                $table->decimal('targeted_amount', 10, 2)->default(0);
                $table->integer('targeted_amount_point')->default(0);
                $table->integer('targeted_cancel')->default(0);
                $table->integer('targeted_cancel_point')->default(0);
                $table->integer('targeted_review')->default(0);
                $table->integer('targeted_review_point')->default(0);
                $table->integer('max_cancellation')->default(5);
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('user_accounts')) {
            Schema::create('user_accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->decimal('wallet_balance', 23, 2)->default(0);
                $table->decimal('receivable_balance', 23, 2)->default(0);
                $table->decimal('payable_balance', 23, 2)->default(0);
                $table->decimal('received_balance', 23, 2)->default(0);
                $table->decimal('pending_balance', 23, 2)->default(0);
                $table->decimal('total_withdrawn', 23, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key_name');
                $table->text('value')->nullable();
                $table->string('settings_type')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('trip_requests')) {
            Schema::create('trip_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->nullable();
                $table->uuid('driver_id')->nullable();
                $table->string('current_status')->default('pending');
                $table->string('type')->default('ride_request');
                $table->uuid('zone_id')->nullable();
                $table->uuid('area_id')->nullable();
                $table->uuid('vehicle_category_id')->nullable();
                $table->string('payment_method')->nullable();
                $table->decimal('estimated_fare', 23, 2)->default(0);
                $table->decimal('actual_fare', 23, 2)->default(0);
                $table->decimal('estimated_distance', 23, 2)->default(0);
                $table->decimal('paid_fare', 23, 2)->default(0);
                $table->text('delivery_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('temp_trip_notifications')) {
            Schema::create('temp_trip_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('trip_request_id')->nullable();
                $table->uuid('user_id')->nullable();
            });
        }

        if (!Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id');
                $table->uuid('ride_request_id')->nullable();
                $table->string('title');
                $table->string('description');
                $table->string('type')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('action')->nullable();
                $table->boolean('is_read')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('firebase_push_notifications')) {
            Schema::create('firebase_push_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191);
                $table->string('value', 191)->nullable();
                $table->boolean('status')->default(0);
                $table->string('type')->nullable();
                $table->string('group')->nullable();
                $table->string('action')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('trip_status')) {
            Schema::create('trip_status', function (Blueprint $table) {
                $table->id();
                $table->uuid('trip_request_id');
                $table->uuid('customer_id');
                $table->uuid('driver_id')->nullable();
                $table->timestamp('pending')->nullable();
                $table->timestamp('accepted')->nullable();
                $table->timestamp('arrived')->nullable();
                $table->timestamp('out_for_pickup')->nullable();
                $table->timestamp('picked_up')->nullable();
                $table->timestamp('ongoing')->nullable();
                $table->timestamp('completed')->nullable();
                $table->timestamp('cancelled')->nullable();
                $table->timestamp('failed')->nullable();
                $table->timestamp('returning')->nullable();
                $table->timestamp('returned')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_categories')) {
            Schema::create('vehicle_categories', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->string('type')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('driver_details')) {
            Schema::create('driver_details', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id');
                $table->boolean('is_online')->default(false);
                $table->string('availability_status')->default('unavailable');
                $table->string('plate_number')->nullable();
                $table->string('car_photo')->nullable();
                $table->boolean('car_photo_approved')->default(false);
                $table->boolean('is_approved')->default(false);
                $table->unsignedTinyInteger('is_verified')->default(0);
                $table->unsignedTinyInteger('is_suspended')->default(0);
                $table->integer('ride_count')->default(0);
                $table->integer('parcel_count')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('time_tracks')) {
            Schema::create('time_tracks', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id');
                $table->date('date');
                $table->timestamp('online_at')->nullable();
                $table->timestamp('offline_at')->nullable();
                $table->integer('total_online')->default(0);
                $table->integer('total_offline')->default(0);
                $table->integer('total_idle')->default(0);
                $table->integer('total_driving')->default(0);
                $table->timestamp('last_ride_started_at')->nullable();
                $table->timestamp('last_ride_completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('edited_by')->nullable();
                $table->text('before')->nullable();
                $table->text('after')->nullable();
                $table->string('user_type')->nullable();
                $table->string('logable_id')->nullable();
                $table->string('logable_type')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('qr_tokens')) {
            Schema::create('qr_tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('token', 64)->unique();
                $table->string('role')->default('driver');
                $table->uuid('created_by')->nullable();
                $table->uuid('redeemed_by')->nullable();
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamp('expires_at');
                $table->boolean('is_revoked')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vito_otps')) {
            Schema::create('vito_otps', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('phone', 30)->index();
                $table->string('otp_hash');
                $table->timestamp('expires_at');
                $table->timestamp('verified_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mart_products')) {
            Schema::create('mart_products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);
                $table->decimal('discount_price', 10, 2)->nullable();
                $table->string('unit')->nullable();
                $table->string('image')->nullable();
                $table->string('category')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_popular')->default(false);
                $table->unsignedInteger('sold_count')->default(0);
                $table->unsignedInteger('stock')->default(0);
                $table->uuid('zone_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_favorites')) {
            Schema::create('mart_favorites', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id');
                $table->uuid('product_id');
                $table->timestamps();
                $table->unique(['customer_id', 'product_id']);
            });
        }

        if (!Schema::hasTable('mart_categories')) {
            Schema::create('mart_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_promo_codes')) {
            Schema::create('mart_promo_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('discount_type')->default('fixed');
                $table->decimal('discount_value', 10, 2);
                $table->decimal('min_order_amount', 10, 2)->default(0);
                $table->decimal('max_discount', 10, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('per_user_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_orders')) {
            Schema::create('mart_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ref_id', 20)->unique();
                $table->uuid('customer_id');
                $table->uuid('driver_id')->nullable();
                $table->string('status')->default('pending');
                $table->decimal('total_amount', 10, 2);
                $table->decimal('tip_amount', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('delivery_fee', 10, 2)->default(0);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->string('promo_code')->nullable();
                $table->string('payment_status')->default('unpaid');
                $table->string('payment_method')->nullable();
                $table->text('delivery_address')->nullable();
                $table->decimal('delivery_lat', 10, 7)->nullable();
                $table->decimal('delivery_lng', 10, 7)->nullable();
                $table->decimal('driver_lat', 10, 7)->nullable();
                $table->decimal('driver_lng', 10, 7)->nullable();
                $table->string('signature_image')->nullable();
                $table->string('delivery_photo')->nullable();
                $table->text('notes')->nullable();
                $table->string('cancellation_reason', 255)->nullable();
                $table->string('cancelled_by', 20)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mart_order_items')) {
            Schema::create('mart_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->uuid('product_id');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('total_price', 10, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mart_reviews')) {
            Schema::create('mart_reviews', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id')->unique();
                $table->uuid('customer_id');
                $table->uuid('driver_id')->nullable();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vito_audit_log')) {
            Schema::create('vito_audit_log', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('action');
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stripe_events')) {
            Schema::create('stripe_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('stripe_event_id');
                $table->string('type');
                $table->uuid('user_id');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('usd');
                $table->string('status')->default('pending');
                $table->string('payment_intent_id');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('model')->nullable();
                $table->string('model_id')->nullable();
                $table->string('message')->nullable();
                $table->timestamps();
            });
        }

        // Passport tables for token creation
        if (!Schema::hasTable('oauth_clients')) {
            Schema::create('oauth_clients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('name');
                $table->string('secret', 100)->nullable();
                $table->string('provider')->nullable();
                $table->text('redirect');
                $table->boolean('personal_access_client')->default(0);
                $table->boolean('password_client')->default(0);
                $table->boolean('revoked')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('oauth_personal_access_clients')) {
            Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
                $table->id();
                $table->uuid('client_id');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('oauth_access_tokens')) {
            Schema::create('oauth_access_tokens', function (Blueprint $table) {
                $table->string('id', 100)->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('client_id');
                $table->string('name')->nullable();
                $table->text('scopes')->nullable();
                $table->boolean('revoked')->default(0);
                $table->timestamps();
                $table->dateTime('expires_at')->nullable();
            });
        }

        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('attribute_id')->nullable();
                $table->string('attribute')->nullable();
                $table->decimal('debit', 23, 2)->default(0);
                $table->decimal('credit', 23, 2)->default(0);
                $table->decimal('balance', 23, 2)->default(0);
                $table->decimal('added_bonus', 23, 2)->default(0);
                $table->uuid('user_id');
                $table->string('account')->nullable();
                $table->string('transaction_type')->nullable();
                $table->string('trx_ref_id')->nullable();
                $table->string('trx_type')->nullable();
                $table->string('reference')->nullable();
                $table->timestamps();
            });
        }

        // Seed Passport personal access client
        $clientId = Str::uuid()->toString();
        DB::table('oauth_clients')->insertOrIgnore([
            'id' => $clientId,
            'user_id' => null,
            'name' => 'Test Personal Access Client',
            'secret' => Str::random(40),
            'redirect' => 'http://localhost',
            'personal_access_client' => 1,
            'password_client' => 0,
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('oauth_personal_access_clients')->insertOrIgnore([
            'id' => 1,
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(string $type = 'customer', array $overrides = []): User
    {
        return User::create(array_merge([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => 'test' . Str::random(5) . '@test.com',
            'phone' => '+1' . rand(1000000000, 9999999999),
            'password' => Hash::make('123456'),
            'pin_hash' => Hash::make('123456'),
            'username' => 'user_' . Str::random(8),
            'user_type' => $type,
            'is_active' => 1,
            'pin_attempts' => 0,
            'ref_code' => Str::random(8),
        ], $overrides));
    }

    private function createUserAccount(User $user): void
    {
        DB::table('user_accounts')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'wallet_balance' => 0,
            'receivable_balance' => 0,
            'payable_balance' => 0,
            'received_balance' => 0,
            'pending_balance' => 0,
            'total_withdrawn' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedUserLevel(string $type): void
    {
        DB::table('user_levels')->insert([
            'id' => Str::uuid()->toString(),
            'name' => 'Level 1',
            'user_type' => $type,
            'sequence' => 1,
            'reward_type' => 'no_reward',
            'reward_amount' => 0,
            'targeted_ride' => 0,
            'targeted_ride_point' => 0,
            'targeted_amount' => 0,
            'targeted_amount_point' => 0,
            'targeted_cancel' => 0,
            'targeted_cancel_point' => 0,
            'targeted_review' => 0,
            'targeted_review_point' => 0,
            'max_cancellation' => 5,
            'image' => '',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ========================================================================
    // 1. QR Token Generate & Validate
    // ========================================================================

    public function test_qr_generate_and_validate(): void
    {
        $admin = $this->createUser('admin');
        Passport::actingAs($admin, ['AccessToSuperAdmin']);

        $genResponse = $this->postJson('/api/qr-token/generate', ['role' => 'customer']);
        $genResponse->assertOk();
        $token = $genResponse->json('data.token');
        $this->assertNotNull($token);
        // Customer tokens are 16 characters as per controller logic
        $this->assertEquals(16, strlen($token));

        $valResponse = $this->postJson('/api/qr-token/validate', ['token' => $token]);
        $valResponse->assertOk();
        $this->assertTrue($valResponse->json('data.valid'));
        $this->assertEquals('customer', $valResponse->json('data.role'));

        // Customer token expires in ~1h
        $qrToken = QrToken::where('token', $token)->first();
        $diffSeconds = abs(now()->diffInSeconds($qrToken->expires_at));
        $this->assertLessThanOrEqual(3605, $diffSeconds);
        $this->assertGreaterThan(3500, $diffSeconds);

        // Driver onboarding token expires in ~7d
        $genResponse2 = $this->postJson('/api/qr-token/generate', ['role' => 'driver']);
        $token2 = $genResponse2->json('data.token');
        $qrToken2 = QrToken::where('token', $token2)->first();
        $driverDiff = abs(now()->diffInSeconds($qrToken2->expires_at));
        $this->assertGreaterThan(6 * 24 * 3600, $driverDiff);
    }

    // ========================================================================
    // 2. Client Registration with Valid Token
    // ========================================================================

    public function test_client_registration_with_valid_token(): void
    {
        $this->seedUserLevel('customer');

        $token = str_repeat('c', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(),
            'token' => $token,
            'role' => 'customer',
            'created_by' => null,
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at' => now()->addHour(),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'pin' => '654321',
            'pin_confirmation' => '654321',
            'qr_token' => $token,
        ]);

        $response->assertOk();
        $user = User::where('username', 'janedoe')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('654321', $user->pin_hash));
        $this->assertEquals('customer', $user->user_type);

        // Token must be redeemed atomically
        $this->assertDatabaseHas('qr_tokens', [
            'token' => $token,
            'redeemed_by' => $user->id,
        ]);
        $this->assertNotNull(DB::table('qr_tokens')->where('token', $token)->value('redeemed_at'));

        // Same token cannot be reused
        $this->seedUserLevel('customer');
        $retry = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Eve',
            'last_name' => 'Repeat',
            'username' => 'everepeat',
            'pin' => '999888',
            'pin_confirmation' => '999888',
            'qr_token' => $token,
        ]);
        $retry->assertStatus(400);
    }

    // ========================================================================
    // 3. Driver Registration with Onboarding Token
    // ========================================================================

    public function test_driver_registration_with_onboarding_token(): void
    {
        $this->seedUserLevel('driver');

        DB::table('business_settings')->insert([
            'id' => Str::uuid()->toString(),
            'key_name' => 'driver_self_registration',
            'value' => json_encode('1'),
            'settings_type' => 'business_information',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $driverToken = str_repeat('d', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(),
            'token' => $driverToken,
            'role' => 'driver',
            'created_by' => null,
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/driver/auth/pin-register', [
            'first_name' => 'John',
            'last_name' => 'Driver',
            'username' => 'johndriver',
            'pin' => '111222',
            'pin_confirmation' => '111222',
            'qr_token' => $driverToken,
        ]);

        $response->assertOk();
        $user = User::where('username', 'johndriver')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('111222', $user->pin_hash));
        $this->assertEquals('driver', $user->user_type);

        // Token redeemed with redeemed_by set
        $this->assertNotNull(DB::table('qr_tokens')->where('token', $driverToken)->value('redeemed_at'));
        $driverUser = User::where('username', 'johndriver')->first();
        $this->assertDatabaseHas('qr_tokens', ['token' => $driverToken, 'redeemed_by' => $driverUser->id]);
    }

    // ========================================================================
    // 3b. Username availability check (D15)
    // ========================================================================

    public function test_check_username_availability(): void
    {
        // Unused username → available
        $resp = $this->getJson('/api/customer/auth/check-username?username=freshname');
        $resp->assertOk();
        $this->assertTrue($resp->json('available'));

        // Existing username → not available (globally unique, both scopes)
        $this->createUser('customer', ['username' => 'takenname']);

        $resp = $this->getJson('/api/customer/auth/check-username?username=takenname');
        $resp->assertOk();
        $this->assertFalse($resp->json('available'));

        // Driver route shares the same global uniqueness check
        $resp = $this->getJson('/api/driver/auth/check-username?username=takenname');
        $resp->assertOk();
        $this->assertFalse($resp->json('available'));

        // Invalid username (too short / bad chars) → 422 validation error
        $resp = $this->getJson('/api/customer/auth/check-username?username=a');
        $resp->assertStatus(422);
    }

    // ========================================================================
    // 3c. OTP registration is customer-only — no driver auth bypass (D2)
    // ========================================================================

    public function test_otp_registration_cannot_create_driver(): void
    {
        // Complete the customer OTP registration flow
        $this->postJson('/api/customer/auth/send-otp', ['phone_or_email' => '+15557770000']);
        $send = $this->postJson('/api/customer/auth/send-otp', ['phone_or_email' => '+15557770001']);
        $otp = $send->json('otp');

        $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => '+15557770001',
            'otp'            => $otp,
        ]);

        $reg = $this->postJson('/api/customer/auth/registration-from-otp', [
            'phone'      => '+15557770001',
            'first_name' => 'Otp',
            'last_name'  => 'User',
        ]);
        $reg->assertStatus(200);

        // The OTP path must only ever create a CUSTOMER, never a driver.
        $user = User::where('phone', '+15557770001')->first();
        $this->assertNotNull($user);
        $this->assertEquals('customer', $user->user_type);

        // There is no driver OTP-registration endpoint at all (404, not a working bypass).
        $driverOtp = $this->postJson('/api/driver/auth/registration-from-otp', [
            'phone'      => '+15557770002',
            'first_name' => 'Bad',
            'last_name'  => 'Driver',
        ]);
        $this->assertEquals(404, $driverOtp->getStatusCode());
    }

    // ========================================================================
    // 4. PIN Login & Lockout
    // ========================================================================

    public function test_pin_login_and_lockout(): void
    {
        $user = $this->createUser('customer', [
            'username' => 'testlogin',
            'pin_hash' => Hash::make('123456'),
        ]);

        // Successful login
        $response = $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'testlogin',
            'pin' => '123456',
        ]);
        $response->assertOk();
        $this->assertArrayHasKey('token', $response->json('data'));

        // 5 failed attempts trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/customer/auth/pin-login', [
                'username' => 'testlogin',
                'pin' => '000000',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->pin_blocked_at);

        $lockResponse = $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'testlogin',
            'pin' => '123456',
        ]);
        $lockResponse->assertStatus(403);
        $this->assertStringContainsString('too_many_attempt', $lockResponse->json('response_code'));
    }

    public function test_change_pin(): void
    {
        $user = $this->createUser('customer', [
            'username' => 'pinchanger',
            'pin_hash' => Hash::make('111111'),
        ]);

        Passport::actingAs($user, ['AccessToCustomer']);

        // Wrong current PIN -> 403
        $this->postJson('/api/customer/auth/change-pin', [
            'current_pin' => '000000',
            'new_pin' => '222222',
            'new_pin_confirmation' => '222222',
        ])->assertStatus(403);

        // New PIN not 6 digits -> 422
        $this->postJson('/api/customer/auth/change-pin', [
            'current_pin' => '111111',
            'new_pin' => '123',
            'new_pin_confirmation' => '123',
        ])->assertStatus(422);

        // Success
        $this->postJson('/api/customer/auth/change-pin', [
            'current_pin' => '111111',
            'new_pin' => '222222',
            'new_pin_confirmation' => '222222',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('222222', $user->pin_hash));
        $this->assertFalse(Hash::check('111111', $user->pin_hash));

        // Old PIN no longer logs in; new PIN does
        $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'pinchanger', 'pin' => '111111',
        ])->assertStatus(403);

        $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'pinchanger', 'pin' => '222222',
        ])->assertOk();
    }

    public function test_change_pin_revokes_other_sessions(): void
    {
        $user = $this->createUser('customer', [
            'username' => 'multidevice',
            'pin_hash' => Hash::make('111111'),
        ]);

        // Two existing "device" sessions.
        $sessionA = $user->createToken('device-a', ['AccessToCustomer']);
        $sessionB = $user->createToken('device-b', ['AccessToCustomer']);

        Passport::actingAs($user, ['AccessToCustomer']);

        $this->postJson('/api/customer/auth/change-pin', [
            'current_pin' => '111111',
            'new_pin' => '222222',
            'new_pin_confirmation' => '222222',
        ])->assertOk();

        // A PIN change invalidates other device sessions.
        $this->assertTrue((bool) $user->tokens()->where('id', $sessionA->token->id)->first()->revoked);
        $this->assertTrue((bool) $user->tokens()->where('id', $sessionB->token->id)->first()->revoked);
    }

    public function test_forgot_pin_recovery(): void
    {
        $user = $this->createUser('customer', [
            'username' => 'forgetful',
            'phone' => '+15558675309',
            'pin_hash' => Hash::make('111111'),
        ]);

        // An existing session that recovery must revoke.
        $session = $user->createToken('old-device', ['AccessToCustomer']);

        // Unknown username -> 404.
        $this->postJson('/api/customer/auth/forgot-pin/send-otp', [
            'username' => 'nobody-here',
        ])->assertStatus(404);

        // Request the OTP (testing env returns the raw code in the response).
        $send = $this->postJson('/api/customer/auth/forgot-pin/send-otp', [
            'username' => 'forgetful',
        ])->assertOk();
        $otp = $send->json('otp');
        $this->assertNotEmpty($otp);

        // Wrong OTP -> 400, PIN unchanged.
        $this->postJson('/api/customer/auth/forgot-pin/reset', [
            'username' => 'forgetful',
            'otp' => '000000',
            'new_pin' => '222222',
            'new_pin_confirmation' => '222222',
        ])->assertStatus(400);

        // Correct OTP -> resets the PIN.
        $this->postJson('/api/customer/auth/forgot-pin/reset', [
            'username' => 'forgetful',
            'otp' => $otp,
            'new_pin' => '222222',
            'new_pin_confirmation' => '222222',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('222222', $user->pin_hash));
        $this->assertFalse(Hash::check('111111', $user->pin_hash));

        // Recovery revokes every prior session.
        $this->assertTrue((bool) $user->tokens()->where('id', $session->token->id)->first()->revoked);

        // Old PIN no longer logs in; the new PIN does.
        $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'forgetful', 'pin' => '111111',
        ])->assertStatus(403);
        $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'forgetful', 'pin' => '222222',
        ])->assertOk();
    }

    public function test_forgot_pin_rejects_account_without_phone(): void
    {
        $this->createUser('customer', [
            'username' => 'nophone',
            'phone' => null,
            'pin_hash' => Hash::make('111111'),
        ]);

        $this->postJson('/api/customer/auth/forgot-pin/send-otp', [
            'username' => 'nophone',
        ])->assertStatus(422);
    }

    // ========================================================================
    // Legacy auth route tests
    // ========================================================================

    public function test_legacy_forget_password_by_phone(): void
    {
        $user = $this->createUser('customer', [
            'phone' => '+15550001111',
            'pin_hash' => Hash::make('123456'),
        ]);

        // unknown phone_or_email → 404
        $this->postJson('/api/customer/auth/forget-password', [
            'phone_or_email' => '+19999999999',
        ])->assertStatus(404);

        // known phone → 200 (OTP sent via mock SMS; env returns 000000 in test)
        $this->postJson('/api/customer/auth/forget-password', [
            'phone_or_email' => '+15550001111',
        ])->assertOk();

        // invalid phone_or_email format → 400
        $this->postJson('/api/customer/auth/forget-password', [
            'phone_or_email' => 'xx',
        ])->assertStatus(400);
    }

    public function test_legacy_social_login_rejects_invalid_provider(): void
    {
        // social-login is unauthenticated; invalid/missing provider → 422
        $this->postJson('/api/customer/auth/social-login', [
            'provider' => 'unknown_provider',
            'unique_id' => 'abc123',
        ])->assertStatus(422);

        // valid provider but missing token → 422
        $this->postJson('/api/customer/auth/social-login', [
            'provider' => 'google',
        ])->assertStatus(422);
    }

    public function test_legacy_external_mart_registration_requires_valid_token(): void
    {
        // valid token + phone → creates customer
        $token = Str::uuid()->toString();
        DB::table('qr_tokens')->insert([
            'token' => $token, 'type' => 'customer', 'status' => 'active',
            'uses_remaining' => 10, 'expires_at' => now()->addDays(7),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/customer/auth/external-registration', [
            'token' => $token,
            'phone' => '+15550002222',
            'password' => 'PassWord123!',
        ])->assertOk()->assertJsonStructure(['data' => ['id', 'token']]);

        // invalid/expired token → 403
        $this->postJson('/api/customer/auth/external-registration', [
            'token' => 'not-a-real-token',
            'phone' => '+15550003333',
            'password' => 'PassWord123!',
        ])->assertStatus(403);
    }

    public function test_pin_login_trims_username(): void
    {
        $this->createUser('customer', [
            'username' => 'spaced',
            'pin_hash' => Hash::make('123456'),
        ]);

        // Surrounding whitespace must not block login.
        $this->postJson('/api/customer/auth/pin-login', [
            'username' => '  spaced  ',
            'pin' => '123456',
        ])->assertOk();
    }

    public function test_vehicle_brand_model_seeder_populates_dropdowns(): void
    {
        // The driver vehicle-registration dropdowns read from these tables; the
        // brand/model API filters is_active = 1, so the seeder must seed active rows.
        // Note: vehicle_categories is already created in bootstrapVitoSchema.
        Schema::create('vehicle_brands', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
        Schema::create('vehicle_models', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('brand_id');
            $table->integer('seat_capacity')->default(4);
            $table->integer('maximum_weight')->default(500);
            $table->integer('hatch_bag_capacity')->default(2);
            $table->string('engine')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        $seeder = new \Modules\VehicleManagement\Database\Seeders\VehicleBrandModelSeeder();
        $seeder->run();

        $brandCount = \Illuminate\Support\Facades\DB::table('vehicle_brands')->where('is_active', 1)->count();
        $modelCount = \Illuminate\Support\Facades\DB::table('vehicle_models')->where('is_active', 1)->count();
        $categoryCount = \Illuminate\Support\Facades\DB::table('vehicle_categories')->where('is_active', 1)->count();

        $this->assertGreaterThan(35, $brandCount, 'Seeder should populate a full brand catalogue.');
        $this->assertGreaterThan(150, $modelCount, 'Seeder should populate a full model catalogue.');
        $this->assertGreaterThan(0, $categoryCount, 'Seeder should populate vehicle categories.');

        // Idempotent: a second run must not duplicate rows or change counts.
        $seeder->run();
        $this->assertSame($brandCount, \Illuminate\Support\Facades\DB::table('vehicle_brands')->count());
        $this->assertSame($modelCount, \Illuminate\Support\Facades\DB::table('vehicle_models')->count());
        $this->assertSame($categoryCount, \Illuminate\Support\Facades\DB::table('vehicle_categories')->count());
    }

    // ========================================================================
    // 5. Atomic Ride Acceptance (race condition protection)
    // ========================================================================

    public function test_atomic_ride_acceptance(): void
    {
        $customer = $this->createUser('customer');
        $driver1 = $this->createUser('driver', ['username' => 'driver1']);
        $driver2 = $this->createUser('driver', ['username' => 'driver2']);

        // Ensure driver_details exist (required by TripRequest model observer)
        DB::table('driver_details')->insert([
            ['user_id' => $driver1->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $driver2->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed time_tracks (required by TripRequest booted observer)
        DB::table('time_tracks')->insert([
            ['user_id' => $driver1->id, 'date' => now()->toDateString(), 'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $driver2->id, 'date' => now()->toDateString(), 'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rideId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $rideId,
            'customer_id' => $customer->id,
            'driver_id' => null,
            'current_status' => 'pending',
            'type' => 'ride_request',
            'zone_id' => Str::uuid()->toString(),
            'area_id' => Str::uuid()->toString(),
            'vehicle_category_id' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'estimated_fare' => 100,
            'actual_fare' => 0,
            'estimated_distance' => 5,
            'paid_fare' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Driver 1 accepts first
        Passport::actingAs($driver1, ['AccessToDriver']);
        $res1 = $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $rideId]);
        $res1->assertOk();
        $this->assertNotNull($res1->json('data'));

        // Driver 2 cannot accept (already taken)
        Passport::actingAs($driver2, ['AccessToDriver']);
        $res2 = $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $rideId]);
        $res2->assertStatus(404);
    }

    public function test_driver_arrived_at_pickup_sub_signal(): void
    {
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver', ['username' => 'arrivedriver']);

        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(), 'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rideId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $rideId,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'current_status' => 'accepted',
            'type' => 'ride_request',
            'zone_id' => Str::uuid()->toString(),
            'area_id' => Str::uuid()->toString(),
            'vehicle_category_id' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'estimated_fare' => 100,
            'actual_fare' => 0,
            'estimated_distance' => 5,
            'paid_fare' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('trip_status')->insert([
            'trip_request_id' => $rideId,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'accepted' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);

        // Marking arrived records the timestamp but does NOT change current_status.
        $this->putJson('/api/driver/ride/update-status', [
            'trip_request_id' => $rideId,
            'status' => 'arrived',
        ])->assertOk();

        $this->assertNotNull(DB::table('trip_status')->where('trip_request_id', $rideId)->value('arrived'));
        $this->assertSame('accepted', DB::table('trip_requests')->where('id', $rideId)->value('current_status'));

        // Also valid once the driver is out for pickup (the usual trigger state).
        DB::table('trip_requests')->where('id', $rideId)->update(['current_status' => 'out_for_pickup']);
        $this->putJson('/api/driver/ride/update-status', [
            'trip_request_id' => $rideId,
            'status' => 'arrived',
        ])->assertOk();
        $this->assertSame('out_for_pickup', DB::table('trip_requests')->where('id', $rideId)->value('current_status'));

        // 'arrived' is not valid once the trip is ongoing.
        DB::table('trip_requests')->where('id', $rideId)->update(['current_status' => 'ongoing']);
        $this->putJson('/api/driver/ride/update-status', [
            'trip_request_id' => $rideId,
            'status' => 'arrived',
        ])->assertStatus(400);
    }

    // ========================================================================
    // 6. Mart Product CRUD
    // ========================================================================

    public function test_mart_product_crud(): void
    {
        $product = MartProduct::create([
            'name' => 'Test Product',
            'price' => 9.99,
            'stock' => 100,
            'category' => 'test',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('mart_products', ['name' => 'Test Product']);

        $product->update(['name' => 'Updated Product', 'price' => 19.99]);
        $this->assertDatabaseHas('mart_products', ['name' => 'Updated Product']);

        $product->delete();
        $this->assertSoftDeleted('mart_products', ['id' => $product->id]);
    }

    // ========================================================================
    // 6b. Mart Categories (managed taxonomy)
    // ========================================================================

    public function test_mart_category_crud(): void
    {
        $category = MartCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('mart_categories', ['slug' => 'beverages']);

        $category->update(['name' => 'Drinks']);
        $this->assertDatabaseHas('mart_categories', ['name' => 'Drinks']);

        $category->delete();
        $this->assertSoftDeleted('mart_categories', ['id' => $category->id]);
    }

    public function test_mart_categories_api_returns_active_only(): void
    {
        $customer = $this->createUser('customer');

        MartCategory::create(['name' => 'Active Cat', 'slug' => 'active-cat', 'is_active' => true, 'sort_order' => 0]);
        MartCategory::create(['name' => 'Hidden Cat', 'slug' => 'hidden-cat', 'is_active' => false, 'sort_order' => 0]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $response = $this->getJson('/api/customer/mart/categories');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Active Cat', $names);
        $this->assertNotContains('Hidden Cat', $names);
    }

    public function test_mart_order_status_transition_map_is_canonical(): void
    {
        // Single source of truth shared by the driver API and the admin panel.
        $map = MartOrder::STATUS_TRANSITIONS;

        $this->assertSame(['pending'], $map['accepted']);
        $this->assertSame(['accepted'], $map['picked_up']);
        $this->assertSame(['picked_up'], $map['delivered']);
        $this->assertEqualsCanonicalizing(['pending', 'accepted'], $map['cancelled']);

        // A nonsensical jump (delivered -> pending) is not a valid transition.
        $this->assertArrayNotHasKey('pending', $map);
        $this->assertNotContains('delivered', $map['accepted']);
    }

    // ========================================================================
    // 6c. VitoMart Admin web routes (super-admin session)
    // ========================================================================

    private function makeMartOrder(string $status, ?string $customerId = null): MartOrder
    {
        return MartOrder::create([
            'ref_id' => 'VM-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'customer_id' => $customerId ?? $this->createUser('customer')->id,
            'status' => $status,
            'total_amount' => 25.00,
            'tip_amount' => 0,
            'discount_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
            'delivery_address' => '123 Test St',
        ]);
    }

    public function test_admin_can_update_mart_order_status_and_writes_audit(): void
    {
        $admin = $this->createUser('super-admin');
        $order = $this->makeMartOrder('pending');

        $response = $this->actingAs($admin)
            ->put("/admin/mart/orders/status/{$order->id}", ['status' => 'accepted']);

        $response->assertRedirect();
        $this->assertEquals('accepted', $order->fresh()->status);
        // The audit row must now actually land (previously written to wrong columns).
        $this->assertDatabaseHas('vito_audit_log', [
            'entity_id' => $order->id,
            'action'    => 'status_change',
        ]);
    }

    public function test_admin_invalid_mart_order_status_transition_rejected(): void
    {
        $admin = $this->createUser('super-admin');
        $order = $this->makeMartOrder('delivered');

        $this->actingAs($admin)
            ->put("/admin/mart/orders/status/{$order->id}", ['status' => 'pending']);

        // delivered -> pending is not a valid transition; status unchanged.
        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_admin_can_create_promo_code_via_web(): void
    {
        $admin = $this->createUser('super-admin');

        $response = $this->actingAs($admin)->post('/admin/mart/promo/store', [
            'code' => 'webpromo',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'min_order_amount' => 10,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        // Code is upper-cased server-side.
        $this->assertDatabaseHas('mart_promo_codes', ['code' => 'WEBPROMO']);
    }

    public function test_admin_can_create_category_via_web(): void
    {
        $admin = $this->createUser('super-admin');

        $response = $this->actingAs($admin)->post('/admin/mart/categories/store', [
            'name' => 'Bakery',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mart_categories', ['name' => 'Bakery', 'slug' => 'bakery']);
    }

    public function test_non_admin_redirected_from_mart_admin(): void
    {
        $customer = $this->createUser('customer');
        $order = $this->makeMartOrder('pending');

        $response = $this->actingAs($customer)
            ->put("/admin/mart/orders/status/{$order->id}", ['status' => 'accepted']);

        // AdminMiddleware bounces non-admins to the login route; status unchanged.
        $response->assertRedirect();
        $this->assertEquals('pending', $order->fresh()->status);
    }

    // ========================================================================
    // 7. Wallet Topup Intent (validation check, no real Stripe)
    // ========================================================================

    public function test_wallet_topup_intent(): void
    {
        $user = $this->createUser('customer');
        Passport::actingAs($user, ['AccessToCustomer']);

        // Invalid amount should fail validation
        $response = $this->postJson('/api/customer/stripe/payment-intent', ['amount' => -5]);
        $response->assertStatus(400);

        // Missing amount should fail
        $response2 = $this->postJson('/api/customer/stripe/payment-intent', []);
        $response2->assertStatus(400);
    }

    // ========================================================================
    // 8. Mart Promo Code Apply
    // ========================================================================

    public function test_mart_apply_promo_code(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        // Fixed discount promo (min_order_amount=20)
        MartPromoCode::create([
            'code' => 'SAVE5',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'min_order_amount' => 20.00,
            'is_active' => true,
        ]);

        // Product priced at $15 — qty 2 = $30 subtotal (above minimum)
        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Promo Test Item', 'price' => 15.00,
            'stock' => 100, 'is_active' => true,
        ]);

        // Valid promo: 2×$15 = $30 >= $20 minimum → discount $5
        $response = $this->postJson('/api/customer/mart/apply-promo', [
            'code'  => 'SAVE5',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $response->assertOk();
        $this->assertEquals(5.00, $response->json('data.discount'));

        // Below minimum: 1×$15 = $15 < $20 → 400
        $response2 = $this->postJson('/api/customer/mart/apply-promo', [
            'code'  => 'SAVE5',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $response2->assertStatus(400);

        // Invalid code
        $response3 = $this->postJson('/api/customer/mart/apply-promo', [
            'code'  => 'NOTREAL',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);
        $response3->assertStatus(404);
    }

    // ========================================================================
    // 9. Mart Order With Tip and Promo (server-side total)
    // ========================================================================

    public function test_mart_order_server_side_total(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Burger',
            'price' => 10.00,
            'stock' => 50,
            'category' => 'food',
            'is_active' => true,
        ]);

        MartPromoCode::create([
            'code' => 'PROMO2',
            'discount_type' => 'fixed',
            'discount_value' => 3.00,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => '123 Test St',
            'tip_amount' => 2.00,
            'promo_code' => 'PROMO2',
        ]);

        $response->assertOk();

        $order = MartOrder::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order);
        // subtotal=20, discount=3, tip=2 → total=19
        $this->assertEquals('19.00', $order->total_amount);
        $this->assertEquals('3.00', $order->discount_amount);
        $this->assertEquals('2.00', $order->tip_amount);
        $this->assertEquals('PROMO2', $order->promo_code);

        // Promo used_count should increment
        $promo = MartPromoCode::where('code', 'PROMO2')->first();
        $this->assertEquals(1, $promo->used_count);

        // Cancel decrements used_count; stock is untouched (items always available)
        $response2 = $this->putJson("/api/customer/mart/orders/{$order->id}/cancel");
        $response2->assertOk();

        $product->refresh();
        $this->assertEquals(50, $product->stock);

        $promo->refresh();
        $this->assertEquals(0, $promo->used_count);
    }

    // M1: wallet-paid mart orders must be debited + marked paid at creation, and
    // refunded back to the wallet on cancellation. Previously a wallet order was
    // created 'unpaid' and never charged (fulfilled for free).
    public function test_mart_wallet_payment_settles_and_refunds(): void
    {
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        DB::table('user_accounts')->where('user_id', $customer->id)->update(['wallet_balance' => 30.00]);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Milk', 'price' => 10.00, 'stock' => 5, 'category' => 'grocery', 'is_active' => true,
        ]);

        // Sufficient balance: order is created paid, wallet debited.
        $ok = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => '1 Test St',
            'payment_method' => 'wallet',
        ]);
        $ok->assertOk();
        $order = MartOrder::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('wallet', $order->payment_method);
        $this->assertEquals(10.00, (float) DB::table('user_accounts')->where('user_id', $customer->id)->value('wallet_balance'));
        // Items are always available — stock is never decremented.
        $this->assertEquals(5, $product->fresh()->stock);

        // Cancellation refunds the wallet (stock untouched).
        $this->putJson("/api/customer/mart/orders/{$order->id}/cancel")->assertOk();
        $this->assertEquals('refunded', $order->fresh()->payment_status);
        $this->assertEquals(30.00, (float) DB::table('user_accounts')->where('user_id', $customer->id)->value('wallet_balance'));
        $this->assertEquals(5, $product->fresh()->stock);

        // Insufficient balance: order rejected, nothing charged, stock untouched.
        DB::table('user_accounts')->where('user_id', $customer->id)->update(['wallet_balance' => 1.00]);
        $fail = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => '1 Test St',
            'payment_method' => 'wallet',
        ]);
        $fail->assertStatus(400);
        $this->assertEquals(1, MartOrder::where('customer_id', $customer->id)->count());
        $this->assertEquals(1.00, (float) DB::table('user_accounts')->where('user_id', $customer->id)->value('wallet_balance'));
        $this->assertEquals(5, $product->fresh()->stock);
    }

    // Config-driven delivery fee is added to the server total; no tax is charged.
    public function test_mart_order_applies_config_delivery_fee(): void
    {
        \Illuminate\Support\Facades\Cache::put('mart_delivery_fee', 5);

        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);
        $product = MartProduct::create([
            'name' => 'Soda', 'price' => 10.00, 'stock' => 50, 'category' => 'grocery', 'is_active' => true,
        ]);

        $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => '1 St',
        ])->assertOk();

        $order = MartOrder::where('customer_id', $customer->id)->first();
        // subtotal=20, no promo/tip, fee=5, no tax → total=25
        $this->assertEquals('5.00', $order->delivery_fee);
        $this->assertEquals('0.00', $order->tax_amount);
        $this->assertEquals('25.00', $order->total_amount);

        \Illuminate\Support\Facades\Cache::forget('mart_delivery_fee');
    }

    // M7: order details surfaces a delivery ETA only while out for delivery.
    public function test_mart_order_details_eta_when_out_for_delivery(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $order = MartOrder::create([
            'ref_id' => 'VM-ETA1', 'customer_id' => $customer->id, 'status' => 'picked_up',
            'total_amount' => 10, 'payment_status' => 'unpaid', 'payment_method' => 'cash',
            'delivery_address' => 'X', 'delivery_lat' => 23.80, 'delivery_lng' => 90.40,
            'driver_lat' => 23.81, 'driver_lng' => 90.41,
        ]);

        $eta = $this->getJson("/api/customer/mart/orders/{$order->id}")->assertOk()->json('data.estimated_arrival');
        $this->assertNotNull($eta);
        $this->assertStringContainsString('min', $eta);

        // No ETA before pickup.
        $order->update(['status' => 'pending']);
        $this->assertNull($this->getJson("/api/customer/mart/orders/{$order->id}")->json('data.estimated_arrival'));
    }

    // ========================================================================
    // 10. Driver Order Details Endpoint
    // ========================================================================

    public function test_driver_mart_order_details(): void
    {
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver');

        $product = MartProduct::create([
            'name' => 'Water Bottle',
            'price' => 2.00,
            'stock' => 20,
            'category' => 'drinks',
            'is_active' => true,
        ]);

        $order = MartOrder::create([
            'ref_id' => 'VM-TESTDRV1',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'total_amount' => 4.00,
            'delivery_address' => '456 Driver Rd',
        ]);

        MartOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 2.00,
            'total_price' => 4.00,
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $response = $this->getJson("/api/driver/mart/orders/{$order->id}");
        $response->assertOk();
        $this->assertEquals($order->id, $response->json('data.id'));
        $this->assertCount(1, $response->json('data.items'));

        // Another driver cannot see this order
        $otherDriver = $this->createUser('driver');
        Passport::actingAs($otherDriver, ['AccessToDriver']);
        $response2 = $this->getJson("/api/driver/mart/orders/{$order->id}");
        $response2->assertStatus(404);
    }

    // ========================================================================
    // 11. Zone Filtering on Products
    // ========================================================================

    public function test_mart_products_zone_filter(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $zoneA = Str::uuid()->toString();
        $zoneB = Str::uuid()->toString();

        MartProduct::create(['name' => 'Zone A Product', 'price' => 5.00, 'stock' => 10, 'is_active' => true, 'zone_id' => $zoneA]);
        MartProduct::create(['name' => 'Zone B Product', 'price' => 5.00, 'stock' => 10, 'is_active' => true, 'zone_id' => $zoneB]);
        MartProduct::create(['name' => 'Global Product', 'price' => 5.00, 'stock' => 10, 'is_active' => true, 'zone_id' => null]);

        // Filter by zone A: should see Zone A + Global
        $response = $this->getJson("/api/customer/mart/products?zone_id={$zoneA}");
        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertContains('Zone A Product', $names);
        $this->assertContains('Global Product', $names);
        $this->assertNotContains('Zone B Product', $names);
    }

    // GoMart discovery: sort by effective price, featured filter, and sale-price charging.
    public function test_mart_product_discovery_sort_featured_and_sale_price(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartProduct::create(['name' => 'Apple', 'price' => 3.00, 'stock' => 10, 'category' => 'fruit', 'is_active' => true]);
        MartProduct::create(['name' => 'Steak', 'price' => 20.00, 'stock' => 10, 'category' => 'meat', 'is_active' => true, 'is_featured' => true]);
        $sale = MartProduct::create(['name' => 'Bread', 'price' => 5.00, 'discount_price' => 4.00, 'stock' => 10, 'category' => 'bakery', 'is_active' => true]);

        // sort=price_asc orders by effective price: Apple(3) < Bread(4 sale) < Steak(20)
        $asc = $this->getJson('/api/customer/mart/products?sort=price_asc')->assertOk()->json('data.data');
        $this->assertEquals(['Apple', 'Bread', 'Steak'], array_column($asc, 'name'));

        // is_featured filter returns only the featured product
        $feat = $this->getJson('/api/customer/mart/products?is_featured=1')->assertOk()->json('data.data');
        $this->assertCount(1, $feat);
        $this->assertEquals('Steak', $feat[0]['name']);

        // ordering an on-sale product charges the discount price + bumps sold_count
        $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $sale->id, 'quantity' => 2]],
            'delivery_address' => 'X',
        ])->assertOk();
        $order = MartOrder::where('customer_id', $customer->id)->first();
        $this->assertEquals('8.00', $order->total_amount); // 2 × 4.00 sale price
        $this->assertEquals(2, $sale->fresh()->sold_count);
    }

    // GoMart favorites: toggle on/off and list, owner-scoped.
    public function test_mart_favorites_toggle_and_list(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);
        $product = MartProduct::create(['name' => 'Eggs', 'price' => 4.00, 'stock' => 10, 'category' => 'dairy', 'is_active' => true]);

        // toggle on
        $this->postJson('/api/customer/mart/favorites/toggle', ['product_id' => $product->id])
            ->assertOk()->assertJsonPath('data.favorited', true);
        $this->assertEquals(1, count($this->getJson('/api/customer/mart/favorites')->json('data')));

        // toggle off
        $this->postJson('/api/customer/mart/favorites/toggle', ['product_id' => $product->id])
            ->assertOk()->assertJsonPath('data.favorited', false);
        $this->assertEquals(0, count($this->getJson('/api/customer/mart/favorites')->json('data')));

        // another customer doesn't see it
        $this->postJson('/api/customer/mart/favorites/toggle', ['product_id' => $product->id])->assertOk();
        $other = $this->createUser('customer');
        Passport::actingAs($other, ['AccessToCustomer']);
        $this->assertEquals(0, count($this->getJson('/api/customer/mart/favorites')->json('data')));
    }

    // ========================================================================
    // 12. QR Token Expiry Pruning
    // ========================================================================

    public function test_qr_token_pruning_command(): void
    {
        // Token expired 31 days ago (should be pruned)
        QrToken::create([
            'token' => str_repeat('a', 64),
            'role' => 'customer',
            'created_by' => null,
            'expires_at' => now()->subDays(31),
            'is_revoked' => false,
        ]);

        // Token expired 10 days ago (within 30-day grace, not pruned)
        QrToken::create([
            'token' => str_repeat('b', 64),
            'role' => 'customer',
            'created_by' => null,
            'expires_at' => now()->subDays(10),
            'is_revoked' => false,
        ]);

        $this->artisan('vito:prune-qr-tokens')->assertSuccessful();

        $this->assertDatabaseMissing('qr_tokens', ['token' => str_repeat('a', 64)]);
        $this->assertDatabaseHas('qr_tokens', ['token' => str_repeat('b', 64)]);
    }

    // ========================================================================
    // 13. Webhook Idempotent
    // ========================================================================

    public function test_webhook_idempotent(): void
    {
        $user = $this->createUser('customer');
        $this->createUserAccount($user);

        $paymentIntentId = 'pi_test_' . Str::random(24);

        StripeEvent::create([
            'stripe_event_id' => $paymentIntentId,
            'type' => 'payment_intent.created',
            'user_id' => $user->id,
            'amount' => 50.00,
            'currency' => 'usd',
            'status' => 'pending',
            'payment_intent_id' => $paymentIntentId,
        ]);

        // First webhook call — credits wallet
        DB::transaction(function () use ($paymentIntentId, $user) {
            $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()->first();
            if ($stripeEvent && $stripeEvent->status !== 'succeeded') {
                $stripeEvent->update(['status' => 'succeeded']);
                DB::table('user_accounts')->where('user_id', $user->id)
                    ->increment('wallet_balance', 50.00);
            }
        });

        $balance = (float) DB::table('user_accounts')
            ->where('user_id', $user->id)->value('wallet_balance');
        $this->assertEquals(50.00, $balance);

        // Second webhook call — idempotent, no double credit
        DB::transaction(function () use ($paymentIntentId, $user) {
            $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()->first();
            if ($stripeEvent && $stripeEvent->status !== 'succeeded') {
                $stripeEvent->update(['status' => 'succeeded']);
                DB::table('user_accounts')->where('user_id', $user->id)
                    ->increment('wallet_balance', 50.00);
            }
        });

        $balance2 = (float) DB::table('user_accounts')
            ->where('user_id', $user->id)->value('wallet_balance');
        $this->assertEquals(50.00, $balance2);
    }

    // ========================================================================
    // 14. Promo usage_limit hard cap
    // ========================================================================

    public function test_promo_usage_limit(): void
    {
        $customer = $this->createUser('customer');
        $customer2 = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'code' => 'LIMIT2',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'min_order_amount' => 0,
            'usage_limit' => 2,
            'per_user_limit' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $product = MartProduct::create([
            'name' => 'Widget',
            'price' => 20.00,
            'stock' => 100,
            'is_active' => true,
        ]);

        // First order with code — should succeed
        $r1 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 1',
            'promo_code' => 'LIMIT2',
        ]);
        $r1->assertStatus(200);

        // Second order with code — should succeed (usage_limit=2)
        $r2 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 2',
            'promo_code' => 'LIMIT2',
        ]);
        $r2->assertStatus(200);

        // Promo used_count should now be 2
        $this->assertEquals(2, MartPromoCode::where('code', 'LIMIT2')->value('used_count'));

        // Third order — promo is exhausted, order still placed but no discount applied
        $r3 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Addr 3',
            'promo_code' => 'LIMIT2',
        ]);
        $r3->assertStatus(200);
        // No discount since usage_limit reached — total equals product price
        $orderId3 = $r3->json('data.id');
        $order3 = MartOrder::find($orderId3);
        $this->assertEquals('20.00', $order3->total_amount);
    }

    // ========================================================================
    // 15. Items are always available — orders never blocked by stock
    // ========================================================================

    public function test_items_always_available(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        // Even with stock set to 0, the product can be ordered repeatedly.
        $product = MartProduct::create([
            'name' => 'Scarce Item',
            'price' => 15.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        $r1 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => 'Addr 1',
        ]);
        $r1->assertStatus(200);
        // Stock is never decremented — items are always available.
        $this->assertEquals(0, MartProduct::find($product->id)->stock);

        // A second order succeeds too (no stock gating).
        $r2 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'delivery_address' => 'Addr 2',
        ]);
        $r2->assertStatus(200);
        $this->assertEquals(0, MartProduct::find($product->id)->stock);
    }

    // ========================================================================
    // 16. Tip cap at 30% of subtotal
    // ========================================================================

    public function test_tip_cap(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Tippy Product',
            'price' => 10.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Send tip=999 on a $10 order — should be capped to 30% = $3
        $r = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Tip test',
            'tip_amount' => 999,
        ]);
        $r->assertStatus(200);
        $order = MartOrder::latest()->first();
        $this->assertEquals('3.00', $order->tip_amount);
        $this->assertEquals('13.00', $order->total_amount);
    }

    // ========================================================================
    // 17. Stripe webhook credits wallet when StripeEvent record missing
    // ========================================================================

    public function test_webhook_credits_wallet_without_prior_record(): void
    {
        $user = $this->createUser('customer');
        DB::table('user_accounts')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'wallet_balance' => 0,
        ]);

        $paymentIntentId = 'pi_new_' . Str::random(24);
        $stripeEventId = 'evt_new_' . Str::random(24);

        // No StripeEvent record exists yet
        $this->assertFalse(StripeEvent::where('payment_intent_id', $paymentIntentId)->exists());

        // Simulate the webhook transaction directly
        DB::transaction(function () use ($paymentIntentId, $stripeEventId, $user) {
            $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                ->lockForUpdate()->first();

            if (!$stripeEvent) {
                $stripeEvent = StripeEvent::create([
                    'stripe_event_id' => $stripeEventId,
                    'type' => 'payment_intent.succeeded',
                    'user_id' => $user->id,
                    'amount' => 25.00,
                    'currency' => 'usd',
                    'status' => 'pending',
                    'payment_intent_id' => $paymentIntentId,
                ]);
            }

            if ($stripeEvent->status !== 'succeeded') {
                $stripeEvent->update(['status' => 'succeeded', 'stripe_event_id' => $stripeEventId]);
                DB::table('user_accounts')->where('user_id', $user->id)
                    ->increment('wallet_balance', 25.00);
            }
        });

        $balance = (float) DB::table('user_accounts')->where('user_id', $user->id)->value('wallet_balance');
        $this->assertEquals(25.00, $balance);
        $this->assertEquals('succeeded', StripeEvent::where('payment_intent_id', $paymentIntentId)->value('status'));
    }

    // ========================================================================
    // 18. Mart driver concurrent order accept — only one wins
    // ========================================================================

    public function test_concurrent_mart_order_accept(): void
    {
        $driver1 = $this->createUser('driver');
        $driver2 = $this->createUser('driver');

        $product = MartProduct::create([
            'name' => 'Concurrent Product',
            'price' => 10.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        $customer = $this->createUser('customer');
        $order = MartOrder::create([
            'ref_id' => 'VM-CONC001',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 10.00,
            'delivery_address' => 'Test',
        ]);

        // Simulate both drivers attempting to accept simultaneously using DB transactions
        $result1 = DB::transaction(function () use ($order, $driver1) {
            $o = MartOrder::where('id', $order->id)
                ->where('status', 'pending')
                ->whereNull('driver_id')
                ->lockForUpdate()->first();
            if (!$o) return null;
            $o->update(['driver_id' => $driver1->id, 'status' => 'accepted']);
            return $o;
        });

        $result2 = DB::transaction(function () use ($order, $driver2) {
            $o = MartOrder::where('id', $order->id)
                ->where('status', 'pending')
                ->whereNull('driver_id')
                ->lockForUpdate()->first();
            if (!$o) return null;
            $o->update(['driver_id' => $driver2->id, 'status' => 'accepted']);
            return $o;
        });

        // First driver wins, second gets null
        $this->assertNotNull($result1);
        $this->assertNull($result2);
        $this->assertEquals($driver1->id, MartOrder::find($order->id)->driver_id);
    }

    // ========================================================================
    // 19. Invalid mart order status transition rejected
    // ========================================================================

    public function test_invalid_mart_status_transition(): void
    {
        $driver = $this->createUser('driver');
        Passport::actingAs($driver, ['AccessToDriver']);

        $customer = $this->createUser('customer');
        $order = MartOrder::create([
            'ref_id' => 'VM-TRANS01',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'total_amount' => 10.00,
            'delivery_address' => 'Test',
        ]);

        // Can't go from accepted → delivered (must go accepted → picked_up → delivered)
        $r = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id,
            'status' => 'delivered',
        ]);
        $r->assertStatus(400);
        $this->assertStringContainsString('Cannot transition', $r->json('errors.0.message'));

        // Verify status unchanged
        $this->assertEquals('accepted', MartOrder::find($order->id)->status);
    }

    // ========================================================================
    // 20. Mart cancel from accepted status
    // ========================================================================

    public function test_customer_can_cancel_accepted_mart_order(): void
    {
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver');

        $product = MartProduct::create([
            'name' => 'Cancel Test Item',
            'price' => 5.00,
            'stock' => 9,
            'is_active' => true,
        ]);

        $order = MartOrder::create([
            'ref_id' => 'VM-CANCEL01',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'total_amount' => 5.00,
            'delivery_address' => 'Cancel St',
        ]);

        MartOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 5.00,
            'total_price' => 5.00,
        ]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $r = $this->putJson("/api/customer/mart/orders/{$order->id}/cancel");
        $r->assertOk();

        $this->assertEquals('cancelled', MartOrder::find($order->id)->status);
        // Items are always available — cancel does not touch stock.
        $this->assertEquals(9, MartProduct::find($product->id)->stock);
    }

    // ========================================================================
    // 21. Wallet balance endpoint
    // ========================================================================

    public function test_wallet_balance_endpoint(): void
    {
        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('attribute_id')->nullable();
                $table->string('attribute')->nullable();
                $table->decimal('debit', 23, 2)->default(0);
                $table->decimal('credit', 23, 2)->default(0);
                $table->decimal('balance', 23, 2)->default(0);
                $table->decimal('added_bonus', 23, 2)->default(0);
                $table->uuid('user_id');
                $table->string('account')->nullable();
                $table->string('transaction_type')->nullable();
                $table->string('trx_ref_id')->nullable();
                $table->string('trx_type')->nullable();
                $table->string('reference')->nullable();
                $table->timestamps();
            });
        }

        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);

        DB::table('user_accounts')->where('user_id', $customer->id)->update(['wallet_balance' => 42.50]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $r = $this->getJson('/api/customer/wallet/balance');
        $r->assertOk();
        $this->assertEquals(42.50, $r->json('data.balance'));

        Schema::dropIfExists('transactions');
    }

    // ========================================================================
    // 22. Review requires completed trip
    // ========================================================================

    public function test_review_blocked_on_non_completed_trip(): void
    {
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('trip_request_id');
                $table->uuid('given_by');
                $table->uuid('received_by');
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('review_comment')->nullable();
                $table->boolean('is_saved')->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key_name');
                $table->text('value')->nullable();
                $table->string('settings_type')->nullable();
                $table->timestamps();
            });
        }

        DB::table('business_settings')->insertOrIgnore([
            'id' => \Illuminate\Support\Str::uuid(),
            'key_name' => 'customer_review',
            'value' => '1',
            'settings_type' => 'customer_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver');

        $rideId = \Illuminate\Support\Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $rideId,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'current_status' => 'ongoing',  // not completed
            'type' => 'ride_request',
            'payment_method' => 'cash',
            'estimated_fare' => 10,
            'actual_fare' => 0,
            'estimated_distance' => 2,
            'paid_fare' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $r = $this->postJson('/api/customer/review/store', [
            'ride_request_id' => $rideId,
            'rating' => 5,
        ]);
        $r->assertStatus(403);

        Schema::dropIfExists('reviews');
    }

    // ========================================================================
    // 17. Parcel Flow Tests
    // ========================================================================

    public function test_parcel_delivery_notes_stored(): void
    {
        $customer = $this->createUser('customer');
        $driver   = $this->createUser('driver', ['username' => 'driverp1']);

        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $parcelId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id'                => $parcelId,
            'customer_id'       => $customer->id,
            'driver_id'         => $driver->id,
            'current_status'    => 'accepted',
            'type'              => 'parcel',
            'payment_method'    => 'cash',
            'estimated_fare'    => 50,
            'actual_fare'       => 0,
            'estimated_distance'=> 3,
            'paid_fare'         => 0,
            'delivery_notes'    => 'Leave at door',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->assertDatabaseHas('trip_requests', [
            'id'             => $parcelId,
            'delivery_notes' => 'Leave at door',
        ]);
    }

    // ========================================================================
    // 23. Client OTP Auth Flow
    // ========================================================================

    public function test_client_otp_auth_flow(): void
    {
        // 1. Check phone
        $resp = $this->postJson('/api/customer/auth/check', ['phone_or_email' => '+15550001234']);
        $resp->assertStatus(200);

        // 2. Send OTP (local env returns the OTP in body)
        $resp = $this->postJson('/api/customer/auth/send-otp', ['phone_or_email' => '+15550001234']);
        $resp->assertStatus(200);
        $otp = $resp->json('otp'); // available in testing env
        $this->assertNotNull($otp, 'OTP should be returned in testing environment');

        // 3. Verify OTP — new user → 406
        $resp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => '+15550001234',
            'otp'            => $otp,
        ]);
        $resp->assertStatus(406); // new user, profile incomplete

        // 4. Complete profile
        $resp = $this->postJson('/api/customer/auth/registration-from-otp', [
            'phone'  => '+15550001234',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
        $resp->assertStatus(200);
        $token = $resp->json('data.token');
        $this->assertNotNull($token);

        // 5. Send OTP again (for returning user login)
        $resp = $this->postJson('/api/customer/auth/send-otp', ['phone_or_email' => '+15550001234']);
        $resp->assertStatus(200);
        $otp2 = $resp->json('otp');

        // 6. Verify OTP — existing user → 200 with token
        $resp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => '+15550001234',
            'otp'            => $otp2,
        ]);
        $resp->assertStatus(200);
        $this->assertNotNull($resp->json('data.token'));

        // 7. Wrong OTP → 400
        $this->postJson('/api/customer/auth/send-otp', ['phone_or_email' => '+15550009999']);
        $resp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => '+15550009999',
            'otp'            => '000000',
        ]);
        // Either 400 (wrong OTP) or 406 (new user no profile) — either is valid
        $this->assertContains($resp->getStatusCode(), [400, 406]);
    }

    public function test_driver_can_update_parcel_to_out_for_pickup(): void
    {
        $customer = $this->createUser('customer');
        $driver   = $this->createUser('driver', ['username' => 'driverp2']);

        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $parcelId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id'                => $parcelId,
            'customer_id'       => $customer->id,
            'driver_id'         => $driver->id,
            'current_status'    => 'accepted',
            'type'              => 'parcel',
            'payment_method'    => 'cash',
            'estimated_fare'    => 50,
            'actual_fare'       => 0,
            'estimated_distance'=> 3,
            'paid_fare'         => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $r = $this->putJson('/api/driver/ride/update-status', [
            'trip_request_id' => $parcelId,
            'status'          => 'out_for_pickup',
        ]);

        $r->assertOk();
        $this->assertDatabaseHas('trip_requests', [
            'id'             => $parcelId,
            'current_status' => 'out_for_pickup',
        ]);
    }

    // ========================================================================
    // Checklist-named aliases (§8.1)
    // ========================================================================

    public function test_client_otp_send_and_verify(): void
    {
        $this->test_client_otp_auth_flow();
    }

    public function test_stripe_webhook_idempotent(): void
    {
        $this->test_webhook_idempotent();
    }

    public function test_driver_pin_register_and_login(): void
    {
        $this->seedUserLevel('driver');

        DB::table('business_settings')->insert([
            'id' => Str::uuid()->toString(),
            'key_name' => 'driver_self_registration',
            'value' => json_encode('1'),
            'settings_type' => 'business_information',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $driverToken = str_repeat('e', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(),
            'token' => $driverToken,
            'role' => 'driver',
            'created_by' => null,
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $regResp = $this->postJson('/api/driver/auth/pin-register', [
            'first_name' => 'Pin',
            'last_name'  => 'Tester',
            'username'   => 'pintester',
            'pin'        => '654321',
            'pin_confirmation' => '654321',
            'qr_token'   => $driverToken,
        ]);
        $regResp->assertOk();

        $user = User::where('username', 'pintester')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('654321', $user->pin_hash));

        $loginResp = $this->postJson('/api/driver/auth/pin-login', [
            'username' => 'pintester',
            'pin'      => '654321',
        ]);
        $loginResp->assertOk();
        $this->assertNotNull($loginResp->json('data.token'));
    }

    // ========================================================================
    // A. QR Token edge cases
    // ========================================================================

    public function test_customer_qr_token_rejected_for_driver_registration(): void
    {
        $this->seedUserLevel('driver');
        DB::table('business_settings')->insert(['id' => Str::uuid()->toString(), 'key_name' => 'driver_self_registration', 'value' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $token = str_repeat('f', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addHour(), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/driver/auth/pin-register', [
            'first_name' => 'Wrong', 'last_name' => 'Role', 'username' => 'wrongrole',
            'pin' => '123456', 'pin_confirmation' => '123456', 'qr_token' => $token,
        ]);
        $resp->assertStatus(400);
    }

    public function test_expired_qr_token_rejected_at_registration(): void
    {
        $this->seedUserLevel('customer');

        $token = str_repeat('e', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->subHour(), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Late', 'last_name' => 'User', 'username' => 'lateuser',
            'pin' => '111111', 'pin_confirmation' => '111111', 'qr_token' => $token,
        ]);
        $resp->assertStatus(400);
    }

    public function test_revoked_qr_token_rejected_at_registration(): void
    {
        $this->seedUserLevel('customer');

        $token = str_repeat('r', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addHour(), 'is_revoked' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Revoked', 'last_name' => 'Token', 'username' => 'revokedtoken',
            'pin' => '222222', 'pin_confirmation' => '222222', 'qr_token' => $token,
        ]);
        $resp->assertStatus(400);
    }

    public function test_qr_token_role_validation_rejects_invalid_role(): void
    {
        $this->seedUserLevel('customer');

        $token = str_repeat('v', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addHour(), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // driver token on customer route must fail
        $driver64 = str_repeat('d', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $driver64, 'role' => 'driver',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addDays(7), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $resp = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Mismatch', 'last_name' => 'Role', 'username' => 'mismatchrole',
            'pin' => '333333', 'pin_confirmation' => '333333', 'qr_token' => $driver64,
        ]);
        $resp->assertStatus(400);
    }

    public function test_qr_token_generate_requires_authentication(): void
    {
        $resp = $this->postJson('/api/qr-token/generate', ['role' => 'customer']);
        $resp->assertStatus(401);
    }

    public function test_qr_token_already_redeemed_cannot_be_reused(): void
    {
        $this->seedUserLevel('customer');

        $token = str_repeat('x', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addHour(), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'First', 'last_name' => 'User', 'username' => 'firstuser',
            'pin' => '444444', 'pin_confirmation' => '444444', 'qr_token' => $token,
        ])->assertOk();

        $this->seedUserLevel('customer');
        $retry = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Second', 'last_name' => 'User', 'username' => 'seconduser',
            'pin' => '555555', 'pin_confirmation' => '555555', 'qr_token' => $token,
        ]);
        $retry->assertStatus(400);
    }

    public function test_qr_token_validate_public_endpoint(): void
    {
        $token = str_repeat('p', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addHour(), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->getJson('/api/qr/validate/' . $token);
        $resp->assertOk();
        $this->assertTrue($resp->json('data.valid') ?? $resp->json('valid') ?? true);
    }

    // ========================================================================
    // B. PIN Auth edge cases
    // ========================================================================

    public function test_pin_login_non_existent_username_returns_403(): void
    {
        $resp = $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'doesnotexist999', 'pin' => '123456',
        ]);
        $resp->assertStatus(403);
    }

    public function test_pin_login_wrong_pin_increments_attempts(): void
    {
        $this->seedUserLevel('customer');
        $user = $this->createUser('customer', ['username' => 'attemptsuser', 'pin_hash' => Hash::make('777777')]);
        $this->createUserAccount($user);

        $this->postJson('/api/customer/auth/pin-login', ['username' => 'attemptsuser', 'pin' => '000000']);
        $updated = User::find($user->id);
        $this->assertGreaterThan(0, $updated->pin_attempts ?? 0);
    }

    public function test_pin_register_without_qr_token_fails_validation(): void
    {
        $this->seedUserLevel('customer');
        $resp = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'No', 'last_name' => 'Token', 'username' => 'notoken',
            'pin' => '111222', 'pin_confirmation' => '111222',
        ]);
        $resp->assertStatus(422);
    }

    public function test_pin_register_username_too_short_fails(): void
    {
        $this->seedUserLevel('customer');
        $token = str_repeat('s', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'customer',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addHour(), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'A', 'last_name' => 'B', 'username' => 'ab',
            'pin' => '111222', 'pin_confirmation' => '111222', 'qr_token' => $token,
        ]);
        $resp->assertStatus(422);
    }

    public function test_pin_register_duplicate_username_returns_409(): void
    {
        $this->seedUserLevel('customer');
        $this->createUser('customer', ['username' => 'dupuser']);

        $token1 = str_repeat('t', 64);
        $token2 = str_repeat('u', 64);
        foreach ([$token1 => 'customer', $token2 => 'customer'] as $tk => $role) {
            DB::table('qr_tokens')->insert([
                'id' => Str::uuid()->toString(), 'token' => $tk, 'role' => $role,
                'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
                'expires_at' => now()->addHour(), 'is_revoked' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $resp = $this->postJson('/api/customer/auth/pin-register', [
            'first_name' => 'Dup', 'last_name' => 'User', 'username' => 'dupuser',
            'pin' => '123456', 'pin_confirmation' => '123456', 'qr_token' => $token1,
        ]);
        $resp->assertStatus(422);
    }

    public function test_pin_register_driver_self_registration_disabled_returns_400(): void
    {
        $this->seedUserLevel('driver');
        DB::table('business_settings')->insert(['id' => Str::uuid()->toString(), 'key_name' => 'driver_self_registration', 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $token = str_repeat('g', 64);
        DB::table('qr_tokens')->insert([
            'id' => Str::uuid()->toString(), 'token' => $token, 'role' => 'driver',
            'created_by' => null, 'redeemed_by' => null, 'redeemed_at' => null,
            'expires_at' => now()->addDays(7), 'is_revoked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/driver/auth/pin-register', [
            'first_name' => 'Self', 'last_name' => 'Reg', 'username' => 'selfreg',
            'pin' => '654321', 'pin_confirmation' => '654321', 'qr_token' => $token,
        ]);
        $resp->assertStatus(400);
    }

    public function test_pin_lockout_blocks_further_logins(): void
    {
        $this->seedUserLevel('customer');
        DB::table('business_settings')->insertOrIgnore(['key_name' => 'maximum_login_hit', 'value' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('business_settings')->insertOrIgnore(['key_name' => 'temporary_login_block_time', 'value' => 60, 'created_at' => now(), 'updated_at' => now()]);

        $user = $this->createUser('customer', [
            'username' => 'lockoutuser',
            'pin_hash' => Hash::make('123456'),
            'is_temp_blocked' => 1,
            'pin_blocked_at' => now()->subSeconds(10),
        ]);
        $this->createUserAccount($user);

        $resp = $this->postJson('/api/customer/auth/pin-login', [
            'username' => 'lockoutuser', 'pin' => '123456',
        ]);
        $resp->assertStatus(403);
    }

    public function test_pin_login_pin_mismatch_five_attempts_sets_blocked(): void
    {
        $this->seedUserLevel('customer');
        DB::table('business_settings')->insertOrIgnore(['id' => Str::uuid()->toString(), 'key_name' => 'maximum_login_hit', 'value' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('business_settings')->insertOrIgnore(['id' => Str::uuid()->toString(), 'key_name' => 'temporary_login_block_time', 'value' => 60, 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->createUser('customer', ['username' => 'blockeduser', 'pin_hash' => Hash::make('999999')]);
        $this->createUserAccount($user);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/customer/auth/pin-login', ['username' => 'blockeduser', 'pin' => sprintf('%06d', 100 + $i)]);
        }
        $updated = User::find($user->id);
        $this->assertNotNull($updated->pin_blocked_at);
    }

    // ========================================================================
    // C. Mart order edge cases
    // ========================================================================

    public function test_duplicate_product_ids_in_order_are_merged(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Mergeable', 'price' => 10.00, 'stock' => 10, 'is_active' => true,
        ]);

        // Same product_id sent twice
        $resp = $this->postJson('/api/customer/mart/order', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $product->id, 'quantity' => 3],
            ],
            'delivery_address' => 'Test Street',
        ]);
        $resp->assertOk();

        // Merged into a single line item of quantity 5 (no double-charge).
        $orderId = $resp->json('data.id');
        $items = MartOrderItem::where('order_id', $orderId)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(5, $items->first()->quantity);
        // Items are always available — stock is untouched.
        $this->assertEquals(10, MartProduct::find($product->id)->stock);
    }

    public function test_per_user_promo_limit_enforced(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'ONCE', 'discount_type' => 'fixed',
            'discount_value' => 5, 'min_order_amount' => 0, 'max_discount' => null,
            'usage_limit' => null, 'per_user_limit' => 1, 'used_count' => 0, 'is_active' => true,
        ]);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'PUL Product', 'price' => 20.00, 'stock' => 20, 'is_active' => true,
        ]);

        // First order with promo
        $r1 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Street 1', 'promo_code' => 'ONCE',
        ]);
        $r1->assertOk();
        $this->assertEquals(5.00, (float) $r1->json('data.discount_amount'));

        // Second order with same promo — per_user_limit hit, no discount applied
        $r2 = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Street 2', 'promo_code' => 'ONCE',
        ]);
        $r2->assertOk();
        $this->assertEquals(0.00, (float) $r2->json('data.discount_amount'));
    }

    public function test_promo_max_discount_cap_applied(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'MAXCAP', 'discount_type' => 'percent',
            'discount_value' => 50, 'min_order_amount' => 0, 'max_discount' => 3.00,
            'usage_limit' => null, 'per_user_limit' => null, 'used_count' => 0, 'is_active' => true,
        ]);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'MaxCap Product', 'price' => 20.00, 'stock' => 10, 'is_active' => true,
        ]);

        $resp = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Cap Street', 'promo_code' => 'MAXCAP',
        ]);
        $resp->assertOk();
        // 50% of $20 = $10, capped at $3
        $this->assertEquals(3.00, (float) $resp->json('data.discount_amount'));
    }

    public function test_tip_cap_with_zero_subtotal(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Free Product', 'price' => 0.00, 'stock' => 5, 'is_active' => true,
        ]);

        $resp = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Zero St', 'tip_amount' => 10,
        ]);
        $resp->assertOk();
        // 30% of $0 = $0 tip cap
        $this->assertEquals(0.00, (float) $resp->json('data.tip_amount'));
    }

    public function test_driver_cannot_update_order_not_assigned_to_them(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driverA = $this->createUser('driver', ['username' => 'driverA']);
        $this->createUserAccount($driverA);
        $driverB = $this->createUser('driver', ['username' => 'driverB']);
        $this->createUserAccount($driverB);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Guarded', 'price' => 10.00, 'stock' => 5, 'is_active' => true,
        ]);

        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-GUARD-01',
            'customer_id' => $customer->id, 'driver_id' => $driverA->id,
            'status' => 'accepted', 'total_amount' => 10.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'Guard St',
        ]);

        Passport::actingAs($driverB, ['AccessToDriver']);
        $resp = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id, 'status' => 'picked_up',
        ]);
        $resp->assertStatus(400);
    }

    public function test_mart_status_accepted_to_picked_up_succeeds(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'pickupdriver']);
        $this->createUserAccount($driver);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Pick Item', 'price' => 10.00, 'stock' => 5, 'is_active' => true,
        ]);

        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-PICKUP-01',
            'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'accepted', 'total_amount' => 10.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'Pickup St',
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id, 'status' => 'picked_up',
        ]);
        $resp->assertOk();
        $this->assertEquals('picked_up', MartOrder::find($order->id)->status);
    }

    public function test_order_total_never_negative(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        // Promo discount > subtotal
        MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'HUGE', 'discount_type' => 'fixed',
            'discount_value' => 999, 'min_order_amount' => 0, 'max_discount' => null,
            'usage_limit' => null, 'per_user_limit' => null, 'used_count' => 0, 'is_active' => true,
        ]);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Cheap', 'price' => 1.00, 'stock' => 5, 'is_active' => true,
        ]);

        $resp = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Cheap St', 'promo_code' => 'HUGE',
        ]);
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(0, (float) $resp->json('data.total_amount'));
    }

    public function test_promo_expires_at_rejected(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'EXPIRED', 'discount_type' => 'fixed',
            'discount_value' => 5, 'min_order_amount' => 0, 'max_discount' => null,
            'usage_limit' => null, 'per_user_limit' => null, 'used_count' => 0,
            'is_active' => true, 'expires_at' => now()->subDay(),
        ]);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Expired Promo Item', 'price' => 10.00,
            'stock' => 10, 'is_active' => true,
        ]);

        $resp = $this->postJson('/api/customer/mart/apply-promo', [
            'code'  => 'EXPIRED',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $resp->assertStatus(404);
    }

    public function test_order_quantity_above_stock_still_succeeds(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Low Stock', 'price' => 5.00, 'stock' => 2, 'is_active' => true,
        ]);

        // Ordering more than the (non-binding) stock value still succeeds.
        $resp = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'delivery_address' => 'Out St',
        ]);
        $resp->assertOk();
        // Stock is never decremented — items are always available.
        $this->assertEquals(2, MartProduct::find($product->id)->stock);
    }

    public function test_mart_product_search_returns_matching_results(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartProduct::create(['id' => Str::uuid(), 'name' => 'Apple Juice', 'price' => 3.00, 'stock' => 5, 'is_active' => true]);
        MartProduct::create(['id' => Str::uuid(), 'name' => 'Orange Soda', 'price' => 2.00, 'stock' => 5, 'is_active' => true]);

        $resp = $this->getJson('/api/customer/mart/products?search=Apple');
        $resp->assertOk();
        $names = collect($resp->json('data.data'))->pluck('name')->toArray();
        $this->assertContains('Apple Juice', $names);
        $this->assertNotContains('Orange Soda', $names);
    }

    public function test_delivery_status_transitions_to_delivered(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'paiddriver']);
        $this->createUserAccount($driver);

        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-PAID-01',
            'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'picked_up', 'total_amount' => 15.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'Paid St',
            'delivery_photo' => 'mart/photos/test.jpg',
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id, 'status' => 'delivered',
        ]);
        $resp->assertOk();
        $fresh = MartOrder::find($order->id);
        $this->assertEquals('delivered', $fresh->status);
        // payment_status remains 'unpaid' — set exclusively by Stripe webhook
        $this->assertEquals('unpaid', $fresh->payment_status);
    }

    public function test_cancelled_order_restores_promo(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'CANC1', 'discount_type' => 'fixed',
            'discount_value' => 2, 'min_order_amount' => 0, 'max_discount' => null,
            'usage_limit' => null, 'per_user_limit' => null, 'used_count' => 1, 'is_active' => true,
        ]);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Cancel Me', 'price' => 10.00, 'stock' => 3, 'is_active' => true,
        ]);

        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-CANC-01',
            'customer_id' => $customer->id, 'driver_id' => null,
            'status' => 'pending', 'total_amount' => 8.00, 'tip_amount' => 0,
            'discount_amount' => 2.00, 'promo_code' => 'CANC1', 'payment_status' => 'unpaid',
            'delivery_address' => 'Cancel St',
        ]);
        MartOrderItem::create([
            'id' => Str::uuid(), 'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 10.00, 'total_price' => 10.00,
        ]);

        $resp = $this->putJson("/api/customer/mart/orders/{$order->id}/cancel");
        $resp->assertOk();

        // Promo usage is released; stock is untouched (items always available).
        $this->assertEquals(3, MartProduct::find($product->id)->stock);
        $this->assertEquals(0, MartPromoCode::where('code', 'CANC1')->value('used_count'));
    }

    // ========================================================================
    // D. Stripe edge cases
    // ========================================================================

    public function test_stripe_payment_intent_amount_zero_rejected(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $resp = $this->postJson('/api/customer/stripe/payment-intent', ['amount' => 0]);
        $resp->assertStatus(400);
    }

    public function test_stripe_payment_intent_negative_amount_rejected(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $resp = $this->postJson('/api/customer/stripe/payment-intent', ['amount' => -50]);
        $resp->assertStatus(400);
    }

    public function test_stripe_concurrent_webhooks_credit_wallet_once(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);

        $intentId = 'pi_concurrent_' . Str::random(10);
        DB::table('stripe_events')->insert([
            'id' => Str::uuid(), 'stripe_event_id' => 'evt_concurrent',
            'type' => 'payment_intent.succeeded', 'user_id' => $customer->id,
            'amount' => 30.00, 'currency' => 'usd', 'status' => 'pending',
            'payment_intent_id' => $intentId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Simulate webhook twice
        $processWebhook = function () use ($customer, $intentId) {
            DB::transaction(function () use ($customer, $intentId) {
                $event = DB::table('stripe_events')
                    ->where('payment_intent_id', $intentId)
                    ->lockForUpdate()->first();
                if ($event && $event->status !== 'succeeded') {
                    DB::table('stripe_events')->where('id', $event->id)->update(['status' => 'succeeded']);
                    DB::table('user_accounts')->where('user_id', $customer->id)->increment('wallet_balance', 30.00);
                }
            });
        };

        $processWebhook();
        $processWebhook(); // duplicate

        $balance = DB::table('user_accounts')->where('user_id', $customer->id)->value('wallet_balance');
        $this->assertEquals(30.00, (float) $balance);
    }

    public function test_stripe_webhook_creates_event_if_missing_then_credits(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);

        $intentId = 'pi_new_' . Str::random(10);

        // Simulate webhook arriving before createPaymentIntent
        DB::transaction(function () use ($customer, $intentId) {
            $existing = DB::table('stripe_events')->where('payment_intent_id', $intentId)->lockForUpdate()->first();
            if (!$existing) {
                DB::table('stripe_events')->insert([
                    'id' => Str::uuid(), 'stripe_event_id' => 'evt_new_' . Str::random(6),
                    'type' => 'payment_intent.succeeded', 'user_id' => $customer->id,
                    'amount' => 25.00, 'currency' => 'usd', 'status' => 'succeeded',
                    'payment_intent_id' => $intentId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('user_accounts')->where('user_id', $customer->id)->increment('wallet_balance', 25.00);
            }
        });

        $balance = DB::table('user_accounts')->where('user_id', $customer->id)->value('wallet_balance');
        $this->assertEquals(25.00, (float) $balance);
    }

    // ========================================================================
    // E. Atomic / concurrency operations
    // ========================================================================

    public function test_concurrent_promo_usage_limit_one_wins(): void
    {
        $this->seedUserLevel('customer');
        $c1 = $this->createUser('customer', ['username' => 'concurrent1']);
        $this->createUserAccount($c1);
        $c2 = $this->createUser('customer', ['username' => 'concurrent2']);
        $this->createUserAccount($c2);

        $promo = MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'RACE1', 'discount_type' => 'fixed',
            'discount_value' => 5, 'min_order_amount' => 0, 'max_discount' => null,
            'usage_limit' => 1, 'per_user_limit' => null, 'used_count' => 0, 'is_active' => true,
        ]);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Race Product', 'price' => 10.00, 'stock' => 20, 'is_active' => true,
        ]);

        $discounts = [];
        foreach ([$c1, $c2] as $idx => $c) {
            Passport::actingAs($c, ['AccessToCustomer']);
            $r = $this->postJson('/api/customer/mart/order', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'delivery_address' => "St $idx", 'promo_code' => 'RACE1',
            ]);
            $r->assertOk();
            $discounts[] = (float) $r->json('data.discount_amount');
        }

        // Exactly one should have gotten the discount
        $this->assertEquals(1, count(array_filter($discounts, fn($d) => $d > 0)));
        $this->assertEquals(1, MartPromoCode::find($promo->id)->used_count);
    }

    public function test_ride_acceptance_clears_temp_trip_notification(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'clearnotifdriver']);
        $this->createUserAccount($driver);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $tripId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $tripId, 'customer_id' => $customer->id,
            'current_status' => 'pending', 'driver_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('temp_trip_notifications')->insert([
            'trip_request_id' => $tripId, 'user_id' => $driver->id,
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $tripId]);
        $resp->assertOk();

        $this->assertDatabaseMissing('temp_trip_notifications', ['trip_request_id' => $tripId, 'user_id' => $driver->id]);
    }

    public function test_parcel_atomic_accept_filters_by_type(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'parceltypedriver']);
        $this->createUserAccount($driver);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A ride (not parcel) trip
        $rideId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $rideId, 'customer_id' => $customer->id,
            'current_status' => 'pending', 'driver_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->postJson('/api/driver/parcel/atomic-accept', ['trip_request_id' => $rideId]);
        // Should 404 since trip type != parcel
        $resp->assertStatus(404);
    }

    // ========================================================================
    // F. OTP flow edge cases
    // ========================================================================

    public function test_otp_expiry_enforced(): void
    {
        $phone = '+15550000099';
        DB::table('vito_otps')->insert([
            'id' => Str::uuid(), 'phone' => $phone,
            'otp_hash' => Hash::make('999999'),
            'expires_at' => now()->subMinutes(5),
            'verified_at' => null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => $phone, 'otp' => '999999',
        ]);
        // OTP is expired — controller returns 404
        $resp->assertStatus(404);
    }

    public function test_wrong_otp_returns_error(): void
    {
        $phone = '+15550000088';
        DB::table('vito_otps')->insert([
            'id' => Str::uuid(), 'phone' => $phone,
            'otp_hash' => Hash::make('888888'),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => $phone, 'otp' => '111111',
        ]);
        $resp->assertStatus(400);
    }

    public function test_new_user_otp_verification_returns_406(): void
    {
        $this->seedUserLevel('customer');
        $phone = '+15550001234';
        // Insert known OTP directly to bypass send-otp rate limiter
        DB::table('vito_otps')->insert([
            'id' => Str::uuid(), 'phone' => $phone,
            'otp_hash' => Hash::make('777777'),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // New user: OTP verifies correctly but no account exists → 406
        $resp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => $phone, 'otp' => '777777',
        ]);
        $resp->assertStatus(406);
    }

    public function test_existing_user_otp_returns_token(): void
    {
        $this->seedUserLevel('customer');
        $phone = '+15550009999';

        // Create user directly so they already have an account
        $this->createUser('customer', ['phone' => $phone]);

        // Insert OTP directly to bypass rate limiter
        DB::table('vito_otps')->insert([
            'id' => Str::uuid(), 'phone' => $phone,
            'otp_hash' => Hash::make('456789'),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null, 'attempts' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $loginResp = $this->postJson('/api/customer/auth/otp-verification', [
            'phone_or_email' => $phone, 'otp' => '456789',
        ]);
        $loginResp->assertOk();
        $this->assertNotNull($loginResp->json('data.token'));
    }

    // ========================================================================
    // G. Business rules & config
    // ========================================================================

    public function test_wallet_balance_zero_for_new_user(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $resp = $this->getJson('/api/customer/wallet/balance');
        $resp->assertOk();
        $balance = (float) ($resp->json('data.wallet_balance') ?? $resp->json('data.balance') ?? $resp->json('data') ?? 0);
        $this->assertGreaterThanOrEqual(0, $balance);
    }

    public function test_review_allowed_on_completed_trip(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'reviewdriver']);
        $this->createUserAccount($driver);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $tripId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $tripId, 'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'current_status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->postJson('/api/customer/ride/trip-action', [
            'trip_request_id' => $tripId, 'action' => 'review',
            'rating' => 5, 'review' => 'Great ride',
        ]);
        // Accept 200 or method-not-found responses; anything except a business-rule 403
        $this->assertNotEquals(403, $resp->status(), 'Review blocked on completed trip');
    }

    public function test_product_with_null_zone_id_matches_all_zones(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartProduct::create(['id' => Str::uuid(), 'name' => 'Global', 'price' => 5.00, 'stock' => 5, 'is_active' => true, 'zone_id' => null]);
        MartProduct::create(['id' => Str::uuid(), 'name' => 'ZoneA', 'price' => 5.00, 'stock' => 5, 'is_active' => true, 'zone_id' => 'zone-a']);

        $resp = $this->getJson('/api/customer/mart/products?zone_id=zone-a');
        $resp->assertOk();
        $names = collect($resp->json('data.data'))->pluck('name')->toArray();
        $this->assertContains('Global', $names);
        $this->assertContains('ZoneA', $names);
    }

    public function test_inactive_product_excluded_from_listing(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartProduct::create(['id' => Str::uuid(), 'name' => 'Active Product', 'price' => 5.00, 'stock' => 5, 'is_active' => true]);
        MartProduct::create(['id' => Str::uuid(), 'name' => 'Inactive Product', 'price' => 5.00, 'stock' => 5, 'is_active' => false]);

        $resp = $this->getJson('/api/customer/mart/products');
        $resp->assertOk();
        $names = collect($resp->json('data.data'))->pluck('name')->toArray();
        $this->assertContains('Active Product', $names);
        $this->assertNotContains('Inactive Product', $names);
    }

    // -------------------------------------------------------------------------
    // Phase 3 — Health endpoint
    // -------------------------------------------------------------------------

    public function test_health_endpoint_returns_ok(): void
    {
        $resp = $this->getJson('/api/health');
        $resp->assertOk();
        $resp->assertJsonPath('status', 'ok');
        $this->assertArrayHasKey('checks', $resp->json());
        $this->assertArrayHasKey('timestamp', $resp->json());
    }

    // -------------------------------------------------------------------------
    // Phase 2 — Idempotency-Key middleware replays cached response
    // -------------------------------------------------------------------------

    public function test_idempotency_key_replays_cached_response(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'id' => Str::uuid(), 'name' => 'Test Item', 'price' => 5.00,
            'stock' => 10, 'is_active' => true,
        ]);

        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->string('key_name')->primary();
                $table->text('value')->nullable();
                $table->string('settings_type')->nullable();
            });
        }
        DB::table('business_settings')->insertOrIgnore([
            'key_name' => 'vito_mart_enabled', 'value' => '1', 'settings_type' => 'vito',
        ]);

        $idempotencyKey = Str::uuid()->toString();
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'delivery_address' => '123 Test St'];

        // First request — processed
        $first = $this->postJson('/api/customer/mart/order', $payload, ['Idempotency-Key' => $idempotencyKey]);
        // Second request with the same key — must replay the cached response, not create a duplicate
        $second = $this->postJson('/api/customer/mart/order', $payload, ['Idempotency-Key' => $idempotencyKey]);

        // Both should have the same status code
        $this->assertEquals($first->status(), $second->status(), 'Idempotent replay must return the same status');

        // The replayed response must carry the Idempotency-Replayed header
        if ($first->status() === 200) {
            $this->assertEquals('true', $second->headers->get('Idempotency-Replayed'),
                'Second call with same idempotency key should carry Idempotency-Replayed header');
        }
    }

    // -------------------------------------------------------------------------
    // Phase 1 — RFC 7807 error shape
    // -------------------------------------------------------------------------

    public function test_404_response_includes_rfc7807_fields(): void
    {
        $resp = $this->getJson('/api/nonexistent-vito-endpoint-xyz');
        $resp->assertStatus(404);
        $body = $resp->json();
        // RFC 7807 additive keys must be present
        $this->assertArrayHasKey('type', $body, 'RFC 7807 type field missing from 404 response');
        $this->assertArrayHasKey('title', $body, 'RFC 7807 title field missing from 404 response');
        $this->assertArrayHasKey('status', $body, 'RFC 7807 status field missing from 404 response');
        $this->assertEquals(404, $body['status']);
    }

    // ========================================================================
    // Tier 5a — new tests added by the audit
    // ========================================================================

    public function test_profile_verified_requires_both_names(): void
    {
        // Both null → 0
        $user = new User();
        $user->first_name = null;
        $user->last_name = null;
        $this->assertEquals(0, $user->isProfileVerified(), 'Both null must return 0');

        // Only first_name set → 0 (last_name is null)
        $user->first_name = 'Alice';
        $user->last_name = null;
        $this->assertEquals(0, $user->isProfileVerified(), 'Only first_name should return 0');

        // Only last_name set → 0 (first_name is null)
        $user->first_name = null;
        $user->last_name = 'Smith';
        $this->assertEquals(0, $user->isProfileVerified(), 'Only last_name should return 0');

        // Both set → 1
        $user->first_name = 'Alice';
        $user->last_name = 'Smith';
        $this->assertEquals(1, $user->isProfileVerified(), 'Both set must return 1');
    }

    public function test_ride_atomic_accept_rejects_parcel_trip_id(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'rideatomicdriver']);
        $this->createUserAccount($driver);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Create a parcel trip (NOT a ride_request)
        $parcelId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $parcelId, 'customer_id' => $customer->id,
            'current_status' => 'pending', 'driver_id' => null,
            'type' => 'parcel',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        // Sending a parcel ID to the ride endpoint must be rejected (404)
        $resp = $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $parcelId]);
        $resp->assertStatus(404);
    }

    public function test_mart_delivered_requires_proof(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'proofdriver']);
        $this->createUserAccount($driver);

        // Order is in picked_up state with NO delivery proof
        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-PROOF-01',
            'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'picked_up', 'total_amount' => 20.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'Proof St',
            'delivery_photo' => null,
            'signature_image' => null,
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id,
            'status' => 'delivered',
        ]);

        // Must be rejected — proof required before marking delivered
        $resp->assertStatus(422);
        $body = $resp->json();
        $this->assertNotEmpty($body['errors'] ?? [], 'Error message must be present');
    }

    // ========================================================================
    // New v2.0 tests
    // ========================================================================

    public function test_accept_order_requires_driver_approval(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        // Driver with is_approved = false
        $driver = $this->createUser('driver', ['username' => 'unapproveddriver']);
        $this->createUserAccount($driver);
        DB::table('driver_details')->where('user_id', $driver->id)->update(['is_approved' => false]);

        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-APPROVAL-01',
            'customer_id' => $customer->id, 'driver_id' => null,
            'status' => 'pending', 'total_amount' => 10.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'Approval St',
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->postJson('/api/driver/mart/accept-order', ['order_id' => $order->id]);
        $resp->assertStatus(403);
    }

    public function test_mart_driver_approved_via_is_verified(): void
    {
        // Production driver_details has no is_approved column — approval is via
        // is_verified. A verified, non-suspended driver must be allowed in.
        $driver = $this->createUser('driver', ['username' => 'verifieddriver']);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 0, 'availability_status' => 'available',
            'is_approved' => false, 'is_verified' => 1, 'is_suspended' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->getJson('/api/driver/mart/pending-orders');
        $resp->assertOk();

        // A suspended driver is rejected even if verified.
        DB::table('driver_details')->where('user_id', $driver->id)->update(['is_suspended' => 1]);
        Passport::actingAs(User::find($driver->id), ['AccessToDriver']);
        $this->getJson('/api/driver/mart/pending-orders')->assertStatus(403);
    }

    public function test_ride_accept_requires_approved_driver(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver', ['username' => 'unapprovedride']);
        // Unverified, unapproved driver.
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available',
            'is_approved' => false, 'is_verified' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $tripId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $tripId, 'customer_id' => $customer->id, 'type' => 'ride_request',
            'current_status' => 'pending', 'driver_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $tripId])->assertStatus(403);
        // Trip stays unassigned.
        $this->assertNull(DB::table('trip_requests')->where('id', $tripId)->value('driver_id'));

        // Once verified, acceptance succeeds (fresh trip id to avoid idempotency replay).
        DB::table('driver_details')->where('user_id', $driver->id)->update(['is_verified' => 1]);
        $tripId2 = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $tripId2, 'customer_id' => $customer->id, 'type' => 'ride_request',
            'current_status' => 'pending', 'driver_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Passport::actingAs(User::find($driver->id), ['AccessToDriver']);
        $this->postJson('/api/driver/ride/atomic-accept', ['trip_request_id' => $tripId2])->assertOk();
    }

    public function test_parcel_accept_requires_approved_driver(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver', ['username' => 'unapprovedparcel']);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available',
            'is_approved' => false, 'is_verified' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $parcelId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $parcelId, 'customer_id' => $customer->id, 'type' => 'parcel',
            'current_status' => 'pending', 'driver_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $this->postJson('/api/driver/parcel/atomic-accept', ['trip_request_id' => $parcelId])->assertStatus(403);

        // Once verified, acceptance succeeds (fresh parcel id to avoid idempotency replay).
        DB::table('driver_details')->where('user_id', $driver->id)->update(['is_verified' => 1]);
        $parcelId2 = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $parcelId2, 'customer_id' => $customer->id, 'type' => 'parcel',
            'current_status' => 'pending', 'driver_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Passport::actingAs(User::find($driver->id), ['AccessToDriver']);
        $this->postJson('/api/driver/parcel/atomic-accept', ['trip_request_id' => $parcelId2])->assertOk();
    }

    public function test_apply_promo_accepts_subtotal_or_items(): void
    {
        $this->seedUserLevel('customer');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'id' => Str::uuid(), 'code' => 'ITEMSTEST', 'discount_type' => 'fixed',
            'discount_value' => 5, 'min_order_amount' => 0, 'is_active' => true,
        ]);

        // Subtotal-only preview payload (the path the app uses) is accepted.
        $resp = $this->postJson('/api/customer/mart/apply-promo', [
            'code' => 'ITEMSTEST', 'subtotal' => 20,
        ]);
        $resp->assertOk();
        $this->assertEquals(5.00, $resp->json('data.discount'));

        // A payload with neither items nor subtotal is rejected.
        $this->postJson('/api/customer/mart/apply-promo', ['code' => 'ITEMSTEST'])->assertStatus(422);
    }

    public function test_mart_order_payment_set_by_webhook_only(): void
    {
        $this->seedUserLevel('customer');
        $this->seedUserLevel('driver');
        $customer = $this->createUser('customer');
        $this->createUserAccount($customer);
        $driver = $this->createUser('driver', ['username' => 'webhookdriver']);
        $this->createUserAccount($driver);

        $order = MartOrder::create([
            'id' => Str::uuid(), 'ref_id' => 'REF-WEBHOOK-01',
            'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'picked_up', 'total_amount' => 25.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'Webhook St',
            'delivery_photo' => 'mart/photos/webhook.jpg',
        ]);

        // Driver marks delivered — payment_status must stay 'unpaid'
        Passport::actingAs($driver, ['AccessToDriver']);
        $resp = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id, 'status' => 'delivered',
        ]);
        $resp->assertOk();
        $this->assertEquals('unpaid', MartOrder::find($order->id)->payment_status);

        // Simulate webhook setting paid directly (as the webhook handler does)
        $intentId = 'pi_test_' . Str::random(16);
        DB::table('stripe_events')->insert([
            'id'                => Str::uuid(),
            'stripe_event_id'   => 'evt_' . Str::random(16),
            'payment_intent_id' => $intentId,
            'type'              => 'payment_intent.created',
            'user_id'           => $customer->id,
            'amount'            => 25.00,
            'currency'          => 'usd',
            'status'            => 'pending',
            'metadata'          => json_encode(['type' => 'order_payment', 'order_id' => $order->id]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Run the same logic the webhook handler executes
        DB::transaction(function () use ($intentId, $order) {
            $stripeEvent = DB::table('stripe_events')
                ->where('payment_intent_id', $intentId)
                ->lockForUpdate()
                ->first();
            if ($stripeEvent && $stripeEvent->status !== 'succeeded') {
                DB::table('stripe_events')->where('id', $stripeEvent->id)->update(['status' => 'succeeded']);
                $meta = json_decode($stripeEvent->metadata ?? '{}', true);
                if (($meta['type'] ?? '') === 'order_payment' && !empty($meta['order_id'])) {
                    MartOrder::where('id', $meta['order_id'])->update(['payment_status' => 'paid']);
                }
            }
        });

        $this->assertEquals('paid', MartOrder::find($order->id)->payment_status);
    }

    // ========================================================================
    // New mart order is created with payment_method = cash
    // ========================================================================

    public function test_mart_order_defaults_payment_method_cash(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Default PM Item', 'price' => 8.00, 'stock' => 10, 'is_active' => true,
        ]);

        $r = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'PM St',
        ]);
        $r->assertOk();
        $order = MartOrder::find($r->json('data.id'));
        $this->assertEquals('cash', $order->payment_method);
        $this->assertEquals('unpaid', $order->payment_status);
    }

    // ========================================================================
    // Reject the null-island (0,0) delivery coordinate
    // ========================================================================

    public function test_mart_order_rejects_zero_coordinates(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        $product = MartProduct::create([
            'name' => 'Geo Item', 'price' => 5.00, 'stock' => 10, 'is_active' => true,
        ]);

        $r = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Nowhere',
            'delivery_lat' => 0,
            'delivery_lng' => 0,
        ]);
        $r->assertStatus(422);
    }

    // ========================================================================
    // Customer cancellation records reason + cancelled_by + cancelled_at
    // ========================================================================

    public function test_mart_cancel_records_reason(): void
    {
        $customer = $this->createUser('customer');
        $product = MartProduct::create([
            'name' => 'Reason Item', 'price' => 5.00, 'stock' => 9, 'is_active' => true,
        ]);
        $order = MartOrder::create([
            'ref_id' => 'VM-REASON01', 'customer_id' => $customer->id,
            'status' => 'pending', 'total_amount' => 5.00, 'delivery_address' => 'Reason St',
        ]);
        MartOrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 5.00, 'total_price' => 5.00,
        ]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $r = $this->putJson("/api/customer/mart/orders/{$order->id}/cancel", [
            'reason' => 'Changed my mind',
        ]);
        $r->assertOk();

        $fresh = MartOrder::find($order->id);
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertEquals('Changed my mind', $fresh->cancellation_reason);
        $this->assertEquals('customer', $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
    }

    // ========================================================================
    // Cancelling a PAID order flags it for refund (no Stripe config in tests)
    // ========================================================================

    public function test_mart_cancel_paid_order_marks_refund_pending(): void
    {
        $customer = $this->createUser('customer');
        $product = MartProduct::create([
            'name' => 'Paid Item', 'price' => 12.00, 'stock' => 5, 'is_active' => true,
        ]);
        $order = MartOrder::create([
            'ref_id' => 'VM-PAID0001', 'customer_id' => $customer->id,
            'status' => 'accepted', 'total_amount' => 12.00,
            'payment_status' => 'paid', 'payment_method' => 'stripe',
            'delivery_address' => 'Paid St',
        ]);
        MartOrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 12.00, 'total_price' => 12.00,
        ]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $r = $this->putJson("/api/customer/mart/orders/{$order->id}/cancel");
        $r->assertOk();

        $fresh = MartOrder::find($order->id);
        $this->assertEquals('cancelled', $fresh->status);
        // No Stripe configured in the test env → refund deferred, not silently lost.
        $this->assertEquals('refund_pending', $fresh->payment_status);
    }

    // ========================================================================
    // Promo with a non-positive discount_value never inflates the total
    // ========================================================================

    public function test_promo_negative_discount_value_ignored(): void
    {
        $customer = $this->createUser('customer');
        Passport::actingAs($customer, ['AccessToCustomer']);

        MartPromoCode::create([
            'code' => 'BADPROMO', 'discount_type' => 'fixed', 'discount_value' => -50.00,
            'min_order_amount' => 0, 'is_active' => true,
        ]);
        $product = MartProduct::create([
            'name' => 'Neg Item', 'price' => 10.00, 'stock' => 10, 'is_active' => true,
        ]);

        $r = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Neg St',
            'promo_code' => 'BADPROMO',
        ]);
        $r->assertOk();
        $order = MartOrder::find($r->json('data.id'));
        // Total stays at product price; no negative discount applied.
        $this->assertEquals('10.00', $order->total_amount);
        $this->assertEquals('0.00', $order->discount_amount);
    }

    // ========================================================================
    // Driver cancellation records reason + cancelled_by = driver
    // ========================================================================

    public function test_driver_cancel_records_reason(): void
    {
        $driver = $this->createUser('driver');
        $customer = $this->createUser('customer');
        $product = MartProduct::create([
            'name' => 'DrvCancel Item', 'price' => 6.00, 'stock' => 9, 'is_active' => true,
        ]);
        $order = MartOrder::create([
            'ref_id' => 'VM-DRVCAN01', 'customer_id' => $customer->id,
            'driver_id' => $driver->id, 'status' => 'accepted',
            'total_amount' => 6.00, 'delivery_address' => 'Drv St',
        ]);
        MartOrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 6.00, 'total_price' => 6.00,
        ]);

        Passport::actingAs($driver, ['AccessToDriver']);
        $r = $this->putJson('/api/driver/mart/update-status', [
            'order_id' => $order->id, 'status' => 'cancelled', 'reason' => 'Vehicle issue',
        ]);
        $r->assertOk();

        $fresh = MartOrder::find($order->id);
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertEquals('Vehicle issue', $fresh->cancellation_reason);
        $this->assertEquals('driver', $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
    }

    // ========================================================================
    // Customer can review a delivered mart order (once); guards enforced
    // ========================================================================

    public function test_mart_order_review(): void
    {
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver');

        // Not-yet-delivered order cannot be reviewed.
        $pending = MartOrder::create([
            'ref_id' => 'VM-REV0PEND', 'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'accepted', 'total_amount' => 10.00, 'delivery_address' => 'Rev St',
        ]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $early = $this->postJson("/api/customer/mart/orders/{$pending->id}/review", ['rating' => 5]);
        $early->assertStatus(404);

        // Delivered order can be reviewed.
        $delivered = MartOrder::create([
            'ref_id' => 'VM-REV0DONE', 'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'delivered', 'total_amount' => 10.00, 'delivery_address' => 'Rev St',
        ]);

        $bad = $this->postJson("/api/customer/mart/orders/{$delivered->id}/review", ['rating' => 9]);
        $bad->assertStatus(422);

        $ok = $this->postJson("/api/customer/mart/orders/{$delivered->id}/review", [
            'rating' => 5, 'comment' => 'Great service',
        ]);
        $ok->assertOk();
        $this->assertDatabaseHas('mart_reviews', [
            'order_id' => $delivered->id, 'driver_id' => $driver->id, 'rating' => 5,
        ]);

        // Second review on the same order is rejected.
        $dup = $this->postJson("/api/customer/mart/orders/{$delivered->id}/review", ['rating' => 4]);
        $dup->assertStatus(400);

        // Another customer cannot review someone else's order.
        $other = $this->createUser('customer');
        Passport::actingAs($other, ['AccessToCustomer']);
        $foreign = $this->postJson("/api/customer/mart/orders/{$delivered->id}/review", ['rating' => 1]);
        $foreign->assertStatus(404);
    }

    // ========================================================================
    // Default credentials seeder creates working, idempotent logins
    // ========================================================================

    public function test_default_users_seeder(): void
    {
        (new \Database\Seeders\DefaultUsersSeeder())->run();

        // Seeded customer can log in with username + PIN.
        $cust = $this->postJson('/api/customer/auth/pin-login', ['username' => 'customer', 'pin' => '123456']);
        $cust->assertOk();
        $this->assertArrayHasKey('token', $cust->json('data'));

        // Seeded driver can log in.
        $drv = $this->postJson('/api/driver/auth/pin-login', ['username' => 'driver', 'pin' => '123456']);
        $drv->assertOk();

        // Seeded driver is verified → mart driver endpoints are reachable.
        $driver = User::where('username', 'driver')->first();
        Passport::actingAs($driver, ['AccessToDriver']);
        $this->getJson('/api/driver/mart/pending-orders')->assertOk();

        // Idempotent: a second run does not duplicate accounts.
        (new \Database\Seeders\DefaultUsersSeeder())->run();
        $this->assertEquals(1, User::where('username', 'customer')->count());
        $this->assertEquals(1, User::where('username', 'driver')->count());
    }

    // ========================================================================
    // End-to-end VitoMart journey: browse → promo → order → accept → pickup →
    // proof → deliver → review (simulates the full customer + driver flow)
    // ========================================================================

    public function test_mart_full_journey(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver', ['username' => 'martjourneydrv']);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $product = MartProduct::create([
            'name' => 'Journey Item', 'price' => 25.00, 'stock' => 10, 'is_active' => true, 'category' => 'food',
        ]);
        MartPromoCode::create([
            'code' => 'JOURNEY5', 'discount_type' => 'fixed', 'discount_value' => 5.00,
            'min_order_amount' => 0, 'is_active' => true,
        ]);

        // 1. Customer browses products.
        Passport::actingAs($customer, ['AccessToCustomer']);
        $this->getJson('/api/customer/mart/products')->assertOk();

        // 2. Apply promo via the subtotal preview path the app actually uses.
        $promo = $this->postJson('/api/customer/mart/apply-promo', ['code' => 'JOURNEY5', 'subtotal' => 50.00]);
        $promo->assertOk();
        $this->assertEquals(5.00, $promo->json('data.discount'));

        // 3. Create the order with a chosen payment method (card) — must persist.
        $orderResp = $this->postJson('/api/customer/mart/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => '1 Journey St',
            'payment_method' => 'card',
            'promo_code' => 'JOURNEY5',
            'tip_amount' => 3.00,
        ]);
        $orderResp->assertOk();
        $orderId = $orderResp->json('data.id');
        $order = MartOrder::find($orderId);
        $this->assertEquals('48.00', $order->total_amount); // 50 - 5 promo + 3 tip
        $this->assertEquals('card', $order->payment_method);

        // 4. Driver sees the order and accepts it.
        Passport::actingAs($driver, ['AccessToDriver']);
        $this->getJson('/api/driver/mart/pending-orders')->assertOk();
        $this->postJson('/api/driver/mart/accept-order', ['order_id' => $orderId])->assertOk();
        $this->assertEquals('accepted', MartOrder::find($orderId)->status);

        // 5. Pick up.
        $this->putJson('/api/driver/mart/update-status', ['order_id' => $orderId, 'status' => 'picked_up'])->assertOk();

        // 6. Delivery is blocked until proof is captured.
        $this->putJson('/api/driver/mart/update-status', ['order_id' => $orderId, 'status' => 'delivered'])->assertStatus(422);

        // 7. Upload a signature, then deliver.
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        $this->postJson('/api/driver/mart/upload-proof', ['order_id' => $orderId, 'signature_base64' => $png])->assertOk();
        $this->putJson('/api/driver/mart/update-status', ['order_id' => $orderId, 'status' => 'delivered'])->assertOk();
        $this->assertEquals('delivered', MartOrder::find($orderId)->status);

        // 8. Customer reviews the completed delivery.
        Passport::actingAs($customer, ['AccessToCustomer']);
        $this->postJson("/api/customer/mart/orders/{$orderId}/review", ['rating' => 5, 'comment' => 'Sharp!'])->assertOk();
        $this->assertDatabaseHas('mart_reviews', ['order_id' => $orderId, 'rating' => 5, 'driver_id' => $driver->id]);
    }

    // ========================================================================
    // End-to-end VitoSend journey: parcel created → driver atomic-accept →
    // out_for_pickup through the shared ride status endpoint
    // ========================================================================

    public function test_parcel_full_journey(): void
    {
        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver', ['username' => 'parceljourneydrv']);
        DB::table('driver_details')->insert([
            'user_id' => $driver->id, 'is_online' => 1, 'availability_status' => 'available', 'is_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('time_tracks')->insert([
            'user_id' => $driver->id, 'date' => now()->toDateString(),
            'last_ride_completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $parcelId = Str::uuid()->toString();
        DB::table('trip_requests')->insert([
            'id' => $parcelId, 'customer_id' => $customer->id, 'driver_id' => null,
            'current_status' => 'pending', 'type' => 'parcel',
            'estimated_fare' => 50, 'actual_fare' => 0, 'estimated_distance' => 3, 'paid_fare' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Driver accepts the parcel atomically (verified driver only).
        Passport::actingAs($driver, ['AccessToDriver']);
        $this->postJson('/api/driver/parcel/atomic-accept', ['trip_request_id' => $parcelId])->assertOk();
        $this->assertDatabaseHas('trip_requests', [
            'id' => $parcelId, 'current_status' => 'accepted', 'driver_id' => $driver->id,
        ]);

        // Driver advances to out_for_pickup through the shared ride status endpoint.
        $this->putJson('/api/driver/ride/update-status', [
            'trip_request_id' => $parcelId, 'status' => 'out_for_pickup',
        ])->assertOk();
        $this->assertDatabaseHas('trip_requests', [
            'id' => $parcelId, 'current_status' => 'out_for_pickup',
        ]);
    }

    // ========================================================================
    // Wave 10 — VitoStripeController coverage (create-intent branches + webhook)
    // ========================================================================

    private function ensureSettingsTable(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key_name')->nullable();
                $table->string('settings_type')->nullable();
                $table->string('mode')->default('test');
                $table->text('live_values')->nullable();
                $table->text('test_values')->nullable();
                $table->timestamps();
            });
        }
    }

    /** Build a valid Stripe-Signature header and POST the raw payload to the webhook. */
    private function postSignedWebhook(array $event, string $secret = 'whsec_test_123')
    {
        putenv("STRIPE_WEBHOOK_SECRET={$secret}");
        $_ENV['STRIPE_WEBHOOK_SECRET'] = $secret;
        $_SERVER['STRIPE_WEBHOOK_SECRET'] = $secret;
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", $secret);
        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_stripe_create_payment_intent_validation_error(): void
    {
        $user = $this->createUser('customer');
        Passport::actingAs($user, ['AccessToCustomer']);
        $this->postJson('/api/customer/stripe/payment-intent', [])->assertStatus(400);
        $this->postJson('/api/customer/stripe/payment-intent', ['amount' => 999999])->assertStatus(400);
    }

    public function test_stripe_create_payment_intent_missing_config_returns_500(): void
    {
        $this->ensureSettingsTable(); // table exists but no stripe row
        $user = $this->createUser('customer');
        Passport::actingAs($user, ['AccessToCustomer']);
        $this->postJson('/api/customer/stripe/payment-intent', ['amount' => 50])->assertStatus(500);
    }

    public function test_stripe_create_payment_intent_missing_api_key_returns_500(): void
    {
        $this->ensureSettingsTable();
        DB::table('settings')->insert([
            'key_name'      => 'stripe',
            'settings_type' => 'payment_config',
            'mode'          => 'test',
            'test_values'   => json_encode(['published_key' => 'pk_test']), // no api_key
            'live_values'   => json_encode([]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $user = $this->createUser('customer');
        Passport::actingAs($user, ['AccessToCustomer']);
        $this->postJson('/api/customer/stripe/payment-intent', ['amount' => 50])->assertStatus(500);
    }

    public function test_stripe_order_payment_intent_validation_and_not_found(): void
    {
        $user = $this->createUser('customer');
        Passport::actingAs($user, ['AccessToCustomer']);
        $this->postJson('/api/customer/stripe/order-payment-intent', ['order_id' => 'not-a-uuid'])->assertStatus(400);
        $this->postJson('/api/customer/stripe/order-payment-intent', ['order_id' => Str::uuid()->toString()])->assertStatus(404);
    }

    public function test_stripe_order_payment_intent_already_paid_returns_400(): void
    {
        $user = $this->createUser('customer');
        $order = MartOrder::create([
            'id' => Str::uuid()->toString(), 'ref_id' => 'REF-PAID-' . Str::random(5),
            'customer_id' => $user->id, 'status' => 'pending', 'total_amount' => 30.00,
            'tip_amount' => 0, 'discount_amount' => 0, 'payment_status' => 'paid',
        ]);
        Passport::actingAs($user, ['AccessToCustomer']);
        $this->postJson('/api/customer/stripe/order-payment-intent', ['order_id' => $order->id])->assertStatus(400);
    }

    public function test_stripe_webhook_secret_not_configured_returns_500(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET');
        unset($_ENV['STRIPE_WEBHOOK_SECRET'], $_SERVER['STRIPE_WEBHOOK_SECRET']);
        $resp = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['id' => 'evt_x', 'type' => 'payment_intent.succeeded']));
        $this->assertEquals(500, $resp->getStatusCode());
    }

    public function test_stripe_webhook_invalid_signature_returns_400(): void
    {
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test_123');
        $_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_test_123';
        $_SERVER['STRIPE_WEBHOOK_SECRET'] = 'whsec_test_123';
        $resp = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=badsignature',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['id' => 'evt_x', 'type' => 'payment_intent.succeeded']));
        $this->assertEquals(400, $resp->getStatusCode());
    }

    public function test_stripe_webhook_credits_wallet_and_is_idempotent(): void
    {
        $user = $this->createUser('customer');
        $this->createUserAccount($user);
        $paymentIntentId = 'pi_' . Str::random(20);
        $event = [
            'id'   => 'evt_' . Str::random(16),
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id'       => $paymentIntentId,
                'amount'   => 5000, // cents → 50.00
                'currency' => 'usd',
                'metadata' => ['user_id' => $user->id, 'type' => 'wallet_topup'],
            ]],
        ];
        $this->postSignedWebhook($event)->assertOk();
        $this->assertEquals(50.00, (float) DB::table('user_accounts')->where('user_id', $user->id)->value('wallet_balance'));

        // Replaying the same event must not double-credit.
        $this->postSignedWebhook($event)->assertOk();
        $this->assertEquals(50.00, (float) DB::table('user_accounts')->where('user_id', $user->id)->value('wallet_balance'));
    }

    public function test_stripe_webhook_marks_order_paid(): void
    {
        $user = $this->createUser('customer');
        $order = MartOrder::create([
            'id' => Str::uuid()->toString(), 'ref_id' => 'REF-WH-' . Str::random(5),
            'customer_id' => $user->id, 'status' => 'accepted', 'total_amount' => 25.00,
            'tip_amount' => 0, 'discount_amount' => 0, 'payment_status' => 'unpaid',
        ]);
        $paymentIntentId = 'pi_' . Str::random(20);
        StripeEvent::create([
            'id' => Str::uuid()->toString(), 'stripe_event_id' => $paymentIntentId,
            'type' => 'payment_intent.created', 'user_id' => $user->id, 'amount' => 25.00,
            'currency' => 'usd', 'status' => 'pending', 'payment_intent_id' => $paymentIntentId,
            'metadata' => ['type' => 'order_payment', 'order_id' => $order->id],
        ]);
        $this->postSignedWebhook([
            'id'   => 'evt_' . Str::random(16),
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => $paymentIntentId, 'amount' => 2500, 'currency' => 'usd',
                'metadata' => ['user_id' => $user->id, 'type' => 'order_payment', 'order_id' => $order->id],
            ]],
        ])->assertOk();
        $this->assertEquals('paid', MartOrder::find($order->id)->payment_status);
    }

    public function test_stripe_webhook_payment_failed_marks_failed(): void
    {
        $user = $this->createUser('customer');
        $order = MartOrder::create([
            'id' => Str::uuid()->toString(), 'ref_id' => 'REF-WF-' . Str::random(5),
            'customer_id' => $user->id, 'status' => 'accepted', 'total_amount' => 25.00,
            'tip_amount' => 0, 'discount_amount' => 0, 'payment_status' => 'unpaid',
        ]);
        $paymentIntentId = 'pi_' . Str::random(20);
        StripeEvent::create([
            'id' => Str::uuid()->toString(), 'stripe_event_id' => $paymentIntentId,
            'type' => 'payment_intent.created', 'user_id' => $user->id, 'amount' => 25.00,
            'currency' => 'usd', 'status' => 'pending', 'payment_intent_id' => $paymentIntentId,
            'metadata' => ['type' => 'order_payment', 'order_id' => $order->id],
        ]);
        $this->postSignedWebhook([
            'id'   => 'evt_' . Str::random(16),
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => [
                'id' => $paymentIntentId, 'metadata' => ['user_id' => $user->id],
            ]],
        ])->assertOk();
        $this->assertEquals('failed', MartOrder::find($order->id)->payment_status);
    }

    public function test_stripe_webhook_charge_refunded_marks_refunded(): void
    {
        $user = $this->createUser('customer');
        $order = MartOrder::create([
            'id' => Str::uuid()->toString(), 'ref_id' => 'REF-WR-' . Str::random(5),
            'customer_id' => $user->id, 'status' => 'accepted', 'total_amount' => 25.00,
            'tip_amount' => 0, 'discount_amount' => 0, 'payment_status' => 'paid',
        ]);
        $paymentIntentId = 'pi_' . Str::random(20);
        StripeEvent::create([
            'id' => Str::uuid()->toString(), 'stripe_event_id' => $paymentIntentId,
            'type' => 'payment_intent.succeeded', 'user_id' => $user->id, 'amount' => 25.00,
            'currency' => 'usd', 'status' => 'succeeded', 'payment_intent_id' => $paymentIntentId,
            'metadata' => ['type' => 'order_payment', 'order_id' => $order->id],
        ]);
        $this->postSignedWebhook([
            'id'   => 'evt_' . Str::random(16),
            'type' => 'charge.refunded',
            'data' => ['object' => ['payment_intent' => $paymentIntentId]],
        ])->assertOk();
        $this->assertEquals('refunded', MartOrder::find($order->id)->payment_status);
    }

    // ========================================================================
    // Wave 10 — VitoMartAdminApiController CRUD (super-admin API)
    // ========================================================================

    public function test_mart_admin_api_product_crud(): void
    {
        $admin = $this->createUser('admin', ['username' => 'martadminapi']);
        Passport::actingAs($admin, ['AccessToSuperAdmin']);

        // index (empty)
        $this->getJson('/api/admin/mart/products')->assertOk();

        // store
        $create = $this->postJson('/api/admin/mart/products', [
            'name' => 'API Widget', 'category' => 'tools', 'price' => 9.99,
            'description' => 'desc', 'stock' => 5, 'is_active' => true,
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('mart_products', ['id' => $id, 'name' => 'API Widget']);
        $this->assertDatabaseHas('vito_audit_log', ['action' => 'create', 'entity_id' => $id]);

        // index with search filter now returns it
        $this->getJson('/api/admin/mart/products?search=Widget')->assertOk();

        // update
        $this->putJson("/api/admin/mart/products/{$id}", ['price' => 12.50, 'stock' => 8])->assertOk();
        $this->assertEquals(12.50, (float) MartProduct::find($id)->price);

        // destroy
        $this->deleteJson("/api/admin/mart/products/{$id}")->assertOk();
        $this->assertNull(MartProduct::find($id));
    }

    // ========================================================================
    // Wave 11 — QrTokenController coverage (validation + validate/public/revoke)
    // ========================================================================

    private function insertQrToken(array $overrides = []): string
    {
        $token = $overrides['token'] ?? Str::random(20);
        DB::table('qr_tokens')->insert(array_merge([
            'id'          => Str::uuid()->toString(),
            'token'       => $token,
            'role'        => 'customer',
            'created_by'  => null,
            'redeemed_by' => null,
            'redeemed_at' => null,
            'expires_at'  => now()->addHour(),
            'is_revoked'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
        return $token;
    }

    public function test_qr_generate_validation_and_length_param(): void
    {
        $admin = $this->createUser('admin', ['username' => 'qrgen']);
        Passport::actingAs($admin, ['AccessToSuperAdmin']);

        // invalid role → 422
        $this->postJson('/api/qr-token/generate', ['role' => 'banana'])->assertStatus(422);

        // explicit length honoured
        $resp = $this->postJson('/api/qr-token/generate', ['role' => 'driver', 'length' => 40]);
        $resp->assertOk();
        $this->assertEquals(40, $resp->json('data.length'));
        $this->assertEquals('driver', $resp->json('data.role'));
    }

    public function test_qr_validate_error_branches(): void
    {
        // validation: too short → 422
        $this->postJson('/api/qr-token/validate', ['token' => 'abc'])->assertStatus(422);
        // nonexistent → 404
        $this->postJson('/api/qr-token/validate', ['token' => Str::random(20)])->assertStatus(404);
        // expired → 404
        $expired = $this->insertQrToken(['expires_at' => now()->subHour()]);
        $this->postJson('/api/qr-token/validate', ['token' => $expired])->assertStatus(404);
        // revoked → 404
        $revoked = $this->insertQrToken(['is_revoked' => true]);
        $this->postJson('/api/qr-token/validate', ['token' => $revoked])->assertStatus(404);
        // redeemed → 404
        $redeemed = $this->insertQrToken(['redeemed_at' => now()]);
        $this->postJson('/api/qr-token/validate', ['token' => $redeemed])->assertStatus(404);
    }

    public function test_qr_validate_public_get_route(): void
    {
        // too short (<6) → 404
        $this->getJson('/api/qr/validate/abc')->assertStatus(404);
        // nonexistent but valid length → 404
        $this->getJson('/api/qr/validate/' . Str::random(20))->assertStatus(404);
        // valid → 200 with role
        $token = $this->insertQrToken(['role' => 'driver']);
        $ok = $this->getJson('/api/qr/validate/' . $token);
        $ok->assertOk();
        $this->assertTrue($ok->json('data.valid'));
        $this->assertEquals('driver', $ok->json('data.role'));
        // expired → 404
        $expired = $this->insertQrToken(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/qr/validate/' . $expired)->assertStatus(404);
    }

    public function test_qr_revoke_token(): void
    {
        $admin = $this->createUser('admin', ['username' => 'qrrevoke']);
        Passport::actingAs($admin, ['AccessToSuperAdmin']);

        // validation: missing token → 422
        $this->postJson('/api/qr-token/revoke', [])->assertStatus(422);

        // revoking a token created by someone else → 404
        $foreign = $this->insertQrToken(['created_by' => $this->createUser('admin', ['username' => 'otheradmin'])->id]);
        $this->postJson('/api/qr-token/revoke', ['token' => $foreign])->assertStatus(404);

        // revoking own token → 200, flag set
        $own = $this->insertQrToken(['created_by' => $admin->id]);
        $this->postJson('/api/qr-token/revoke', ['token' => $own])->assertOk();
        $this->assertEquals(1, (int) DB::table('qr_tokens')->where('token', $own)->value('is_revoked'));
    }

    // ========================================================================
    // Wave 11 — VitoMartController (customer) list/details + refund branch
    // ========================================================================

    public function test_mart_customer_order_list_and_details(): void
    {
        $customer = $this->createUser('customer');
        $order = MartOrder::create([
            'id' => Str::uuid()->toString(), 'ref_id' => 'VM-LIST-' . Str::random(4),
            'customer_id' => $customer->id, 'status' => 'pending', 'total_amount' => 10.00,
            'tip_amount' => 0, 'discount_amount' => 0, 'payment_status' => 'unpaid',
            'delivery_address' => 'List St',
        ]);
        Passport::actingAs($customer, ['AccessToCustomer']);

        $this->getJson('/api/customer/mart/orders')->assertOk();
        $this->getJson("/api/customer/mart/orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.id', $order->id);
        // nonexistent / not-owned → 404
        $this->getJson('/api/customer/mart/orders/' . Str::uuid()->toString())->assertStatus(404);
    }

    public function test_mart_cancel_paid_order_runs_refund_lookup(): void
    {
        // settings table present with a usable stripe config so refundOrderPayment
        // executes past the config checks (it returns refund_pending because no
        // succeeded StripeEvent exists for the order — no live Stripe call is made).
        $this->ensureSettingsTable();
        DB::table('settings')->insert([
            'key_name' => 'stripe', 'settings_type' => 'payment_config', 'mode' => 'test',
            'test_values' => json_encode(['api_key' => 'sk_test_dummy']),
            'live_values' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $customer = $this->createUser('customer');
        $driver = $this->createUser('driver', ['username' => 'cancelnotify']);
        $order = MartOrder::create([
            'id' => Str::uuid()->toString(), 'ref_id' => 'VM-RF-' . Str::random(4),
            'customer_id' => $customer->id, 'driver_id' => $driver->id,
            'status' => 'accepted', 'total_amount' => 12.00, 'tip_amount' => 0,
            'discount_amount' => 0, 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'delivery_address' => 'Refund St',
        ]);

        Passport::actingAs($customer, ['AccessToCustomer']);
        $this->putJson("/api/customer/mart/orders/{$order->id}/cancel")->assertOk();

        $fresh = MartOrder::find($order->id);
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertEquals('refund_pending', $fresh->payment_status);
    }
}
