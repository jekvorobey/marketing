<?php

namespace App\Models\Basket;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Basket implements \JsonSerializable
{
    /** @var int */
    private $user;
    /** @var string */
    private $referalCode;
    /** @var int */
    private $deliveryMethod;
    /** @var int */
    private $payMethod;
    
    /** @var float */
    private $price;
    /** @var float */
    private $discount;
    /** @var BasketItem[] */
    private $items;
    
    public static function fromRequestData(array $data): self
    {
        $validator = Validator::make($data, [
            'user' => 'required|integer',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.offer_id' => 'required|integer',
            'items.*.brand_id' => 'required|integer',
            'items.*.category_id' => 'required|integer',
            
            'referal_code' => 'string',
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
            ['id' => $id, 'offer_id' => $offerId, 'category_id' => $categoryId, 'brand_id' => $brandId] = $itemData;
            $basket->items[] = new BasketItem($id, $offerId, $categoryId, $brandId);
        }
        
        return $basket;
    }
    
    public function __construct(int $userId)
    {
        $this->user = $userId;
    }
    
    public function calculatePrices()
    {
        foreach ($this->items as $item) {
            // todo
        }
    }
    
    public function jsonSerialize()
    {
        return [
            'price' => $this->price,
            'discount' => $this->discount,
            'items' => $this->items,
        ];
    }
}
