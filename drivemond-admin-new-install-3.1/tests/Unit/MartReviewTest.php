<?php

namespace Tests\Unit;

use Modules\TripManagement\Entities\MartReview;
use PHPUnit\Framework\TestCase;

class MartReviewTest extends TestCase
{
    public function test_review_fillable_attributes(): void
    {
        $review = new MartReview([
            'order_id' => 'order-123',
            'customer_id' => 'cust-456',
            'driver_id' => 'drv-789',
            'rating' => 5,
            'comment' => 'Great service!',
        ]);

        $this->assertEquals('order-123', $review->order_id);
        $this->assertEquals('cust-456', $review->customer_id);
        $this->assertEquals('drv-789', $review->driver_id);
        $this->assertEquals(5, $review->rating);
        $this->assertEquals('Great service!', $review->comment);
    }

    public function test_review_casts_rating_to_integer(): void
    {
        $review = new MartReview([
            'order_id' => 'order-123',
            'customer_id' => 'cust-456',
            'driver_id' => 'drv-789',
            'rating' => '4',
            'comment' => 'Good service',
        ]);

        $this->assertIsInt($review->rating);
        $this->assertEquals(4, $review->rating);
    }

    public function test_review_has_uuids(): void
    {
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\Concerns\HasUuids', class_uses(MartReview::class)));
    }

    public function test_order_relationship_method_exists(): void
    {
        $review = new MartReview(['order_id' => 'test']);
        $this->assertTrue(method_exists($review, 'order'));
    }

    public function test_customer_relationship_method_exists(): void
    {
        $review = new MartReview(['customer_id' => 'test']);
        $this->assertTrue(method_exists($review, 'customer'));
    }

    public function test_driver_relationship_method_exists(): void
    {
        $review = new MartReview(['driver_id' => 'test']);
        $this->assertTrue(method_exists($review, 'driver'));
    }

    public function test_review_allows_null_comment(): void
    {
        $review = new MartReview([
            'order_id' => 'order-123',
            'customer_id' => 'cust-456',
            'driver_id' => 'drv-789',
            'rating' => 3,
            'comment' => null,
        ]);

        $this->assertNull($review->comment);
    }

    public function test_review_allows_null_driver_id(): void
    {
        $review = new MartReview([
            'order_id' => 'order-123',
            'customer_id' => 'cust-456',
            'driver_id' => null,
            'rating' => 4,
            'comment' => 'Nice!',
        ]);

        $this->assertNull($review->driver_id);
    }
}
