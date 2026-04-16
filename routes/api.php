<?php

use App\Http\Controllers\Api\DrugCombinationController;
use App\Http\Controllers\Api\ManufacturerController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Middleware\CheckApiClientIpAllowlist;
use App\Http\Middleware\TrackApiKeyUsage;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:api'])->group(function () {
    Route::middleware([
        'auth:sanctum',
        CheckApiClientIpAllowlist::class,
        TrackApiKeyUsage::class,
    ])->group(function () {
        // Medicines
        Route::get('/medicines', [MedicineController::class, 'index']);
        Route::get('/medicines/search', [MedicineController::class, 'search'])->middleware('throttle:search');
        Route::get('/medicines/{uuid}', [MedicineController::class, 'show']);
        Route::get('/medicines/slug/{slug}', [MedicineController::class, 'showBySlug']);
        Route::get('/medicines/barcode/{barcode}', [MedicineController::class, 'showByBarcode']);
        Route::get('/medicines/gtin/{gtin}', [MedicineController::class, 'showByGtin']);

        // Manufacturers
        Route::get('/manufacturers', [ManufacturerController::class, 'index']);
        Route::get('/manufacturers/{uuid}', [ManufacturerController::class, 'show']);

        // Drug Combinations
        Route::get('/drug-combinations', [DrugCombinationController::class, 'index']);
        Route::get('/drug-combinations/{slug}', [DrugCombinationController::class, 'show']);
        Route::get('/drug-combinations/{slug}/faqs', [DrugCombinationController::class, 'faqs']);
        Route::get('/drug-combinations/{slug}/sections/{key}', [DrugCombinationController::class, 'section']);
    });

    // System
    Route::get('/health', [SystemController::class, 'health']);
    Route::get('/version', [SystemController::class, 'version']);
});
