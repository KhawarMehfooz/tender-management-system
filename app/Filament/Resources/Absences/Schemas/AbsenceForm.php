<?php

namespace App\Filament\Resources\Absences\Schemas;

use App\Enums\AbsenceType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AbsenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('absences.label'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        Select::make('user_id')
                            ->label(__('absences.fields.user_id'))
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('type')
                            ->label(__('absences.fields.type'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->options(AbsenceType::class)
                            ->required(),
                        Select::make('cover_user_id')
                            ->label(__('absences.fields.cover_user_id'))
                            ->prefixIcon(Heroicon::OutlinedUserGroup)
                            ->options(fn (Get $get): array => User::query()
                                ->when($get('user_id'), fn ($query, $userId) => $query->whereKeyNot($userId))
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
                    ->columns(2),
            ]);
    }
}
