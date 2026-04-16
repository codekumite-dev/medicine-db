<?php

namespace App\Filament\Resources\MedicineResource\Pages;

use App\Filament\Resources\MedicineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListMedicines extends ListRecords
{
    protected static string $resource = MedicineResource::class;

    protected function paginateTableQuery(Builder $query): Paginator
    {
        $perPage = $this->getTableRecordsPerPage();
        $perPage = $perPage === 'all' ? 100 : (int) $perPage;

        return $query->simplePaginate(
            $perPage,
            pageName: $this->getTablePaginationPageName()
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
