<?php

use Illuminate\Support\Facades\Route;
use Modules\AppManager\Http\Controllers\AppManagerController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('appmanagers', AppManagerController::class)->names('appmanager');
});
