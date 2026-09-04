<?php

use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\ReferenceAttachmentDownloadController;
use App\Http\Controllers\ScheduledReportDownloadController;
use App\Http\Controllers\TaskAttachmentDownloadController;
use App\Http\Controllers\TenderDocumentDownloadController;
use App\Http\Controllers\TenderDocumentRequestFileDownloadController;
use App\Http\Controllers\TenderSiteVisitPhotoDownloadController;
use App\Http\Controllers\TenderSubmissionFileDownloadController;
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

Route::get('/tender-site-visit-photos/{tenderSiteVisitPhoto}/download', TenderSiteVisitPhotoDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('tender-site-visit-photos.download');

Route::get('/tender-submission-files/{tenderSubmissionFile}/download', TenderSubmissionFileDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('tender-submission-files.download');

Route::get('/tender-document-request-files/{tenderDocumentRequestFile}/download', TenderDocumentRequestFileDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('tender-document-request-files.download');

Route::get('/scheduled-reports/{scheduledReport}/download', ScheduledReportDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('scheduled-reports.download');
