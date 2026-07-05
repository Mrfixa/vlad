<?php

namespace Tests\Unit;

use Modules\TripManagement\Entities\MartOrderItem;
use PHPUnit\Framework\TestCase;

class MartOrderItemTest extends TestCase
{
    public function test_fillable_attributes_are_defined(): void
    {
        $item = new MartOrderItem([
            'order_id' => 'order-123',
            'product_id' => 'product-456',
            'quantity' => 3,
            'unit_price' => 19.99,
            'total_price' => 59.97,
        ]);

        $this->assertEquals('order-123', $item->order_id);
        $this->assertEquals('product-456', $item->product_id);
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(19.99, $item->unit_price);
        $this->assertEquals(59.97, $item->total_price);
    }

    public function test_casts_quantity_as_integer(): void
    {
        $item = new MartOrderItem(['quantity' => '5']);
        $this->assertEquals(5, $item->quantity);
        $this->assertIsInt($item->quantity);
    }

    public function test_casts_unit_price_as_decimal(): void
    {
        $item = new MartOrderItem(['unit_price' => '25.50']);
        $this->assertEquals('25.50', $item->unit_price);
    }

    public function test_casts_total_price_as_decimal(): void
    {
        $item = new MartOrderItem(['total_price' => '75.00']);
        $this->assertEquals('75.00', $item->total_price);
    }

    public function test_order_relationship_method_exists(): void
    {
        $item = new MartOrderItem();
        $this->assertTrue(method_exists($item, 'order'));
    }

    public function test_product_relationship_method_exists(): void
    {
        $item = new MartOrderItem();
        $this->assertTrue(method_exists($item, 'product'));
    }

    public function test_fillable_includes_expected_fields(): void
    {
        $expectedFillable = ['order_id', 'product_id', 'quantity', 'unit_price', 'total_price'];
        $this->assertEquals($expectedFillable, MartOrderItem::getFillable());
    }

    public function test_has_uuids_trait_is_applied(): void
    {
        $item = new MartOrderItem();
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\Concerns\HasUuids', class_uses($item)));
    }

    public function test_quantity_casts_from_integer(): void
    {
        $item = new MartOrderItem(['quantity' => 10]);
        $this->assertEquals(10, $item->quantity);
        $this->assertIsInt($item->quantity);
    }

    public function test_quantity_casts_from_float(): void
    {
        $item = new MartOrderItem(['quantity' => 7.8]);
        $this->assertEquals(7, $item->quantity);
    }
}
