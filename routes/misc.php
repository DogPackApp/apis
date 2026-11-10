<?php

use App\Http\Controllers\Misc\CountryListController;
use App\Http\Controllers\Misc\CurrencyListController;
use App\Http\Controllers\Misc\LanguageListController;
use App\Http\Controllers\Misc\StateListController;
use Illuminate\Support\Facades\Route;

Route::get('/country', CountryListController::class)->name('misc.country.list');
Route::get('/state', StateListController::class)->name('misc.state.list');
Route::get('/currency', CurrencyListController::class)->name('misc.currency.list');
Route::get('/language', LanguageListController::class)->name('misc.language.list');
