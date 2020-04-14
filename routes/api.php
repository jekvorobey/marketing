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

        Route::prefix('{id}')->group(function () {
            Route::get('', 'DiscountController@read');
            Route::put('', 'DiscountController@update');
        });

    });

    Route::prefix('promoCodes')->group(function () {
        Route::get('', 'PromoCodeController@read');
        Route::post('', 'PromoCodeController@create');
        Route::get('generate', 'PromoCodeController@generate');

        Route::prefix('{id}')->group(function () {
            Route::get('', 'PromoCodeController@read');
            Route::put('', 'PromoCodeController@update');
            Route::delete('', 'PromoCodeController@delete');
        });
    });

    Route::prefix('offers')->group(function () {
        Route::prefix('prices')->group(function () {
            Route::post('', 'PriceController@read');
            Route::put('', 'PriceController@setPrices');
        });

        Route::prefix('{offerId}')->group(function () {
            Route::prefix('price')->group(function () {
                Route::get('', 'PriceController@price');
                Route::put('', 'PriceController@setPrice');
            });
        });
        Route::post('catalogCombinations', 'PriceController@catalogCombinations');
    });

    Route::prefix('price-reactor')->group(function () {
        Route::get('basket', 'PriceReactorController@calculateBasketPrice');
    });

    Route::get('aggregate/{customer_id}/personal-global-percent', 'AggregateController@getPersonalGlobalPercent')->where('customer_id', '[0-9]+');
});
