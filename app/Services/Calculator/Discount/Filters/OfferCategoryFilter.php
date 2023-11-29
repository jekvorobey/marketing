<?php

namespace App\Services\Calculator\Discount\Filters;

use App\Services\Calculator\InputCalculator;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\CategoryDto;

/**
 * Фильтр офферов для скидки по категории
 */
class OfferCategoryFilter
{
    protected Collection $categoryIds;
    protected Collection $exceptBrandIds;
    protected Collection $exceptOfferIds;
    /** @var Collection [[category_id (int) => ['except' => bool, 'ids' => int[]]], ...] */
    protected Collection $additionalCategories;
    protected Collection $basketItems;
    protected Collection $filteredBasketItems;
    protected ?int $merchantId = null;

    public function __construct()
    {
        $this->categoryIds = new Collection();
        $this->exceptBrandIds = new Collection();
        $this->exceptOfferIds = new Collection();
        $this->additionalCategories = new Collection();
        $this->basketItems = new Collection();
        $this->filteredBasketItems = new Collection();
    }

    /**
     * @return Collection
     */
    public function getFilteredOfferIds(): Collection
    {
        $this->filter();
        return $this->filteredBasketItems->pluck('offer_id');
    }

    /**
     * @return void
     */
    protected function filter(): void
    {
        $this->filterByCategories();
        $this->filterByExceptBrands();
        $this->filterByExceptOffers();
        $this->filterByMerchant();
        $this->filterByAdditionalCategories();
    }

    /**
     * @return void
     */
    protected function filterByCategories(): void
    {
        $this->filteredBasketItems = $this->filteredBasketItems
            ->filter(function (Collection $basketItem) {
                $offerCategory = $this->getAllCategories()->get($basketItem['category_id']);

                return $offerCategory
                    && $this->categoryIds->reduce(function ($carry, $categoryId) use ($offerCategory) {
                        /** @var CategoryDto $category */
                        $category = $this->getAllCategories()->get($categoryId);
                        return $carry || $category?->isSelfOrAncestorOf($offerCategory);
                    });
            });
    }

    /**
     * @return void
     */
    protected function filterByExceptBrands(): void
    {
        $this->filteredBasketItems = $this->filteredBasketItems->reject(
            fn (Collection $basketItem) => $this->exceptBrandIds->contains($basketItem['brand_id'])
        );
    }

    /**
     * @return void
     */
    protected function filterByExceptOffers(): void
    {
        $this->filteredBasketItems = $this->filteredBasketItems->reject(
            fn (Collection $basketItem) => $this->exceptOfferIds->contains($basketItem['offer_id'])
        );
    }

    /**
     * @return void
     */
    protected function filterByMerchant(): void
    {
        if (!$this->merchantId) {
            return;
        }

        $this->filteredBasketItems = $this->filteredBasketItems->filter(
            fn (Collection $basketItem) => $basketItem['merchant_id'] == $this->merchantId
        );
    }

    /**
     * @return void
     */
    protected function filterByAdditionalCategories(): void
    {
        if ($this->additionalCategories->isEmpty()) {
            return;
        }

        $this->filteredBasketItems = $this->filteredBasketItems->filter(function (Collection $basketItem) {
            /** @var Collection $data */
            $data = $this->additionalCategories->get($basketItem['category_id']);
            if (!$data) {
                return true;
            }
            /** @var Collection $ids */
            $ids = $data->get('ids');
            $diff = $ids->intersect($basketItem['additional_category_ids']);
            return $data->get('except')
                ? $diff->isEmpty()
                : $diff->isNotEmpty();
        });
    }

    /**
     * @return Collection
     * @throws PimException
     */
    protected function getAllCategories(): Collection
    {
        return InputCalculator::getAllCategories();
    }

    /**
     * @param Collection $categoryIds
     * @return $this
     */
    public function setCategoryIds(Collection $categoryIds): OfferCategoryFilter
    {
        $this->categoryIds = $categoryIds;
        return $this;
    }

    /**
     * @param Collection $exceptBrandIds
     * @return $this
     */
    public function setExceptBrandIds(Collection $exceptBrandIds): OfferCategoryFilter
    {
        $this->exceptBrandIds = $exceptBrandIds;
        return $this;
    }

    /**
     * @param Collection $exceptOfferIds
     * @return $this
     */
    public function setExceptOfferIds(Collection $exceptOfferIds): OfferCategoryFilter
    {
        $this->exceptOfferIds = $exceptOfferIds;
        return $this;
    }

    /**
     * @param Collection $additionalCategories
     * @return $this
     */
    public function setAdditionalCategories(Collection $additionalCategories): OfferCategoryFilter
    {
        $this->additionalCategories = $additionalCategories;
        return $this;
    }

    /**
     * @param Collection $basketItems
     * @return $this
     */
    public function setBasketItems(Collection $basketItems): OfferCategoryFilter
    {
        $this->basketItems = $basketItems;
        $this->filteredBasketItems = $basketItems;
        return $this;
    }

    /**
     * @param int|null $merchantId
     * @return $this
     */
    public function setMerchantId(?int $merchantId): OfferCategoryFilter
    {
        $this->merchantId = $merchantId;
        return $this;
    }
}
