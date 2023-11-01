<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\PropertyConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Dto\Product\ProductApprovalStatus;
use Pim\Dto\Product\ProductDto;
use Pim\Dto\Product\ProductPropertyValueDto;
use Pim\Dto\PropertyDto;
use Pim\Services\ProductService\ProductService;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

class PropertyConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    protected int $propertyId;
    protected array $propertyValues;
    protected int $productId;

    /**
     * @return void
     * @throws PimException
     */
    public function setUp(): void
    {
        parent::setUp();

        $productService = app(ProductService::class);

        /** @var PropertyDto $property */
        $property = $productService->getProperties(
            (new RestQuery())
                ->include('categoryPropertyLinks')
                ->setFilter('type', PropertyDto::TYPE_DIRECTORY)
        )->first();
        $categories = array_column($property->categoryPropertyLinks, 'category_id');

        /** @var ProductDto $product */
        $products = $productService->products(
            (new RestQuery())
                ->include('properties.propertyDirectoryValue')
                ->setFilter('approval_status', ProductApprovalStatus::STATUS_APPROVED)
                ->setFilter('category_id', $categories)
        )->take(50);

        foreach ($products as $product) {
            if (!empty($product->properties)) {
                /** @var ProductPropertyValueDto $property */
                $property = head($product->properties);
                $this->propertyId = $property->property_id;
                $this->propertyValues = [$property->propertyDirectoryValue->id];
                $this->productId = $product->id;
                break;
            }
        }

        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_property_valid()
    {

        $this->inputBuilder->setProductIds([$this->productId]);
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new PropertyConditionChecker($input, $this->makeValidPropertyCondition());
        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_property_invalid()
    {
        $this->inputBuilder->setProductIds([$this->productId]);
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new PropertyConditionChecker($input,$this->makeInValidPropertyCondition());
        $this->assertFalse($checker->check());
    }

    /**
     * @return DiscountCondition
     */
    private function makeValidPropertyCondition(): DiscountCondition
    {
        return DiscountConditionMock::create(
            DiscountCondition::PROPERTY,
            [
                DiscountCondition::FIELD_PROPERTY => $this->propertyId,
                DiscountCondition::FIELD_PROPERTY_VALUES => $this->propertyValues
            ]
        );

    }

    /**
     * @return DiscountCondition
     */
    private function makeInValidPropertyCondition(): DiscountCondition
    {
        return DiscountConditionMock::create(
            DiscountCondition::PROPERTY,
            [
                DiscountCondition::FIELD_PROPERTY => $this->propertyId,
                DiscountCondition::FIELD_PROPERTY_VALUES => [99999]
            ]
        );
    }
}
