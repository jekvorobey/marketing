<?php

use Illuminate\Database\Seeder;
use App\Models\Discount\Discount;
use App\Models\Discount\DiscountOffer;
use App\Models\Discount\DiscountBrand;
use App\Models\Discount\DiscountCategory;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountUserRole;
use App\Models\Discount\DiscountSegment;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Services\CategoryService\CategoryService;
use Pim\Services\BrandService\BrandService;
use Pim\Services\OfferService\OfferService;
use MerchantManagement\Services\MerchantService\MerchantService;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\CommonMsa\Dto\UserDto;
use Pim\Core\PimException;

/**
 * Class DiscountsTableSeeder
 */
class DiscountsTableSeeder extends Seeder
{
    const FAKER_SEED = 123456;

    const DISCOUNT_SIZE = 200;

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var array
     */
    protected $offerIds;

    /**
     * @var array
     */
    protected $categoryIds;

    /**
     * @var array
     */
    protected $brandIds;

    /**
     * @var array
     */
    protected $merchantsIds;

    /**
     * @var array
     */
    protected $userIds;

    /**
     * Run the database seeds.
     * @throws PimException
     */
    public function run()
    {
        $this->faker = Faker\Factory::create('ru_RU');
        $this->faker->seed(self::FAKER_SEED);

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $this->offerIds = $offerService->offers(new RestQuery())->pluck('id')->toArray();

        /** @var CategoryService $categoryService */
        $categoryService = resolve(CategoryService::class);
        $this->categoryIds = $categoryService->categories($categoryService->newQuery())->pluck('id')->toArray();

        /** @var BrandService $brandService */
        $brandService = resolve(BrandService::class);
        $this->brandIds = $brandService->brands($brandService->newQuery())->pluck('id')->toArray();

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $this->merchantsIds = $merchantService->merchants($merchantService->newQuery())->pluck('id')->toArray();

        /** @var UserService $userService */
        $userService = resolve(UserService::class);
        $this->userIds = $userService->users($userService->newQuery())->pluck('id')->toArray();

        $names = [
            'Первое мая',
            '8 марта',
            'Распродажа',
            'Юбилей',
            '23 февраля',
            'Успей все скупить',
            'Скидка на шампуни',
        ];

        $types = Discount::availableTypes();
        $statuses = Discount::availableStatuses();

        for ($i = 0; $i < self::DISCOUNT_SIZE; $i++) {
            $discount = new Discount();
            $discount->user_id = $this->faker->randomElement($this->userIds);
            $discount->merchant_id = $this->faker->boolean(80)
                ? $this->faker->randomElement($this->merchantsIds)
                : null;

            $discount->type = $this->faker->randomElement($types);
            $discount->name = $this->faker->randomElement($names);
            $discount->value_type = $this->faker->randomElement([
                Discount::DISCOUNT_VALUE_TYPE_PERCENT,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
            ]);

            $discount->value = ($discount->value_type === Discount::DISCOUNT_VALUE_TYPE_RUB)
                ? $discount->value = $this->faker->numberBetween(10, 1000)
                : $discount->value = $this->faker->numberBetween(5, 20);

            $discount->start_date = $this->faker->boolean()
                ? $this->faker->dateTimeBetween($startDate = '-6 month', $endDate = '+1 month')
                : null;

            $discount->end_date = $this->faker->boolean()
                ? $this->faker->dateTimeBetween($startDate = '+1 month', $endDate = '+5 month')
                : null;

            $discount->status = $this->faker->boolean()
                ? Discount::STATUS_ACTIVE
                : $this->faker->randomElement($statuses);

            $discount->promo_code_only = $this->faker->boolean();
            $discount->created_at = $this->faker->dateTimeThisYear();
            $discount->save();

            $this->seedRelations($discount);
        }
    }

    /**
     * @param Discount $discount
     */
    protected function seedRelations(Discount $discount)
    {
        $this->seedForTypes($discount);
        $this->seedForUser($discount);
        $this->seedForCondition($discount);
    }

