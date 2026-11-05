<?php declare(strict_types=1);

use App\Http\Controllers\Auth\{AuthController, EmailVerificationController, PasswordResetController};
use App\Http\Controllers\{ProductController, SaleController};
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register');
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh')->name('refresh');
        Route::prefix('password')->middleware('throttle:password')->group(function (): void {
            Route::post('/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
            Route::post('/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
        });
        Route::get('/email/verify/{userId}/{token}', [EmailVerificationController::class, 'verify'])->middleware('throttle:6,1')->name('email.verify');
    });

    Route::middleware(['jwt.auth', 'throttle:api'])->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
            Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('/password/change', [PasswordResetController::class, 'change'])->middleware('throttle:password')->name('auth.password.change');
            Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1')->name('auth.email.resend');
        });

        Route::middleware('email.verified')->group(function (): void {
            Route::middleware('role:Admin')->prefix('admin')->group(function (): void {
                Route::get('/dashboard', fn () => response()->json(['section' => 'Admin Dashboard']));
                Route::middleware('permission:manage-users')->group(function (): void {
                    Route::get('/users', fn () => response()->json(['action' => 'list users']));
                    Route::post('/users', fn () => response()->json(['action' => 'create user']));
                    Route::put('/users/{id}', fn () => response()->json(['action' => 'update user']));
                    Route::delete('/users/{id}', fn () => response()->json(['action' => 'delete user']));
                    Route::patch('/users/{id}/role', fn () => response()->json(['action' => 'change role']));
                });
                Route::middleware('permission:manage-products')->group(function (): void {
                    Route::get('/products', [ProductController::class, 'index']);
                    Route::post('/products', [ProductController::class, 'store']);
                    Route::put('/products/{id}', [ProductController::class, 'update']);
                    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
                    Route::post('/products/{id}/stock-adjustments', [ProductController::class, 'adjustStock']);
                });
                Route::middleware('permission:manage-prices')->group(function (): void {
                    Route::patch('/products/{id}/price', function (\Illuminate\Http\Request $request, string $id) {
                        $data = $request->validate(['unit_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2']]);
                        $product = \App\Models\Product::findOrFail($id);
                        $product->update(['unit_price' => $data['unit_price']]);

                        return response()->json(['status' => 'success', 'data' => $product->refresh()]);
                    });
                });
                Route::middleware('permission:view-profits')->group(function (): void {
                    Route::get('/profits', fn () => response()->json(['action' => 'view profits']));
                });
                Route::middleware('permission:view-reports')->group(function (): void {
                    Route::get('/reports/daily', fn () => response()->json(['action' => 'daily report']));
                    Route::get('/reports/monthly', fn () => response()->json(['action' => 'monthly report']));
                    Route::get('/reports/annual', fn () => response()->json(['action' => 'annual report']));
                });
                Route::middleware('permission:manage-debts')->group(function (): void {
                    Route::get('/debts', fn () => response()->json(['action' => 'list debts']));
                    Route::patch('/debts/{id}', fn () => response()->json(['action' => 'manage debt']));
                    Route::get('/debts/{id}/statement', fn () => response()->json(['action' => 'debt statement']));
                });
            });

            Route::middleware('role:Admin,Shop Attendant')->prefix('shop')->group(function (): void {
                Route::middleware('permission:view-inventory')->group(function (): void {
                    Route::get('/products', [ProductController::class, 'index']);
                    Route::get('/products/{id}', [ProductController::class, 'show']);
                });
                Route::middleware('permission:record-sales')->group(function (): void {
                    Route::post('/sales', [SaleController::class, 'store']);
                    Route::get('/sales', [SaleController::class, 'index']);
                    Route::get('/sales/{id}', [SaleController::class, 'show']);
                });
                Route::middleware('permission:create-customer-accounts')->group(function (): void {
                    Route::post('/customers', fn () => response()->json(['action' => 'create customer']));
                    Route::get('/customers', fn () => response()->json(['action' => 'list customers']));
                });
                Route::middleware('permission:record-payments')->group(function (): void {
                    Route::post('/payments', fn () => response()->json(['action' => 'record payment']));
                    Route::get('/payments', fn () => response()->json(['action' => 'list payments']));
                });
            });

            Route::middleware('role:Customer')->prefix('portal')->group(function (): void {
                Route::middleware('permission:view-debt-balance')->group(function (): void {
                    Route::get('/balance', fn () => response()->json(['action' => 'view balance']));
                });
                Route::middleware('permission:view-payment-history')->group(function (): void {
                    Route::get('/payments', fn () => response()->json(['action' => 'payment history']));
                });
                Route::middleware('permission:view-account-statement')->group(function (): void {
                    Route::get('/statement', fn () => response()->json(['action' => 'account statement']));
                    Route::get('/statement/pdf', fn () => response()->json(['action' => 'download PDF']));
                });
            });
        });
    });
});
