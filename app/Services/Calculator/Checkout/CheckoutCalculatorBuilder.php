<?php

namespace App\Services\Calculator\Checkout;

use Illuminate\Support\Collection;
use Pim\Core\PimException;

/**
 * Class CheckoutCalculatorBuilder
 * @package App\Core\Discount
 */
class CheckoutCalculatorBuilder
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
     * @return CheckoutCalculatorBuilder
     */
    public function customer($customers)
    {
        $this->params['customer'] = collect($customers);
        return $this;
    }

    /**
     * @param Collection|array $offers
     *
     * @return CheckoutCalculatorBuilder
     */
    public function offers($offers)
    {
        $this->params['offers'] = collect($offers);
        return $this;
    }

    /**
     * @param string|null $promoCode
     *
     * @return CheckoutCalculatorBuilder
     */
    public function promoCode(?string $promoCode)
    {
        $this->params['promoCode'] = $promoCode;
        return $this;
    }

    /**
     * @param Collection|array $deliveries
     *
     * @return CheckoutCalculatorBuilder
     */
    public function deliveries($deliveries)
    {
        $this->params['deliveries'] = collect($deliveries);
        return $this;
    }

    /**
     * @param Collection|array $payment
     *
     * @return CheckoutCalculatorBuilder
     */
    public function payment($payment)
    {
        $this->params['payment'] = collect($payment);
        return $this;
    }

    /**
     * @return array
     * @throws PimException
     */
    public function calculate()
    {
        return (new CheckoutCalculator($this->params))->calculate();
    }
}
