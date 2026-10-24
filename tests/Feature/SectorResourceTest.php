<?php

use App\Filament\Resources\Sectors\Pages\CreateSector;
use App\Models\User;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;

it('shows check and cross icons for the sector active toggle', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateSector::class)
        ->assertFormFieldExists('active', function (Toggle $field): bool {
            return $field->getOnIcon() === Heroicon::OutlinedCheck
                && $field->getOffIcon() === Heroicon::OutlinedXMark;
        });
});
