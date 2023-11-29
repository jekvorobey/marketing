<?php

namespace App\Services\Calculator\Discount\Checker\ConditionCheckers;

use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\Product\ProductDto;
use Pim\Dto\Product\ProductPropertyValueDto;
use Pim\Services\ProductService\ProductService;

class PropertyConditionChecker extends AbstractConditionChecker
{
    /**
     * @throws PimException
     */
    public function check(): bool
    {
        $success = $this
            ->allBasketItemsProperties()
            ->contains(
                fn (ProductPropertyValueDto $dto) => $dto->property_id == $this->condition->getProperty() &&
                    in_array($dto->value, $this->condition->getPropertyValues())
            );

        if ($success) {
            $this->saveConditionToStore();
        }

        return $success;
    }

    /**
     * Все характеристики всех товаров из корзины
     * @return Collection
     * @throws PimException
     */
    private function allBasketItemsProperties(): Collection
    {
        $productIds = $this->input
            ->basketItems
            ->pluck('product_id')
            ->toArray();

        $products = app(ProductService::class)->products(
            (new RestQuery())
                ->addFields(ProductDto::entity(), 'id', 'properties')
                ->include('properties')
                ->setFilter('id', $productIds)
        );

        return $products->pluck('properties')->flatten(1);
    }
}
