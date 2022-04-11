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
        $this->params->put('items', collect());
        $this->params->put('promoCode', null);
        $this->params->put('deliveries', collect());
        $this->params->put('payment', collect());
        $this->params->put('bonus', 0);
    }

    /**
     * @param Collection|array $customers
     */
    public function customer($customers): self
    {
        $this->params['customer'] = collect($customers);
        return $this;
    }

    /**
     * @param Collection|array $basketItems
     */
    public function basketItems($basketItems): self
    {
        $this->params['basketItems'] = collect($basketItems);
        return $this;
    }

    /**
     * @param Collection|array $bundles
     */
    public function bundles($bundles): self
    {
        $this->params['bundles'] = collect($bundles);
        return $this;
    }

    public function promoCode(?string $promoCode): self
    {
        $this->params['promoCode'] = $promoCode;
        return $this;
    }

    /**
     * @param Collection|array $deliveries
     */
    public function deliveries($deliveries): self
    {
        $this->params['deliveries'] = collect($deliveries);
        return $this;
    }

    /**
     * @param Collection|array $payment
     */
    public function payment($payment): self
    {
        $this->params['payment'] = collect($payment);
        return $this;
    }

    /**
     * @param string $id
     */
    public function regionFiasId($id): self
    {
        $this->params['regionFiasId'] = $id;
        return $this;
    }

    public function bonus(?int $bonus): self
    {
        $this->params['bonus'] = $bonus ?? 0;
        return $this;
    }

    /**
     * @throws PimException
     */
    public function calculate(): array
    {
        return (new CheckoutCalculator($this->params))->calculate();
    }
}
