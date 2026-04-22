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
            ->when($request->filled('from'), fn ($query) => $query->whereDate('AttendanceDate', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('AttendanceDate', '<=', $request->date('to')));

        return response()->json($query->paginate($request->integer('per_page', 15)));
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
            'Time_In' => 'sometimes|nullable|date_format:H:i:s',
            'Time_Out' => 'sometimes|nullable|date_format:H:i:s',
            'AttendanceStatus' => 'sometimes|required|in:Present,Absent,Late,Half-Day',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update($validated);

        return response()->json($attendance->load('employee'));
    }
}
