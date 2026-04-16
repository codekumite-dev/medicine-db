<?php

namespace App\Filament\Resources\MedicineResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'aliases';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('alias')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('alias_type')
                    ->options(['brand_name' => 'Brand Name', 'generic_name' => 'Generic Name', 'spelling_variant' => 'Spelling Variant', 'local_name' => 'Local Name', 'alternate_pack' => 'Alternate Pack'])
                    ->required(),
                Forms\Components\TextInput::make('language_code')->default('en'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alias')
            ->columns([
                Tables\Columns\TextColumn::make('alias'),
                Tables\Columns\TextColumn::make('alias_type'),
                Tables\Columns\TextColumn::make('language_code'),
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
