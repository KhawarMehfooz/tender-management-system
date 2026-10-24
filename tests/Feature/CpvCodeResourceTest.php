<?php

use App\Filament\Resources\CpvCodes\Pages\CreateCpvCode;
use App\Models\User;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;

it('shows check and cross icons for the CPV code active toggle', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateCpvCode::class)
        ->assertFormFieldExists('active', function (Toggle $field): bool {
            return $field->getOnIcon() === Heroicon::OutlinedCheck
                && $field->getOffIcon() === Heroicon::OutlinedXMark;
        });
});
