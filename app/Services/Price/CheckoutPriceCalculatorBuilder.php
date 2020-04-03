<?php

namespace App\Services\Price;

use Illuminate\Support\Collection;

/**
 * Class PriceCalculatorBuilder
 * @package App\Core\Discount
 */
class CheckoutPriceCalculatorBuilder
{
    private $params;

    public function __construct()
    {
        $this->params = collect();
        $this->params->put('customer', collect());
        $this->params->put('offers', collect());
        $this->params->put('promoCode', null);
        $this->params->put('deliveries', collect());
        $this->params->put('payment', collect());
    }

    /**
     * @param Collection|array $customers
     *
     * @return CheckoutPriceCalculatorBuilder
     */
    public function customer($customers)
    {
        $this->params['customer'] = collect($customers);
        return $this;
    }

    /**
     * @param Collection|array $offers
     *
     * @return CheckoutPriceCalculatorBuilder
     */
    public function offers($offers)
    {
        $this->params['offers'] = collect($offers);
        return $this;
    }

    /**
     * @param string|null $promoCode
     *
     * @return CheckoutPriceCalculatorBuilder
     */
    public function promoCode(?string $promoCode)
    {
        $this->params['promoCode'] = $promoCode;
        return $this;
    }

    /**
     * @param Collection|array $deliveries
     *
     * @return CheckoutPriceCalculatorBuilder
     */
    public function deliveries($deliveries)
    {
        $this->params['deliveries'] = collect($deliveries);
        return $this;
    }

    /**
     * @param Collection|array $payment
     *
     * @return CheckoutPriceCalculatorBuilder
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
        return (new CheckoutPriceCalculator($this->params))->calculate();
    }

    /**
     * @return Collection
     */
    public function getParams()
    {
        return $this->params;
    }
}
