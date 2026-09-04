<?php

namespace App\Filament\Resources\Tenders\Schemas;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\TeamRole;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\Source;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class TenderForm
{
    /**
     * Only team leads, department heads, and super admins may assign the owner or
     * team members — everyone else sees the Team step read-only.
     */
    public static function canManageTeam(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::TEAM_LEAD->value,
            RoleName::DEPARTMENT_HEAD->value,
            RoleName::SUPER_ADMIN->value,
        ]) ?? false;
    }

    /**
     * Restrict owner/team-member options to the acting user's own service category plus
     * management-level users (null category), per [[scopes-models]]. Deliberately keyed off
     * the *acting user's* category rather than the tender's own service_category_id field:
     * a category-scoped actor's tender is always pinned to their own category anyway (see
     * service_category_id's disabled+forced pattern below), so this avoids a fragile
     * cross-field Get() dependency inside a multi-step wizard while landing on the same
     * result. Management-level actors (null category) see every user.
     *
     * @return array<string, string>
     */
    private static function scopedUserOptions(): array
    {
        $categoryId = auth()->user()?->service_category_id;

        return User::query()
            ->when(
                $categoryId,
                fn (Builder $query) => $query->where(function (Builder $query) use ($categoryId): void {
                    $query->whereNull('service_category_id')
                        ->orWhere('service_category_id', $categoryId);
                }),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    // Steps are skippable so users can jump around and fill fields out of
                    // order; required-field validation still runs in full on create/save.
                    Step::make(__('tenders.form.steps.basic_info'))
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('title')
                                    ->label(__('tenders.fields.title'))
                                    ->prefixIcon(Heroicon::OutlinedDocumentText)
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('procurement_number')
                                    ->label(__('tenders.fields.procurement_number'))
                                    ->prefixIcon(Heroicon::OutlinedHashtag),
                                TextInput::make('contracting_authority')
                                    ->label(__('tenders.fields.contracting_authority'))
                                    ->prefixIcon(Heroicon::OutlinedBuildingLibrary)
                                    ->required(),
                                Select::make('client_id')
                                    ->label(__('tenders.fields.client_id'))
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                    ->relationship('client', 'name', fn (Builder $query) => $query->where('active', true))
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('procurement_office')
                                    ->label(__('tenders.fields.procurement_office'))
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
                                TextInput::make('contact_person')
                                    ->label(__('tenders.fields.contact_person'))
                                    ->prefixIcon(Heroicon::OutlinedUser),
                                TextInput::make('contact_email')
                                    ->label(__('tenders.fields.contact_email'))
                                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                                    ->email(),
                                TextInput::make('contact_phone')
                                    ->label(__('tenders.fields.contact_phone'))
                                    ->prefixIcon(Heroicon::OutlinedPhone)
                                    ->tel(),
                            ]),
                        ]),

                    Step::make(__('tenders.form.steps.location_classification'))
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('service_category_id')
                                    ->label(__('tenders.fields.service_category_id'))
                                    ->prefixIcon(Heroicon::OutlinedTag)
                                    ->relationship('serviceCategory', 'name', fn (Builder $query) => $query->where('active', true))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->default(fn (): ?string => auth()->user()?->service_category_id)
                                    ->disabled(fn (): bool => (bool) auth()->user()?->service_category_id)
                                    ->dehydrated(),
                                Select::make('sector_id')
                                    ->label(__('tenders.fields.sector_id'))
                                    ->prefixIcon(Heroicon::OutlinedBriefcase)
                                    ->relationship('sector', 'name', fn (Builder $query) => $query->where('active', true))
                                    ->default(fn () => Sector::query()->where('name', 'Unknown')->value('id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Select::make('procurement_procedure_id')
                                    ->label(__('tenders.fields.procurement_procedure_id'))
                                    ->prefixIcon(Heroicon::OutlinedScale)
                                    ->relationship('procurementProcedure', 'name', fn (Builder $query) => $query->where('active', true))
                                    ->default(fn () => ProcurementProcedure::query()->where('name', 'Unknown')->value('id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('city')
                                    ->label(__('tenders.fields.city'))
                                    ->prefixIcon(Heroicon::OutlinedMapPin),
                                Select::make('nuts_code_id')
                                    ->label(__('tenders.fields.nuts_code_id'))
                                    ->prefixIcon(Heroicon::OutlinedMap)
                                    ->relationship('nutsCode', 'label', fn (Builder $query) => $query->where('active', true))
                                    ->searchable()
                                    ->preload(),
                                Select::make('cpv_code_id')
                                    ->label(__('tenders.fields.cpv_code_id'))
                                    ->prefixIcon(Heroicon::OutlinedRectangleGroup)
                                    ->relationship('cpvCode', 'label', fn (Builder $query) => $query->where('active', true))
                                    ->searchable()
                                    ->preload(),
                            ]),
                        ]),

                    Step::make(__('tenders.form.steps.dates_deadlines'))
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->schema([
                            Grid::make(2)->schema([
                                DateTimePicker::make('submission_deadline')
                                    ->label(__('tenders.fields.submission_deadline'))
                                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                                    ->required(),
                                DateTimePicker::make('bidder_question_deadline')
                                    ->label(__('tenders.fields.bidder_question_deadline'))
                                    ->prefixIcon(Heroicon::OutlinedQuestionMarkCircle),
                                DateTimePicker::make('site_visit_date')
                                    ->label(__('tenders.fields.site_visit_date'))
                                    ->prefixIcon(Heroicon::OutlinedMapPin),
                                DatePicker::make('publication_date')
                                    ->label(__('tenders.fields.publication_date'))
                                    ->prefixIcon(Heroicon::OutlinedMegaphone),
                                TextInput::make('bid_validity_days')
                                    ->label(__('tenders.fields.bid_validity_days'))
                                    ->prefixIcon(Heroicon::OutlinedClock)
                                    ->numeric()
                                    ->minValue(0),
                            ]),
                        ]),

                    Step::make(__('tenders.form.steps.contract_terms'))
                        ->icon(Heroicon::OutlinedBriefcase)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('estimated_contract_volume')
                                    ->label(__('tenders.fields.estimated_contract_volume'))
                                    ->numeric()
                                    ->prefix('€')
                                    ->disabled(fn (Get $get): bool => (bool) $get('estimated_contract_volume_unknown'))
                                    ->visible(fn (): bool => auth()->user()?->can(Right::SEE_PRICES->value) ?? false),
                                Toggle::make('estimated_contract_volume_unknown')
                                    ->label(__('tenders.fields.estimated_contract_volume_unknown'))
                                    ->inline(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?bool $state): void {
                                        if ($state) {
                                            $set('estimated_contract_volume', null);
                                        }
                                    })
                                    ->visible(fn (): bool => auth()->user()?->can(Right::SEE_PRICES->value) ?? false),
                                TextInput::make('contract_term')
                                    ->label(__('tenders.fields.contract_term'))
                                    ->prefixIcon(Heroicon::OutlinedClock),
                                DatePicker::make('contract_start_date')
                                    ->label(__('tenders.fields.contract_start_date'))
                                    ->prefixIcon(Heroicon::OutlinedCalendarDays),
                                DatePicker::make('contract_end_date')
                                    ->label(__('tenders.fields.contract_end_date'))
                                    ->prefixIcon(Heroicon::OutlinedCalendarDays),
                                Textarea::make('extension_options')
                                    ->label(__('tenders.fields.extension_options'))
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                        ]),

                    Step::make(__('tenders.form.steps.source_notes'))
                        ->icon(Heroicon::OutlinedRss)
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('source_id')
                                    ->label(__('tenders.fields.source_id'))
                                    ->prefixIcon(Heroicon::OutlinedRss)
                                    ->relationship('source', 'name', fn (Builder $query) => $query->where('active', true))
                                    ->default(fn () => Source::query()->where('name', 'Unknown')->value('id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('portal_link')
                                    ->label(__('tenders.fields.portal_link'))
                                    ->prefixIcon(Heroicon::OutlinedLink)
                                    ->url()
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->label(__('tenders.fields.notes'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        ]),

                    Step::make(__('tenders.form.steps.team'))
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->schema([
                            Select::make('owner_id')
                                ->label(__('tenders.fields.owner_id'))
                                ->prefixIcon(Heroicon::OutlinedUser)
                                ->options(fn (): array => self::scopedUserOptions())
                                ->default(fn (): ?string => static::canManageTeam() ? null : (auth()->id() === null ? null : (string) auth()->id()))
                                ->required()
                                ->searchable()
                                ->disabled(fn (): bool => ! static::canManageTeam())
                                ->dehydrated(),
                            Repeater::make('teamMembers')
                                ->label(__('tenders.fields.team_members'))
                                ->relationship()
                                ->schema([
                                    Select::make('user_id')
                                        ->label(__('tenders.fields.team_member_user'))
                                        ->options(fn (): array => self::scopedUserOptions())
                                        ->required()
                                        ->searchable(),
                                    Select::make('functional_role')
                                        ->label(__('tenders.fields.team_member_role'))
                                        ->options(TeamRole::class)
                                        ->required(),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->defaultItems(0)
                                ->addActionLabel(__('tenders.actions.add_team_member'))
                                ->disabled(fn (): bool => ! static::canManageTeam())
                                ->dehydrated(fn (): bool => static::canManageTeam()),
                        ]),
                ])
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }
}
