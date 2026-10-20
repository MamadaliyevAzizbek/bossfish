<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogProductController;
use App\Http\Controllers\Api\DirectorCabinetController;
use App\Http\Controllers\Api\DirectorFinanceController;
use App\Http\Controllers\Api\OwnerCabinetController;
use App\Http\Controllers\Api\OwnerPlannerController;
use App\Http\Controllers\Api\OwnerRationController;
use App\Http\Controllers\Api\OwnerSubscriptionController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\VengerCabinetController;
use App\Http\Controllers\Api\VengerClientController;
use App\Http\Controllers\Api\WorkerCabinetController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/owner-login', [AuthController::class, 'ownerLogin']);
    Route::post('/venger-login', [AuthController::class, 'vengerLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/support', [SupportController::class, 'info']);
Route::post('/support/messages', [SupportController::class, 'store']);

Route::get('/catalog/products', [CatalogProductController::class, 'publicIndex']);

Route::middleware(['auth:sanctum', 'owner'])->prefix('owner')->group(function () {
    Route::get('/dashboard', [OwnerCabinetController::class, 'dashboard']);
    Route::get('/regions', [OwnerCabinetController::class, 'regions']);

    Route::get('/directors', [OwnerCabinetController::class, 'directors']);
    Route::post('/directors', [OwnerCabinetController::class, 'storeDirector']);
    Route::put('/directors/{director}', [OwnerCabinetController::class, 'updateDirector']);

    Route::get('/subscriptions', [OwnerSubscriptionController::class, 'index']);
    Route::post('/subscriptions', [OwnerSubscriptionController::class, 'store']);
    Route::put('/subscriptions/{subscription}', [OwnerSubscriptionController::class, 'update']);
    Route::post('/subscriptions/{subscription}/check', [OwnerSubscriptionController::class, 'markChecked']);

    Route::get('/farms', [OwnerCabinetController::class, 'farms']);
    Route::post('/farms', [OwnerCabinetController::class, 'storeFarm']);

    Route::get('/workers', [OwnerCabinetController::class, 'workers']);
    Route::post('/workers', [OwnerCabinetController::class, 'storeWorker']);
    Route::put('/workers/{worker}', [OwnerCabinetController::class, 'updateWorker']);

    Route::get('/ponds', [OwnerCabinetController::class, 'ponds']);
    Route::post('/ponds', [OwnerCabinetController::class, 'storePond']);
    Route::put('/ponds/{pond}', [OwnerCabinetController::class, 'updatePond']);
    Route::delete('/ponds/{pond}', [OwnerCabinetController::class, 'destroyPond']);

    Route::get('/security', [OwnerCabinetController::class, 'security']);
    Route::put('/security/owner', [OwnerCabinetController::class, 'updateOwnerCredentials']);

    Route::get('/venger', [OwnerCabinetController::class, 'vengerOverview']);
    Route::post('/venger/staff', [OwnerCabinetController::class, 'storeVengerStaff']);
    Route::put('/venger/staff/{vengerStaff}', [OwnerCabinetController::class, 'updateVengerStaff']);
    Route::delete('/venger/staff/{vengerStaff}', [OwnerCabinetController::class, 'destroyVengerStaff']);

    Route::get('/venger/clients', [VengerClientController::class, 'index']);
    Route::get('/venger/clients/{client}', [VengerClientController::class, 'show']);
    Route::get('/venger/ponds', [VengerClientController::class, 'listPonds']);
    Route::get('/venger/results/{result}', [VengerClientController::class, 'showResult']);

    Route::get('/closure-requests', [OwnerCabinetController::class, 'closureRequests']);
    Route::post('/closure-requests/{closureRequest}/approve', [OwnerCabinetController::class, 'approveClosureRequest']);
    Route::post('/closure-requests/{closureRequest}/reject', [OwnerCabinetController::class, 'rejectClosureRequest']);

    Route::get('/pond-create-requests', [OwnerCabinetController::class, 'pondCreateRequests']);
    Route::post('/pond-create-requests/{createRequest}/approve', [OwnerCabinetController::class, 'approvePondCreateRequest']);
    Route::post('/pond-create-requests/{createRequest}/reject', [OwnerCabinetController::class, 'rejectPondCreateRequest']);

    Route::get('/support-messages', [OwnerCabinetController::class, 'supportMessages']);
    Route::post('/support-messages/{supportMessage}/read', [OwnerCabinetController::class, 'markSupportMessageRead']);

    Route::get('/planner', [OwnerPlannerController::class, 'show']);
    Route::put('/planner', [OwnerPlannerController::class, 'update']);
    Route::get('/planner/yearly', [OwnerPlannerController::class, 'showYearly']);
    Route::put('/planner/yearly', [OwnerPlannerController::class, 'updateYearly']);
    Route::get('/planner/monthly', [OwnerPlannerController::class, 'showMonthly']);
    Route::put('/planner/monthly', [OwnerPlannerController::class, 'updateMonthly']);
    Route::get('/planner/daily', [OwnerPlannerController::class, 'showDaily']);
    Route::put('/planner/daily/extras', [OwnerPlannerController::class, 'updateDailyExtras']);
    Route::post('/planner/inbox', [OwnerPlannerController::class, 'storeInbox']);
    Route::post('/planner/inbox/{inboxTask}/complete', [OwnerPlannerController::class, 'completeInbox']);
    Route::delete('/planner/inbox/{inboxTask}', [OwnerPlannerController::class, 'destroyInbox']);

    Route::put('/rations/{feedRation}/sale-price', [OwnerRationController::class, 'updateSalePrice']);

    Route::get('/catalog/products', [CatalogProductController::class, 'index']);
    Route::post('/catalog/products', [CatalogProductController::class, 'store']);
    Route::put('/catalog/products/{catalogProduct}', [CatalogProductController::class, 'update']);
    Route::delete('/catalog/products/{catalogProduct}', [CatalogProductController::class, 'destroy']);

    Route::get('/rations/products', [OwnerRationController::class, 'products']);
    Route::post('/rations/products', [OwnerRationController::class, 'storeProduct']);
    Route::put('/rations/products/{feedProduct}', [OwnerRationController::class, 'updateProduct']);
    Route::delete('/rations/products/{feedProduct}', [OwnerRationController::class, 'destroyProduct']);

    Route::get('/rations', [OwnerRationController::class, 'rations']);
    Route::get('/rations/{feedRation}', [OwnerRationController::class, 'showRation']);
    Route::post('/rations', [OwnerRationController::class, 'storeRation']);
    Route::put('/rations/{feedRation}', [OwnerRationController::class, 'updateRation']);
    Route::delete('/rations/{feedRation}', [OwnerRationController::class, 'destroyRation']);
});

Route::middleware(['auth:sanctum', 'director'])->prefix('director')->group(function () {
    Route::get('/regions', [DirectorCabinetController::class, 'regions']);

    Route::get('/farms', [DirectorCabinetController::class, 'farms']);
    Route::get('/farms/{farm}', [DirectorCabinetController::class, 'showFarm']);
    Route::get('/farms/{farm}/ponds', [DirectorCabinetController::class, 'farmPonds']);
    Route::post('/farms/{farm}/ponds', [DirectorCabinetController::class, 'storeFarmPond']);
    Route::get('/farms/{farm}/pond-create-requests', [DirectorCabinetController::class, 'farmPondCreateRequests']);

    Route::get('/farms/{farm}/warehouse/intake-feed-names', [DirectorCabinetController::class, 'warehouseIntakeFeedNames']);
    Route::post('/farms/{farm}/warehouse/intake', [DirectorCabinetController::class, 'storeWarehouseIntake']);
    Route::get('/farms/{farm}/warehouse', [DirectorCabinetController::class, 'warehouse']);
    Route::post('/farms/{farm}/warehouse', [DirectorCabinetController::class, 'storeWarehouseItem']);
    Route::post('/farms/{farm}/warehouse/{warehouseItem}/issue', [DirectorCabinetController::class, 'issueWarehouseItem']);

    Route::get('/farms/{farm}/finance/overview', [DirectorFinanceController::class, 'overview']);
    Route::get('/farms/{farm}/finance/sections', [DirectorFinanceController::class, 'sections']);
    Route::post('/farms/{farm}/finance/sections', [DirectorFinanceController::class, 'storeSection']);
    Route::delete('/farms/{farm}/finance/sections/{section}', [DirectorFinanceController::class, 'destroySection']);
    Route::get('/farms/{farm}/finance/expenses', [DirectorFinanceController::class, 'expenses']);
    Route::post('/farms/{farm}/finance/expenses', [DirectorFinanceController::class, 'storeExpense']);
    Route::put('/farms/{farm}/finance/expenses/{expense}', [DirectorFinanceController::class, 'updateExpense']);
    Route::delete('/farms/{farm}/finance/expenses/{expense}', [DirectorFinanceController::class, 'destroyExpense']);
    Route::get('/farms/{farm}/finance/investments', [DirectorFinanceController::class, 'investments']);
    Route::post('/farms/{farm}/finance/investments', [DirectorFinanceController::class, 'storeInvestment']);
    Route::put('/farms/{farm}/finance/investments/{investment}', [DirectorFinanceController::class, 'updateInvestment']);
    Route::delete('/farms/{farm}/finance/investments/{investment}', [DirectorFinanceController::class, 'destroyInvestment']);

    Route::get('/ponds', [DirectorCabinetController::class, 'ponds']);
    Route::post('/ponds', [DirectorCabinetController::class, 'storePond']);
    Route::get('/ponds/{pond}', [DirectorCabinetController::class, 'showPond']);

    Route::get('/ponds/{pond}/daily-reports', [DirectorCabinetController::class, 'dailyReports']);
    Route::get('/ponds/{pond}/feed-types', [DirectorCabinetController::class, 'feedTypes']);
    Route::post('/ponds/{pond}/daily-reports', [DirectorCabinetController::class, 'storeDailyReport']);
    Route::put('/ponds/{pond}/daily-reports/{dailyReport}', [DirectorCabinetController::class, 'updateDailyReport']);
    Route::delete('/ponds/{pond}/daily-reports/{dailyReport}', [DirectorCabinetController::class, 'destroyDailyReport']);
    Route::get('/water-quality-alerts', [DirectorCabinetController::class, 'waterQualityAlerts']);
    Route::get('/work-reminders', [DirectorCabinetController::class, 'workReminders']);
    Route::get('/ponds/{pond}/main-reports/{mainReport}/water-quality', [DirectorCabinetController::class, 'waterQualityDetail']);
    Route::get('/ponds/{pond}/main-reports', [DirectorCabinetController::class, 'mainReports']);
    Route::post('/ponds/{pond}/main-reports', [DirectorCabinetController::class, 'storeMainReport']);
    Route::put('/ponds/{pond}/main-reports/{mainReport}', [DirectorCabinetController::class, 'updateMainReport']);
    Route::delete('/ponds/{pond}/main-reports/{mainReport}', [DirectorCabinetController::class, 'destroyMainReport']);
    Route::get('/ponds/{pond}/summary', [DirectorCabinetController::class, 'summary']);
    Route::get('/ponds/{pond}/activities', [DirectorCabinetController::class, 'activities']);
    Route::get('/ponds/{pond}/fish-stock', [DirectorCabinetController::class, 'fishStock']);
    Route::get('/ponds/{pond}/closure', [DirectorCabinetController::class, 'closurePreview']);
    Route::post('/ponds/{pond}/closure-request', [DirectorCabinetController::class, 'submitClosureRequest']);
    Route::get('/ponds/{pond}/closure-archive', [DirectorCabinetController::class, 'closureArchive']);
    Route::post('/ponds/{pond}/close', [DirectorCabinetController::class, 'closePond']);

    Route::get('/ponds/{pond}/fish-operations', [DirectorCabinetController::class, 'fishOperations']);
    Route::post('/ponds/{pond}/fish-operations', [DirectorCabinetController::class, 'storeFishOperation']);
    Route::put('/ponds/{pond}/fish-operations/{fishOperation}', [DirectorCabinetController::class, 'updateFishOperation']);
    Route::delete('/ponds/{pond}/fish-operations/{fishOperation}', [DirectorCabinetController::class, 'destroyFishOperation']);

    Route::get('/ponds/{pond}/warehouse/available', [DirectorCabinetController::class, 'warehouseAvailable']);
});

Route::middleware(['auth:sanctum', 'worker'])->prefix('worker')->group(function () {
    Route::get('/me', [WorkerCabinetController::class, 'me']);

    Route::get('/ponds/{pond}/warehouse/available', [WorkerCabinetController::class, 'warehouseAvailable']);
    Route::get('/ponds/{pond}/daily-reports', [WorkerCabinetController::class, 'dailyReports']);
    Route::get('/ponds/{pond}/feed-types', [WorkerCabinetController::class, 'feedTypes']);
    Route::post('/ponds/{pond}/daily-reports', [WorkerCabinetController::class, 'storeDailyReport']);
    Route::put('/ponds/{pond}/daily-reports/{dailyReport}', [WorkerCabinetController::class, 'updateDailyReport']);
    Route::delete('/ponds/{pond}/daily-reports/{dailyReport}', [WorkerCabinetController::class, 'destroyDailyReport']);
    Route::get('/ponds/{pond}/main-reports', [WorkerCabinetController::class, 'mainReports']);
    Route::post('/ponds/{pond}/main-reports', [WorkerCabinetController::class, 'storeMainReport']);
    Route::put('/ponds/{pond}/main-reports/{mainReport}', [WorkerCabinetController::class, 'updateMainReport']);
    Route::delete('/ponds/{pond}/main-reports/{mainReport}', [WorkerCabinetController::class, 'destroyMainReport']);
    Route::get('/ponds/{pond}/fish-stock', [WorkerCabinetController::class, 'fishStock']);
});

Route::middleware(['auth:sanctum', 'venger'])->prefix('venger')->group(function () {
    Route::get('/dashboard', [VengerCabinetController::class, 'dashboard']);

    Route::get('/clients', [VengerClientController::class, 'index']);
    Route::post('/clients', [VengerClientController::class, 'store']);
    Route::get('/clients/{client}', [VengerClientController::class, 'show']);
    Route::put('/clients/{client}', [VengerClientController::class, 'update']);
    Route::delete('/clients/{client}', [VengerClientController::class, 'destroy']);
    Route::post('/clients/{client}/results', [VengerClientController::class, 'storeResult']);

    Route::get('/ponds', [VengerClientController::class, 'listPonds']);
    Route::get('/results/{result}', [VengerClientController::class, 'showResult']);
    Route::put('/results/{result}', [VengerClientController::class, 'updateResult']);
    Route::delete('/results/{result}', [VengerClientController::class, 'destroyResult']);
});
