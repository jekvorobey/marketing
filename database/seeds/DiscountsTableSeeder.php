<?php

use Illuminate\Database\Seeder;
use App\Models\Discount\Discount;

/**
 * Class DiscountsTableSeeder
 */
class DiscountsTableSeeder extends Seeder
{
    const FAKER_SEED = 123456;

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);

        $merchants = [1, 2];

        for ($i=0; $i<= 50; $i++) {
            $discount = new Discount();
            $discount->merchant_id = $merchants[array_rand($merchants)];
            $discount->type = rand(1, 9);
            $discount->promo_code = rand(0, 1) ? null : $faker->regexify('[A-Z0-9]{7}');

            $discount->name = $faker->text(30);
            // Тип значения (1 - проценты, 2 - рубли)
            if(rand(0, 1)) {
                $discount->value_type = 1;
                $discount->value = rand(10, 1000);
            } else {
                $discount->value_type = 2;
                $discount->value = rand(5, 15);
            }
            $discount->region_id = null;
            $discount->approval_status = rand(1, 3);
            $discount->status = rand(1, 3);
            $discount->validity = rand(0, 365);
            $discount->started_at = rand(0, 1) ? null : $faker->dateTimeThisYear();
            $discount->created_at = $faker->dateTimeThisYear();
            $discount->save();
        }
    }
}

