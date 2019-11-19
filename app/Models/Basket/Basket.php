<?php

namespace App\Models\Basket;

use App\Models\Price\Price;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Basket implements \JsonSerializable
{
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
    /** @var BasketItem[] */
    public $items;
    
    public static function fromRequestData(array $data): self
    {
        $validator = Validator::make($data, [
            'user' => 'required|integer',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|integer',
            'items.*.offer_id' => 'required|integer',
            'items.*.brand_id' => 'required|integer',
            'items.*.category_id' => 'required|integer',
            
            //'refferal_code' => 'string',
            'delivery_method' => 'integer',
            'pay_method' => 'integer',
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $basket = new self($data['user']);
        $basket->referalCode = $data['referal_code'] ?? null;
        $basket->deliveryMethod = $data['delivery_method'] ?? null;
        $basket->payMethod = $data['pay_method'] ?? null;
        
        foreach ($data['items'] as $itemData) {
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
        
        $basketDiscount = 0;
        $this->cost = $totalCost;
        $this->discount = $totalItemDiscount + $basketDiscount;
        $this->price = $totalCost - $this->discount;
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
