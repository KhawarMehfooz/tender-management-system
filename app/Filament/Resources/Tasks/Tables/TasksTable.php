<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class TasksTable
{
    private static function canCommentOrAttach(Task $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return TaskForm::canManageTask() || $record->isLinkedTo($user);
    }

    /**
     * @return array<int, TextColumn|IconColumn>
     */
    public static function columns(bool $includeTenderColumn = true): array
    {
        return [
            TextColumn::make('title')
                ->label(__('tasks.fields.title'))
                ->searchable()
                ->limit(40),
            TextColumn::make('tender.title')
                ->label(__('tasks.fields.tender_id'))
                ->searchable()
                ->limit(40)
                ->visible($includeTenderColumn),
            TextColumn::make('owner.name')
                ->label(__('tasks.fields.owner_id')),
            TextColumn::make('priority')
                ->label(__('tasks.fields.priority'))
                ->badge(),
            TextColumn::make('status')
                ->label(__('tasks.fields.status'))
                ->badge(),
            TextColumn::make('due_date')
                ->label(__('tasks.fields.due_date'))
                ->date()
                ->sortable(),
            IconColumn::make('is_overdue')
                ->label(__('tasks.fields.is_overdue'))
                ->state(fn (Task $record): bool => $record->isOverdue())
                ->boolean()
                ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),
            TextColumn::make('created_at')
                ->label(__('tasks.fields.created_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->label(__('tasks.fields.updated_at'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(bool $includeTenderFilter = true): array
    {
        return [
            SelectFilter::make('tender_id')
                ->label(__('tasks.fields.tender_id'))
                ->relationship('tender', 'title')
                ->visible($includeTenderFilter),
            SelectFilter::make('status')
                ->label(__('tasks.fields.status'))
                ->options(TaskStatus::class),
            SelectFilter::make('priority')
                ->label(__('tasks.fields.priority'))
                ->options(TaskPriority::class),
        ];
    }

    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label(__('tasks.actions.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Task $record): bool => $record->status->allowedTransitions() !== [])
            ->modalWidth(Width::Medium)
            ->schema(fn (Task $record) => [
                Select::make('status')
                    ->label(__('tasks.fields.status'))
                    ->prefixIcon(Heroicon::OutlinedFlag)
                    ->options(fn () => collect($record->status->allowedTransitions())
                        ->reject(fn (TaskStatus $status) => $status === TaskStatus::DONE && ! $record->dependenciesComplete())
                        ->mapWithKeys(fn (TaskStatus $status) => [$status->value => $status->getLabel()]))
                    ->required(),
                Textarea::make('reason')
                    ->label(__('tasks.fields.status_change_reason'))
                    ->rows(2),
            ])
            ->action(function (Task $record, array $data): void {
                $record->changeStatusTo(TaskStatus::from($data['status']), auth()->user(), $data['reason'] ?? null);
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title(__('tasks.actions.change_status_success')),
            );
    }

    public static function addCommentAction(): Action
    {
        return Action::make('addComment')
            ->label(__('tasks.actions.add_comment'))
            ->icon(Heroicon::OutlinedChatBubbleLeft)
            ->color('gray')
            ->visible(fn (Task $record): bool => self::canCommentOrAttach($record))
            ->modalWidth(Width::Medium)
            ->schema([
                Textarea::make('body')
                    ->label(__('tasks.fields.comment_body'))
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->action(function (Task $record, array $data): void {
                if (! self::canCommentOrAttach($record)) {
                    return;
                }

                $record->comments()->create([
                    'user_id' => auth()->id(),
                    'body' => $data['body'],
                ]);
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title(__('tasks.actions.add_comment_success')),
            );
    }

    public static function addAttachmentAction(): Action
    {
        return Action::make('addAttachment')
            ->label(__('tasks.actions.add_attachment'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (Task $record): bool => self::canCommentOrAttach($record))
            ->modalWidth(Width::Medium)
            ->schema([
                FileUpload::make('file')
                    ->label(__('tasks.fields.attachment_file'))
                    ->required()
                    ->disk('local')
                    ->directory('task-attachments')
                    ->preserveFilenames()
                    ->preventFilePathTampering(),
            ])
            ->action(function (Task $record, array $data): void {
                if (! self::canCommentOrAttach($record)) {
                    return;
                }

                $path = $data['file'];

                $record->attachments()->create([
                    'file_path' => $path,
                    'original_filename' => basename((string) $path),
                    'mime_type' => Storage::disk('local')->mimeType($path),
                    'size' => Storage::disk('local')->size($path),
                    'uploaded_by' => auth()->id(),
                ]);
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title(__('tasks.actions.add_attachment_success')),
            );
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions([
                ActionGroup::make([
                    static::changeStatusAction(),
                    static::addCommentAction(),
                    static::addAttachmentAction(),
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ]);
    }
}
