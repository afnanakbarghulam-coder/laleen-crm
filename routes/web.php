<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\Kpi\AdLeadEntryController;
use App\Http\Controllers\Kpi\AdsConversionController;
use App\Http\Controllers\Kpi\AgentShiftLogController;
use App\Http\Controllers\Kpi\AgentTargetController;
use App\Http\Controllers\Kpi\ChatEvaluationController;
use App\Http\Controllers\Kpi\ContentEntryController;
use App\Http\Controllers\Kpi\ContentKpiController;
use App\Http\Controllers\Kpi\StaffSalesController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffBlockController;
use App\Http\Controllers\StaffComplaintController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDeductionController;
use App\Http\Controllers\StaffNoticeController;
use App\Http\Controllers\StaffOvertimeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile', [UserController::class, 'profile_edit'])->name('profile.edit');
    Route::put('/profile', [UserController::class, 'profile_update'])->name('profile.update');

    // ---- Leads ----
    // NOTE: literal paths ('create') must be registered before the '{lead}'
    // wildcard (show), or Laravel will match "create" as a lead id.
    Route::middleware('module:leads')->group(function () {
        Route::get('/leads-analytics', [LeadController::class, 'analytics'])->name('leads.analytics');
        Route::get('/leads-check-followups', [LeadController::class, 'checkTodaysFollowUps'])->name('leads.check.followups');
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    });
    Route::middleware('module:leads,edit')->group(function () {
        Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::patch('/leads/{lead}/needful-done', [LeadController::class, 'updateNeedfulDone'])->name('leads.needful-done');
        Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
    });
    Route::middleware('module:leads')->group(function () {
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    });

    // ---- Bookings ----
    Route::middleware('module:bookings')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments-calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
        Route::get('/appointments-calendar-data', [AppointmentController::class, 'calendarData'])->name('appointments.calendar_data');
        Route::get('/appointments/customer-profile/{phone}', [AppointmentController::class, 'customerProfile'])->name('appointments.customerProfile');
        Route::get('/appointments-available-staff', [AppointmentController::class, 'availableStaff'])->name('appointments.availableStaff');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    });
    Route::middleware('module:bookings,edit')->group(function () {
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
        Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
        Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::post('/appointments/{appointment}/services', [AppointmentController::class, 'addService'])->name('appointments.services.store');
        Route::patch('/appointments/{appointment}/services/{appointmentService}', [AppointmentController::class, 'updateService'])->name('appointments.services.update');
        Route::delete('/appointments/{appointment}/services/{appointmentService}', [AppointmentController::class, 'destroyService'])->name('appointments.services.destroy');
        Route::post('/appointments/{appointment}/upsells', [AppointmentController::class, 'addUpsell'])->name('appointments.upsells.store');
        Route::patch('/appointments/{appointment}/upsells/{appointmentUpsell}', [AppointmentController::class, 'updateUpsell'])->name('appointments.upsells.update');
        Route::delete('/appointments/{appointment}/upsells/{appointmentUpsell}', [AppointmentController::class, 'destroyUpsell'])->name('appointments.upsells.destroy');
        Route::post('/staff-blocks', [StaffBlockController::class, 'store'])->name('staff-blocks.store');
        Route::delete('/staff-blocks/{staffBlock}', [StaffBlockController::class, 'destroy'])->name('staff-blocks.destroy');
    });

    // ---- Finance ----
    Route::middleware('module:finance')->group(function () {
        Route::get('/revenue', [FinanceController::class, 'index'])->name('appointments.revenue.index');
    });
    Route::middleware('module:finance,edit')->group(function () {
        Route::get('/appointments/{appointment}/payment', [AppointmentController::class, 'payment'])->name('appointments.revenue.payment');
        Route::post('/appointments/{appointment}/payment', [AppointmentController::class, 'storePayment'])->name('appointments.revenue.storePayment');
        Route::post('/expenses', [FinanceController::class, 'storeExpense'])->name('expenses.store');
        Route::delete('/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('expenses.destroy');
        Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    });

    // ---- Staff Management (team members + scheduled shifts) ----
    Route::middleware('module:staff_management')->group(function () {
        Route::get('/staffs', [StaffController::class, 'index'])->name('staffs.index');
        Route::get('/scheduled-shifts', [ShiftController::class, 'index'])->name('shifts.index');
    });
    Route::middleware('module:staff_management,edit')->group(function () {
        Route::post('/staffs', [StaffController::class, 'store'])->name('staffs.store');
        Route::put('/staffs/{staff}', [StaffController::class, 'update'])->name('staffs.update');
        Route::delete('/staffs/{staff}', [StaffController::class, 'destroy'])->name('staffs.destroy');
        Route::post('/scheduled-shifts/{staff}/pattern', [ShiftController::class, 'savePattern'])->name('shifts.pattern.store');
        Route::post('/scheduled-shifts/{staff}/time-off', [ShiftController::class, 'storeTimeOff'])->name('shifts.timeoff.store');
        Route::delete('/scheduled-shifts/time-off/{timeOff}', [ShiftController::class, 'destroyTimeOff'])->name('shifts.timeoff.destroy');

        Route::post('/staff-overtime', [StaffOvertimeController::class, 'store'])->name('staff-overtime.store');
        Route::put('/staff-overtime/{staffOvertimeEntry}', [StaffOvertimeController::class, 'update'])->name('staff-overtime.update');
        Route::delete('/staff-overtime/{staffOvertimeEntry}', [StaffOvertimeController::class, 'destroy'])->name('staff-overtime.destroy');

        Route::post('/staff-complaints', [StaffComplaintController::class, 'store'])->name('staff-complaints.store');
        Route::put('/staff-complaints/{staffComplaint}', [StaffComplaintController::class, 'update'])->name('staff-complaints.update');
        Route::delete('/staff-complaints/{staffComplaint}', [StaffComplaintController::class, 'destroy'])->name('staff-complaints.destroy');
        Route::post('/staff-complaints/{staffComplaint}/generate-notice', [StaffComplaintController::class, 'generateNotice'])->name('staff-complaints.generate-notice');
        Route::post('/staff-complaints/{staffComplaint}/draft-notice-ai', [StaffComplaintController::class, 'draftNoticeAi'])->name('staff-complaints.draft-notice-ai');

        Route::post('/staff-deductions', [StaffDeductionController::class, 'store'])->name('staff-deductions.store');
        Route::put('/staff-deductions/{staffDeduction}', [StaffDeductionController::class, 'update'])->name('staff-deductions.update');
        Route::delete('/staff-deductions/{staffDeduction}', [StaffDeductionController::class, 'destroy'])->name('staff-deductions.destroy');

        Route::put('/staff-notices/{staffNotice}', [StaffNoticeController::class, 'update'])->name('staff-notices.update');
        Route::delete('/staff-notices/{staffNotice}', [StaffNoticeController::class, 'destroy'])->name('staff-notices.destroy');
    });

    // ---- Services ----
    Route::middleware('module:services')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    });
    Route::middleware('module:services,edit')->group(function () {
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::post('/service-categories', [ServiceCategoryController::class, 'store'])->name('service-categories.store');
        Route::delete('/service-categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');
    });

    // ---- Products ----
    Route::middleware('module:products')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    });
    Route::middleware('module:products,edit')->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // ---- Clients ----
    Route::middleware('module:clients')->group(function () {
        Route::get('/customers-lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
        Route::get('/customers-search', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/follow-ups', [CustomerController::class, 'followUps'])->name('customers.follow-ups');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware('module:clients,edit')->group(function () {
        Route::patch('/customers/{customer}/notes', [CustomerController::class, 'updateNotes'])->name('customers.notes.update');
        Route::patch('/customers/{customer}/allergies', [CustomerController::class, 'updateAllergies'])->name('customers.allergies.update');
        Route::post('/customers/{customer}/redeem', [CustomerController::class, 'redeemPoints'])->name('customers.loyalty.redeem');
    });

    // ---- Staff Access: a single super-admin account only, independent of the module matrix ----
    Route::middleware('super-admin')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    // ---- KPIs ----
    // NOTE: each subresource's literal 'create' path is registered before its
    // '{report}' wildcard (show), same reasoning as the Leads section above.
    Route::prefix('kpis')->name('kpi.')->group(function () {
        Route::middleware('module:kpis')->get('/', function () {
            return view('kpi.hub');
        })->name('hub');

        Route::middleware('module:kpis')->get('/ads', [AdsConversionController::class, 'index'])->name('ads.index');
        Route::middleware('module:kpis,edit')->post('/ads', [AdsConversionController::class, 'store'])->name('ads.store');
        Route::middleware('module:kpis')->get('/ads/{report}', [AdsConversionController::class, 'show'])->name('ads.show');
        Route::middleware('module:kpis,edit')->delete('/ads/{report}', [AdsConversionController::class, 'destroy'])->name('ads.destroy');

        Route::middleware('module:kpis,edit')->post('/ad-leads', [AdLeadEntryController::class, 'store'])->name('ad-leads.store');
        Route::middleware('module:kpis,edit')->put('/ad-leads/{adLeadEntry}', [AdLeadEntryController::class, 'update'])->name('ad-leads.update');
        Route::middleware('module:kpis,edit')->delete('/ad-leads/{adLeadEntry}', [AdLeadEntryController::class, 'destroy'])->name('ad-leads.destroy');

        Route::middleware('module:kpis')->get('/agents', [AgentTargetController::class, 'index'])->name('agents.index');
        Route::middleware('module:kpis,edit')->post('/agents', [AgentTargetController::class, 'store'])->name('agents.store');
        Route::middleware('module:kpis')->get('/agents/{report}', [AgentTargetController::class, 'show'])->name('agents.show');
        Route::middleware('module:kpis,edit')->delete('/agents/{report}', [AgentTargetController::class, 'destroy'])->name('agents.destroy');

        Route::middleware('module:kpis,edit')->post('/agent-shift-logs', [AgentShiftLogController::class, 'store'])->name('agent-shift-logs.store');
        Route::middleware('module:kpis,edit')->put('/agent-shift-logs/{agentShiftLog}', [AgentShiftLogController::class, 'update'])->name('agent-shift-logs.update');
        Route::middleware('module:kpis,edit')->delete('/agent-shift-logs/{agentShiftLog}', [AgentShiftLogController::class, 'destroy'])->name('agent-shift-logs.destroy');

        Route::middleware('module:kpis')->get('/staff-sales', [StaffSalesController::class, 'index'])->name('staff-sales.index');

        Route::middleware('module:kpis')->get('/chat-eval', [ChatEvaluationController::class, 'index'])->name('chat-eval.index');
        Route::middleware('module:kpis,edit')->get('/chat-eval/create', [ChatEvaluationController::class, 'create'])->name('chat-eval.create');
        Route::middleware('module:kpis,edit')->post('/chat-eval', [ChatEvaluationController::class, 'store'])->name('chat-eval.store');
        Route::middleware('module:kpis')->get('/chat-eval/{report}', [ChatEvaluationController::class, 'show'])->name('chat-eval.show');
        Route::middleware('module:kpis,edit')->delete('/chat-eval/{report}', [ChatEvaluationController::class, 'destroy'])->name('chat-eval.destroy');

        Route::middleware('module:kpis')->get('/content', [ContentKpiController::class, 'index'])->name('content.index');
        Route::middleware('module:kpis,edit')->post('/content', [ContentKpiController::class, 'store'])->name('content.store');
        Route::middleware('module:kpis')->get('/content/{report}', [ContentKpiController::class, 'show'])->name('content.show');
        Route::middleware('module:kpis,edit')->delete('/content/{report}', [ContentKpiController::class, 'destroy'])->name('content.destroy');

        Route::middleware('module:kpis,edit')->post('/content-entries', [ContentEntryController::class, 'store'])->name('content-entries.store');
        Route::middleware('module:kpis,edit')->put('/content-entries/{contentEntry}', [ContentEntryController::class, 'update'])->name('content-entries.update');
        Route::middleware('module:kpis,edit')->delete('/content-entries/{contentEntry}', [ContentEntryController::class, 'destroy'])->name('content-entries.destroy');
    });
});
