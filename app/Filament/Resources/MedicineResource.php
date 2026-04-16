<?php

namespace App\Filament\Resources;

use App\Enums\ApprovalStatusEnum;
use App\Enums\DosageFormEnum;
use App\Filament\Resources\MedicineResource\Pages;
use App\Filament\Resources\MedicineResource\RelationManagers;
use App\Models\AuditLog;
use App\Models\Medicine;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class MedicineResource extends Resource
{
    protected static ?string $model = Medicine::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Basic Info')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\Textarea::make('short_composition')
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('description')
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('warnings')
                                    ->columnSpanFull(),
                            ]),
                        Tabs\Tab::make('Classification')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options(DosageFormEnum::class),
                                Forms\Components\Select::make('dosage_form')
                                    ->options(DosageFormEnum::class),
                                Forms\Components\TextInput::make('strength'),
                                Forms\Components\Select::make('route_of_administration')
                                    ->options(['oral' => 'Oral', 'topical' => 'Topical', 'iv' => 'IV']),
                                Forms\Components\Select::make('schedule')
                                    ->options([
                                        'OTC' => 'OTC',
                                        'Schedule H' => 'Schedule H',
                                        'Schedule H1' => 'Schedule H1',
                                        'Schedule X' => 'Schedule X',
                                    ]),
                                Forms\Components\Toggle::make('rx_required'),
                                Forms\Components\TextInput::make('rx_required_header'),
                                Forms\Components\TextInput::make('atc_code'),
                            ]),
                        Tabs\Tab::make('Manufacturer & Pricing')
                            ->schema([
                                Forms\Components\Select::make('manufacturer_id')
                                    ->relationship('manufacturer', 'name')
                                    ->searchable(),
                                Forms\Components\TextInput::make('price')->numeric(),
                                Forms\Components\TextInput::make('mrp')->numeric(),
                                Forms\Components\Select::make('currency')->options(['INR' => 'INR', 'USD' => 'USD'])->default('INR'),
                            ]),
                        Tabs\Tab::make('Packaging')
                            ->schema([
                                Forms\Components\TextInput::make('pack_size_label'),
                                Forms\Components\TextInput::make('quantity')->numeric(),
                                Forms\Components\Select::make('quantity_unit')
                                    ->options(['tablets' => 'tablets', 'capsules' => 'capsules', 'ml' => 'ml', 'mg' => 'mg', 'units' => 'units']),
                            ]),
                        Tabs\Tab::make('Identifiers & Regulatory')
                            ->schema([
                                Forms\Components\TextInput::make('barcode'),
                                Forms\Components\TextInput::make('gs1_gtin'),
                                Forms\Components\TextInput::make('hsn_code'),
                                Forms\Components\TextInput::make('ndc_code'),
                                Forms\Components\TextInput::make('storage_conditions'),
                                Forms\Components\TextInput::make('shelf_life'),
                                Forms\Components\Select::make('country_of_origin')->options(['IN' => 'India', 'US' => 'USA', 'UK' => 'UK'])->default('IN'),
                            ]),
                        Tabs\Tab::make('Publishing')
                            ->schema([
                                Forms\Components\Select::make('approval_status')
                                    ->options(ApprovalStatusEnum::class)
                                    ->default(ApprovalStatusEnum::Draft),
                                Forms\Components\Toggle::make('is_discontinued'),
                                Forms\Components\DateTimePicker::make('published_at'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('manufacturer.name')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('pack_size_label')->searchable(),
                Tables\Columns\TextColumn::make('rx_required_header')->badge()->searchable(),
                Tables\Columns\TextColumn::make('price')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('approval_status')->badge()->searchable(),
                Tables\Columns\IconColumn::make('is_discontinued')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')->relationship('manufacturer', 'name'),
                Tables\Filters\SelectFilter::make('type')->options(DosageFormEnum::class),
                Tables\Filters\SelectFilter::make('approval_status')->options(ApprovalStatusEnum::class),
                Tables\Filters\TernaryFilter::make('rx_required'),
                Tables\Filters\TernaryFilter::make('is_discontinued'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('publish')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['super-admin', 'admin', 'clinical-editor']))
                    ->action(function (Medicine $record) {
                        $record->update([
                            'approval_status' => ApprovalStatusEnum::Published,
                            'published_at' => now(),
                        ]);
                        AuditLog::record('published', $record);
                    }),
                Action::make('archive')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['super-admin', 'admin']))
                    ->requiresConfirmation()
                    ->action(function (Medicine $record) {
                        $record->update(['approval_status' => ApprovalStatusEnum::Archived]);
                        AuditLog::record('archived', $record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish_selected')
                        ->label('Publish selected')
                        ->action(fn (Collection $records) => $records->each->update(['approval_status' => ApprovalStatusEnum::Published, 'published_at' => now()])),
                    Tables\Actions\BulkAction::make('mark_discontinued')
                        ->label('Mark discontinued')
                        ->action(fn (Collection $records) => $records->each->update(['is_discontinued' => true])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AliasesRelationManager::class,
            RelationManagers\IdentifiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedicines::route('/'),
            'create' => Pages\CreateMedicine::route('/create'),
            'edit' => Pages\EditMedicine::route('/{record}/edit'),
        ];
    }
}
