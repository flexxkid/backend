<?php

namespace App\Http\Controllers;

use App\Models\AdditionalDocuments;
use App\Services\DocumentStorageService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'url' => $this->documentStorageService->url($document->Document),
        ]);
    }
}
