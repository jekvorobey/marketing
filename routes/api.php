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

        Route::prefix('{id}')->group(function () {
            Route::get('', 'DiscountController@read');
            Route::put('', 'DiscountController@update');
            Route::delete('', 'DiscountController@delete');
        });

    });
    
    Route::prefix('offers')->group(function () {
        Route::prefix('{id}')->group(function () {
            Route::prefix('price')->group(function () {
                Route::get('', 'PriceController@price');
                Route::put('', 'PriceController@setPrice');
            });
        });
    });
});
