<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\Kpi\AdsConversionController;
use App\Http\Controllers\Kpi\AgentTargetController;
use App\Http\Controllers\Kpi\ChatEvaluationController;
use App\Http\Controllers\Kpi\ContentKpiController;
use App\Http\Controllers\Kpi\StaffSalesController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffBlockController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile', [UserController::class, 'profile_edit'])->name('profile.edit');
    Route::put('/profile', [UserController::class, 'profile_update'])->name('profile.update');

    Route::resource('leads', LeadController::class);
    Route::get('/leads-check-followups', [LeadController::class, 'checkTodaysFollowUps'])->name('leads.check.followups');

    Route::resource('appointments', AppointmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/appointments-calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
    Route::get('/appointments-calendar-data', [AppointmentController::class, 'calendarData'])->name('appointments.calendar_data');
    Route::get('/appointments/customer-profile/{phone}', [AppointmentController::class, 'customerProfile'])->name('appointments.customerProfile');
    Route::get('/appointments-available-staff', [AppointmentController::class, 'availableStaff'])->name('appointments.availableStaff');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/services', [AppointmentController::class, 'addService'])->name('appointments.services.store');
    Route::patch('/appointments/{appointment}/services/{appointmentService}', [AppointmentController::class, 'updateService'])->name('appointments.services.update');
    Route::delete('/appointments/{appointment}/services/{appointmentService}', [AppointmentController::class, 'destroyService'])->name('appointments.services.destroy');
    Route::get('/appointments/{appointment}/payment', [AppointmentController::class, 'payment'])->name('appointments.revenue.payment');
    Route::post('/appointments/{appointment}/payment', [AppointmentController::class, 'storePayment'])->name('appointments.revenue.storePayment');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/revenue', [FinanceController::class, 'index'])->name('appointments.revenue.index');
    Route::post('/expenses', [FinanceController::class, 'storeExpense'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('expenses.destroy');

    Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');

    Route::post('/staff-blocks', [StaffBlockController::class, 'store'])->name('staff-blocks.store');
    Route::delete('/staff-blocks/{staffBlock}', [StaffBlockController::class, 'destroy'])->name('staff-blocks.destroy');

    Route::resource('staffs', StaffController::class)->only(['index']);
    Route::get('/scheduled-shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::middleware('role:admin')->group(function () {
        Route::resource('staffs', StaffController::class)->only(['store', 'update', 'destroy']);
        Route::post('/scheduled-shifts/{staff}/pattern', [ShiftController::class, 'savePattern'])->name('shifts.pattern.store');
        Route::post('/scheduled-shifts/{staff}/time-off', [ShiftController::class, 'storeTimeOff'])->name('shifts.timeoff.store');
        Route::delete('/scheduled-shifts/time-off/{timeOff}', [ShiftController::class, 'destroyTimeOff'])->name('shifts.timeoff.destroy');
    });
    Route::resource('services', ServiceController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/service-categories', [ServiceCategoryController::class, 'store'])->name('service-categories.store');
    Route::delete('/service-categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/customers-lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::get('/customers-search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class)->only(['index', 'show']);
    Route::patch('/customers/{customer}/notes', [CustomerController::class, 'updateNotes'])->name('customers.notes.update');
    Route::patch('/customers/{customer}/allergies', [CustomerController::class, 'updateAllergies'])->name('customers.allergies.update');
    Route::post('/customers/{customer}/redeem', [CustomerController::class, 'redeemPoints'])->name('customers.loyalty.redeem');
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::prefix('kpis')->name('kpi.')->group(function () {
        Route::get('/', function () {
            return view('kpi.hub');
        })->name('hub');

        Route::get('/ads', [AdsConversionController::class, 'index'])->name('ads.index');
        Route::get('/ads/create', [AdsConversionController::class, 'create'])->name('ads.create');
        Route::post('/ads', [AdsConversionController::class, 'store'])->name('ads.store');
        Route::get('/ads/{report}', [AdsConversionController::class, 'show'])->name('ads.show');
        Route::delete('/ads/{report}', [AdsConversionController::class, 'destroy'])->name('ads.destroy');

        Route::get('/agents', [AgentTargetController::class, 'index'])->name('agents.index');
        Route::get('/agents/create', [AgentTargetController::class, 'create'])->name('agents.create');
        Route::post('/agents', [AgentTargetController::class, 'store'])->name('agents.store');
        Route::get('/agents/{report}', [AgentTargetController::class, 'show'])->name('agents.show');
        Route::delete('/agents/{report}', [AgentTargetController::class, 'destroy'])->name('agents.destroy');

        Route::get('/staff-sales', [StaffSalesController::class, 'index'])->name('staff-sales.index');
        Route::get('/staff-sales/create', [StaffSalesController::class, 'create'])->name('staff-sales.create');
        Route::post('/staff-sales', [StaffSalesController::class, 'store'])->name('staff-sales.store');
        Route::get('/staff-sales/{report}', [StaffSalesController::class, 'show'])->name('staff-sales.show');
        Route::delete('/staff-sales/{report}', [StaffSalesController::class, 'destroy'])->name('staff-sales.destroy');

        Route::get('/chat-eval', [ChatEvaluationController::class, 'index'])->name('chat-eval.index');
        Route::get('/chat-eval/create', [ChatEvaluationController::class, 'create'])->name('chat-eval.create');
        Route::post('/chat-eval', [ChatEvaluationController::class, 'store'])->name('chat-eval.store');
        Route::get('/chat-eval/{report}', [ChatEvaluationController::class, 'show'])->name('chat-eval.show');
        Route::delete('/chat-eval/{report}', [ChatEvaluationController::class, 'destroy'])->name('chat-eval.destroy');

        Route::get('/content', [ContentKpiController::class, 'index'])->name('content.index');
        Route::get('/content/create', [ContentKpiController::class, 'create'])->name('content.create');
        Route::post('/content', [ContentKpiController::class, 'store'])->name('content.store');
        Route::get('/content/{report}', [ContentKpiController::class, 'show'])->name('content.show');
        Route::delete('/content/{report}', [ContentKpiController::class, 'destroy'])->name('content.destroy');
    });
});
