<?php

namespace Tests\Unit;

use Modules\TripManagement\Entities\MartCategory;
use PHPUnit\Framework\TestCase;

class MartCategoryTest extends TestCase
{
    public function test_category_fillable_attributes(): void
    {
        $category = new MartCategory([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'image' => 'electronics.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertEquals('Electronics', $category->name);
        $this->assertEquals('electronics', $category->slug);
        $this->assertEquals('electronics.jpg', $category->image);
        $this->assertTrue($category->is_active);
        $this->assertEquals(1, $category->sort_order);
    }

    public function test_category_casts_attributes_correctly(): void
    {
        $category = new MartCategory([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'image' => 'test.jpg',
            'is_active' => 1,
            'sort_order' => '5',
        ]);

        $this->assertTrue($category->is_active);
        $this->assertIsInt($category->sort_order);
        $this->assertEquals(5, $category->sort_order);
    }

    public function test_category_casts_inactive_to_false(): void
    {
        $category = new MartCategory([
            'name' => 'Inactive Category',
            'slug' => 'inactive',
            'image' => 'inactive.jpg',
            'is_active' => 0,
            'sort_order' => 10,
        ]);

        $this->assertFalse($category->is_active);
    }

    public function test_category_has_soft_deletes(): void
    {
        $category = new MartCategory([
            'name' => 'Soft Delete Test',
            'slug' => 'soft-delete-test',
        ]);

        $this->assertTrue(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(MartCategory::class)));
    }

    public function test_category_has_uuids(): void
    {
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\Concerns\HasUuids', class_uses(MartCategory::class)));
    }

    public function test_products_relationship_method_exists(): void
    {
        $category = new MartCategory(['name' => 'Test']);
        $this->assertTrue(method_exists($category, 'products'));
    }
}
