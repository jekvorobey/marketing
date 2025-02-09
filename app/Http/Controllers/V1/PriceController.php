<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount\DiscountSegment;
use App\Models\Price\Price;
use App\Services\Cache\CacheHelper;
use App\Services\Calculator\Catalog\CatalogCalculator;
use App\Services\Price\PriceWriter;
use Exception;
use Greensight\CommonMsa\Dto\Front;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MerchantManagement\Dto\MerchantPricesDto;
use MerchantManagement\Services\MerchantService\Dto\GetMerchantPricesDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Search\IndexType;
use Pim\Services\OfferService\OfferService;
use Pim\Services\SearchService\SearchService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class PriceController
 * @package App\Http\Controllers\V1
 */
class PriceController extends Controller
{
    protected function read(Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
                'role_ids' => 'array',
                'segment_id' => 'integer|nullable',
            ]);

            $discountPriceCalculator = new CatalogCalculator($params);
            return response()->json([
                'items' => $discountPriceCalculator->calculate(),
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }

    protected function list(Request $request, PriceWriter $priceWriter): JsonResponse
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
            ]);

            $prices = $priceWriter->pricesByOffers($params['offer_ids']);

            return response()->json([
                'items' => $prices,
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }

    /**
     * Получить список полей, которые можно редактировать через стандартные rest действия.
     * Пример return ['name', 'status'];
     * @return array
     */
    protected function writableFieldList(): array
    {
        return Price::FILLABLE;
    }

    /**
     * Получить класс модели в виде строки
     * Пример: return MyModel::class;
     */
    public function modelClass(): string
    {
        return Price::class;
    }

    /**
     * Задать права для выполнения стандартных rest действий.
     * Пример: return [ RestAction::$DELETE => 'permission' ];
     * @return array
     */
    public function permissionMap(): array
    {
        return [
            // todo добавить необходимые права
        ];
    }

    /**
     * Получить цену на предложение мерчанта
     * @param int $offerId - id предложения
     * @throws PimException
     */
    public function price(int $offerId, Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'role_ids' => 'array',
                'segment_id' => 'integer',
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }

        $items = (new CatalogCalculator([
            'offer_ids' => [$offerId],
            'role_ids' => $params['role_ids'] ?? null,
            'segment_id' => $params['segment_id'] ?? null,
        ]))->calculate();

        if (count($items) === 0) {
            return response()->json(null, 404);
        }

        return response()->json([
            'item' => [
                'offer_id' => $offerId,
                'cost' => $items[0]['cost'],
                'price' => $items[0]['price'],
                'price_base' => $items[0]['price_base'],
                'price_prof' => $items[0]['prices_by_roles'][RoleDto::ROLE_SHOWCASE_PROFESSIONAL]['price'] ?? null,
                'price_retail' => $items[0]['prices_by_roles'][RoleDto::ROLE_SHOWCASE_CUSTOMER]['price'] ?? null,
                'percent_prof' => $items[0]['prices_by_roles'][RoleDto::ROLE_SHOWCASE_PROFESSIONAL]['percent_by_base_price'] ?? null,
                'percent_retail' => $items[0]['prices_by_roles'][RoleDto::ROLE_SHOWCASE_CUSTOMER]['percent_by_base_price'] ?? null,
                'discounts' => $items[0]['discounts'] ?? null,
                'bonus' => $items[0]['bonus'] ?? 0,
            ],
        ]);
    }

    /**
     * @throws PimException
     */
    public function catalogCombinations(Request $request): JsonResponse
    {
        $offerIds = $request->get('offer_ids');
        if (!$offerIds) {
            throw new BadRequestHttpException('offer_ids is required');
        }

        $segments = DiscountSegment::query()
            ->select(['id', 'segment_id'])
            ->get()
            ->pluck('segment_id')
            ->unique()
            ->all();
        $segments[] = null;
        $roles = array_keys(RoleDto::rolesByFrontIds([Front::FRONT_SHOWCASE]));
        $roles[] = null;

        $prices = [];
        $params = [];
        foreach ($offerIds as $offerId) {
            $params = ['offer_ids' => [$offerId]];

            foreach ($segments as $segment) {
                $segmentKey = $segment ?? '0';
                $params['segment_id'] = $segment;

                foreach ($roles as $role) {
                    $roleKey = $role ?? '0';
                    $params['role_ids'] = [$role];

                    $items = Cache::remember(
                        CacheHelper::getCacheKey(self::class, $params),
                        15 * 60,
                        function () use ($params) {
                            return (new CatalogCalculator($params))->calculate(false);
                        }
                    );

                    foreach ($items as $item) {
                        $discounts = $item['discounts'] ?? null;
                        $bonus = $item['bonus'] ?? null;
                        if (!$discounts && !$bonus && ($segment || $role)) {
                            continue;
                        }

                        $prices[$offerId][$segmentKey][$roleKey] = $this->formatPriceData($item);
                    }
                }
            }
        }

        return response()->json($prices);
    }

    /**
     * Установить цену для предложения мерчанта
     * @throws PimException
     */
    public function setPrice(int $offerId, Request $request, PriceWriter $priceWriter): Response
    {
        $price = (float) $request->input('price');
        $nullable = (bool) $request->input('nullable');

        $priceWriter->setPrices([$offerId => $price], $nullable);

        return response('', 204);
    }

    /**
     * Установить цены для предложений мерчанта
     * На вход должен быть передан массив:
     * 'prices' => [
     *      [
     *          'offer_id' => ...,
     *          'price' => ...,
     *      ],
     *      ...
     * ]
     */
    public function setPrices(Request $request, PriceWriter $priceWriter): Response
    {
        $data = $request->validate([
            'prices' => 'required|array',
            'prices.*.offer_id' => 'required|integer',
            'prices.*.price' => 'required|numeric',
        ]);
        $newPrices = array_combine(
            array_column($data['prices'], 'offer_id'),
            array_column($data['prices'], 'price')
        );

        try {
            DB::transaction(fn() => $priceWriter->setPrices($newPrices));
        } catch (\Throwable $e) {
            throw new HttpException(500, $e->getMessage());
        }

        return response('', 204);
    }

    /**
     * Удалить цену при удалении самого оффера, не цепляя хуки индексации товара
     * @return mixed
     */
    public function deletePriceByOffer(int $offerId)
    {
        /** @var Price $price */
        $price = Price::query()->where('offer_id', $offerId)->firstOrFail();
        $price->delete();

        return response('', 204);
    }

    /**
     * Пересчитать все цены на товары мерчанта
     * @return mixed
     */
    public function updatePriceByMerchant(int $merchantId, Request $request, PriceWriter $priceWriter)
    {
        $merchantPriceId = (int) $request->input('price_id');
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);

        $offersId = $offerService->offers(
            (new RestQuery())
                ->setFilter('merchant_id', $merchantId)
                ->addFields(OfferDto::entity(), 'id')
        )->pluck('id')->toArray();

        /** @var Collection|Price[] $prices */
        $prices = Price::query()
            ->select('id', 'offer_id', 'merchant_id', 'price')
            ->whereIn('offer_id', $offersId)->get();

        $newPrices = [];

        foreach ($prices as $basePrice) {
            if (!$basePrice->merchant_id) {
                $basePrice->merchant_id = $merchantId;
                $basePrice->save();
            }

            if ($basePrice->price) {
                $newPrices[$basePrice->offer_id] = $basePrice->price;
            }
        }

        if ($newPrices) {
            try {
                $priceWriter->setPrices($newPrices);
            } catch (PimException) {
            }
        }

        try {
            $merchantPrices = $merchantService->merchantPrices(
                (new GetMerchantPricesDto())
                    ->addType(MerchantPricesDto::TYPE_GLOBAL)
                    ->addType(MerchantPricesDto::TYPE_MERCHANT)
                    ->addType(MerchantPricesDto::TYPE_BRAND)
                    ->addType(MerchantPricesDto::TYPE_CATEGORY)
                    ->addType(MerchantPricesDto::TYPE_SKU)
                    ->setMerchantId($merchantId)
            );

            /** @var MerchantPricesDto|null $merchantPrice */
            $merchantPrice = $merchantPrices->filter(function ($item) use ($merchantPriceId) {
                return $item->id === $merchantPriceId;
            })->first();
            if (!$merchantPrice || !$merchantPrice->type) {
                $searchService->markForIndexByMerchant($merchantId);
            } else {
                switch ($merchantPrice->type) {
                    case MerchantPricesDto::TYPE_BRAND:
                        $searchService->markForIndexByMerchantAndBrands($merchantId, [$merchantPrice->brand_id]);
                        break;
                    case MerchantPricesDto::TYPE_CATEGORY:
                        $searchService->markForIndexByMerchantAndCategories($merchantId, [$merchantPrice->category_id]);
                        break;
                    case MerchantPricesDto::TYPE_SKU:
                            $searchService->markForIndexByIds(IndexType::PRODUCT, [$merchantPrice->product_id]);
                        break;
                    case MerchantPricesDto::TYPE_GLOBAL:
                    case MerchantPricesDto::TYPE_MERCHANT:
                    default:
                        $searchService->markForIndexByMerchant($merchantId);
                }
            }
        } catch (Exception) {
        }

        return response('', 204);
    }

    public function offersIdsByPricesConditionsAndOffer(Request $request): JsonResponse
    {
        try {
            $params = $request->validate([
                'offer_ids' => 'array',
                'offer_ids.*' => 'integer',
                'role_ids' => 'array',
                'segment_id' => 'integer|nullable',
                'price_from' => 'integer',
                'price_to' => 'integer',
            ]);

            $discountPriceCalculator = new CatalogCalculator($params);
            $prices = $discountPriceCalculator->calculate();

            $prices = collect($prices)->keyBy('offer_id')
                ->map(function ($item) {
                    return $item['price'];
                })
                ->all();

            if (array_key_exists('price_from', $params)) {
                $priceFrom = $params['price_from'];
                $prices = array_filter($prices, function ($price) use ($priceFrom) {
                    return $price >= $priceFrom;
                });
            }
            if (array_key_exists('price_to', $params)) {
                $priceTo = $params['price_to'];
                $prices = array_filter($prices, function ($price) use ($priceTo) {
                    return $price <= $priceTo;
                });
            }

            return response()->json([
                'offer_ids' => array_keys($prices),
            ]);
        } catch (\Throwable $ex) {
            return response()->json(['error' => $ex->getMessage()], 400);
        }
    }

    private function formatPriceData(array $item): array
    {
        return [
            'cost' => $item['cost'],
            'price' => $item['price'],
            'price_base' => $item['price_base'],
            'price_prof' => $item['prices_by_roles'][RoleDto::ROLE_SHOWCASE_PROFESSIONAL]['price'] ?? null,
            'price_retail' => $item['prices_by_roles'][RoleDto::ROLE_SHOWCASE_CUSTOMER]['price'] ?? null,
            'percent_prof' => $item['prices_by_roles'][RoleDto::ROLE_SHOWCASE_PROFESSIONAL]['percent_by_base_price'] ?? null,
            'percent_retail' => $item['prices_by_roles'][RoleDto::ROLE_SHOWCASE_CUSTOMER]['percent_by_base_price'] ?? null,
            'bonus' => $item['bonus'],
            'discounts' => $item['discounts'] ?? null,
        ];
    }
}
