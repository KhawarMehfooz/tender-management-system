<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Models\Task;
use App\Models\TaskComment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    /**
     * Anyone assigned to the task (owner/creator/reviewer/participant) or a task manager may
     * add comments — same scope as attachments.
     */
    private function canComment(): bool
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
            Textarea::make('body')
                ->label(__('tasks.fields.comment_body'))
                ->required()
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('author.name')
                    ->label(__('tasks.fields.comment_author')),
                TextColumn::make('body')
                    ->label(__('tasks.fields.comment_body'))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('tasks.fields.comment_created_at'))
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canComment())
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (TaskComment $record): bool => $record->user_id === auth()->id() || TaskForm::canManageTask()),
            ]);
    }
}
