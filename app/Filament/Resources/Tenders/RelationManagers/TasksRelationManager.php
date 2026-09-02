<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;

/**
 * Create/edit are live on both ViewTender and EditTender — AdminPanelProvider disables
 * Filament's read-only-relation-managers-on-View default panel-wide (see
 * .ai/rules/filament-resources-tenders.md), since TaskResource itself has no canCreate()/
 * canEdit() restriction (any authenticated category-scoped user can already create/edit tasks
 * via the standalone resource), so gating this relation manager's actions to Edit-only added
 * friction without adding any real protection.
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
                    ->modalWidth(Width::SixExtraLarge)
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
                    ViewAction::make()
                        ->modalWidth(Width::SixExtraLarge)
                        ->schema(fn (): array => TaskInfolist::components()),
                    EditAction::make()
                        ->modalWidth(Width::SixExtraLarge)
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
