<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MinPriceBrandConditionChecker;
use App\Services\Calculator\InputCalculator;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Services\BrandService\BrandService;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array|int[] $brands
 * @property array|int[] $minPrice
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class MinPriceBrandConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     * @throws PimException
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->brands = app(BrandService::class)
            ->brands((new RestQuery())->addFields('id'))
            ->pluck('id')
            ->shuffle()
            ->take(10)
            ->toArray();
        $this->inputBuilder = new InputParamsBuilder();
        $this->inputBuilder->setBasketItemsBrandIds($this->brands);
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
            DiscountCondition::MIN_PRICE_BRAND,
            [
                DiscountCondition::FIELD_BRANDS => $this->brands,
                DiscountCondition::FIELD_MIN_PRICE => $input->getMaxTotalPriceForBrands($this->brands) - 10,
            ]
        );

        $checker = new MinPriceBrandConditionChecker($input, $condition);

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
            DiscountCondition::MIN_PRICE_BRAND,
            [
                DiscountCondition::FIELD_BRANDS => $this->brands,
                DiscountCondition::FIELD_MIN_PRICE => $input->getMaxTotalPriceForBrands($this->brands) + 10,
            ]
        );

        $checker = new MinPriceBrandConditionChecker($input, $condition);

        $this->assertFalse($checker->check());
    }
}
