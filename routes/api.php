<?php

use Illuminate\Support\Facades\Route;

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
        Route::get('count', 'DiscountController@count');
        Route::get('', 'DiscountController@read');
        Route::post('', 'DiscountController@create');
        Route::delete('', 'DiscountController@delete');
        Route::get('authors', 'DiscountController@getAuthors');
        Route::get('initiators', 'DiscountController@getInitiators');
        Route::get('users', 'DiscountController@getUsers');
        Route::post('calculate', 'DiscountController@calculate');
        Route::put('status', 'DiscountController@updateStatus');
        Route::post('bundle-discount-values', 'DiscountController@bundleDiscountValues');

        Route::prefix('{id}')->group(function () {
            Route::get('', 'DiscountController@read');
            Route::put('', 'DiscountController@update');
        });

    });

    Route::prefix('promoCodes')->group(function () {
        Route::get('', 'PromoCodeController@read');
        Route::post('', 'PromoCodeController@create');
        Route::get('generate', 'PromoCodeController@generate');
        Route::get('check', 'PromoCodeController@check');

        Route::prefix('{id}')->group(function () {
            Route::get('', 'PromoCodeController@read');
            Route::put('', 'PromoCodeController@update');
            Route::delete('', 'PromoCodeController@delete');
        });
    });

    Route::prefix('bonuses')->group(function () {
        Route::get('', 'BonusController@read');
        Route::post('', 'BonusController@create');

        Route::prefix('{id}')->group(function () {
            Route::put('', 'BonusController@update');
            Route::delete('', 'BonusController@delete');
        });

        Route::prefix('options/product/{id}')->group(function () {
            Route::get('', 'ProductBonusOptionController@get');
            Route::get('{key}', 'ProductBonusOptionController@value');
            Route::put('{key}', 'ProductBonusOptionController@put');
            Route::delete('{key}', 'ProductBonusOptionController@delete');
        });
    });

    Route::prefix('offers')->group(function () {
        Route::prefix('prices')->group(function () {
            Route::post('', 'PriceController@read');
            Route::put('', 'PriceController@setPrices');
            Route::post('offers-ids-by-prices-conditions-and-offer', 'PriceController@offersIdsByPricesConditionsAndOffer');
        });

        Route::prefix('{offerId}')->group(function () {
            Route::prefix('price')->group(function () {
                Route::get('', 'PriceController@price');
                Route::put('', 'PriceController@setPrice');
                Route::delete('', 'PriceController@deletePriceByOffer');
            });
        });
        Route::post('catalogCombinations', 'PriceController@catalogCombinations');
    });

    Route::prefix('price-reactor')->group(function () {
        Route::post('basket', 'PriceReactorController@calculateBasketPrice');
    });

    Route::get('aggregate/{customer_id}/personal-global-percent', 'AggregateController@getPersonalGlobalPercent')->where('customer_id', '[0-9]+');

    Route::prefix('options/{key}')->group(function () {
        Route::get('', 'OptionController@getOption');
        Route::put('', 'OptionController@putOption');
    });

    Route::prefix('certificate')->group(function () {

        Route::prefix('reports')->group(function () {
            Route::get('', 'CertificateReportController@read');
            Route::post('', 'CertificateReportController@create');
            Route::get('kpi', 'CertificateReportController@kpi');
        });

        Route::prefix('nominals')->group(function () {
            Route::post('', 'CertificateNominalController@create');
            Route::get('{id?}', 'CertificateNominalController@read');
            Route::put('{id}', 'CertificateNominalController@update');
            Route::delete('{id}', 'CertificateNominalController@delete');
        });

        Route::prefix('designs')->group(function () {
            Route::post('', 'CertificateDesignController@create');
            Route::get('{id?}', 'CertificateDesignController@read');
            Route::put('{id}', 'CertificateDesignController@update');
            Route::delete('{id}', 'CertificateDesignController@delete');
        });

        Route::prefix('cards')->group(function () {

            Route::post('activate', 'CertificateCardController@activateByPin');

            Route::get('{id?}', 'CertificateCardController@read');
            Route::post('{id}/activate', 'CertificateCardController@activateById');
            Route::post('{id}/pay', 'CertificateCardController@pay');
        });

        Route::prefix('orders')->group(function () {

            Route::get('', 'CertificateOrderController@read');
            Route::post('', 'CertificateOrderController@create');

            Route::prefix('{orderId}')->group(function () {
                // {orderId} - ID заказа в OMS
                Route::get('', 'CertificateOrderController@getOrder');
                Route::put('link', 'CertificateOrderController@linkOrder');
                Route::put('payment-status', 'CertificateOrderController@setPaymentStatus');
            });
        });

    });

    Route::get('history/{id?}', 'HistoryController@read');
});
