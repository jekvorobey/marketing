<?php

use Illuminate\Database\Seeder;
use App\Models\Certificate\Design;
use App\Models\Certificate\Nominal;

class GiftCardNominalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $designs = Design::query()->get('id')->pluck('id')->toArray();

        foreach ([1000, 2000, 5000, 10000, 20000, 50000] as $price)
        {
            if (Nominal::query()->where('price', $price)->exists())
                continue;

            /** @var Nominal $nominal */
            $nominal = Nominal::query()->create([
                'price' => $price,
                'status' => true,
                'activation_period' => 30,
                'validity' => 365 * 3,
                'amount' => 1000,
                'creator_id' => 1,
            ]);
            $nominal->designs()->sync($designs);
        }
    }
}
