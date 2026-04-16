<?php

namespace App\Filament\Resources\MedicineResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IdentifiersRelationManager extends RelationManager
{
    protected static string $relationship = 'identifiers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('identifier_type')
                    ->options(['barcode' => 'Barcode', 'gtin' => 'GTIN', 'gs1' => 'GS1', 'internal_sku' => 'Internal SKU', 'regulatory_code' => 'Regulatory Code', 'ndc' => 'NDC'])
                    ->required(),
                Forms\Components\TextInput::make('identifier_value')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('issuing_body'),
                Forms\Components\Toggle::make('is_primary')->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('identifier_value')
            ->columns([
                Tables\Columns\TextColumn::make('identifier_type'),
                Tables\Columns\TextColumn::make('identifier_value'),
                Tables\Columns\TextColumn::make('issuing_body'),
                Tables\Columns\IconColumn::make('is_primary')->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
