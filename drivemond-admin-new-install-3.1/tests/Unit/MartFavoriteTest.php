<?php

namespace Tests\Unit;

use Modules\TripManagement\Entities\MartFavorite;
use PHPUnit\Framework\TestCase;

class MartFavoriteTest extends TestCase
{
    public function test_fillable_attributes_are_defined(): void
    {
        $favorite = new MartFavorite([
            'customer_id' => 'customer-123',
            'product_id' => 'product-456',
        ]);

        $this->assertEquals('customer-123', $favorite->customer_id);
        $this->assertEquals('product-456', $favorite->product_id);
    }

    public function test_fillable_includes_expected_fields(): void
    {
        $expectedFillable = ['customer_id', 'product_id'];
        $this->assertEquals($expectedFillable, MartFavorite::getFillable());
    }

    public function test_product_relationship_method_exists(): void
    {
        $favorite = new MartFavorite();
        $this->assertTrue(method_exists($favorite, 'product'));
    }

    public function test_has_uuids_trait_is_applied(): void
    {
        $favorite = new MartFavorite();
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\Concerns\HasUuids', class_uses($favorite)));
    }

    public function test_customer_id_is_settable(): void
    {
        $favorite = new MartFavorite();
        $favorite->customer_id = 'cust-789';
        $this->assertEquals('cust-789', $favorite->customer_id);
    }

    public function test_product_id_is_settable(): void
    {
        $favorite = new MartFavorite();
        $favorite->product_id = 'prod-101';
        $this->assertEquals('prod-101', $favorite->product_id);
    }
}
