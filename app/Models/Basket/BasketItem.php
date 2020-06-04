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
     * Примененные скидки
     * @var array
     */
    public $discounts;

    /**
     * Список бонусов за оффер
     * @var array
     */
    public $bonuses;

    /**
     * Сумма бонусов за оффер
     * @var int
     */
    public $bonus;

    /**
     * Сумма потраченных бонусов
     * @var int
     */
    public $bonusSpent;

    /**
     * Сумма оплаченная бонусами
     * @var int
     */
    public $bonusDiscount;

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
     * ID бандла, в который входит товар.
     * @var int
     */
    public $bundleId;

    /**
     * BasketItem constructor.
     * @param int $id
     * @param int $qty
     * @param int $offerId
     * @param int $categoryId
     * @param int $brandId
     * @param int $bundleId
     */
    public function __construct(int $id, int $qty, int $offerId, int $categoryId, int $brandId, ?int $bundleId)
    {
        $this->id = $id;
        $this->offerId = $offerId;
        $this->categoryId = $categoryId;
        $this->brandId = $brandId;
        $this->qty = $qty;
        $this->bundleId = $bundleId;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'offerId' => $this->offerId,
            'qty' => $this->qty,
            'price' => $this->price,
            'discount' => $this->discount,
            'discounts' => $this->discounts,
            'totalCost' => $this->totalCost,
            'cost' => $this->cost,
            'bonusSpent' => $this->bonusSpent ?? 0,
            'bonusDiscount' => $this->bonusDiscount ?? 0,
            'bonus' => $this->bonus ?? 0,
            'bonuses' => $this->bonuses ?? [],
            'bundleId' => $this->bundleId ?? 0,
        ];
    }
}
