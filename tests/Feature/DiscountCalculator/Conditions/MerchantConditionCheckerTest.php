<?php

namespace Tests\Feature\DiscountCalculator\Conditions;

use App\Models\Discount\DiscountCondition;
use App\Services\Calculator\Discount\Checker\ConditionCheckers\MerchantConditionChecker;
use App\Services\Calculator\Discount\DiscountConditionStore;
use App\Services\Calculator\InputCalculator;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Services\OfferService\OfferService;
use Tests\CreatesApplication;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\Conditions\DiscountConditionMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array|int[] $merchants
 * @property DiscountCondition $condition
 * @property InputParamsBuilder $inputBuilder
 */
class MerchantConditionCheckerTest extends TestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $offers = app(OfferService::class)->offers(
            (new RestQuery())->addFields('merchant_id')->pageNumber(1, 10)
        )->pluck('merchant_id');
        $this->merchants = [$offers->random()];

        $this->condition = DiscountConditionMock::create(
            DiscountCondition::MERCHANT,
            [DiscountCondition::FIELD_MERCHANTS => $this->merchants]
        );
        $this->inputBuilder = new InputParamsBuilder();
    }

    /**
     * @throws PimException
     */
    public function test_merchant_valid()
    {
        $this->inputBuilder->setBasketItemsMerchantIds($this->merchants);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new MerchantConditionChecker($input, $this->condition);

        $this->assertTrue($checker->check());
        $this->assertNotNull(
            DiscountConditionStore::get(spl_object_hash($this->condition))
        );
    }

    /**
     * @throws PimException
     */
    public function test_merchant_invalid()
    {
        $this->inputBuilder->setExceptedBasketItemsMerchantIds($this->merchants);

        $input = new InputCalculator(
            $this->inputBuilder->build()
        );

        $checker = new MerchantConditionChecker($input, $this->condition);

        $this->assertFalse($checker->check());
    }
}
