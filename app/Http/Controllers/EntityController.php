<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Support\HrmsEntityRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;

class EntityController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function index(Request $request, string $entity): JsonResponse
    {
        $model = HrmsEntityRegistry::model($entity);
        $query = $model->newQuery();

        $includes = $this->requestedIncludes($request, $entity);

        if ($includes !== []) {
            $query->with($includes);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
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
}
