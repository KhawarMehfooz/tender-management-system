<?php

use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\ReferenceAttachmentDownloadController;
use App\Http\Controllers\TaskAttachmentDownloadController;
use App\Http\Controllers\TenderDocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/task-attachments/{taskAttachment}/download', TaskAttachmentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('task-attachments.download');

Route::get('/reference-attachments/{referenceAttachment}/download', ReferenceAttachmentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('reference-attachments.download');

Route::get('/certificates/{certificate}/download', CertificateDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('certificates.download');

Route::get('/tender-documents/{tenderDocumentVersion}/download', TenderDocumentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('tender-documents.download');
