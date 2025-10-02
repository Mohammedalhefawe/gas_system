<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\CheckRoleMiddleware;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\UserAddressController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DeliveryFeeController;
use App\Http\Controllers\DriverController;

//AUTH
Route::prefix('auth')->group(function () {

    // Customer Registration
    Route::post('register/customer', [AuthController::class, 'registerCustomer']);

    // Driver Registration
    Route::post('register/driver', [AuthController::class, 'registerDriver']);

    // General Login (for all roles)
    Route::post('login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

//PRODUCT CATEGORIES
Route::prefix('categories')->group(function () {
    Route::get('/', [ProductCategoryController::class, 'index']);
    Route::get('/{id}', [ProductCategoryController::class, 'show']);

    //    Route::middleware(['auth:api',CheckRoleMiddleware::class])->group(function () {
    Route::post('/', [ProductCategoryController::class, 'store']);
    Route::put('/{id}', [ProductCategoryController::class, 'update']);
    Route::delete('/{id}', [ProductCategoryController::class, 'destroy']);
    // })->withoutMiddleware(['auth:api',CheckRoleMiddleware::class]);

});


// PRODUCT
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
// Route::middleware(['auth:api', 'admin'])->group(function () {
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{product}', [ProductController::class, 'update']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
// })->withoutMiddleware(['auth:api',AdminMiddleware::class]);


//PRODUCT REVIEWS
Route::middleware(['auth:api'])->group(function () {

    Route::prefix('product-reviews')->group(function () {
        Route::get('/', [ProductReviewController::class, 'index']);
        Route::post('/', [ProductReviewController::class, 'store']);
        Route::get('/{id}', [ProductReviewController::class, 'show']);
        Route::put('/{id}', [ProductReviewController::class, 'update']);
        Route::delete('/{id}', [ProductReviewController::class, 'destroy']);
    });
})->withoutMiddleware(['auth:api']);


//ADDRESS
Route::middleware(['auth:api'])->group(function () {

    Route::prefix('addresses')->group(function () {
        Route::get('/', [UserAddressController::class, 'index']);
        Route::post('/', [UserAddressController::class, 'store']);
        Route::get('/{id}', [UserAddressController::class, 'show']);
        Route::put('/{id}', [UserAddressController::class, 'update']);
        Route::delete('/{id}', [UserAddressController::class, 'destroy']);
    });
})->withoutMiddleware(['auth:api']);

//ORDERS
Route::middleware(['auth:api'])->group(function () {
Route::prefix('customer')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']); // Get all orders
    Route::get('/orders/{order_id}', [OrderController::class, 'show']); // Get order by id
    Route::post('/orders/{order_id}/cancel', [OrderController::class, 'cancel']);
    Route::get('my-orders', [OrderController::class, 'myOrders']); // Get my orders
    Route::post('/orders/{order_id}/review', [OrderController::class, 'addReview']); // Add review
});
})->withoutMiddleware(['auth:api']);


//DELIVERY FEE
//    Route::middleware(['auth:api',AdminMiddleware::class])->group(function () {
    // Admin sets delivery fee
    Route::post('/delivery-fee', [DeliveryFeeController::class, 'store']);

    // Get last delivery fee
    Route::get('/delivery-fee', [DeliveryFeeController::class, 'latest']);
// })->withoutMiddleware(['auth:api']);



//DRIVER
Route::prefix('driver')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Route::post('accept/{order_id}', [DriverController::class, 'acceptOrder']);   // قبول الطلب
        Route::post('reject/{order_id}', [DriverController::class, 'rejectOrder']);   // رفض الطلب
        Route::post('start/{order_id}', [DriverController::class, 'startDelivery']);  // بدء التوصيل
        Route::post('complete/{order_id}', [DriverController::class, 'completeOrder']); // إكمال الطلب
        Route::get('my-orders', [DriverController::class, 'myOrders']); // الطلبات الخاصة بالسائق
    });
});



//TEST
Route::middleware('auth:api')->group(function () {
    Route::get('/test', function () {
        return 'This is a protected route';
    });
});


// Route::get('/test', function () {
//     echo 'test';
// });
