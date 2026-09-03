<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\RoleName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Task;
use App\Models\User;
use App\Models\UserAbsence;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TaskForm
{
    /**
     * Only team leads, department heads, and super admins may assign the owner, reviewer, or
     * participants — everyone else sees those fields read-only, mirroring
     * TenderForm::canManageTeam().
     */
    public static function canManageTask(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::TEAM_LEAD->value,
            RoleName::DEPARTMENT_HEAD->value,
            RoleName::SUPER_ADMIN->value,
        ]) ?? false;
    }

    /**
     * Restrict owner/reviewer/participant options to the acting user's own service category
     * plus management-level users (null category), per [[scopes-models]] — same reasoning as
     * TenderForm::scopedUserOptions().
     *
     * @return array<string, string>
     */
    private static function scopedUserOptions(): array
    {
        return static::scopedUserQuery(User::query())
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => static::workloadLabel($user)])
            ->all();
    }

    /**
     * Surfaces a candidate's current capacity before they're piled on with another task, per
     * idea.md's "surfaced before every new assignment" acceptance point.
     */
    private static function workloadLabel(User $user): string
    {
        $openTaskCount = Task::query()
            ->where('owner_id', $user->id)
            ->where('status', '!=', TaskStatus::DONE)
            ->count();

        return __('tasks.fields.owner_workload_suffix', [
            'name' => $user->name,
            'count' => $openTaskCount,
        ]);
    }

    /**
     * Non-blocking warning shown under the due-date field when the selected owner has a known
     * UserAbsence covering that date — surfaces the risk without stopping the save, per idea.md
     * (which says "warns," not "prevents").
     */
    private static function dueDateAbsenceWarning(?string $ownerId, mixed $dueDate): ?string
    {
        if ($ownerId === null || $dueDate === null || $dueDate === '') {
            return null;
        }

        $owner = User::query()->find($ownerId);
        $moment = Carbon::parse($dueDate);

        $absence = $owner?->absences()
            ->get()
            ->first(fn (UserAbsence $absence): bool => $absence->covers($moment));

        return $absence === null ? null : (string) __('tasks.fields.due_date_absence_warning', [
            'type' => $absence->type->getLabel(),
        ]);
    }

    /**
     * @return Builder<User>
     */
    private static function scopedUserQuery(Builder $query): Builder
    {
        $categoryId = auth()->user()?->service_category_id;

        return $query
            ->when(
                $categoryId,
                fn (Builder $query) => $query->where(function (Builder $query) use ($categoryId): void {
                    $query->whereNull('service_category_id')
                        ->orWhere('service_category_id', $categoryId);
                }),
            )
            ->orderBy('name');
    }

    /**
     * @param  bool  $includeTenderField  Omit when embedding this form inside a
     *                                    TasksRelationManager on TenderResource — the parent
     *                                    tender is already implied by relation context there.
     * @param  ?string  $tenderId  The owning tender's id, required to scope the dependencies
     *                             field when $includeTenderField is false (the relation
     *                             manager has no tender_id form field to read via Get()).
     */
    public static function configure(Schema $schema, bool $includeTenderField = true, ?string $tenderId = null): Schema
    {
        return $schema
            ->components([
                Section::make(__('tasks.form.sections.details'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        Select::make('tender_id')
                            ->label(__('tasks.fields.tender_id'))
                            ->prefixIcon(Heroicon::OutlinedDocumentText)
                            ->relationship('tender', 'title')
                            ->required($includeTenderField)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible($includeTenderField)
                            ->dehydrated($includeTenderField),
                        Select::make('priority')
                            ->label(__('tasks.fields.priority'))
                            ->prefixIcon(Heroicon::OutlinedFlag)
                            ->options(TaskPriority::class)
                            ->default(TaskPriority::MEDIUM)
                            ->required(),
                        Select::make('functional_role')
                            ->label(__('tasks.fields.functional_role'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->options(TeamRole::class),
                        TextInput::make('title')
                            ->label(__('tasks.fields.title'))
                            ->prefixIcon(Heroicon::OutlinedPencil)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('description')
                            ->label(__('tasks.fields.description'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('tasks.form.sections.assignment'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        Select::make('owner_id')
                            ->label(__('tasks.fields.owner_id'))
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->options(fn (): array => static::scopedUserOptions())
                            ->default(fn (): ?string => static::canManageTask() ? null : auth()->id())
                            ->required()
                            ->searchable()
                            ->live()
                            ->disabled(fn (): bool => ! static::canManageTask())
                            ->dehydrated(),
                        Select::make('reviewer_id')
                            ->label(__('tasks.fields.reviewer_id'))
                            ->prefixIcon(Heroicon::OutlinedShieldCheck)
                            ->options(fn (): array => static::scopedUserOptions())
                            ->searchable()
                            ->disabled(fn (): bool => ! static::canManageTask())
                            ->dehydrated(),
                        Select::make('participants')
                            ->label(__('tasks.fields.participants'))
                            ->prefixIcon(Heroicon::OutlinedUserGroup)
                            ->relationship('participants', 'name', fn (Builder $query) => static::scopedUserQuery($query))
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => static::workloadLabel($record))
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull()
                            ->disabled(fn (): bool => ! static::canManageTask())
                            ->dehydrated(fn (): bool => static::canManageTask()),
                    ])
                    ->columns(2),

                Section::make(__('tasks.form.sections.dependencies'))
                    ->icon(Heroicon::OutlinedLink)
                    ->schema([
                        Select::make('dependencies')
                            ->label(__('tasks.fields.dependencies'))
                            ->prefixIcon(Heroicon::OutlinedLink)
                            ->relationship(
                                name: 'dependencies',
                                titleAttribute: 'title',
                                modifyQueryUsing: function (Builder $query, ?Task $record, Get $get) use ($includeTenderField, $tenderId): Builder {
                                    $scopeTenderId = $includeTenderField ? $get('tender_id') : $tenderId;

                                    return $query
                                        ->when(
                                            $record,
                                            fn (Builder $query) => $query->whereKeyNot([$record->id, ...$record->transitiveDependentIds()]),
                                        )
                                        ->when(
                                            $scopeTenderId,
                                            fn (Builder $query) => $query->where('tender_id', $scopeTenderId),
                                            fn (Builder $query) => $query->whereRaw('1 = 0'),
                                        );
                                },
                            )
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('tasks.form.sections.dates'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('tasks.fields.start_date'))
                            ->prefixIcon(Heroicon::OutlinedCalendarDays),
                        DatePicker::make('due_date')
                            ->label(__('tasks.fields.due_date'))
                            ->prefixIcon(Heroicon::OutlinedCalendarDays)
                            ->live()
                            ->helperText(fn (Get $get): ?string => static::dueDateAbsenceWarning($get('owner_id'), $get('due_date'))),
                    ])
                    ->columns(2),

                Section::make(__('tasks.form.sections.checklist'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->schema([
                        Repeater::make('checklistItems')
                            ->label(__('tasks.fields.checklist_items'))
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('tasks.fields.checklist_item_description'))
                                    ->required()
                                    ->columnSpan(2),
                                Checkbox::make('is_done')
                                    ->label(__('tasks.fields.checklist_item_done')),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel(__('tasks.actions.add_checklist_item'))
                            ->reorderable('position'),
                    ]),
            ]);
    }
}
