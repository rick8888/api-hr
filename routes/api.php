<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyUserController;
use App\Http\Controllers\Api\ComplianceObligationController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DesignationController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\DocumentRequestController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\CompensatoryOffController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoicePaymentController;
use App\Http\Controllers\Api\EmployeeStructureController;
use App\Http\Controllers\Api\LegalTaxController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\AttendanceTypeController;
use App\Http\Controllers\Api\EntityStatusController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\RegularizationController;
use App\Http\Controllers\Api\RegularizationTypeController;
use App\Http\Controllers\Api\DayTypeController;
use App\Http\Controllers\Api\ExpenseTypeController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\TerminationTypeController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\EquipmentCategoryController;
use App\Http\Controllers\Api\PayrollCycleController;
use App\Http\Controllers\Api\TrainingCategoryController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PayrollModifierController;
use App\Http\Controllers\Api\PayrollSettingsController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\SalaryComponentController;
use App\Http\Controllers\Api\SelfServiceAttendanceController;
use App\Http\Controllers\Api\SelfServiceLeaveBalanceController;
use App\Http\Controllers\Api\SelfServicePayslipsController;
use App\Http\Controllers\Api\SelfServiceProfileController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\EquipmentAssignmentController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\EquipmentRequestController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\JobOpeningController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\TrainingEnrollmentController;
use App\Http\Controllers\Api\TenantCompanyProfileController;
use App\Http\Controllers\Api\TenantSubscriptionController;
use App\Http\Controllers\Api\SubscriptionServiceController;
use App\Http\Controllers\Api\WhatsAppSettingsController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| HR / Payroll API
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);

    // Webhooks publics (Meta WhatsApp Cloud API)
    Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::middleware('permission:self_service.access')->group(function () {
            Route::get('self-service/profile', [SelfServiceProfileController::class, 'show']);
            Route::put('self-service/profile', [SelfServiceProfileController::class, 'update']);
            Route::put('self-service/profile/password', [SelfServiceProfileController::class, 'updatePassword']);

            Route::get('self-service/payslips', [SelfServicePayslipsController::class, 'index']);
            Route::get('self-service/payslips/{payroll}/pdf', [SelfServicePayslipsController::class, 'downloadPdf']);
            Route::get('self-service/payslips/{payroll}', [SelfServicePayslipsController::class, 'show']);

            Route::get('attendance', [SelfServiceAttendanceController::class, 'show']);
            Route::post('attendance/check-in', [SelfServiceAttendanceController::class, 'checkIn']);
            Route::post('attendance/check-out', [SelfServiceAttendanceController::class, 'checkOut']);
            Route::post('attendance/break/start', [SelfServiceAttendanceController::class, 'startBreak']);
            Route::post('attendance/break/end', [SelfServiceAttendanceController::class, 'endBreak']);
            Route::get('leave-balance', [SelfServiceLeaveBalanceController::class, 'show']);
        });

        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('dashboard/admin', [AdminDashboardController::class, 'show']);
        });

        Route::middleware('permission:tenant.users|employees.manage')->group(function () {
            Route::get('company-users', [CompanyUserController::class, 'index']);
        });

        Route::middleware('permission:tenant.users')->group(function () {
            Route::post('company-users', [CompanyUserController::class, 'store']);
            Route::put('company-users/{user}', [CompanyUserController::class, 'update']);
            Route::patch('company-users/{user}', [CompanyUserController::class, 'update']);
        });

        // Lecture référentiels org / présence (manager d'équipe)
        Route::middleware('permission:attendance.manage|organization.manage')->group(function () {
            Route::get('sites', [SiteController::class, 'index']);
            Route::get('sites/{site}', [SiteController::class, 'show']);
            Route::get('departments', [DepartmentController::class, 'index']);
            Route::get('departments/{department}', [DepartmentController::class, 'show']);
            Route::get('holidays', [HolidayController::class, 'index']);
            Route::get('holidays/{holiday}', [HolidayController::class, 'show']);
            Route::get('teams', [TeamController::class, 'index']);
            Route::get('teams/{team}', [TeamController::class, 'show']);
            Route::get('designations', [DesignationController::class, 'index']);
            Route::get('designations/{designation}', [DesignationController::class, 'show']);
            Route::get('shifts', [ShiftController::class, 'index']);
            Route::get('shifts/{shift}', [ShiftController::class, 'show']);
        });

        Route::middleware('permission:organization.manage')->group(function () {
            Route::apiResource('teams', TeamController::class)->except(['index', 'show']);
            Route::patch('teams/{team}/status', [TeamController::class, 'toggleStatus']);

            Route::apiResource('departments', DepartmentController::class)->except(['index', 'show']);
            Route::patch('departments/{department}/status', [DepartmentController::class, 'toggleStatus']);

            Route::apiResource('designations', DesignationController::class)->except(['index', 'show']);
            Route::patch('designations/{designation}/status', [DesignationController::class, 'toggleStatus']);

            Route::apiResource('sites', SiteController::class)->except(['index', 'show']);
            Route::patch('sites/{site}/status', [SiteController::class, 'toggleStatus']);

            Route::apiResource('holidays', HolidayController::class)->except(['index', 'show']);
            Route::patch('holidays/{holiday}/status', [HolidayController::class, 'toggleStatus']);
        });

        Route::middleware('permission:employees.view|employees.manage')->group(function () {
            Route::get('employees/export', [EmployeeController::class, 'export']);
            Route::get('employees/{employee}/activities', [EmployeeController::class, 'activities']);
            Route::get('employees/{employee}/structure', [EmployeeStructureController::class, 'index']);
            Route::apiResource('employees', EmployeeController::class)->only(['index', 'show']);
        });

        Route::middleware('permission:employees.manage|attendance.manage')->group(function () {
            Route::put('employees/{employee}', [EmployeeController::class, 'update']);
            Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        });

        Route::middleware('permission:employees.manage')->group(function () {
            Route::post('employees/import', [EmployeeController::class, 'import']);
            Route::post('employees/bulk-status', [EmployeeController::class, 'bulkStatus']);
            Route::apiResource('employees', EmployeeController::class)->only(['store', 'destroy']);
            Route::patch('employees/{employee}/status', [EmployeeController::class, 'toggleStatus']);
            Route::post('employees/{employee}/suspend', [EmployeeController::class, 'suspend']);
            Route::post('employees/{employee}/terminate', [EmployeeController::class, 'terminate']);
            Route::post('employees/{employee}/retire', [EmployeeController::class, 'retire']);
            Route::post('employees/{employee}/structure', [EmployeeStructureController::class, 'store']);
            Route::post('employees/{employee}/document-templates/preview', [DocumentTemplateController::class, 'preview']);
            Route::post('employees/{employee}/document-templates/generate', [DocumentTemplateController::class, 'generate']);
        });

        Route::middleware('permission:employees.view|employees.manage|settings.manage')->group(function () {
            Route::get('document-templates/catalog', [DocumentTemplateController::class, 'catalog']);
            Route::get('document-templates/variables', [DocumentTemplateController::class, 'variables']);
            Route::get('document-templates/events', [DocumentTemplateController::class, 'events']);
        });

        Route::middleware('permission:payroll.manage')->group(function () {
            Route::apiResource('salary-components', SalaryComponentController::class)
                ->parameter('salary-components', 'salary_component');

            Route::apiResource('payroll-modifiers', PayrollModifierController::class)
                ->parameter('payroll-modifiers', 'payroll_modifier');

            Route::put('payroll-settings', [PayrollSettingsController::class, 'update']);
            Route::post('payrolls/generate', [PayrollController::class, 'generate']);
            Route::post('payrolls/preview', [PayrollController::class, 'preview']);
            Route::patch('payrolls/{payroll}/status', [PayrollController::class, 'updateStatus']);
            Route::delete('payrolls/{payroll}', [PayrollController::class, 'destroy']);
        });

        Route::middleware('permission:payroll.view|payroll.manage')->group(function () {
            Route::get('payroll-settings', [PayrollSettingsController::class, 'show']);
            Route::get('payrolls', [PayrollController::class, 'index']);
            Route::get('payrolls/{payroll}', [PayrollController::class, 'show']);
            Route::get('payrolls/{payroll}/pdf', [PayrollController::class, 'downloadPdf']);
        });

        Route::middleware('permission:organization.manage|settings.manage')->group(function () {
            Route::apiResource('shifts', ShiftController::class)->except(['index', 'show']);
            Route::patch('shifts/{shift}/status', [ShiftController::class, 'toggleStatus']);
        });

        Route::middleware('permission:attendance.manage|settings.manage')->group(function () {
            Route::get('attendance-types', [AttendanceTypeController::class, 'index']);
            Route::get('attendance-types/{attendance_type}', [AttendanceTypeController::class, 'show']);
        });

        Route::middleware('permission:attendance.manage')->group(function () {
            Route::apiResource('attendance-records', AttendanceRecordController::class)
                ->parameters(['attendance-records' => 'attendance_record'])
                ->only(['index', 'store', 'update', 'destroy']);

            Route::apiResource('regularizations', RegularizationController::class);
            Route::patch('regularizations/{regularization}/status', [RegularizationController::class, 'updateStatus']);
        });

        Route::middleware('permission:leave.manage|self_service.access')->group(function () {
            Route::get('leave-requests/filter-options', [LeaveRequestController::class, 'filterOptions']);
            Route::apiResource('leave-requests', LeaveRequestController::class)
                ->parameters(['leave-requests' => 'leave_request']);
            Route::patch('leave-requests/{leave_request}/status', [LeaveRequestController::class, 'updateStatus']);

            Route::get('leave-balances', [LeaveBalanceController::class, 'index']);
            Route::get('leave-balances/{employeeCode}', [LeaveBalanceController::class, 'show']);

            Route::apiResource('compensatory-offs', CompensatoryOffController::class)
                ->parameters(['compensatory-offs' => 'compensatory_off'])
                ->only(['index', 'store', 'show', 'destroy']);
            Route::patch('compensatory-offs/{compensatory_off}/status', [CompensatoryOffController::class, 'updateStatus']);

            Route::apiResource('expenses', ExpenseController::class)
                ->only(['index', 'store', 'show', 'destroy']);
            Route::patch('expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);

            Route::apiResource('loans', LoanController::class)
                ->only(['index', 'store', 'show', 'destroy']);
            Route::patch('loans/{loan}/status', [LoanController::class, 'updateStatus']);

            Route::post('employee-documents', [EmployeeDocumentController::class, 'store']);
            Route::delete('employee-documents/{employee_document}', [EmployeeDocumentController::class, 'destroy']);
            Route::patch('employee-documents/{employee_document}/status', [EmployeeDocumentController::class, 'updateStatus']);
        });

        // Lecture GED employé aussi depuis la fiche (managers / RH)
        Route::middleware('permission:leave.manage|self_service.access|employees.view|employees.manage')->group(function () {
            Route::get('employee-documents', [EmployeeDocumentController::class, 'index']);
            Route::get('employee-documents/{employee_document}', [EmployeeDocumentController::class, 'show']);
        });

        Route::middleware('permission:leave.manage|self_service.access')->group(function () {

            Route::apiResource('document-requests', DocumentRequestController::class)
                ->parameters(['document-requests' => 'document_request'])
                ->only(['index', 'store', 'show', 'destroy']);
            Route::patch('document-requests/{document_request}/status', [DocumentRequestController::class, 'updateStatus']);
        });

        Route::middleware('permission:leave.manage|self_service.access')->group(function () {
            Route::get('leave-types', [LeaveTypeController::class, 'index']);
            Route::get('leave-types/{leave_type}', [LeaveTypeController::class, 'show']);
        });

        Route::middleware('permission:leave.manage')->group(function () {
            Route::apiResource('leave-types', LeaveTypeController::class)
                ->parameters(['leave-types' => 'leave_type'])
                ->except(['index', 'show']);
            Route::patch('leave-types/{leave_type}/status', [LeaveTypeController::class, 'toggleStatus']);
            Route::put('leave-balances/{employeeCode}/initial', [LeaveBalanceController::class, 'setInitial']);
        });

        Route::middleware('permission:organization.manage|employees.manage')->group(function () {
            Route::apiResource('trainings', TrainingController::class);
            Route::apiResource('training-enrollments', TrainingEnrollmentController::class)
                ->parameters(['training-enrollments' => 'training_enrollment'])
                ->only(['index', 'store', 'show', 'destroy']);
            Route::patch(
                'training-enrollments/{training_enrollment}/status',
                [TrainingEnrollmentController::class, 'updateStatus']
            );

            Route::apiResource('job-openings', JobOpeningController::class)
                ->parameters(['job-openings' => 'job_opening']);
            Route::apiResource('candidates', CandidateController::class);
            Route::patch('candidates/{candidate}/stage', [CandidateController::class, 'updateStage']);
            Route::patch('candidates/{candidate}/status', [CandidateController::class, 'updateStage']);

            Route::apiResource('equipments', EquipmentController::class);
            Route::apiResource('equipment-requests', EquipmentRequestController::class)
                ->parameters(['equipment-requests' => 'equipment_request'])
                ->only(['index', 'store', 'show', 'destroy']);
            Route::patch(
                'equipment-requests/{equipment_request}/status',
                [EquipmentRequestController::class, 'updateStatus']
            );
            Route::get('equipment-assignments', [EquipmentAssignmentController::class, 'index']);
            Route::get('equipment-assignments/{equipment_assignment}', [EquipmentAssignmentController::class, 'show']);
            Route::post(
                'equipment-assignments/{equipment_assignment}/report-issue',
                [EquipmentAssignmentController::class, 'reportIssue']
            );
            Route::patch(
                'equipment-assignments/{equipment_assignment}/return',
                [EquipmentAssignmentController::class, 'returnItem']
            );
        });

        Route::middleware('permission:tenant.portal')->group(function () {
            Route::get('tenant/dashboard', [TenantSubscriptionController::class, 'dashboard']);
            Route::get('tenant/subscription', [TenantSubscriptionController::class, 'show']);
            Route::post('tenant/subscription/change', [TenantSubscriptionController::class, 'changePlan']);
            Route::post('tenant/subscription/cancel', [TenantSubscriptionController::class, 'cancel']);
            Route::get('tenant/usage', [TenantSubscriptionController::class, 'usage']);
            Route::get('tenant/billings', [TenantSubscriptionController::class, 'billings']);
            Route::get('tenant/invoices', [TenantSubscriptionController::class, 'invoices']);
            Route::get('tenant/invoices/{invoice}/download', [TenantSubscriptionController::class, 'downloadInvoice']);
            Route::get('tenant/invoices/{invoice}', [TenantSubscriptionController::class, 'showInvoice']);
            Route::get('tenant/profile', [TenantCompanyProfileController::class, 'show']);
            Route::put('tenant/profile', [TenantCompanyProfileController::class, 'update']);
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('whatsapp-settings', [WhatsAppSettingsController::class, 'show']);
            Route::put('whatsapp-settings', [WhatsAppSettingsController::class, 'update']);

            Route::apiResource('attendance-types', AttendanceTypeController::class)
                ->parameter('attendance-types', 'attendance_type')
                ->except(['index', 'show']);
            Route::patch('attendance-types/{attendance_type}/status', [AttendanceTypeController::class, 'toggleStatus']);

            Route::apiResource('regularization-types', RegularizationTypeController::class)
                ->parameter('regularization-types', 'regularization_type');
            Route::patch('regularization-types/{regularization_type}/status', [RegularizationTypeController::class, 'toggleStatus']);

            Route::apiResource('day-types', DayTypeController::class)
                ->parameter('day-types', 'day_type');
            Route::patch('day-types/{day_type}/status', [DayTypeController::class, 'toggleStatus']);

            Route::apiResource('expense-types', ExpenseTypeController::class)
                ->parameter('expense-types', 'expense_type');
            Route::patch('expense-types/{expense_type}/status', [ExpenseTypeController::class, 'toggleStatus']);

            Route::apiResource('termination-types', TerminationTypeController::class)
                ->parameter('termination-types', 'termination_type');
            Route::patch('termination-types/{termination_type}/status', [TerminationTypeController::class, 'toggleStatus']);

            Route::apiResource('document-categories', DocumentCategoryController::class)
                ->parameter('document-categories', 'document_category');
            Route::patch('document-categories/{document_category}/status', [DocumentCategoryController::class, 'toggleStatus']);

            Route::post('document-templates/{document_template}/publish', [DocumentTemplateController::class, 'publish']);
            Route::post('document-templates/{document_template}/duplicate', [DocumentTemplateController::class, 'duplicate']);
            Route::post('document-templates/{document_template}/preview', [DocumentTemplateController::class, 'previewTemplate']);
            Route::apiResource('document-templates', DocumentTemplateController::class)
                ->parameter('document-templates', 'document_template');

            Route::apiResource('payroll-cycles', PayrollCycleController::class)
                ->parameter('payroll-cycles', 'payroll_cycle');
            Route::patch('payroll-cycles/{payroll_cycle}/status', [PayrollCycleController::class, 'toggleStatus']);

            Route::apiResource('training-categories', TrainingCategoryController::class)
                ->parameter('training-categories', 'training_category');
            Route::patch('training-categories/{training_category}/status', [TrainingCategoryController::class, 'toggleStatus']);

            Route::apiResource('equipment-categories', EquipmentCategoryController::class)
                ->parameter('equipment-categories', 'equipment_category');
            Route::patch('equipment-categories/{equipment_category}/status', [EquipmentCategoryController::class, 'toggleStatus']);
        });

        Route::middleware('permission:platform.roles|employees.manage|employees.view')->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('roles/{role}', [RoleController::class, 'show']);
        });

        Route::middleware('permission:platform.roles')->group(function () {
            Route::get('role-permissions', [RoleController::class, 'permissionCatalog']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::patch('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);
            Route::patch('roles/{role}/status', [RoleController::class, 'toggleStatus']);
        });

        Route::middleware('permission:platform.manage')->group(function () {
            Route::get('platform-settings', [PlatformSettingController::class, 'index']);
            Route::put('platform-settings', [PlatformSettingController::class, 'update']);

            Route::get('entity-statuses/catalogs', [EntityStatusController::class, 'catalogs']);
            Route::apiResource('entity-statuses', EntityStatusController::class)
                ->parameter('entity-statuses', 'entity_status');
            Route::patch('entity-statuses/{entity_status}/status', [EntityStatusController::class, 'toggleStatus']);

            Route::post('countries', [CountryController::class, 'store']);
            Route::get('countries/{country}', [CountryController::class, 'show']);
            Route::put('countries/{country}', [CountryController::class, 'update']);
            Route::patch('countries/{country}/status', [CountryController::class, 'toggleStatus']);
            Route::delete('countries/{country}', [CountryController::class, 'destroy']);

            Route::apiResource('companies', CompanyController::class);
            Route::patch('companies/{company}/status', [CompanyController::class, 'toggleStatus']);

            Route::get('subscription-services', [SubscriptionServiceController::class, 'index']);
            Route::apiResource('subscription-plans', SubscriptionPlanController::class)
                ->parameter('subscription-plans', 'subscription_plan');
            Route::patch('subscription-plans/{subscription_plan}/status', [SubscriptionPlanController::class, 'toggleStatus']);

            Route::apiResource('legal-taxes', LegalTaxController::class)
                ->parameter('legal-taxes', 'legal_tax');
        });

        // Lecture des obligations (référentiel échéances) — tenant + plateforme
        Route::middleware('permission:tenant.portal|platform.manage')->group(function () {
            Route::get('compliance-obligations', [ComplianceObligationController::class, 'index']);
            Route::get('compliance-obligations/{compliance_obligation}', [ComplianceObligationController::class, 'show']);
        });

        Route::middleware('permission:platform.manage')->group(function () {
            Route::post('compliance-obligations', [ComplianceObligationController::class, 'store']);
            Route::put('compliance-obligations/{compliance_obligation}', [ComplianceObligationController::class, 'update']);
            Route::patch('compliance-obligations/{compliance_obligation}', [ComplianceObligationController::class, 'update']);
            Route::delete('compliance-obligations/{compliance_obligation}', [ComplianceObligationController::class, 'destroy']);
        });

        Route::middleware('permission:platform.billing')->group(function () {
            Route::get('invoices/{invoice}/payment-proof', [InvoiceController::class, 'paymentProof'])
                ->name('invoices.payment-proof');
            Route::apiResource('invoices', InvoiceController::class);
            Route::post('invoices/{invoice}/settle', [InvoiceController::class, 'settle']);
            Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store']);
            Route::get('invoice-payments', [InvoicePaymentController::class, 'index']);
            Route::delete('invoice-payments/{invoice_payment}', [InvoicePaymentController::class, 'destroy']);
        });
    });

    // Référentiels publics (lecture seule — formulaires / onboarding)
    Route::get('countries', [CountryController::class, 'index']);
    Route::get('payment-methods', [PaymentMethodController::class, 'index']);
});
