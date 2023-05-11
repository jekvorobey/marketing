<?php

namespace App\Console\Commands;

use App\Services\Price\PriceWriter;
use Illuminate\Console\Command;
use Pim\Core\PimException;

class TestPriceWriter extends Command
{
    /** @var string */
    protected $signature = 'test:price-writer';
    /** @var string */
    protected $description = '';

    /**
     * @throws PimException
     */
    public function handle(PriceWriter $priceWriter): void
    {
        $offerId = 15858;
        $price = 5730;

        $priceWriter->setPrices([$offerId => $price], true);
    }
}
