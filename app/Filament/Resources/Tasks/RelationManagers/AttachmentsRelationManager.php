<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Notifications\TaskAttachmentAddedNotification;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    /**
     * Anyone assigned to the task (owner/creator/reviewer/participant) or a task manager may
     * add attachments — broader than TaskForm::canManageTask() alone, since attachments are
     * evidence the assignees themselves produce, not an assignment decision.
     */
    private function canUpload(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @var Task $task */
        $task = $this->getOwnerRecord();

        return TaskForm::canManageTask() || $task->isLinkedTo($user);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file')
                ->label(__('tasks.fields.attachment_file'))
                ->required()
                ->disk('local')
                ->directory('task-attachments')
                ->preserveFilenames()
                ->preventFilePathTampering(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                TextColumn::make('original_filename')
                    ->label(__('tasks.fields.attachment_file'))
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->searchable(),
                TextColumn::make('size')
                    ->label(__('tasks.fields.attachment_size'))
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('uploadedBy.name')
                    ->label(__('tasks.fields.attachment_uploaded_by')),
                TextColumn::make('created_at')
                    ->label(__('tasks.fields.attachment_uploaded_at'))
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canUpload())
                    ->mutateDataUsing(function (array $data): array {
                        $path = $data['file'];

                        return [
                            'file_path' => $path,
                            'original_filename' => basename((string) $path),
                            'mime_type' => Storage::disk('local')->mimeType($path),
                            'size' => Storage::disk('local')->size($path),
                            'uploaded_by' => auth()->id(),
                        ];
                    })
                    ->after(function (TaskAttachment $record): void {
                        /** @var Task $task */
                        $task = $this->getOwnerRecord();

                        Notification::send(
                            $task->linkedUsers()->reject(fn ($user) => $user->is($record->uploadedBy)),
                            new TaskAttachmentAddedNotification($record),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('tasks.actions.download_attachment'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (TaskAttachment $record): string => route('task-attachments.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->visible(fn (TaskAttachment $record): bool => $record->uploaded_by === auth()->id() || TaskForm::canManageTask())
                    ->before(fn (TaskAttachment $record) => Storage::disk('local')->delete($record->file_path)),
            ]);
    }
}
