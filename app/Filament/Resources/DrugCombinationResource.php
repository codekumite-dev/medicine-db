<?php

namespace App\Filament\Resources;

use App\Enums\EditorialStatusEnum;
use App\Enums\EvidenceLevelEnum;
use App\Enums\SectionKeyEnum;
use App\Filament\Resources\DrugCombinationResource\Pages;
use App\Filament\Resources\DrugCombinationResource\RelationManagers;
use App\Models\DrugCombination;
use App\Models\DrugCombinationSection;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class DrugCombinationResource extends Resource
{
    protected static ?string $model = DrugCombination::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Clinical Content';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('title')->required(),
                                Forms\Components\TextInput::make('slug')->required(),
                                Forms\Components\TextInput::make('canonical_name')->required(),
                                Forms\Components\TextInput::make('short_name'),
                                Forms\Components\Textarea::make('summary')->columnSpanFull(),
                                Forms\Components\TagsInput::make('alternate_names'),
                                Forms\Components\Select::make('evidence_level')->options(EvidenceLevelEnum::class),
                                Forms\Components\Toggle::make('is_featured'),
                            ]),
                        Tabs\Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title'),
                                Forms\Components\Textarea::make('seo_description'),
                                Forms\Components\TextInput::make('canonical_url'),
                                Forms\Components\Textarea::make('schema_markup'),
                            ]),
                        Tabs\Tab::make('Review Workflow')
                            ->schema([
                                Forms\Components\Select::make('editorial_status')
                                    ->options(EditorialStatusEnum::class)->default(EditorialStatusEnum::Draft),
                                Forms\Components\DateTimePicker::make('reviewed_at'),
                                Forms\Components\Select::make('reviewed_by')
                                    ->options(User::pluck('name', 'id'))
                                    ->searchable(),
                                Forms\Components\DateTimePicker::make('published_at'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('editorial_status')->badge()->searchable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('editorial_status')->options(EditorialStatusEnum::class),
                Tables\Filters\TernaryFilter::make('is_featured'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('scaffold_sections')
                    ->label('Add All Standard Sections')
                    ->action(function (DrugCombination $record) {
                        $order = 0;
                        foreach (SectionKeyEnum::cases() as $key) {
                            DrugCombinationSection::firstOrCreate(
                                ['drug_combination_id' => $record->id, 'section_key' => $key->value],
                                [
                                    'section_title' => $key->label(),
                                    'content' => '',
                                    'display_order' => $order++,
                                    'is_visible' => true,
                                ]
                            );
                        }
                    }),
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
            RelationManagers\SectionsRelationManager::class,
            RelationManagers\FaqsRelationManager::class,
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrugCombinations::route('/'),
            'create' => Pages\CreateDrugCombination::route('/create'),
            'edit' => Pages\EditDrugCombination::route('/{record}/edit'),
        ];
    }
}
