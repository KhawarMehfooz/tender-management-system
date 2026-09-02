<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\CommunicationType;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use App\Models\TenderCommunication;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * A structured, chronological log of bidder questions, clarifications, amendments, emails,
 * phone calls, and internal comments — kept separate from the tender document library's
 * COMMUNICATION/SITE_VISIT categories ([[documents]]), which are for attaching actual files
 * related to correspondence, not logging the correspondence itself.
 *
 * Append-only: entries can be edited (to fix a typo or add detail) but never deleted, so the
 * log stays a reliable record of what was actually communicated. Write access follows the
 * same linkedToDocuments()/canManageTeam() pattern as DocumentsRelationManager — owner, team
 * member, or team lead/department head/super admin.
 */
class CommunicationRelationManager extends RelationManager
{
    protected static string $relationship = 'communications';

    private function canManage(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return TenderForm::canManageTeam() || $tender->linkedToDocuments($user);
    }

    private function canEditCommunication(TenderCommunication $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $record->logged_by === $user->id || TenderForm::canManageTeam();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label(__('tender_communications.fields.type'))
                ->prefixIcon(Heroicon::OutlinedTag)
                ->options(CommunicationType::class)
                ->required(),
            TextInput::make('subject')
                ->label(__('tender_communications.fields.subject'))
                ->prefixIcon(Heroicon::OutlinedPencil)
                ->required(),
            Textarea::make('content')
                ->label(__('tender_communications.fields.content'))
                ->required()
                ->columnSpanFull(),
            TextInput::make('contact_person')
                ->label(__('tender_communications.fields.contact_person'))
                ->prefixIcon(Heroicon::OutlinedUser),
            DateTimePicker::make('occurred_at')
                ->label(__('tender_communications.fields.occurred_at'))
                ->prefixIcon(Heroicon::OutlinedClock)
                ->default(now())
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label(__('tender_communications.fields.type'))
                    ->badge(),
                TextColumn::make('subject')
                    ->label(__('tender_communications.fields.subject'))
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->label(__('tender_communications.fields.contact_person'))
                    ->placeholder('—'),
                TextColumn::make('occurred_at')
                    ->label(__('tender_communications.fields.occurred_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('loggedBy.name')
                    ->label(__('tender_communications.fields.logged_by')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('tender_communications.fields.type'))
                    ->options(CommunicationType::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['logged_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (TenderCommunication $record): bool => $this->canEditCommunication($record))
                        ->before(fn (TenderCommunication $record) => abort_unless($this->canEditCommunication($record), 403)),
                ]),
            ]);
    }
}
