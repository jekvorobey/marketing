<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\Discount;
use App\Services\Calculator\InputCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Pim\Core\PimException;

class DiscountFetcher
{
    private Collection $discounts;
    private InputCalculator $input;

    public function __construct(InputCalculator $input)
    {
        $this->discounts = collect();
        $this->input = $input;
    }

    public function getDiscounts(array $filterTypes = []): Collection
    {
        $this->fetchDiscounts($filterTypes);

        return $this->discounts;
    }

    /**
     * Получаем все скидки
     * @throws PimException
     */
    private function fetchDiscounts(array $filterTypes = []): void
    {
        $query = Discount::query()
            ->select([
                'id',
                'type',
                'name',
                'merchant_id',
                'value',
                'value_type',
                'promo_code_only',
                'max_priority',
                'summarizable_with_all',
                'merchant_id',
                'product_qty_limit',
            ])
            ->where(function (Builder $query) {
                $query->where('promo_code_only', false);
                $promoCodeDiscounts = $this->input->promoCodeDiscounts;
                if ($promoCodeDiscounts->isNotEmpty()) {
                    $query->orWhereIn('id', $promoCodeDiscounts->pluck('id'));
                }
            })
            ->active();

        if ($filterTypes) {
            $query->whereIn('type', $filterTypes);
        }

        $query
            ->orderBy('promo_code_only')
            ->orderBy('type')
            ->with($this->withOffers())
            ->with($this->withBrands())
            ->with($this->withCategories())
            ->with($this->withPublicEvents())
            ->with($this->withSegments())
            ->with($this->withRoles())
            ->with($this->withBundleItems())
            ->with($this->withConditions())
            ->with($this->withBundles());

        $this->discounts = $query
            ->get()
            ->keyBy('id');

        $categories = InputCalculator::getAllCategories();
        $this->discounts->each(function (Discount $discount) use ($categories) {
            $filteredCategories = $discount->categories->filter(function ($discountCategory) use ($categories) {
                $categoryLeaf = $categories[$discountCategory->category_id];
                foreach ($this->input->categories->keys() as $categoryId) {
                    if ($categoryLeaf->isSelfOrAncestorOf($categories[$categoryId])) {
                        return true;
                    }
                }

                return false;
            });
            $discount->setRelation('categories', $filteredCategories);
        });
    }

    /**
     * Получаем все возможные офферы из DiscountOffer
     */
    private function withOffers(): array
    {
        return [
            'offers' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'offer_id', 'except'])
                    ->whereIn('offer_id', $this->input->basketItems->pluck('offer_id'));
            },
        ];
    }

    /**
     * Получаем все возможные бренды из DiscountBrand
     */
    private function withBrands(): array
    {
        return [
            'brands' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'brand_id', 'except']);
            },
        ];
    }

    /**
     * Получаем все возможные категории из DiscountCategory
     */
    private function withCategories(): array
    {
        return [
            'categories' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'category_id', 'except']);
            },
        ];
    }

    /**
     * Получаем скидки для мастер-классов
     */
    private function withPublicEvents(): array
    {
        return [
            'publicEvents' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'ticket_type_id'])
                    ->whereIn('ticket_type_id', $this->input->ticketTypeIds->keys());
            },
        ];
    }

    /**
     * Получаем все возможные сегменты из DiscountSegment
     */
    private function withSegments(): array
    {
        return [
            'segments' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'segment_id']);
            },
        ];
    }

    /**
     * Получаем все возможные роли из DiscountRole
     */
    private function withRoles(): array
    {
        return [
            'roles' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'role_id']);
            },
        ];
    }

    /**
     * Получаем все BundleItems
     */
    private function withBundleItems(): array
    {
        return [
            'bundleItems' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'item_id'])
                    ->whereIn('discount_id', $this->input->bundles);
            },
        ];
    }

    /**
     * Получаем все Bundle
     */
    private function withBundles(): array
    {
        return [
            'bundles' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'bundle_id']);
            },
        ];
    }

    /**
     * Получаем все возможные условия из DiscountCondition
     */
    private function withConditions(): array
    {
        return [
            'conditions' => function (Relation $builder): void {
                $builder
                    ->select(['discount_id', 'type', 'condition']);
            },
        ];
    }
}
