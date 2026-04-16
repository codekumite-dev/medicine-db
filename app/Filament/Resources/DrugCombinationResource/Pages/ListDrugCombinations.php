<?php

namespace App\Filament\Resources\DrugCombinationResource\Pages;

use App\Filament\Resources\DrugCombinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrugCombinations extends ListRecords
{
    protected static string $resource = DrugCombinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
