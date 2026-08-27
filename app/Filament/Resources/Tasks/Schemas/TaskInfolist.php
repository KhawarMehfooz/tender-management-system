<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Models\Task;
use App\Models\TaskAttachment;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class TaskInfolist
{
    /**
     * Shared by the Comments and Attachments sections — both are visible only to the task's
     * linked users (owner/creator/reviewer/participant) or a task manager.
     */
    private static function canSeeLinkedContent(Task $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return TaskForm::canManageTask() || $record->isLinkedTo($user);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    /**
     * Extracted from configure() so a ViewAction outside the standalone TaskResource pages
     * (e.g. TasksRelationManager on Tender) can pass this straight to ->schema(), which takes
     * a component array rather than a Schema — see [[relation-managers]].
     *
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('tasks.infolist.overview_heading'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    TextEntry::make('tender.title')
                        ->label(__('tasks.fields.tender_id')),
                    TextEntry::make('status')
                        ->label(__('tasks.fields.status'))
                        ->badge(),
                    TextEntry::make('priority')
                        ->label(__('tasks.fields.priority'))
                        ->badge(),
                    TextEntry::make('due_date')
                        ->label(__('tasks.fields.due_date'))
                        ->date(),
                    TextEntry::make('title')
                        ->label(__('tasks.fields.title'))
                        ->columnSpanFull(),
                    TextEntry::make('description')
                        ->label(__('tasks.fields.description'))
                        ->columnSpanFull()
                        ->visible(fn (Task $record): bool => filled($record->description)),
                    IconEntry::make('is_overdue')
                        ->label(__('tasks.fields.is_overdue'))
                        ->state(fn (Task $record): bool => $record->isOverdue())
                        ->boolean()
                        ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),
                    TextEntry::make('completion_date')
                        ->label(__('tasks.fields.completion_date'))
                        ->dateTime()
                        ->visible(fn (Task $record): bool => filled($record->completion_date)),
                ])
                ->columns(2),

            Section::make(__('tasks.infolist.assignment_heading'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    TextEntry::make('owner.name')
                        ->label(__('tasks.fields.owner_id')),
                    TextEntry::make('reviewer.name')
                        ->label(__('tasks.fields.reviewer_id'))
                        ->visible(fn (Task $record): bool => filled($record->reviewer_id)),
                    TextEntry::make('creator.name')
                        ->label(__('tasks.fields.creator_id')),
                    TextEntry::make('participants.name')
                        ->label(__('tasks.fields.participants'))
                        ->listWithLineBreaks()
                        ->visible(fn (Task $record): bool => $record->participants()->exists()),
                ])
                ->columns(2),

            Section::make(__('tasks.infolist.dependencies_heading'))
                ->icon(Heroicon::OutlinedLink)
                ->schema([
                    TextEntry::make('dependencies.title')
                        ->label(__('tasks.fields.dependencies'))
                        ->listWithLineBreaks(),
                ])
                ->visible(fn (Task $record): bool => $record->dependencies()->exists()),

            Section::make(__('tasks.infolist.checklist_heading'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->schema([
                    RepeatableEntry::make('checklistItems')
                        ->hiddenLabel()
                        ->schema([
                            IconEntry::make('is_done')
                                ->label(__('tasks.fields.checklist_item_done'))
                                ->boolean(),
                            TextEntry::make('description')
                                ->label(__('tasks.fields.checklist_item_description')),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->visible(fn (Task $record): bool => $record->checklistItems()->exists()),

            Section::make(__('tasks.infolist.status_history_heading'))
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    ViewEntry::make('statusChanges')
                        ->hiddenLabel()
                        ->view('filament.infolists.task-status-timeline'),
                ])
                ->visible(fn (Task $record): bool => $record->statusChanges()->exists()),

            Section::make(__('tasks.infolist.attachments_heading'))
                ->icon(Heroicon::OutlinedPaperClip)
                ->schema([
                    RepeatableEntry::make('attachments')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make(__('tasks.actions.download_attachment'))->hiddenHeaderLabel(),
                            TableColumn::make(__('tasks.fields.attachment_size')),
                            TableColumn::make(__('tasks.fields.attachment_uploaded_by')),
                            TableColumn::make(__('tasks.fields.attachment_uploaded_at')),
                        ])
                        ->schema([
                            TextEntry::make('original_filename')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->url(fn (TaskAttachment $record): string => $record->downloadUrl())
                                ->openUrlInNewTab(),
                            TextEntry::make('size')
                                ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                            TextEntry::make('uploadedBy.name'),
                            TextEntry::make('created_at')
                                ->dateTime(),
                        ])
                        ->columnSpanFull(),
                ])
                ->visible(fn (Task $record): bool => $record->attachments()->exists() && static::canSeeLinkedContent($record)),

            Section::make(__('tasks.infolist.comments_heading'))
                ->icon(Heroicon::OutlinedChatBubbleLeft)
                ->schema([
                    ViewEntry::make('comments')
                        ->hiddenLabel()
                        ->view('filament.infolists.task-comments-timeline'),
                ])
                ->visible(fn (Task $record): bool => $record->comments()->exists() && static::canSeeLinkedContent($record)),

            Section::make(__('tasks.infolist.meta_heading'))
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    TextEntry::make('created_at')
                        ->label(__('tasks.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->label(__('tasks.fields.updated_at'))
                        ->dateTime(),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ];
    }
}
