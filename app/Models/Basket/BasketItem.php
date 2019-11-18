<?php

namespace App\Models\Basket;

class BasketItem implements \JsonSerializable
{
    /** @var int */
    public $id;
    /** @var float */
    public $price;
    /** @var float */
    public $discount;
    /** @var int */
    public $offerId;
    /** @var int */
    public $categoryId;
    /** @var int */
    public $brandId;
    
    /**
     * BasketItem constructor.
     * @param int $id
     * @param int $offerId
     * @param int $categoryId
     * @param int $brandId
     */
    public function __construct(int $id, int $offerId, int $categoryId, int $brandId)
    {
        $this->id = $id;
        $this->offerId = $offerId;
        $this->categoryId = $categoryId;
        $this->brandId = $brandId;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'discount' => $this->discount
        ];
    }
}
