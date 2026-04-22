<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Training::with('employees')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function enrol(Request $request, int $employeeId): JsonResponse
    {
        $request->validate([
            'TrainingID' => 'required|exists:Training,TrainingID',
            'CompletionStatus' => 'required|in:Enrolled,Completed,Failed',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $existing = $employee->trainings()->where('Training.TrainingID', $request->integer('TrainingID'))->exists();

        if ($existing) {
            $employee->trainings()->updateExistingPivot($request->integer('TrainingID'), [
                'CompletionStatus' => $request->string('CompletionStatus')->toString(),
            ]);
        } else {
            $employee->trainings()->attach($request->integer('TrainingID'), [
                'CompletionStatus' => $request->string('CompletionStatus')->toString(),
            ]);
        }

        return response()->json($employee->load('trainings'));
    }

    public function outstanding(): JsonResponse
    {
        $employees = Employee::with(['trainings' => fn ($query) => $query->wherePivot('CompletionStatus', '!=', 'Completed')])->get();

        return response()->json($employees);
    }
}
