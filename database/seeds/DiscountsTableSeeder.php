<?php

use Illuminate\Database\Seeder;
use App\Models\Discount\Discount;
use MerchantManagement\Services\MerchantService\MerchantService;

/**
 * Class DiscountsTableSeeder
 */
class DiscountsTableSeeder extends Seeder
{
    const FAKER_SEED = 123456;

    /**
     * Run the database seeds.
     * @param MerchantService $merchantService
     */
    public function run(MerchantService $merchantService)
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);

        $merchantsIds = $merchantService->merchants()->keys()->toArray();

        $names = [
            'Первое мая',
            '8 марта',
            'Распродажа',
            'Юбилей',
            '23 февраля',
            'Успей все скупить',
            'Скидка 10% на шампуни',
        ];

        $types = Discount::availableTypes();
        $approvalStatuses = Discount::availableAppStatuses();
        $statuses = Discount::availableStatuses();

        for ($i = 0; $i <= 50; $i++) {
            $discount = new Discount();
            $discount->sponsor = $faker->randomElement([
                Discount::DISCOUNT_MERCHANT_SPONSOR,
                Discount::DISCOUNT_ADMIN_SPONSOR
            ]);
            $discount->merchant_id = $faker->randomElement($merchantsIds);
            $discount->type = $faker->randomElement($types);
            $discount->name = $faker->randomElement($names);
            $discount->value_type = $faker->randomElement([
                Discount::DISCOUNT_VALUE_TYPE_PERCENT,
                Discount::DISCOUNT_VALUE_TYPE_RUB,
            ]);

            $discount->value = ($discount->value_type === Discount::DISCOUNT_VALUE_TYPE_RUB)
                ? $discount->value = $faker->numberBetween(10, 1000)
                : $discount->value = $faker->numberBetween(5, 20);

            $discount->start_date = null;
            $discount->end_date = null;

            $discount->start_date = $faker->boolean()
                ? $faker->dateTimeBetween($startDate = '-6 month', $endDate = '+1 month')
                : null;

            $discount->end_date = $faker->boolean()
                ? $faker->dateTimeBetween($startDate = '+1 month', $endDate = '+5 month')
                : null;

            $discount->status = $faker->randomElement($statuses);
            $discount->approval_status = ($discount->status === Discount::STATUS_ACTIVE)
                ? Discount::APP_STATUS_APPROVED
                : $faker->randomElement($approvalStatuses);

            $discount->promo_code_only = $faker->boolean();
            $discount->created_at = $faker->dateTimeThisYear();
            $discount->save();
        }
    }
}

