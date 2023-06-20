<?php

use App\Http\Controllers\V1\BonusController;
use App\Http\Controllers\V1\OptionController;
use App\Http\Controllers\V1\PriceController;
use App\Http\Controllers\V1\PriceReactorController;
use App\Http\Controllers\V1\ProductBonusOptionController;
use App\Http\Controllers\V1\PromoCodeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\DiscountController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::namespace('V1')->prefix('v1')->group(function () {
    Route::prefix('discounts')->group(function () {
        Route::get('count', [DiscountController::class, 'count']);
        Route::get('', [DiscountController::class, 'read']);
        Route::post('', [DiscountController::class, 'create']);
        Route::delete('', [DiscountController::class, 'delete']);
        Route::get('authors', [DiscountController::class, 'getAuthors']);
        Route::get('initiators', [DiscountController::class, 'getInitiators']);
        Route::get('users', [DiscountController::class, 'getUsers']);
        Route::post('calculate', [DiscountController::class, 'calculate']);
        Route::put('status', [DiscountController::class, 'updateStatus']);
        Route::post('bundle-discount-values', [DiscountController::class, 'bundleDiscountValues']);
        Route::post('copy', [DiscountController::class, 'copy']);

        Route::prefix('{id}')->group(function () {
            Route::get('', [DiscountController::class, 'read']);
            Route::put('', [DiscountController::class, 'update']);
        });
    });

    Route::prefix('promoCodes')->group(function () {
        Route::get('', [PromoCodeController::class, 'read']);
        Route::post('', [PromoCodeController::class, 'create']);
        Route::get('generate', [PromoCodeController::class, 'generate']);
        Route::get('check', [PromoCodeController::class, 'check']);

        Route::prefix('{id}')->group(function () {
            Route::get('', [PromoCodeController::class, 'read']);
            Route::put('', [PromoCodeController::class, 'update']);
            Route::delete('', [PromoCodeController::class, 'delete']);
        });
    });

    Route::prefix('bonuses')->group(function () {
        Route::get('', [BonusController::class, 'read']);
        Route::post('', [BonusController::class, 'create']);

        Route::prefix('{id}')->group(function () {
            Route::put('', [BonusController::class, 'update']);
            Route::delete('', [BonusController::class, 'delete']);
        });

        Route::prefix('options/product/{id}')->group(function () {
            Route::get('', [ProductBonusOptionController::class, 'get']);
            Route::get('{key}', [ProductBonusOptionController::class, 'value']);
            Route::put('{key}', [ProductBonusOptionController::class, 'put']);
            Route::delete('{key}', [ProductBonusOptionController::class, 'delete']);
        });
    });

    Route::prefix('offers')->group(function () {
        Route::prefix('prices')->group(function () {
            Route::post('', [PriceController::class, 'read']);
            Route::put('', [PriceController::class, 'setPrices']);
            Route::post('offers-ids-by-prices-conditions-and-offer', [PriceController::class, 'offersIdsByPricesConditionsAndOffer']);
            Route::post('list', [PriceController::class, 'list']);
        });

        Route::prefix('{offerId}')->group(function () {
            Route::prefix('price')->group(function () {
                Route::get('', [PriceController::class, 'price']);
                Route::put('', [PriceController::class, 'setPrice']);
                Route::delete('', [PriceController::class, 'deletePriceByOffer']);
            });
        });
        Route::post('catalogCombinations', [PriceController::class, 'catalogCombinations']);
        Route::get('getCatalogCombinations', [PriceController::class, 'catalogCombinations']);
    });

    Route::prefix('merchants')->group(function () {
        Route::prefix('{merchantId}')->group(function () {
            Route::prefix('price')->group(function () {
                Route::put('', [PriceController::class, 'updatePriceByMerchant']);
            });
        });
    });

    Route::prefix('price-reactor')->group(function () {
        Route::post('basket', [PriceReactorController::class, 'calculateBasketPrice']);
    });

    Route::get('aggregate/{customer_id}/personal-global-percent', 'AggregateController@getPersonalGlobalPercent')->where('customer_id', '[0-9]+');

    Route::prefix('options/{key}')->group(function () {
        Route::get('', [OptionController::class, 'getOption']);
        Route::put('', [OptionController::class, 'putOption']);
    });
});
