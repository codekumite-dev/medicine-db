<?php

namespace App\Filament\Resources\DrugCombinationResource\Pages;

use App\Filament\Resources\DrugCombinationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDrugCombination extends EditRecord
{
    protected static string $resource = DrugCombinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
