<?php

namespace App\Http\Controllers;

use App\Models\AdditionalDocuments;
use App\Services\DocumentStorageService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly DocumentStorageService $documentStorageService,
    )
    {
    }

    public function store(Request $request, int $employeeId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'DocumentTypeID' => 'required|exists:DocumentType,DocumentTypeID',
            'Description' => 'nullable|string',
            'ExpiryDate' => 'nullable|date|after:today',
        ]);

        $path = $this->documentStorageService->store(
            $request->file('file'),
            "employees/{$employeeId}/documents",
        );

        $document = AdditionalDocuments::create([
            'EmployeeID' => $employeeId,
            'DocumentTypeID' => $request->integer('DocumentTypeID'),
            'Document' => $path,
            'Description' => $request->string('Description')->toString(),
            'ExpiryDate' => $request->date('ExpiryDate'),
            'UploadDate' => now(),
            'UploadedBy' => $request->user()?->EmployeeID,
        ]);

        $this->notificationService->notifyRole(
            'HR Administrator',
            'Document uploaded',
            "A document was uploaded for employee #{$employeeId}.",
            'DOCUMENT_UPLOAD',
            'AdditionalDocuments',
            $document->DocumentID,
        );

        return response()->json($document->load('documentType'), 201);
    }

    public function show(int $documentId): JsonResponse
    {
        $document = AdditionalDocuments::findOrFail($documentId);
        $this->authorizeDocumentAccess(request(), $document);

        return response()->json([
            'url' => $this->documentStorageService->isLocalDisk()
                ? URL::temporarySignedRoute(
                    'employees.documents.show',
                    now()->addMinutes(5),
                    ['documentId' => $document->DocumentID]
                )
                : $this->documentStorageService->url($document->Document),
        ]);
    }

    public function stream(Request $request, int $documentId): BinaryFileResponse
    {
        $document = AdditionalDocuments::findOrFail($documentId);
        $this->authorizeDocumentAccess($request, $document);

        abort_unless(filled($document->Document), 404);
        abort_unless($this->documentStorageService->exists($document->Document), 404);
        abort_unless($this->documentStorageService->isLocalDisk(), 404);

        $absolutePath = $this->documentStorageService->absolutePath($document->Document);
        abort_unless(is_file($absolutePath), 404);

        return response()->file(
            $absolutePath,
            [
                'Content-Disposition' => 'inline; filename="' . basename($document->Document) . '"',
            ]
        );
    }

    private function authorizeDocumentAccess(Request $request, AdditionalDocuments $document): void
    {
        $user = $request->user();
        $roleName = $user?->role?->RoleName;

        if ($roleName === 'Employee') {
            abort_if((int) $user?->EmployeeID !== (int) $document->EmployeeID, 403, 'Forbidden: insufficient role');

            return;
        }

        if ($roleName === 'Branch Manager') {
            abort_if(
                $document->employee?->BranchID !== $user?->employee?->BranchID,
                403,
                'Forbidden: insufficient role'
            );
        }
    }
}
