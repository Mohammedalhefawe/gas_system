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
use App\Http\Controllers\AdController;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsDriver;
use App\Http\Middleware\SetLocale;


Route::middleware([SetLocale::class])->group(function () {


    //AUTH
    Route::prefix('auth')->group(function () {

        // Customer Registration
        Route::post('register/customer', [AuthController::class, 'registerCustomer']);

        // Driver Registration
        Route::middleware(['auth:api', EnsureUserIsAdmin::class])->group(function () {
            Route::post('register/driver', [AuthController::class, 'registerDriver']);
        });

        // General Login (for all roles)
        Route::post('login', [AuthController::class, 'login']);
        // Customer verification
        Route::post('customer/verify', [AuthController::class, 'verifyCustomer']);

        Route::post('customer/resend-pin', [AuthController::class, 'resendCustomerPin']);

        Route::post('customer/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('customer/verify-reset-pin', [AuthController::class, 'verifyResetPin']);
        Route::post('customer/reset-password', [AuthController::class, 'resetPassword']);


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

        Route::middleware(['auth:api', EnsureUserIsAdmin::class])->group(function () {
            Route::post('/', [ProductCategoryController::class, 'store']);
            Route::put('/{id}', [ProductCategoryController::class, 'update']);
            Route::delete('/{id}', [ProductCategoryController::class, 'destroy']);
        });
    });


    // PRODUCT
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::middleware(['auth:api', EnsureUserIsAdmin::class])->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });


    //PRODUCT REVIEWS
    Route::middleware(['auth:api', EnsureUserIsAdmin::class])->group(function () {
        Route::get('/products/{product_id}/reviews', [ProductReviewController::class, 'getReviewsByProduct']);
        Route::get('/product-reviews', [ProductReviewController::class, 'index']);
    });

    Route::middleware(['auth:api', EnsureUserIsCustomer::class])->group(function () {

        Route::prefix('product-reviews')->group(function () {

            Route::post('/', [ProductReviewController::class, 'store']);
            Route::get('/{id}', [ProductReviewController::class, 'show']);
            Route::put('/{id}', [ProductReviewController::class, 'update']);
            Route::delete('/{id}', [ProductReviewController::class, 'destroy']);
        });
    });


    //ADDRESS
    Route::middleware(['auth:api', EnsureUserIsCustomer::class])->group(function () {
        Route::prefix('addresses')->group(function () {
            Route::get('/', [UserAddressController::class, 'index']);
            Route::post('/', [UserAddressController::class, 'store']);
            Route::get('/{id}', [UserAddressController::class, 'show']);
            Route::put('/{id}', [UserAddressController::class, 'update']);
            Route::delete('/{id}', [UserAddressController::class, 'destroy']);
        });
    });

    //ORDERS
    Route::middleware(['auth:api', EnsureUserIsCustomer::class])->group(function () {
        Route::prefix('customer')->group(function () {
            Route::post('/orders', [OrderController::class, 'store']);
            Route::get('/orders/{order_id}', [OrderController::class, 'show']); // Get order by id
            Route::post('/orders/{order_id}/cancel', [OrderController::class, 'cancel']);
            Route::get('my-orders', [OrderController::class, 'myOrders']); // Get my orders
            Route::post('/orders/{order_id}/review', [OrderController::class, 'addReview']); // Add review
        });
    })->withoutMiddleware(['auth:api']);

    Route::get('/orders', [OrderController::class, 'index']); // Get all orders


    //DELIVERY FEE
    Route::middleware(['auth:api', EnsureUserIsAdmin::class])->group(function () {
        // Admin sets delivery fee
        Route::post('/delivery-fee', [DeliveryFeeController::class, 'store']);
        Route::get('/drivers/{driver_id}/orders', [DriverController::class, 'getOrdersByDriverForAdmin']);
        Route::get('/drivers', [DriverController::class, 'getAllDrivers']);
        Route::patch('/drivers/{driver_id}/toggle-block', [DriverController::class, 'toggleBlockDriver']);
        Route::get('/customers/{customer_id}/orders', [OrderController::class, 'getOrdersByCustomer']);
        Route::get('/customers', [AuthController::class, 'getAllCustomers']);
        Route::patch('/customers/{customer_id}/toggle-block', [AuthController::class, 'toggleBlockCustomer']);
    });
    // Get last delivery fee
    Route::get('/delivery-fee', [DeliveryFeeController::class, 'latest']);


    //DRIVER
    Route::prefix('driver')->group(function () {
        Route::middleware('auth:api', EnsureUserIsDriver::class)->group(function () {
            Route::post('accept/{order_id}', [DriverController::class, 'acceptOrder']);   // قبول الطلب
            Route::post('reject/{order_id}', [DriverController::class, 'rejectOrder']);   // رفض الطلب
            Route::post('start/{order_id}', [DriverController::class, 'startDelivery']);  // بدء التوصيل
            Route::post('complete/{order_id}', [DriverController::class, 'completeOrder']); // إكمال الطلب
            Route::get('my-orders', [DriverController::class, 'myOrders']); // الطلبات الخاصة بالسائق

        });
    });



    // ADS
    Route::get('/ads', [AdController::class, 'index']);
    Route::get('/ads/{id}', [AdController::class, 'show']);
    Route::middleware(['auth:api', EnsureUserIsAdmin::class])->group(function () {
        Route::post('/ads', [AdController::class, 'store']);
        Route::put('/ads/{id}', [AdController::class, 'update']);
        Route::delete('/ads/{id}', [AdController::class, 'destroy']);
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
