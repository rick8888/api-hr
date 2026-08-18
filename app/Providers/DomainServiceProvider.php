<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Attendance\Repositories\AttendanceRecordRepositoryInterface;
use App\Domain\Attendance\Repositories\RegularizationRepositoryInterface;
use App\Domain\Attendance\Repositories\ShiftRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Leave\Repositories\CompensatoryOffRepositoryInterface;
use App\Domain\Expense\Repositories\ExpenseRepositoryInterface;
use App\Domain\Loan\Repositories\LoanRepositoryInterface;
use App\Domain\DocumentRequest\Repositories\DocumentRequestRepositoryInterface;
use App\Domain\Equipment\Repositories\EquipmentAssignmentRepositoryInterface;
use App\Domain\Equipment\Repositories\EquipmentRepositoryInterface;
use App\Domain\Equipment\Repositories\EquipmentRequestRepositoryInterface;
use App\Domain\Recruitment\Repositories\CandidateRepositoryInterface;
use App\Domain\Recruitment\Repositories\JobOpeningRepositoryInterface;
use App\Domain\Training\Repositories\TrainingEnrollmentRepositoryInterface;
use App\Domain\Training\Repositories\TrainingRepositoryInterface;
use App\Domain\EmployeeDocument\Repositories\EmployeeDocumentRepositoryInterface;
use App\Domain\Billing\Repositories\InvoicePaymentRepositoryInterface;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\Compliance\Repositories\ComplianceObligationRepositoryInterface;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Domain\Organization\Repositories\DesignationRepositoryInterface;
use App\Domain\Organization\Repositories\HolidayRepositoryInterface;
use App\Domain\Organization\Repositories\SiteRepositoryInterface;
use App\Domain\Organization\Repositories\TeamRepositoryInterface;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Domain\Platform\Repositories\AttendanceTypeRepositoryInterface;
use App\Domain\Platform\Repositories\DayTypeRepositoryInterface;
use App\Domain\Platform\Repositories\DocumentCategoryRepositoryInterface;
use App\Domain\Platform\Repositories\EquipmentCategoryRepositoryInterface;
use App\Domain\Platform\Repositories\ExpenseTypeRepositoryInterface;
use App\Domain\Platform\Repositories\PayrollCycleRepositoryInterface;
use App\Domain\Platform\Repositories\TrainingCategoryRepositoryInterface;
use App\Domain\Platform\Repositories\RegularizationTypeRepositoryInterface;
use App\Domain\Platform\Repositories\EntityStatusRepositoryInterface;
use App\Domain\Platform\Repositories\PlatformSettingRepositoryInterface;
use App\Domain\Platform\Repositories\RoleRepositoryInterface;
use App\Domain\Payroll\Repositories\PayrollModifierRepositoryInterface;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Domain\Payroll\Repositories\SalaryComponentRepositoryInterface;
use App\Domain\Payroll\Repositories\EmployeeSalaryStructureRepositoryInterface;
use App\Domain\Subscription\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Subscription\Repositories\SubscriptionServiceRepositoryInterface;
use App\Domain\Tax\Repositories\LegalTaxRepositoryInterface;
use App\Domain\Tax\Repositories\TaxBracketRepositoryInterface;
use App\Engines\FormulaEngine;
use App\Engines\ModifierEngine;
use App\Engines\PayrollEngine;
use App\Engines\TaxEngine;
use App\Infrastructure\Repositories\Eloquent\EloquentCompanyRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentInvoicePaymentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentInvoiceRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentComplianceObligationRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentDepartmentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentDesignationRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentHolidayRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentAttendanceRecordRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentLeaveBalanceRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentLeaveRequestRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentLeaveTypeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCompensatoryOffRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentExpenseRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentLoanRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentDocumentRequestRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEquipmentAssignmentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEquipmentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEquipmentRequestRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCandidateRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentJobOpeningRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTrainingEnrollmentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTrainingRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEmployeeDocumentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentRegularizationRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentShiftRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentSiteRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTeamRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEmployeeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEmployeeSalaryStructureRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentLegalTaxRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPayrollModifierRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPayrollRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentSubscriptionPlanRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentSubscriptionServiceRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTaxBracketRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentAttendanceTypeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentDayTypeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentDocumentCategoryRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEquipmentCategoryRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentExpenseTypeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPayrollCycleRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTrainingCategoryRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentRegularizationTypeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentEntityStatusRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPlatformSettingRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentSalaryComponentRepository;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CompanyRepositoryInterface::class => EloquentCompanyRepository::class,
        InvoiceRepositoryInterface::class => EloquentInvoiceRepository::class,
        InvoicePaymentRepositoryInterface::class => EloquentInvoicePaymentRepository::class,
        ComplianceObligationRepositoryInterface::class => EloquentComplianceObligationRepository::class,
        TeamRepositoryInterface::class => EloquentTeamRepository::class,
        DepartmentRepositoryInterface::class => EloquentDepartmentRepository::class,
        DesignationRepositoryInterface::class => EloquentDesignationRepository::class,
        SiteRepositoryInterface::class => EloquentSiteRepository::class,
        HolidayRepositoryInterface::class => EloquentHolidayRepository::class,
        ShiftRepositoryInterface::class => EloquentShiftRepository::class,
        AttendanceRecordRepositoryInterface::class => EloquentAttendanceRecordRepository::class,
        RegularizationRepositoryInterface::class => EloquentRegularizationRepository::class,
        LeaveBalanceRepositoryInterface::class => EloquentLeaveBalanceRepository::class,
        LeaveRequestRepositoryInterface::class => EloquentLeaveRequestRepository::class,
        LeaveTypeRepositoryInterface::class => EloquentLeaveTypeRepository::class,
        CompensatoryOffRepositoryInterface::class => EloquentCompensatoryOffRepository::class,
        ExpenseRepositoryInterface::class => EloquentExpenseRepository::class,
        LoanRepositoryInterface::class => EloquentLoanRepository::class,
        EmployeeDocumentRepositoryInterface::class => EloquentEmployeeDocumentRepository::class,
        DocumentRequestRepositoryInterface::class => EloquentDocumentRequestRepository::class,
        TrainingRepositoryInterface::class => EloquentTrainingRepository::class,
        TrainingEnrollmentRepositoryInterface::class => EloquentTrainingEnrollmentRepository::class,
        JobOpeningRepositoryInterface::class => EloquentJobOpeningRepository::class,
        CandidateRepositoryInterface::class => EloquentCandidateRepository::class,
        EquipmentRepositoryInterface::class => EloquentEquipmentRepository::class,
        EquipmentRequestRepositoryInterface::class => EloquentEquipmentRequestRepository::class,
        EquipmentAssignmentRepositoryInterface::class => EloquentEquipmentAssignmentRepository::class,
        EmployeeRepositoryInterface::class => EloquentEmployeeRepository::class,
        SalaryComponentRepositoryInterface::class => EloquentSalaryComponentRepository::class,
        PayrollModifierRepositoryInterface::class => EloquentPayrollModifierRepository::class,
        EmployeeSalaryStructureRepositoryInterface::class => EloquentEmployeeSalaryStructureRepository::class,
        PayrollRepositoryInterface::class => EloquentPayrollRepository::class,
        LegalTaxRepositoryInterface::class => EloquentLegalTaxRepository::class,
        TaxBracketRepositoryInterface::class => EloquentTaxBracketRepository::class,
        SubscriptionPlanRepositoryInterface::class => EloquentSubscriptionPlanRepository::class,
        SubscriptionServiceRepositoryInterface::class => EloquentSubscriptionServiceRepository::class,
        RoleRepositoryInterface::class => EloquentRoleRepository::class,
        AttendanceTypeRepositoryInterface::class => EloquentAttendanceTypeRepository::class,
        DayTypeRepositoryInterface::class => EloquentDayTypeRepository::class,
        DocumentCategoryRepositoryInterface::class => EloquentDocumentCategoryRepository::class,
        EquipmentCategoryRepositoryInterface::class => EloquentEquipmentCategoryRepository::class,
        ExpenseTypeRepositoryInterface::class => EloquentExpenseTypeRepository::class,
        PayrollCycleRepositoryInterface::class => EloquentPayrollCycleRepository::class,
        TrainingCategoryRepositoryInterface::class => EloquentTrainingCategoryRepository::class,
        RegularizationTypeRepositoryInterface::class => EloquentRegularizationTypeRepository::class,
        EntityStatusRepositoryInterface::class => EloquentEntityStatusRepository::class,
        PlatformSettingRepositoryInterface::class => EloquentPlatformSettingRepository::class,
    ];

    /** @var array<int, class-string> */
    public array $singletons = [
        FormulaEngine::class,
        TaxEngine::class,
        ModifierEngine::class,
        PayrollEngine::class,
    ];

    public function register(): void
    {
        // bindings & singletons handled via the public properties above
    }

    public function boot(): void
    {
    }
}
