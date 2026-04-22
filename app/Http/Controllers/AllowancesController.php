<?php

namespace App\Http\Controllers;

use App\Models\Allowances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AllowancesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Allowances::query()
            ->with('assignedAllowances')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where('AllowanceName', 'like', '%'.$request->string('search')->toString().'%')
            );

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'AllowanceName' => ['required', 'string', 'max:150', Rule::unique('Allowances', 'AllowanceName')],
        ]);

        $allowance = Allowances::create($validated);

        return response()->json($allowance->load('assignedAllowances'), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Allowances::with('assignedAllowances')->findOrFail($id)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $allowance = Allowances::findOrFail($id);

        $validated = $request->validate([
            'AllowanceName' => [
                'required',
                'string',
                'max:150',
                Rule::unique('Allowances', 'AllowanceName')->ignore($allowance->AllowanceID, 'AllowanceID'),
            ],
        ]);

        $allowance->update($validated);

        return response()->json($allowance->load('assignedAllowances'));
    }

    public function destroy(int $id): Response
    {
        Allowances::findOrFail($id)->delete();

        return response()->noContent();
    }
}
