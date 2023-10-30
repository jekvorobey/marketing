<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\DiscountCondition;
use Illuminate\Support\Collection;

/**
 * Класс для хранения данных по условиям, которые нужны в дальнейшем при расчете или применении.
 * Например, в условии на кол-во разных товаров в корзине (DiscountCondition::DIFFERENT_PRODUCTS_COUNT).
 * По сути просто стор.
 */
class DiscountConditionStore
{
    protected static ?self $instance = null;
    protected Collection $conditions;

    private function __construct()
    {}

    /**
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance) {
            return self::$instance;
        }

        self::$instance = new self();
        self::$instance->conditions = new Collection();

        return self::$instance;
    }

    /**
     * @return Collection
     */
    public static function getConditions(): Collection
    {
        return self::getInstance()->conditions;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->conditions->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return Collection
     */
    public static function put(string $key, mixed $value): Collection
    {
        return self::getInstance()->conditions->put($key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->conditions->has($key);
    }

    /**
     * @return bool
     */
    public static function isEmpty(): bool
    {
        return self::getInstance()->conditions->isEmpty();
    }

    /**
     * @return bool
     */
    public static function isNotEmpty(): bool
    {
        return !self::isEmpty();
    }

    /**
     * @param int $type
     * @return Collection
     */
    public static function getByType(int $type): Collection
    {
        return self::getConditions()->where('type', $type);
    }

    /**
     * @param int $type
     * @return DiscountCondition|null
     */
    public static function findByType(int $type): ?DiscountCondition
    {
        return self::getByType($type)->first();
    }
}
