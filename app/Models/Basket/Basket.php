<?php

namespace App\Models\Basket;

use App\Models\Price\Price;

class Basket implements \JsonSerializable
{
    private const CERTS = [
        'CERT2020-500' => ['id' => 1,'code' => 'CERT2020-500', 'amount' => 500],
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
    /** @var int */
    public $deliveryMethod;
    /** @var int */
    public $payMethod;
    /** @var int */
    public $bonus;
    /** @var string */
    public $promocode;
    /** @var array */
    public $certificates;
    
    /** @var int */
    private $appliedBonus;
    private $discountByBonus = 0;
    /** @var int */
    private $newBonus;
    /** @var string */
    private $appliedPromocode;
    private $discountByPromocode = 0;
    /** @var array */
    private $appliedCertificates;
    private $discountByCertificates = 0;
    
    /** @var BasketItem[] */
    public $items;
    
    public static function fromRequestData(array $data): self
    {
        $basket = new self($data['user']);
        
        @([
            'referal_code' => $basket->referalCode,
            'delivery_method' => $basket->deliveryMethod,
            'pay_method' => $basket->payMethod,
            
            'bonus' => $basket->bonus,
            'promocode' => $basket->promocode,
            'certificates' => $basket->certificates,
            
            'items' => $items
        ] = $data);
        
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
    
    public function addPrices()
    {
        $ids = array_map(function (BasketItem $item) {
            return $item->offerId;
        }, $this->items);
        $prices = Price::query()->whereIn('offer_id', $ids)->get()->keyBy('offer_id');
        $totalCost = 0;
        $totalItemDiscount = 0;
        // todo добавить расчёт скидок
        foreach ($this->items as $item) {
            if ($prices->has($item->offerId)) {
                $item->cost = $prices[$item->offerId]->price;
                $item->totalCost = $item->cost * $item->qty;
                $item->discount = 0 * $item->qty;
                $item->price = $item->totalCost - $item->discount;
                
                $totalCost += $item->totalCost;
                $totalItemDiscount += $item->discount;
            } else {
                throw new \Exception("basket item offer {$item->offerId} without price");
            }
        }
        
        $this->applyBonus();
        $this->applyPromocode();
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
    
    private function applyPromocode(): void
    {
        if ($this->promocode == 'ADMITAD700') {
            $this->appliedPromocode = 'ADMITAD700';
            $this->discountByPromocode = 700;
        } else {
            $this->appliedPromocode = '';
        }
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
            'discount' => $this->discount,
            
            'items' => $this->items,
        ];
    }
}
