<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportJobResource\Pages;
use App\Filament\Resources\ImportJobResource\RelationManagers;
use App\Models\ImportJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ImportJobResource extends Resource
{
    protected static ?string $model = ImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('filename')
                    ->required(),
                Forms\Components\TextInput::make('storage_path')
                    ->required(),
                Forms\Components\TextInput::make('total_rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('valid_rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('error_rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('imported_rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('column_map')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('settings')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('error_summary')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('created_by')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('filename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('storage_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_rows')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_rows')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_rows')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('imported_rows')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_by')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListImportJobs::route('/'),
            'create' => Pages\CreateImportJob::route('/create'),
            'edit' => Pages\EditImportJob::route('/{record}/edit'),
        ];
    }
}
