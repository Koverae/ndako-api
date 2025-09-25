<?php

use Illuminate\Support\Facades\Route;
use Modules\AppManager\Http\Controllers\AppManagerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('appmanagers', AppManagerController::class)->names('appmanager');
});
