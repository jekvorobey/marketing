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
        $this->params->put('customer', collect());
        $this->params->put('offers', collect());
        $this->params->put('promoCode', collect());
        $this->params->put('delivery', collect());
        $this->params->put('payment', collect());
    }

    /**
     * @param Collection|array $customers
     * @return DiscountCalculatorBuilder
     */
    public function customer($customers)
    {
        $this->params['customer'] = collect($customers);
        return $this;
    }

    /**
     * @param Collection|array $offers
     * @return DiscountCalculatorBuilder
     */
    public function offers($offers)
    {
        $this->params['offers'] = collect($offers);
        return $this;
    }

    /**
     * @param Collection|array $promoCode
     * @return DiscountCalculatorBuilder
     */
    public function promoCode($promoCode)
    {
        $this->params['promoCode'] = collect($promoCode);
        return $this;
    }

    /**
     * @param Collection|array $delivery
     * @return DiscountCalculatorBuilder
     */
    public function delivery($delivery)
    {
        $this->params['delivery'] = collect($delivery);
        return $this;
    }

    /**
     * @param Collection|array $payment
     * @return DiscountCalculatorBuilder
     */
    public function payment($payment)
    {
        $this->params['payment'] = collect($payment);
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
