<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\AbsenceType;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Full CRUD, unlike SkillsRelationManager's attach-only shape — an absence has no separate
 * lookup resource to create it on, it only ever exists as a record on the absent user.
 */
class AbsencesRelationManager extends RelationManager
{
    protected static string $relationship = 'absences';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('absences.fields.type'))
                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                    ->options(AbsenceType::class)
                    ->required(),
                Select::make('cover_user_id')
                    ->label(__('absences.fields.cover_user_id'))
                    ->prefixIcon(Heroicon::OutlinedUserGroup)
                    ->options(fn (): array => User::query()
                        ->whereKeyNot($this->getOwnerRecord()->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                DatePicker::make('starts_at')
                    ->label(__('absences.fields.starts_at'))
                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                    ->required(),
                DatePicker::make('ends_at')
                    ->label(__('absences.fields.ends_at'))
                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                    ->required()
                    ->afterOrEqual('starts_at')
                    ->validationMessages([
                        'after_or_equal' => __('absences.validation.ends_before_starts'),
                    ]),
                Textarea::make('notes')
                    ->label(__('absences.fields.notes'))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->label(__('absences.fields.type'))
                    ->badge(),
                TextColumn::make('starts_at')
                    ->label(__('absences.fields.starts_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('absences.fields.ends_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('coverUser.name')
                    ->label(__('absences.fields.cover_user_id'))
                    ->placeholder('—'),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('absences.fields.type'))
                    ->options(AbsenceType::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
