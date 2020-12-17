<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Certificate\Report;
use App\Services\Certificate\KpiHelper;
use App\Http\Traits\HasSearch;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateReportController extends Controller
{
    use HasSearch;

    public function read(Request $request): JsonResponse
    {
        return $this->searchResult($request,Report::class);
    }

    public function create(RequestInitiator $initiator)
    {
        return Report::query()->create([
            'data' => KpiHelper::getKpi(),
            'creator_id' => $initiator->userId()
        ]);
    }

    public function kpi(): JsonResponse
    {
        return response()->json(KpiHelper::getKpi());
    }
}
