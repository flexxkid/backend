<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Support\PersonName;
use App\Support\HrmsEntityRegistry;
use App\Models\AuditLog;
use App\Models\UserAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function index(Request $request, string $entity): JsonResponse
    {
        $model = HrmsEntityRegistry::model($entity);
        $query = $model->newQuery();

        if ($entity === 'audit-logs') {
            $query->with('userAccount.employee');
        }

        $this->applyEntityFilters($query, $request, $entity);

        $includes = $this->requestedIncludes($request, $entity);

        if ($includes !== []) {
            $query->with($includes);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function exportAuditLogs(Request $request): StreamedResponse
    {
        $query = AuditLog::query()->with('userAccount.employee');
        $this->applyEntityFilters($query, $request, 'audit-logs');

        $filename = 'audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['AuditID', 'Action', 'AffectedTable', 'AffectedRecordID', 'Username', 'FullName', 'IPAddress', 'CreatedAt']);

            foreach ($query->cursor() as $log) {
                fputcsv($handle, [
                    $log->AuditID,
                    $log->Action,
                    $log->AffectedTable,
                    $log->AffectedRecordID,
                    $log->Username,
                    $log->user['FullName'] ?? null,
                    $log->IPAddress,
                    $log->CreatedAt,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, string $entity): JsonResponse
    {
        $this->abortIfReadonly($entity);

        $data = $request->validate(HrmsEntityRegistry::rules($entity));
        $model = HrmsEntityRegistry::model($entity);
        $payload = $this->preparePayload($entity, $data);
        $record = $model->create($payload);

        $this->auditLogService->record(
            'create',
            $record->getTable(),
            $record->getKey(),
            null,
            $record->fresh()->toArray(),
            $request->user(),
            $request->ip(),
        );

        return response()->json($this->loadIncludes($request, $entity, $record), 201);
    }

    public function show(Request $request, int $id, string $entity): JsonResponse
    {
        $record = $this->findRecord($entity, $id);

        return response()->json($this->loadIncludes($request, $entity, $record));
    }

    public function update(Request $request, int $id, string $entity): JsonResponse
    {
        $this->abortIfReadonly($entity);

        $record = $this->findRecord($entity, $id);
        $before = $record->toArray();
        $data = $request->validate(HrmsEntityRegistry::rules($entity, $id));
        $payload = $this->preparePayload($entity, $data, $record);

        $record->fill($payload)->save();

        $this->auditLogService->record(
            'update',
            $record->getTable(),
            $record->getKey(),
            $before,
            $record->fresh()->toArray(),
            $request->user(),
            $request->ip(),
        );

        return response()->json($this->loadIncludes($request, $entity, $record->fresh()));
    }

    public function destroy(Request $request, int $id, string $entity): Response
    {
        $this->abortIfReadonly($entity);

        $record = $this->findRecord($entity, $id);
        $before = $record->toArray();
        $table = $record->getTable();
        $recordId = $record->getKey();

        $record->delete();

        $this->auditLogService->record(
            'delete',
            $table,
            $recordId,
            $before,
            null,
            $request->user(),
            $request->ip(),
        );

        return response()->noContent();
    }

    private function requestedIncludes(Request $request, string $entity): array
    {
        $allowed = HrmsEntityRegistry::includes($entity);
        $requested = array_filter(explode(',', (string) $request->query('include')));

        return array_values(array_intersect($allowed, $requested));
    }

    private function loadIncludes(Request $request, string $entity, Model $record): Model
    {
        $includes = $this->requestedIncludes($request, $entity);

        return $includes === [] ? $record : $record->load($includes);
    }

    private function findRecord(string $entity, int $id): Model
    {
        $model = HrmsEntityRegistry::model($entity);

        return $model->newQuery()->findOrFail($id);
    }

    private function abortIfReadonly(string $entity): void
    {
        abort_if(HrmsEntityRegistry::readonly($entity), 405, 'This resource is read-only.');
    }

    private function preparePayload(string $entity, array $payload, ?Model $record = null): array
    {
        if ($entity === 'user-accounts' && array_key_exists('PasswordHash', $payload)) {
            $payload['PasswordHash'] = $this->hashPasswordIfNeeded($payload['PasswordHash']);
        }

        if (in_array($entity, ['applicants', 'employees'], true)) {
            $payload = PersonName::normalizePayload($payload, $record === null);
        }

        if ($entity === 'leave-balances') {
            $payload['UsedDays'] = (int) ($payload['UsedDays'] ?? $record?->UsedDays ?? 0);
            $payload['RemainingDays'] = (int) ($payload['RemainingDays']
                ?? (($payload['TotalDays'] ?? $record?->TotalDays ?? 0) - $payload['UsedDays']));
        }

        if ($entity === 'notifications') {
            $payload['CreatedAt'] = $payload['CreatedAt'] ?? Carbon::now();
            $payload['ReadAt'] = ($payload['IsRead'] ?? false) ? ($payload['ReadAt'] ?? Carbon::now()) : ($payload['ReadAt'] ?? null);
        }

        if ($entity === 'additional-documents') {
            $payload['UploadDate'] = $payload['UploadDate'] ?? Carbon::now();
        }

        if ($entity === 'audit-logs') {
            $payload['CreatedAt'] = $payload['CreatedAt'] ?? Carbon::now();
        }

        return $payload;
    }

    private function hashPasswordIfNeeded(string $password): string
    {
        return str_starts_with($password, '$2y$') ? $password : Hash::make($password);
    }

    private function applyEntityFilters($query, Request $request, string $entity): void
    {
        if ($entity !== 'audit-logs') {
            return;
        }

        $query
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = strtolower((string) $request->string('search'));
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->whereRaw('LOWER(Username) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(Action) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(AffectedTable) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(IPAddress) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when($request->filled('Action'), fn ($query) => $query->where('Action', strtolower($request->string('Action')->toString())))
            ->when($request->filled('TableName'), function ($query) use ($request) {
                $table = strtolower($request->string('TableName')->toString());
                $query->whereRaw('LOWER(AffectedTable) LIKE ?', ["%{$table}%"]);
            })
            ->when($request->filled('UserID'), function ($query) use ($request) {
                $userFilter = $request->integer('UserID');
                $userIds = UserAccount::query()
                    ->where('UserID', $userFilter)
                    ->orWhere('EmployeeID', $userFilter)
                    ->pluck('UserID')
                    ->all();

                if ($userIds === []) {
                    $query->where('UserID', $userFilter);

                    return;
                }

                $query->whereIn('UserID', $userIds);
            })
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('CreatedAt', '>=', $request->string('from_date')->toString()))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('CreatedAt', '<=', $request->string('to_date')->toString()))
            ->orderByDesc('CreatedAt');
    }
}
