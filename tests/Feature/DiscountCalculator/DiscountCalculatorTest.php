<?php

namespace Tests\Feature\DiscountCalculator;

use App\Models\Discount\Discount;
use App\Services\Calculator\Discount\Calculators\DiscountCalculator;
use App\Services\Calculator\InputCalculator;
use App\Services\Calculator\OutputCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\DiscountCalculator\Mocks\InputParams;
use Tests\Feature\DiscountCalculator\Mocks\InputParamsBuilder;
use Tests\TestCase;

/**
 * @property array $inputParams
 * @property InputParamsBuilder $inputBuilder
 */
class DiscountCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    protected int $basketItemsCount;
    protected array $inputParams;

    /**
     * @return void
     * @throws \Throwable
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createApplication();
        \DB::beginTransaction();

        $this->pauseAllDiscounts();
        $this->inputBuilder = new InputParamsBuilder();
        $this->inputParams = (new InputParams())->baseParamsWithRandomBasketItems();
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function tearDown(): void
    {
        \DB::rollback();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test()
    {
        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    private function pauseAllDiscounts(): void
    {
        Discount::query()->update([
            'status' => Discount::STATUS_PAUSED
        ]);
    }

    /**
     * @param int $basketItemsCount
     * @return void
     */
    public function setBasketItemsCount(int $basketItemsCount): void
    {
        $this->basketItemsCount = $basketItemsCount;
    }

    /**
     * @return DiscountCalculator
     */
    protected function getCalculator(): DiscountCalculator
    {
        return new DiscountCalculator(
            new InputCalculator($this->inputParams),
            new OutputCalculator()
        );
    }
}
