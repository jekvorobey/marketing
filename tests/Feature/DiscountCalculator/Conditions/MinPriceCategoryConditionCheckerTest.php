<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceCategoryConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Services\CategoryService\CategoryService;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array|int[] $categories
 * @property array|int[] $minPrice
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class MinPriceCategoryConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     * @throws PimException
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->categories = app(CategoryService::class)
            ->categories((new RestQuery())->addFields('id')->pageNumber(1, 300))
            ->pluck('id')
            ->shuffle()
            ->take(10)
            ->toArray();
        $this->inputBuilder = new InputParamsBuilder();
        $this->inputBuilder->setBasketItemsCategoryIds($this->categories);
    }

    /**
     * @throws PimException
     */
    public function test_min_price_valid()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $condition = DiscountConditionMock::create(
            DiscountCondition::MIN_PRICE_CATEGORY,
            [
                DiscountCondition::FIELD_CATEGORIES => $this->categories,
                DiscountCondition::FIELD_MIN_PRICE => $input->getMaxTotalPriceForCategories($this->categories) - 10,
            ]
        );

        $checker = new MinPriceCategoryConditionChecker($input, $condition);

        $this->assertTrue($checker->check());
    }

    /**
     * @throws PimException
     */
    public function test_min_price_invalid()
    {
        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $condition = DiscountConditionMock::create(
            DiscountCondition::MIN_PRICE_CATEGORY,
            [
                DiscountCondition::FIELD_CATEGORIES => $this->categories,
                DiscountCondition::FIELD_MIN_PRICE => $input->getMaxTotalPriceForCategories($this->categories) + 10,
            ]
        );

        $checker = new MinPriceCategoryConditionChecker($input, $condition);

        $this->assertFalse($checker->check());
    }
}
