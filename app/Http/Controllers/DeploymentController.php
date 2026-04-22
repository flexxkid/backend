<?php

namespace App\Http\Controllers;

use App\Models\DeploymentHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeploymentHistory::with(['employee', 'branch', 'deployedBy'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('BranchID'), fn ($query) => $query->where('BranchID', $request->integer('BranchID')));

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'EmployeeID' => 'required|exists:Employee,EmployeeID',
            'BranchID' => 'nullable|exists:Branch,BranchID',
            'DeploymentSite' => 'required|string|max:255',
            'StartDate' => 'required|date',
            'EndDate' => 'nullable|date|after_or_equal:StartDate',
            'Reason' => 'nullable|string',
        ]);

        $deployment = DeploymentHistory::create($validated + [
            'DeployedBy' => $request->user()?->EmployeeID,
        ]);

        return response()->json($deployment->load(['employee', 'branch']), 201);
    }

    public function currentlyDeployed(int $branchId): JsonResponse
    {
        $guards = DeploymentHistory::with('employee')
            ->where('BranchID', $branchId)
            ->whereNull('EndDate')
            ->get();

        return response()->json($guards);
    }
}
