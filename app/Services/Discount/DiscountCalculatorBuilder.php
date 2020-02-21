<?php

namespace App\Services\Discount;

use Illuminate\Support\Collection;

/**
 * Class DiscountCalculatorBuilder
 * @package App\Core\Discount
 */
class DiscountCalculatorBuilder
{
    private $params;

    public function __construct()
    {
        $this->params = collect();
        $this->params->put('customers', collect());
        $this->params->put('offers', collect());
        $this->params->put('promoCode', collect());
        $this->params->put('delivery', collect());
        $this->params->put('payment', collect());
        $this->params->put('basket', collect());
    }

    /**
     * @param Collection $customers
     * @return DiscountCalculatorBuilder
     */
    public function customers(Collection $customers)
    {
        $this->params['customers'] = $customers;
        return $this;
    }

    /**
     * @param Collection $offers
     * @return DiscountCalculatorBuilder
     */
    public function offers(Collection $offers)
    {
        $this->params['offers'] = $offers;
        return $this;
    }

    /**
     * @param Collection $promoCode
     * @return DiscountCalculatorBuilder
     */
    public function promoCode(Collection $promoCode)
    {
        $this->params['promoCode'] = $promoCode;
        return $this;
    }

    /**
     * @param Collection $delivery
     * @return DiscountCalculatorBuilder
     */
    public function delivery(Collection $delivery)
    {
        $this->params['delivery'] = $delivery;
        return $this;
    }

    /**
     * @param Collection $payment
     * @return DiscountCalculatorBuilder
     */
    public function payment(Collection $payment)
    {
        $this->params['payment'] = $payment;
        return $this;
    }

    /**
     * @param Collection $basket
     * @return DiscountCalculatorBuilder
     */
    public function basket(Collection $basket)
    {
        $this->params['basket'] = $basket;
        return $this;
    }

    /**
     * @return array
     */
    public function calculate()
    {
        return (new DiscountCalculator($this->params))->calculate();
    }
}
