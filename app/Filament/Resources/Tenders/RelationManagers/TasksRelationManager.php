<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Filament v4 makes relation managers read-only by default on a resource's ViewRecord page
 * (Panel::hasReadOnlyRelationManagersOnResourceViewPagesByDefault(), true by default) — so
 * on ViewTender this only lists tasks, while on EditTender create/edit are live. That split
 * matches the rest of the app's view-vs-edit surface, so it's left as the framework default
 * rather than overridden.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema, includeTenderField: false, tenderId: (string) $this->getOwnerRecord()->getKey());
    }

    /**
     * Doesn't reuse TasksTable::configure() wholesale: a RelationManager's Create/Edit actions
     * open modals rather than navigating to the standalone resource's pages, so — unlike
     * TaskResource's own table, where CreateTask/EditTask's page-level mutate hooks already
     * apply — the owner/reviewer/creator belt-and-braces enforcement has to be wired directly
     * onto the actions here (see .ai/rules/permissions.md).
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns(TasksTable::columns(includeTenderColumn: false))
            ->filters(TasksTable::filters(includeTenderFilter: false))
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['creator_id'] = auth()->id();

                        if (! TaskForm::canManageTask()) {
                            $data['owner_id'] = auth()->id();
                            $data['reviewer_id'] = null;
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    TasksTable::changeStatusAction(),
                    TasksTable::addCommentAction(),
                    TasksTable::addAttachmentAction(),
                    ViewAction::make(),
                    EditAction::make()
                        ->mutateDataUsing(function (Task $record, array $data): array {
                            if (! TaskForm::canManageTask()) {
                                $data['owner_id'] = $record->owner_id;
                                $data['reviewer_id'] = $record->reviewer_id;
                            }

                            return $data;
                        }),
                ]),
            ]);
    }
}
