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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'TrainingName' => 'required|string|max:200',
            'TrainingType' => 'nullable|string|max:100',
            'StartDate' => 'nullable|date',
            'EndDate' => 'nullable|date|after_or_equal:StartDate',
        ]);

        $training = Training::create($validated);

        return response()->json($training->load('employees'), 201);
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

    public function destroy(int $id): JsonResponse
    {
        $training = Training::findOrFail($id);
        $training->employees()->detach();
        $training->delete();

        return response()->json(['message' => 'Training deleted successfully.']);
    }

    public function outstanding(): JsonResponse
    {
        $employees = Employee::with(['trainings' => fn ($query) => $query->wherePivot('CompletionStatus', '!=', 'Completed')])->get();

        return response()->json($employees);
    }
}
