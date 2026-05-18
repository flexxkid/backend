<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isEmployee = $user?->role?->RoleName === 'Employee';

        $query = LeaveRequest::with(['employee', 'approvedBy'])
            ->when($isEmployee, fn ($query) => $query->where('EmployeeID', $user?->EmployeeID))
            ->when($request->filled('EmployeeID') && ! $isEmployee, fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('LeaveStatus'), fn ($query) => $query->where('LeaveStatus', $request->string('LeaveStatus')));

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function balances(Request $request): JsonResponse
    {
        $user = $request->user();
        $isEmployee = $user?->role?->RoleName === 'Employee';

        $query = LeaveBalance::with('employee')
            ->when($isEmployee, fn ($query) => $query->where('EmployeeID', $user?->EmployeeID))
            ->when($request->filled('EmployeeID') && ! $isEmployee, fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')));

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleName = $user?->role?->RoleName;

        $validated = $request->validate([
            'EmployeeID' => 'required|exists:Employee,EmployeeID',
            'LeaveType' => 'required|in:Annual,Sick,Maternity,Emergency',
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after_or_equal:StartDate',
            'Reason' => 'nullable|string',
        ]);

        if ($roleName === 'Employee') {
            abort_if((int) $validated['EmployeeID'] !== (int) $user?->EmployeeID, 403, 'Employees can only submit leave for their own account.');
        }

        if ($roleName === 'Branch Manager') {
            $employee = Employee::query()->findOrFail($validated['EmployeeID']);
            abort_if(
                $employee->BranchID !== $user?->employee?->BranchID,
                403,
                'Branch managers can only submit leave requests for employees in their branch.'
            );
        }

        $days = Carbon::parse($validated['StartDate'])->diffInDays(Carbon::parse($validated['EndDate'])) + 1;
        $balance = LeaveBalance::query()
            ->where('EmployeeID', $validated['EmployeeID'])
            ->where('LeaveType', $validated['LeaveType'])
            ->first();

        abort_if(! $balance || $balance->RemainingDays < $days, 422, 'Insufficient leave balance.');

        $leave = LeaveRequest::create($validated + ['LeaveStatus' => 'Pending']);

        $this->notificationService->notifyRole(
            'Branch Manager',
            'Leave request submitted',
            "Leave request #{$leave->LeaveID} is awaiting approval.",
            'LEAVE_REQUEST',
            'LeaveRequest',
            $leave->LeaveID,
        );

        return response()->json($leave->load('employee'), 201);
    }

    public function allocateBalances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'EmployeeIDs' => 'required|array|min:1',
            'EmployeeIDs.*' => 'integer|exists:Employee,EmployeeID',
            'AllocationData' => 'required|array',
            'AllocationData.Annual' => 'nullable|integer|min:0',
            'AllocationData.Sick' => 'nullable|integer|min:0',
            'AllocationData.Maternity' => 'nullable|integer|min:0',
            'AllocationData.Emergency' => 'nullable|integer|min:0',
            'Type' => 'required|in:allocate,reevaluate',
        ]);

        $allocations = collect($validated['AllocationData'])
            ->filter(fn ($value) => $value !== null && $value !== '');

        abort_if($allocations->isEmpty(), 422, 'Please provide at least one leave allocation value.');

        DB::transaction(function () use ($validated, $allocations) {
            foreach ($validated['EmployeeIDs'] as $employeeId) {
                foreach ($allocations as $leaveType => $totalDays) {
                    $balance = LeaveBalance::query()->firstOrNew([
                        'EmployeeID' => $employeeId,
                        'LeaveType' => $leaveType,
                    ]);

                    $usedDays = (int) ($validated['Type'] === 'reevaluate' ? 0 : ($balance->UsedDays ?? 0));
                    $totalDays = (int) $totalDays;

                    $balance->fill([
                        'TotalDays' => $totalDays,
                        'UsedDays' => min($usedDays, $totalDays),
                        'RemainingDays' => max(0, $totalDays - min($usedDays, $totalDays)),
                    ]);
                    $balance->save();
                }
            }
        });

        return response()->json([
            'message' => 'Leave balances updated successfully.',
            'balances' => LeaveBalance::with('employee')
                ->whereIn('EmployeeID', $validated['EmployeeIDs'])
                ->get(),
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'comment' => 'nullable|string|max:1000',
        ]);

        $leave = LeaveRequest::with('employee')->findOrFail($id);

        DB::transaction(function () use ($leave, $validated, $request) {
            $leave->update([
                'LeaveStatus' => $validated['status'],
                'ApprovedBy' => $request->user()?->EmployeeID,
                'ApprovedAt' => now(),
                'ApprovalComment' => $validated['comment'] ?? null,
            ]);

            if ($validated['status'] === 'Approved') {
                $days = Carbon::parse($leave->StartDate)->diffInDays(Carbon::parse($leave->EndDate)) + 1;
                $balance = LeaveBalance::query()
                    ->where('EmployeeID', $leave->EmployeeID)
                    ->where('LeaveType', $leave->LeaveType)
                    ->firstOrFail();

                $usedDays = $balance->UsedDays + $days;
                $balance->update([
                    'UsedDays' => $usedDays,
                    'RemainingDays' => max(0, $balance->TotalDays - $usedDays),
                ]);
            }
        });

        $this->notificationService->create(
            $leave->employee?->userAccount?->UserID,
            'Leave request updated',
            "Your leave request #{$leave->LeaveID} was {$validated['status']}.",
            'LEAVE_STATUS',
            'LeaveRequest',
            $leave->LeaveID,
        );

        $employee = $leave->employee;
        $statusText = strtolower($validated['status']);
        $comment = ! empty($validated['comment']) ? "\n\nComment: {$validated['comment']}" : '';
        $period = Carbon::parse($leave->StartDate)->format('d M Y').' to '.Carbon::parse($leave->EndDate)->format('d M Y');

        $this->notificationService->sendEmailToEmployee(
            $employee,
            "Leave request {$statusText}",
            "Hello {$employee?->FullName},\n\nYour {$leave->LeaveType} leave request for {$period} has been {$statusText}.{$comment}\n\nRegards,\nHRMS",
        );

        return response()->json($leave->fresh()->load(['employee', 'approvedBy']));
    }
}
