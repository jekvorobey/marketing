<?php

namespace App\Console\Commands;

use App\Models\Price\Price;
use Illuminate\Support\Facades\DB;
use Pim\Dto\Offer\OfferDto;
use App\Services\Price\PriceWriter;
use Illuminate\Console\Command;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\SearchService\SearchService;

class GeneratePricesByRoles extends Command
{
    private const ITEMS_PER_REQUEST = 200;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:generate-prices-by-roles {--merchant_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Сгенерировать цены для каждой роли';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(OfferService $offerService, SearchService $searchService, PriceWriter $priceWriter)
    {
        DB::disableQueryLog();

        $offersQuery = $offerService->newQuery()
            ->include(ProductDto::entity())
//            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
            ->addFields(ProductDto::entity(), 'id', 'category_id', 'brand_id');

        if ((int) $this->option('merchant_id')) {
            $offersQuery->setFilter('merchant_id', $this->option('merchant_id'));
        }

        $offersCount = $offerService->offersCount($offersQuery);
        $progressBar = $this->output->createProgressBar($offersCount['total'] / static::ITEMS_PER_REQUEST);
        $currPage = 0;

        do {
            $progressBar->advance();
            $offers = $offerService->offers($offersQuery->pageOffset($currPage * static::ITEMS_PER_REQUEST, static::ITEMS_PER_REQUEST));
            $offerIds = $offers->pluck('id')->toArray();

            $basePrices = Price::query()
                ->with('pricesByRoles')
                ->whereIn('offer_id', $offerIds)
                ->get()
                ->keyBy('offer_id');

            /** @var OfferDto $offer */
            foreach ($offers as $offer) {
                if ($offer->product && $basePrice = $basePrices->get($offer->id)) {
                    $priceWriter->generatePricesByRoles($offer, $basePrice);
                }
            }

            if (!empty($offerIds)) {
                $searchService->markProductsForIndexByOfferIds($offerIds);
            }

            $currPage++;
        } while(!$offers->isEmpty());
    }
}
