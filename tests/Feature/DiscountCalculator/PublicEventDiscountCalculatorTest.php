<?php

namespace Tests\Feature\DiscountCalculator;

use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;
use Tests\Feature\DiscountCalculator\Mocks\Discounts\PublicEventDiscountMock;
use Tests\Feature\DiscountCalculator\Mocks\InputParams;

class PublicEventDiscountCalculatorTest extends DiscountCalculatorTest
{
    /**
     * Скидка на мастер-класс применение
     *
     * @return void
     * @throws PimException
     */
    public function test_public_event_discount_apply()
    {
        $this->inputParams = (new InputParams())
            ->withPublicEvents()
            ->baseParamsWithRandomBasketItems();

        $offerIds = collect($this->inputParams['basketItems'])->pluck('offerId');
        $offerId = $offerIds->first();

        /** @var OfferDto $offer */
        $offer = app(OfferService::class)->offers(
            (new RestQuery())->setFilter('id', $offerId)
        )->first();

        $discount = (new PublicEventDiscountMock())->create($offer->ticket_type_id);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(1, $calculator->getOutput()->appliedDiscounts->count());
        $this->assertEquals(
            $discount->value,
            $calculator->getOutput()->appliedDiscounts->first()['change']
        );
    }

    /**
     * Скидка на мастер-класс НЕ применение, другой categoryId
     *
     * @return void
     * @throws PimException
     */
    public function test_category_discount_not_apply()
    {
        $this->inputParams = (new InputParams())
            ->withPublicEvents()
            ->baseParamsWithRandomBasketItems();

        (new PublicEventDiscountMock())->create(9999);

        $calculator = $this->getCalculator();
        $calculator->calculate();

        $this->assertEquals(0, $calculator->getOutput()->appliedDiscounts->count());
    }
}
