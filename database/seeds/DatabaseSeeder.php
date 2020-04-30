<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
         $this->call(DiscountsTableSeeder::class);
         $this->call(PricesSeeder::class);
         $this->call(BonusesTableSeeder::class);
         $this->call(PromoCodesTableSeeder::class);
    }
}
