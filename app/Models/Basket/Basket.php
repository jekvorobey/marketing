<?php

namespace App\Models\Basket;

use App\Services\Price\CheckoutPriceCalculatorBuilder;
use Illuminate\Support\Collection;
use Exception;

class Basket implements \JsonSerializable
{
    private const CERTS = [
        'CERT2020-500' => ['id' => 1, 'code' => 'CERT2020-500', 'amount' => 500],
        'CERT2019-1000' => ['id' => 2, 'code' => 'CERT2019-1000', 'amount' => 1000],
    ];

    /**
     * Стоимость корзины без скидок.
     * @var float
     */
    public $cost;
    /**
     * Итоговая цена всей корзины (cost - discount).
     * @var float
     */
    public $price;
    /**
     * Размер скидки.
     * @var float
     */
    public $discount;

    /** @var int */
    public $user;
    /** @var string */
    public $referalCode;
    /** @var Collection|array */
    public $deliveries;
    /** @var int */
    public $payMethod;
    /** @var int */
    public $bonus;
    /** @var string */
    public $promoCode;
    /** @var array */
    public $certificates;

    /** @var int */
    private $appliedBonus;
    private $discountByBonus = 0;

    /** @var array */
    private $appliedCertificates;
    private $discountByCertificates = 0;

    /** @var array */
    private $appliedDiscounts;
    /** @var array */
    private $appliedPromoCodes = [];

    /** @var BasketItem[] */
    public $items;

    public static function fromRequestData(array $data): self
    {
        $basket = new self($data['user']);

        @([
            'referal_code' => $basket->referalCode,
            'deliveries' => $basket->deliveries,
            'pay_method' => $basket->payMethod,
            'marketing' => $marketing,
            'bonus' => $basket->bonus,
            'promoCode' => $basket->promoCode,
            'certificates' => $basket->certificates,

            'items' => $items
        ] = $data);

         $basket->promoCode = $marketing['promoCode'] ?? '';
         $basket->bonus = $marketing['bonus'] ?? 0;
         $basket->certificates = $marketing['certificates'] ?? [];

        foreach ($items as $itemData) {
            [
                'id' => $id,
                'qty' => $qty,
                'offer_id' => $offerId,
                'category_id' => $categoryId,
                'brand_id' => $brandId
            ] = $itemData;
            $basket->items[] = new BasketItem($id, $qty, $offerId, $categoryId, $brandId);
        }

        return $basket;
    }

    public function __construct(int $userId)
    {
        $this->user = $userId;
    }

    /**
     * @throws Exception
     */
    public function addPrices()
    {
        $offers = collect($this->items)->transform(function(BasketItem $item) {
            return ['id' => $item->offerId, 'qty' => $item->qty];
        });

        $calculation = (new CheckoutPriceCalculatorBuilder())
            ->customer(['id' => $this->user])
            ->payment(['method' => $this->payMethod])
            ->deliveries($this->deliveries)
            ->offers($offers)
            ->promoCode($this->promoCode)
            ->calculate();

        $this->appliedPromoCodes = $calculation['promoCodes'];
        $this->appliedDiscounts = $calculation['discounts'];
        $this->deliveries       = $calculation['deliveries'];

        $totalCost = 0;
        $totalItemDiscount = 0;
        foreach ($this->items as $item) {
            if (!$calculation['offers']->has($item->offerId)) {
                throw new Exception("basket item offer {$item->offerId} without price");
            }

            $offer = $calculation['offers'][$item->offerId];
            $offer['cost'] = $offer['cost'] ?? $offer['price'];
            $offer['discount'] = $offer['discount'] ?? 0;

            $item->cost = $offer['cost'];
            $item->totalCost = $offer['cost'] * $offer['qty'];
            $item->discount = $offer['discount'] * $offer['qty'];
            $item->discounts = $offer['discounts'] ?? [];
            $item->price = $offer['price'] * $offer['qty'];
            $totalCost += $item->totalCost;
            $totalItemDiscount += $item->discount;
        }

        $this->applyBonus();
        $this->applyCertificates();

        $basketDiscount = 0;
        $this->cost = $totalCost;
        $this->discount = $totalItemDiscount + $basketDiscount;
        $this->price = $totalCost - $this->discount;
    }

    private function availableBonus(): int
    {
        return 500; // todo
    }

    private function applyBonus(): void
    {
        $available = $this->availableBonus();
        if ($this->bonus <= $available) {
            $this->appliedBonus = $this->bonus;
        } else {
            $this->appliedBonus = $available;
        }
        $this->discountByBonus = $this->appliedBonus;
    }

    private function applyCertificates(): void
    {
        if (!$this->certificates) {
            return;
        }
        foreach ($this->certificates as $code) {
            if (isset($this->appliedCertificates[$code])) {
                continue;
            }
            $cert = self::CERTS[$code] ?? null;
            if ($cert) {
                $this->appliedCertificates[] = $cert;
                $this->discountByCertificates += $cert['amount'];
            }
        }
    }

    public function jsonSerialize()
    {
        return [
            'cost' => $this->cost,
            'price' => $this->price,
            'discounts' => $this->appliedDiscounts,
            'promoCodes' => $this->appliedPromoCodes,
            'items' => $this->items,
            'deliveries' => $this->deliveries,
        ];
    }
}
