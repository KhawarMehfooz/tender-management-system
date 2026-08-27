<?php

use App\Http\Controllers\TaskAttachmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/task-attachments/{taskAttachment}/download', TaskAttachmentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->name('task-attachments.download');
