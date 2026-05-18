<?php

namespace App\Http\Controllers;

use App\Models\DeploymentHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeploymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeploymentHistory::with(['employee', 'branch', 'deployedBy'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('BranchID'), fn ($query) => $query->where('BranchID', $request->integer('BranchID')))
            ->orderByDesc('StartDate');

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

    public function printable(Request $request): Response
    {
        $records = DeploymentHistory::with(['employee', 'branch', 'deployedBy'])
            ->when($request->filled('EmployeeID'), fn ($query) => $query->where('EmployeeID', $request->integer('EmployeeID')))
            ->when($request->filled('BranchID'), fn ($query) => $query->where('BranchID', $request->integer('BranchID')))
            ->orderByDesc('StartDate')
            ->get();

        $rows = $records->map(function (DeploymentHistory $record) {
            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($record->employee?->FullName ?? 'N/A'),
                e($record->branch?->BranchName ?? 'N/A'),
                e($record->DeploymentSite ?? 'N/A'),
                e((string) $record->StartDate),
                e((string) ($record->EndDate ?? 'Present')),
                e($record->Reason ?? 'N/A')
            );
        })->implode('');

        return response($this->printableHtml('Deployment History', [
            'Employee',
            'Branch',
            'Site',
            'Start Date',
            'End Date',
            'Reason',
        ], $rows), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function printableHtml(string $title, array $columns, string $rows): string
    {
        $thead = collect($columns)->map(fn (string $column) => '<th>'.e($column).'</th>')->implode('');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{$title}</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 24px; color: #0A1628; }
    h1 { margin-bottom: 8px; }
    p { color: #445; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cfd8e3; padding: 8px; text-align: left; font-size: 12px; }
    th { background: #f3f6fa; }
  </style>
</head>
<body>
  <h1>{$title}</h1>
  <p>Generated at: {$this->escape(now()->toDateTimeString())}</p>
  <table>
    <thead><tr>{$thead}</tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