    /**
     * @param Discount $discount
     */
    protected function seedForTypes(Discount $discount)
    {
        switch ($discount->type) {
            case Discount::DISCOUNT_TYPE_OFFER:
                $count = min(10, count($this->offerIds));
                $offers = $this->faker->randomElements($this->offerIds, $this->faker->numberBetween(1, $count));
                foreach ($offers as $offerId) {
                    $this->createDiscountOffer($discount->id, $offerId);
                }
                break;
            case Discount::DISCOUNT_TYPE_BUNDLE:
                // todo
                break;
            case Discount::DISCOUNT_TYPE_BRAND:
                # Скидка на бренды
                $count = min(5, count($this->brandIds));
                $brands = $this->faker->randomElements($this->brandIds, $this->faker->numberBetween(1, $count));
                foreach ($brands as $brandId) {
                    $this->createDiscountBrand($discount->id, $brandId);
                }

                # За исключением офферов
                if ($this->faker->boolean(15)) {
                    $count = min(3, count($this->offerIds));
                    $offers = $this->faker->randomElements($this->offerIds, $this->faker->numberBetween(1, $count));
                    foreach ($offers as $offerId) {
                        $this->createDiscountOffer($discount->id, $offerId, 1);
                    }
                }
                break;
            case Discount::DISCOUNT_TYPE_CATEGORY:
                # Скидка на категории
                $count = min(3, count($this->categoryIds));
                $categories = $this->faker->randomElements($this->categoryIds, $this->faker->numberBetween(1, $count));
                foreach ($categories as $category) {
                    $this->createDiscountCategory($discount->id, $category);
                }

                # За исключением брендов
                if ($this->faker->boolean(15)) {
                    $count = min(3, count($this->brandIds));
                    $brands = $this->faker->randomElements($this->brandIds, $this->faker->numberBetween(1, $count));
                    foreach ($brands as $brandId) {
                        $this->createDiscountBrand($discount->id, $brandId, 1);
                    }
                }

                # За исключением офферов
                if ($this->faker->boolean(15)) {
                    $count = min(3, count($this->offerIds));
                    $offers = $this->faker->randomElements($this->offerIds, $this->faker->numberBetween(1, $count));
                    foreach ($offers as $offerId) {
                        $this->createDiscountOffer($discount->id, $offerId, 1);
                    }
                }
                break;
        }
    }

    /**
     * @param Discount $discount
     */
    protected function seedForUser(Discount $discount)
    {
        if ($this->faker->boolean(10)) {
            $userRole = $this->faker->randomElement([
                UserDto::SHOWCASE__PROFESSIONAL,
                UserDto::SHOWCASE__REFERRAL_PARTNER
            ]);
            $this->createDiscountUserRole($discount->id, $userRole);
        }

        if ($this->faker->boolean(10)) {
            // todo - Не реализованы сегменты
            $this->createDiscountSegment($discount->id, $this->faker->numberBetween(1, 10));
        }
    }

