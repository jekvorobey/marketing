<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pim\Services\SearchService\SearchService;

class UpdatePimContent implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 5 * 60;
    protected string $funcName;
    protected ?array $relations;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $funcName, ?array $relations = null)
    {
        $this->funcName = $funcName;
        $this->relations = $relations;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SearchService $searchService)
    {
        call_user_func([$searchService, $this->funcName], $this->relations);
    }
}
