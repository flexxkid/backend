<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
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
        $query = LeaveRequest::with(['employee', 'approvedBy'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('LeaveStatus'), fn ($query) => $query->where('LeaveStatus', $request->string('LeaveStatus')));

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'EmployeeID' => 'required|exists:Employee,EmployeeID',
            'LeaveType' => 'required|in:Annual,Sick,Maternity,Emergency',
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after_or_equal:StartDate',
            'Reason' => 'nullable|string',
        ]);

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

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected',
        ]);

        $leave = LeaveRequest::with('employee')->findOrFail($id);

        DB::transaction(function () use ($leave, $validated, $request) {
            $leave->update([
                'LeaveStatus' => $validated['status'],
                'ApprovedBy' => $request->user()?->EmployeeID,
                'ApprovedAt' => now(),
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

        return response()->json($leave->fresh()->load(['employee', 'approvedBy']));
    }
}
