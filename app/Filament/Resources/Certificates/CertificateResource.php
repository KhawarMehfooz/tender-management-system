<?php

namespace App\Filament\Resources\Certificates;

use App\Enums\Right;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\EditCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Resources\Certificates\Pages\ViewCertificate;
use App\Filament\Resources\Certificates\Schemas\CertificateForm;
use App\Filament\Resources\Certificates\Schemas\CertificateInfolist;
use App\Filament\Resources\Certificates\Tables\CertificatesTable;
use App\Models\Certificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('certificates.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('certificates.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.reference_library');
    }

    /**
     * Certificates are a hard disqualification risk if expired (idea.md's explicit framing),
     * so unlike References/ConceptBlocks (open to any panel user, matching the master-data
     * convention) this whole resource — not just its write actions — is gated behind
     * Right::MANAGE_CERTIFICATES. No separate "informational tab" surface exists for a
     * top-level resource the way BidDecisionRelationManager could leave viewing open, so
     * canViewAny() gates the list/nav entirely.
     */
    private static function canManage(): bool
    {
        return auth()->user()?->can(Right::MANAGE_CERTIFICATES->value) ?? false;
    }

    public static function canViewAny(): bool
    {
        return self::canManage();
    }

    public static function canCreate(): bool
    {
        return self::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canManage();
    }

    public static function canDeleteAny(): bool
    {
        return self::canManage();
    }

    public static function form(Schema $schema): Schema
    {
        return CertificateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CertificateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CertificatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificates::route('/'),
            'create' => CreateCertificate::route('/create'),
            'view' => ViewCertificate::route('/{record}'),
            'edit' => EditCertificate::route('/{record}/edit'),
        ];
    }
}
