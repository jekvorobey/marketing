<?php

use Illuminate\Database\Seeder;
use App\Models\Discount\Discount;
use MerchantManagement\Services\MerchantService\MerchantService;
use Greensight\CommonMsa\Services\AuthService\UserService;

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
        $userService = resolve(UserService::class);
        $userIds = $userService->users()->keys()->toArray();

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

        for ($i = 0; $i <= 200; $i++) {
            $discount = new Discount();
            $discount->user_id = $faker->randomElement($userIds);
            $discount->merchant_id = $faker->boolean(80)
                ? $faker->randomElement($merchantsIds)
                : null;

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
            $discount->promo_code_only = $faker->boolean();
            $discount->created_at = $faker->dateTimeThisYear();
            $discount->save();
        }
    }
}

