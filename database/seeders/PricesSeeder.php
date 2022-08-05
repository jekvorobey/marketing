<?php

namespace Database\Seeders;

use App\Models\Price\Price;
use Faker\Factory;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Seeder;
use Pim\Core\PimException;
use Pim\Services\OfferService\OfferService;

class PricesSeeder extends Seeder
{
    public const FAKER_SEED = 123456;

    /**
     * @throws PimException
     */
    public function run()
    {
        $faker = Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $offers = $offerService->offers(new RestQuery());
        foreach ($offers as $offer) {
            $price = new Price();
            $price->offer_id = $offer->id;
            $price->price = $faker->numberBetween(100, 10000);
            $price->save();
        }
    }
}
