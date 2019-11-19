<?php

namespace App\Models\Basket;

class BasketItem implements \JsonSerializable
{
    /** @var int */
    public $id;
    
    /**
     * Стоимость единцы товара без скидок.
     * @var float
     */
    public $cost;
    /**
     * Стоимость элемента корзины без скидок (cost*qty).
     * @var float
     */
    public $totalCost;
    /**
     * Цена элемента корзины со скидкой (totalCost - discount).
     * @var float
     */
    public $price;
    /**
     * Рамер скидки.
     * @var float
     */
    public $discount;

    /**
     * Количество единиц товара в элементе корзины.
     * @var int
     */
    public $qty;
    /**
     * Id товара (предложения мерчанта).
     * @var int
     */
    public $offerId;
    /** @var int */
    public $categoryId;
    /** @var int */
    public $brandId;
    
    /**
     * BasketItem constructor.
     * @param int $id
     * @param int $qty
     * @param int $offerId
     * @param int $categoryId
     * @param int $brandId
     */
    public function __construct(int $id, int $qty, int $offerId, int $categoryId, int $brandId)
    {
        $this->id = $id;
        $this->offerId = $offerId;
        $this->categoryId = $categoryId;
        $this->brandId = $brandId;
        $this->qty = $qty;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'qty' => $this->qty,
            'price' => $this->price,
            'discount' => $this->discount,
            'totalCost' => $this->totalCost,
            'cost' => $this->cost,
        ];
    }
}
