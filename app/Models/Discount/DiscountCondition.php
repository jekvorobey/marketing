<?php

namespace App\Models\Discount;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Условие возникновения скидки"
 * App\Models\Discount\Discount
 *
 * @property int $discount_id
 * @property int $type
 * @property array $condition
 * @mixin \Eloquent
 *
 */
class DiscountCondition extends AbstractModel
{
    /**
     * Тип условия возникновения права на скидку
     */
    /** На первый заказ */
    const FIRST_ORDER = 1;
    /** На заказ от определенной суммы */
    const MIN_PRICE_ORDER = 2;
    /** На заказ от определенной суммы товаров заданного бренда */
    const MIN_PRICE_BRAND = 3;
    /** На заказ от определенной суммы товаров заданной категории */
    const MIN_PRICE_CATEGORY = 4;
    /** На количество единиц одного товара */
    const EVERY_UNIT_PRODUCT = 5;
    /** На способ доставки */
    const DELIVERY_METHOD = 6;
    /** На способ оплаты */
    const PAY_METHOD = 7;
    /** Территория действия (регион с точки зрения адреса доставки заказа) */
    const REGION = 8;
    /** Для определенных покупателей */
    const CUSTOMER = 9;
    /** Порядковый номер заказа */
    const ORDER_SEQUENCE_NUMBER = 10;
    /** Взаимодействия с другими маркетинговыми инструментами */
    const DISCOUNT_SYNERGY = 11;
    /**
     * Скидка на определенные бандлы.
     * Не показывается в списке условий.
     */
    const BUNDLE = 12;

    /** Свойства условий скидки (для поля condition) */
    const FIELD_MIN_PRICE = 'minPrice';
    const FIELD_BRANDS = 'brands';
    const FIELD_CATEGORIES = 'categories';
    const FIELD_OFFER = 'offer';
    const FIELD_COUNT = 'count';
    const FIELD_DELIVERY_METHODS = 'deliveryMethods';
    const FIELD_PAYMENT_METHODS = 'paymentMethods';
    const FIELD_REGIONS = 'regions';
    const FIELD_ORDER_SEQUENCE_NUMBER = 'orderSequenceNumber';
    const FIELD_BUNDLES = 'bundles';
    const FIELD_CUSTOMER_IDS = 'customerIds';
    const FIELD_SYNERGY = 'synergy';

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['discount_id', 'type', 'condition'];

    /**
     * @var array
     */
    protected $casts = [
        'condition' => 'array',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @return float|null
     */
    public function getMinPrice()
    {
        return $this->condition[self::FIELD_MIN_PRICE] ?? null;
    }

    /**
     * @return array
     */
    public function getBrands()
    {
        return $this->condition[self::FIELD_BRANDS] ?? [];
    }

    /**
     * @return array
     */
    public function getCategories()
    {
        return $this->condition[self::FIELD_CATEGORIES] ?? [];
    }

    /**
     * @return int|null
     */
    public function getOffer()
    {
        return $this->condition[self::FIELD_OFFER] ?? null;
    }

    /**
     * @return int|null
     */
    public function getCount()
    {
        return $this->condition[self::FIELD_COUNT] ?? null;
    }

    /**
     * @return array
     */
    public function getDeliveryMethods()
    {
        return $this->condition[self::FIELD_DELIVERY_METHODS] ?? [];
    }

    /**
     * @return array
     */
    public function getPaymentMethods()
    {
        return $this->condition[self::FIELD_PAYMENT_METHODS] ?? [];
    }

    /**
     * @return array
     */
    public function getRegions()
    {
        return $this->condition[self::FIELD_REGIONS] ?? [];
    }

    /**
     * @return int|null
     */
    public function getOrderSequenceNumber()
    {
        return $this->condition[self::FIELD_ORDER_SEQUENCE_NUMBER] ?? null;
    }

    /**
     * @return array
     */
    public function getBundles()
    {
        return $this->condition[self::FIELD_BUNDLES] ?? [];
    }

    /**
     * @return array
     */
    public function getCustomerIds()
    {
        return $this->condition[self::FIELD_CUSTOMER_IDS] ?? [];
    }

    /**
     * @return array
     */
    public function getSynergy()
    {
        return $this->condition[self::FIELD_SYNERGY] ?? [];
    }
}
