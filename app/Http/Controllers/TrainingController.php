<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Training;
use Carbon\CarbonImmutable;
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
        $validated = $request->validate([
            'TrainingID' => 'required|exists:Training,TrainingID',
            'CompletionStatus' => 'required|in:Enrolled,Completed,Failed',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $training = Training::findOrFail((int) $validated['TrainingID']);

        if ($this->trainingHasEnded($training)) {
            return response()->json([
                'message' => 'This training is already over and cannot accept new applications.',
            ], 422);
        }

        $existing = $employee->trainings()->where('Training.TrainingID', $training->TrainingID)->exists();
        $completionStatus = $request->string('CompletionStatus')->toString();

        if ($existing) {
            $employee->trainings()->updateExistingPivot($training->TrainingID, [
                'CompletionStatus' => $completionStatus,
            ]);
        } else {
            $employee->trainings()->attach($training->TrainingID, [
                'CompletionStatus' => $completionStatus,
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

    private function trainingHasEnded(Training $training): bool
    {
        $todayInEastAfrica = CarbonImmutable::now('Africa/Nairobi')->startOfDay();

        if ($training->EndDate) {
            return CarbonImmutable::parse($training->EndDate, 'Africa/Nairobi')
                ->startOfDay()
                ->lt($todayInEastAfrica);
        }

        if ($training->StartDate) {
            return CarbonImmutable::parse($training->StartDate, 'Africa/Nairobi')
                ->startOfDay()
                ->lt($todayInEastAfrica);
        }

        return false;
    }
}
