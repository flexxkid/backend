<?php

namespace App\Http\Controllers;

use App\Models\PerformanceEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceEvaluationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PerformanceEvaluation::with(['employee', 'evaluator']);

        match ($user?->role?->RoleName) {
            'Employee' => $query->where('EmployeeID', $user->EmployeeID),
            'Branch Manager' => $query->whereHas('employee', fn ($q) => $q->where('SupervisorID', $user->EmployeeID)),
            default => null,
        };

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'EmployeeID' => 'required|exists:Employee,EmployeeID',
            'EvaluationPeriod' => 'required|string|max:100',
            'Score' => 'required|integer|min:1|max:100',
            'Comments' => 'nullable|string',
        ]);

        $evaluation = PerformanceEvaluation::create($validated + [
            'EvaluatorID' => $request->user()?->EmployeeID,
        ]);

        return response()->json($evaluation->load(['employee', 'evaluator']), 201);
    }
}
