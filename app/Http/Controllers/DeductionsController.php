<?php

namespace App\Http\Controllers;

use App\Models\Deductions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DeductionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Deductions::query()
            ->with('assignedDeductions')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where('DeductionName', 'like', '%'.$request->string('search')->toString().'%')
            );

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'DeductionName' => ['required', 'string', 'max:150', Rule::unique('Deductions', 'DeductionName')],
            'Rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $deduction = Deductions::create($validated);

        return response()->json($deduction->load('assignedDeductions'), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Deductions::with('assignedDeductions')->findOrFail($id)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $deduction = Deductions::findOrFail($id);

        $validated = $request->validate([
            'DeductionName' => [
                'required',
                'string',
                'max:150',
                Rule::unique('Deductions', 'DeductionName')->ignore($deduction->DeductionID, 'DeductionID'),
            ],
            'Rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $deduction->update($validated);

        return response()->json($deduction->load('assignedDeductions'));
    }

    public function destroy(int $id): Response
    {
        Deductions::findOrFail($id)->delete();

        return response()->noContent();
    }
}
