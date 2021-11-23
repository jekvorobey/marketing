<?php

namespace App\Services\Calculator\Discount;

use App\Models\Discount\BundleItem;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountPublicEvent;
use App\Models\Discount\DiscountSegment;
use App\Models\Discount\DiscountUserRole;
use App\Services\Calculator\InputCalculator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Pim\Core\PimException;

class DiscountFetcher
{
    private Collection $discounts;
    private Collection $relations;

    public function __construct(private InputCalculator $input)
    {
        $this->discounts = collect();
        $this->relations = collect();
    }

    public function getDiscounts(): Collection
    {
        $this->fetchDiscounts();

        return $this->discounts;
    }

    /**
     * @throws PimException
     */
    public function getRelations(): Collection
    {
        $this->fetchRelations();

        return $this->relations;
    }

    /**
     * Загружает необходимые данные о полученных скидках ($this->discount)
     * @throws PimException
     */
    private function fetchRelations(): void
    {
        $this->fetchDiscountOffers()
            ->fetchDiscountBrands()
            ->fetchDiscountCategories()
            ->fetchDiscountPublicEvents()
            ->fetchDiscountSegments()
            ->fetchDiscountCustomerRoles()
            ->fetchBundleItems();
    }

    private function fetchDiscounts()
    {
        $this->discounts = Discount::select([
            'id',
            'type',
            'name',
            'merchant_id',
            'value',
            'value_type',
            'promo_code_only',
            'merchant_id',
            'product_qty_limit',
        ])
            ->where(function ($query) {
                $query->where('promo_code_only', false);
                $promoCodeDiscountId = $this->input->promoCodeDiscount->id ?? null;
                if ($promoCodeDiscountId) {
                    $query->orWhere('id', $promoCodeDiscountId);
                }
            })
            ->active()
            ->orderBy('promo_code_only')
            ->orderBy('type')
            ->with($this->withOffers())
            ->get();

        return $this;
    }

    private function withOffers(): array
    {
        return [
            'offers' => function (Builder $builder): void {
                $builder
                    ->select(['discount_id', 'offer_id', 'except'])
                    ->whereIn('offer_id', $this->input->offers->pluck('id'));
            },
        ];
    }

    /**
     * Получаем все возможные скидки и офферы из DiscountOffer
     */
    protected function fetchDiscountOffers(): self
    {
        $validTypes = [Discount::DISCOUNT_TYPE_OFFER, Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->input->offers->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['offers'] = collect();

            return $this;
        }

        $this->relations['offers'] = DiscountOffer::select(['discount_id', 'offer_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('offer_id', $this->input->offers->pluck('id'))
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и бренды из DiscountBrand
     */
    protected function fetchDiscountBrands(): self
    {
        /** Если не передали офферы, то пропускаем скидки на бренды */
        $validTypes = [Discount::DISCOUNT_TYPE_BRAND, Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->input->brands->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['brands'] = collect();
            return $this;
        }

        $this->relations['brands'] = DiscountBrand::select(['discount_id', 'brand_id', 'except'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('brand_id', $this->input->brands->keys())
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и категории из DiscountCategory
     * @throws PimException
     */
    protected function fetchDiscountCategories(): self
    {
        /** Если не передали офферы, то пропускаем скидки на категорию */
        $validTypes = [Discount::DISCOUNT_TYPE_CATEGORY];
        if ($this->input->categories->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['categories'] = collect();

            return $this;
        }

        $categories = InputCalculator::getAllCategories();
        $this->relations['categories'] = DiscountCategory::select(['discount_id', 'category_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->filter(function ($discountCategory) use ($categories) {
                $categoryLeaf = $categories[$discountCategory->category_id];
                foreach ($this->input->categories->keys() as $categoryId) {
                    if ($categoryLeaf->isSelfOrAncestorOf($categories[$categoryId])) {
                        return true;
                    }
                }

                return false;
            })
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем скидки для мастер-классов
     * @uses \App\Models\Discount\DiscountPublicEvent
     */
    protected function fetchDiscountPublicEvents(): self
    {
        /** Если не передали ID типов билетов, то пропускаем скидки на мастер-классы */
        $validTypes = [Discount::DISCOUNT_TYPE_MASTERCLASS, Discount::DISCOUNT_TYPE_ANY_MASTERCLASS];
        if ($this->input->ticketTypeIds->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['ticketTypeIds'] = collect();

            return $this;
        }

        $this->relations['ticketTypeIds'] = DiscountPublicEvent::select(['discount_id', 'ticket_type_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->whereIn('ticket_type_id', $this->input->ticketTypeIds->keys())
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Получаем все возможные скидки и сегменты из DiscountSegment
     */
    protected function fetchDiscountSegments(): self
    {
        $this->relations['segments'] = DiscountSegment::select(['discount_id', 'segment_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('segment_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки и роли из DiscountRole
     */
    protected function fetchDiscountCustomerRoles(): self
    {
        $this->relations['roles'] = DiscountUserRole::select(['discount_id', 'role_id'])
            ->whereIn('discount_id', $this->discounts->pluck('id'))
            ->get()
            ->groupBy('discount_id')
            ->transform(function ($segments) {
                return $segments->pluck('role_id');
            });

        return $this;
    }

    /**
     * Получаем все возможные скидки из BundleItems
     */
    protected function fetchBundleItems(): self
    {
        /** Если не передали офферы, то пропускаем скидки на бренды */
        $validTypes = [Discount::DISCOUNT_TYPE_BUNDLE_OFFER, Discount::DISCOUNT_TYPE_BUNDLE_MASTERCLASS];
        if ($this->input->bundles->isEmpty() || $this->existsAnyTypeInDiscounts($validTypes)) {
            $this->relations['bundleItems'] = collect();
            return $this;
        }

        $this->relations['bundleItems'] = BundleItem::query()
            ->select(['discount_id', 'item_id'])
            ->whereIn('discount_id', $this->input->bundles)
            ->get()
            ->groupBy('discount_id');

        return $this;
    }

    /**
     * Существует ли хотя бы одна скидка с одним из типов скидки ($types)
     *
     * метод не совсем соответсвует названию, в случае если существует скидка с таким типом,
     * то возвращается false, хотя по названию должно возвращаться true
     */
    protected function existsAnyTypeInDiscounts(array $types): bool
    {
        return $this->discounts->groupBy('type')
            ->keys()
            ->intersect($types)
            ->isEmpty();
    }
}
