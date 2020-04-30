<?php

use Illuminate\Database\Seeder;
use App\Models\Bonus\Bonus;
use App\Models\Bonus\BonusOffer;
use App\Models\Bonus\BonusBrand;
use App\Models\Bonus\BonusCategory;
use Greensight\CommonMsa\Rest\RestQuery;
use Pim\Services\OfferService\OfferService;
use Pim\Services\BrandService\BrandService;
use Pim\Services\CategoryService\CategoryService;

class BonusesTableSeeder extends Seeder
{
    const FAKER_SEED = 123456;

    /** @var \Faker\Generator */
    protected $faker;

    /** @var array */
    protected $names;

    /** @var array */
    protected $offerIds;

    /** @var array */
    protected $brandIds;

    /** @var array */
    protected $categoryIds;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->faker = Faker\Factory::create('ru_RU');
        $this->faker->seed(self::FAKER_SEED);

        $this->loadData();

        for ($i = 0; $i < 100; $i++) {
            $bonus = new Bonus();
            $bonus->name = $this->faker->numerify($this->faker->randomElement($this->names));
            $bonus->type = $this->faker->randomElement(Bonus::availableTypes());

            $bonus->value_type = $this->faker->randomElement([
                Bonus::VALUE_TYPE_PERCENT,
                Bonus::VALUE_TYPE_RUB,
            ]);

            $bonus->value = ($bonus->value_type === Bonus::VALUE_TYPE_RUB)
                ? $bonus->value = $this->faker->numberBetween(10, 1000)
                : $bonus->value = $this->faker->numberBetween(1, 20);

            $bonus->valid_period = $this->faker->boolean(90)
                ? $this->faker->numberBetween(7, 182)
                : null;

            $bonus->start_date = $this->faker->boolean()
                ? $this->faker->dateTimeBetween($startDate = '-6 month', $endDate = '+1 month')
                : null;
            $bonus->end_date = $this->faker->boolean()
                ? $this->faker->dateTimeBetween($startDate = '+1 month', $endDate = '+5 month')
                : null;

            $bonus->promo_code_only = ($bonus->type === Bonus::TYPE_CART_TOTAL) || $this->faker->boolean();

            $bonus->status = $bonus->isExpired()
                ? Bonus::STATUS_EXPIRED
                : $this->faker->randomElement([
                    Bonus::STATUS_CREATED,
                    Bonus::STATUS_ACTIVE,
                    Bonus::STATUS_PAUSED,
                ]);

            $bonus->save();

            $countItems = $this->faker->numberBetween(1, 5);
            switch ($bonus->type) {
                case Bonus::TYPE_OFFER:
                    $offerIds = $this->faker->randomElements($this->offerIds, min($countItems, count($this->offerIds)));
                    foreach ($offerIds as $offerId) {
                        $bonusOffer = new BonusOffer();
                        $bonusOffer->bonus_id = $bonus->id;
                        $bonusOffer->offer_id = $offerId;
                        $bonusOffer->except = 0;
                        $bonusOffer->save();
                    }
                    break;
                case Bonus::TYPE_BRAND:
                    $brandIds = $this->faker->randomElements($this->brandIds, min($countItems, count($this->brandIds)));
                    foreach ($brandIds as $brandId) {
                        $bonusBrand = new BonusBrand();
                        $bonusBrand->bonus_id = $bonus->id;
                        $bonusBrand->brand_id = $brandId;
                        $bonusBrand->except = 0;
                        $bonusBrand->save();
                    }
                    break;
                case Bonus::TYPE_CATEGORY:
                    $categoryIds = $this->faker->randomElements($this->categoryIds, min($countItems, count($this->categoryIds)));
                    foreach ($categoryIds as $categoryId) {
                        $bonusCategory = new BonusCategory();
                        $bonusCategory->bonus_id = $bonus->id;
                        $bonusCategory->category_id = $categoryId;
                        $bonusCategory->except = 0;
                        $bonusCategory->save();
                    }
                    break;
                case Bonus::TYPE_SERVICE:
                    // todo
                    break;
            }
        }
    }

    protected function loadData()
    {
        $this->names = [
            'Повышенный бонус – ###',
            'Акционный бонус  – ###',
            'Бонус – ###',
            'Летний бонус – ###',
            'Зимний бонус – ###',
            'Праздничный бонус – ###',
            'Юбилейный бонус – ###',
        ];

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $this->offerIds = $offerService->offers(new RestQuery())->pluck('id')->toArray();

        /** @var CategoryService $categoryService */
        $categoryService = resolve(CategoryService::class);
        $this->categoryIds = $categoryService->categories($categoryService->newQuery())->pluck('id')->toArray();

        /** @var BrandService $brandService */
        $brandService = resolve(BrandService::class);
        $this->brandIds = $brandService->brands($brandService->newQuery())->pluck('id')->toArray();
    }
}