    /**
     * @param Discount $discount
     * @todo
     */
    protected function seedForCondition(Discount $discount)
    {
        /** Скидка на первый заказ */
        if ($this->faker->boolean(5)) {
            $this->createDiscountCondition($discount->id, DiscountCondition::FIRST_ORDER, null);
        }

        /** На заказ от определенной суммы */
        if ($this->faker->boolean(10)) {
            $this->createDiscountCondition(
                $discount->id,
                DiscountCondition::MIN_PRICE_ORDER,
                [DiscountCondition::FIELD_MIN_PRICE => $this->faker->numberBetween(100, 1000)]
            );
        }

        /** На заказ от определенной суммы товаров заданного бренда */
        if ($this->faker->boolean(5)) {
            $brands = $this->faker->randomElements($this->brandIds, min(5, count($this->brandIds)));
            $this->createDiscountCondition(
                $discount->id,
                DiscountCondition::MIN_PRICE_BRAND,
                [
                    DiscountCondition::FIELD_MIN_PRICE => $this->faker->numberBetween(1000, 10000),
                    DiscountCondition::FIELD_BRANDS => $brands
                ]
            );
        }

        /** На заказ от определенной суммы товаров заданной категории */
        if ($this->faker->boolean(5)) {
            $categoryIds = $this->faker->randomElements($this->categoryIds, min(5, count($this->categoryIds)));
            $this->createDiscountCondition(
                $discount->id,
                DiscountCondition::MIN_PRICE_CATEGORY,
                [
                    DiscountCondition::FIELD_MIN_PRICE => $this->faker->numberBetween(1000, 10000),
                    DiscountCondition::FIELD_CATEGORIES => $categoryIds
                ]
            );
        }

        /** На количество единиц одного товара */
        if ($this->faker->boolean(5)) {
            $this->createDiscountCondition(
                $discount->id,
                DiscountCondition::EVERY_UNIT_PRODUCT,
                [
                    DiscountCondition::FIELD_OFFER => $this->faker->randomElement($this->offerIds),
                    DiscountCondition::FIELD_COUNT => $this->faker->numberBetween(1, 10)
                ]
            );
        }

        /**
         * todo
         * DELIVERY_METHOD - На способ доставки
         * PAY_METHOD - На способ оплаты
         * REGION - Территория действия (регион с точки зрения адреса доставки заказа)
         * USER - Для определенных пользователей системы
         * ORDER_SEQUENCE_NUMBER - Порядковый номер заказа
         * DISCOUNT_SYNERGY - Взаимодействия с другими маркетинговыми инструментами
         * BUNDLE - Скидка на определенные бандлы.
         */
    }

    /**
     * @param int $discountId
     * @param int $type
     * @param array|null $condition
     * @return bool
     */
    protected function createDiscountCondition(int $discountId, int $type, ?array $condition = null)
    {
        $discountCondition = new DiscountCondition();
        $discountCondition->discount_id = $discountId;
        $discountCondition->type = $type;
        $discountCondition->condition = $condition;
        return $discountCondition->save();
    }

    /**
     * @param int $discountId
     * @param int $roleId
     * @return bool
     */
    protected function createDiscountUserRole(int $discountId, int $roleId)
    {
        $discountUserRole = new DiscountUserRole();
        $discountUserRole->discount_id = $discountId;
        $discountUserRole->role_id = $roleId;
        return $discountUserRole->save();
    }

    /**
     * @param int $discountId
     * @param int $segmentId
     * @return bool
     */
    protected function createDiscountSegment(int $discountId, int $segmentId)
    {
        $discountSegment = new DiscountSegment();
        $discountSegment->discount_id = $discountId;
        $discountSegment->segment_id = $segmentId;
        return $discountSegment->save();
    }

    /**
     * @param $discountId
     * @param $offerId
     * @param int $except
     * @return bool
     */
    protected function createDiscountOffer($discountId, $offerId, $except = 0)
    {
        $discountOffer = new DiscountOffer();
        $discountOffer->discount_id = $discountId;
        $discountOffer->offer_id = $offerId;
        $discountOffer->except = $except;
        return $discountOffer->save();
    }

    /**
     * @param $discountId
     * @param $brandId
     * @param int $except
     * @return bool
     */
    protected function createDiscountBrand($discountId, $brandId, $except = 0)
    {
        $discountBrand = new DiscountBrand();
        $discountBrand->discount_id = $discountId;
        $discountBrand->brand_id = $brandId;
        $discountBrand->except = $except;
        return $discountBrand->save();
    }

    /**
     * @param $discountId
     * @param $categoryId
     * @param int $except
     * @return bool
     */
    protected function createDiscountCategory($discountId, $categoryId, $except = 0)
    {
        $discountCategory = new DiscountCategory();
        $discountCategory->discount_id = $discountId;
        $discountCategory->category_id = $categoryId;
        return $discountCategory->save();
    }
}

