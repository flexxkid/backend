<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with('employee')
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('AttendanceDate', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('AttendanceDate', '<=', $request->input('to')));

        $sortBy = $request->input('sort_by');
        $sortDirection = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'AttendanceDate') {
            $query->orderBy('AttendanceDate', $sortDirection)
                ->orderBy('AttendanceID', $sortDirection);
        } else {
            $query->orderByDesc('AttendanceDate')
                ->orderByDesc('AttendanceID');
        }

        $paginator = $query->paginate($request->integer('per_page', 15));
        $directionFactor = $sortDirection === 'asc' ? 1 : -1;

        $sorted = $paginator->getCollection()
            ->sort(function ($left, $right) use ($sortBy, $directionFactor) {
                $leftDate = (string) ($left->AttendanceDate ?? '');
                $rightDate = (string) ($right->AttendanceDate ?? '');

                if ($sortBy === 'AttendanceDate') {
                    $dateComparison = strcmp($leftDate, $rightDate) * $directionFactor;

                    if ($dateComparison !== 0) {
                        return $dateComparison;
                    }

                    return ((int) ($left->AttendanceID ?? 0) <=> (int) ($right->AttendanceID ?? 0)) * $directionFactor;
                }

                $dateComparison = strcmp($rightDate, $leftDate);

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return (int) ($right->AttendanceID ?? 0) <=> (int) ($left->AttendanceID ?? 0);
            })
            ->values();

        $paginator->setCollection($sorted);

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'EmployeeID' => 'required|exists:Employee,EmployeeID',
            'AttendanceDate' => 'required|date',
            'Time_In' => 'nullable|date_format:H:i:s',
            'Time_Out' => 'nullable|date_format:H:i:s',
            'AttendanceStatus' => 'required|in:Present,Absent,Late,Half-Day',
        ]);

        $attendance = Attendance::create($validated);

        return response()->json($attendance->load('employee'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'AttendanceDate' => 'sometimes|required|date',
            'Time_In' => 'sometimes|nullable|date_format:H:i:s',
            'Time_Out' => 'sometimes|nullable|date_format:H:i:s',
            'AttendanceStatus' => 'sometimes|required|in:Present,Absent,Late,Half-Day',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update($validated);

        return response()->json($attendance->load('employee'));
    }
}
