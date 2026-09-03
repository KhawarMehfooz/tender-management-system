<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\SkillProficiency;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('users.infolist.identity_heading'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('users.fields.name')),
                        TextEntry::make('email')
                            ->label(__('users.fields.email')),
                        TextEntry::make('serviceCategory.name')
                            ->label(__('users.fields.service_category_id'))
                            ->placeholder(__('users.infolist.no_service_category')),
                    ])
                    ->columns(3),

                Section::make(__('users.infolist.skills_heading'))
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->schema([
                        RepeatableEntry::make('skills')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('skills.fields.name')),
                                TextEntry::make('category')
                                    ->label(__('skills.fields.category'))
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('pivot.proficiency_level')
                                    ->label(__('skills.fields.proficiency_level'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => $state === null
                                        ? '-'
                                        : SkillProficiency::from($state)->getLabel())
                                    ->color(fn (?string $state): string => $state === null
                                        ? 'gray'
                                        : SkillProficiency::from($state)->color()),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn (User $record): bool => $record->skills->isNotEmpty()),

                Section::make(__('users.infolist.profile_heading'))
                    ->icon(Heroicon::OutlinedChartBar)
                    ->schema([
                        TextEntry::make('tenders_handled')
                            ->label(__('users.infolist.tenders_handled'))
                            ->state(fn (User $record): string => self::formatCounts($record->tendersHandledByStatus())),
                        TextEntry::make('on_time_completion_rate')
                            ->label(__('users.infolist.on_time_completion_rate'))
                            ->state(fn (User $record): ?float => $record->onTimeTaskCompletionRate())
                            ->formatStateUsing(fn (?float $state): string => $state === null
                                ? __('users.infolist.no_completed_tasks')
                                : number_format($state * 100, 0).'%'),
                        TextEntry::make('correction_loop_count')
                            ->label(__('users.infolist.correction_loop_count'))
                            ->state(fn (User $record): int => $record->correctionLoopCount()),
                        TextEntry::make('average_handling_time')
                            ->label(__('users.infolist.average_handling_time'))
                            ->state(fn (User $record): ?float => $record->averageTaskHandlingTimeDays())
                            ->formatStateUsing(fn (?float $state): string => $state === null
                                ? __('users.infolist.no_completed_tasks')
                                : __('users.infolist.days', ['count' => number_format($state, 1)])),
                        TextEntry::make('sector_experience')
                            ->label(__('users.infolist.sector_experience'))
                            ->state(fn (User $record): string => self::formatCounts($record->sectorExperience()))
                            ->columnSpanFull(),
                        TextEntry::make('performance_score')
                            ->label(__('users.infolist.performance_score'))
                            ->state(fn (User $record): float => $record->performanceScore())
                            ->formatStateUsing(fn (float $state): string => number_format($state, 1)),
                        TextEntry::make('win_rate')
                            ->label(__('users.infolist.win_rate'))
                            ->state(fn (User $record): ?float => $record->winRate())
                            ->formatStateUsing(fn (?float $state): string => $state === null
                                ? __('users.infolist.no_data')
                                : number_format($state * 100, 0).'%'),
                    ])
                    ->columns(2),

                Section::make(__('users.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('users.fields.created_at'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('users.fields.updated_at'))
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function formatCounts(array $counts): string
    {
        if ($counts === []) {
            return __('users.infolist.no_data');
        }

        return collect($counts)
            ->map(fn (int $count, string $label): string => "{$label}: {$count}")
            ->implode(', ');
    }
}
