<?php

namespace App\Filament\Resources\DrugCombinationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ingredient_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('medicine_id')
                    ->relationship('medicine', 'name')
                    ->searchable(),
                Forms\Components\TextInput::make('strength'),
                Forms\Components\Select::make('role')
                    ->options(['primary' => 'Primary', 'adjuvant' => 'Adjuvant', 'inactive' => 'Inactive']),
                Forms\Components\TextInput::make('display_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ingredient_name')
            ->columns([
                Tables\Columns\TextColumn::make('ingredient_name'),
                Tables\Columns\TextColumn::make('medicine.name'),
                Tables\Columns\TextColumn::make('strength'),
                Tables\Columns\TextColumn::make('role'),
                Tables\Columns\TextColumn::make('display_order')->sortable(),
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
