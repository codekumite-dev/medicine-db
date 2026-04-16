<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiClientResource\Pages;
use App\Models\ApiClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ApiClientResource extends Resource
{
    protected static ?string $model = ApiClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'System';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('owner_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('environment')
                    ->options([
                        'production' => 'Production',
                        'sandbox' => 'Sandbox',
                    ])
                    ->default('production')
                    ->required(),
                Forms\Components\CheckboxList::make('abilities')
                    ->options([
                        'medicines:read' => 'Read Medicines',
                        'medicines:search' => 'Search Medicines',
                        'manufacturers:read' => 'Read Manufacturers',
                        'combinations:read' => 'Read Combinations',
                        'combinations:search' => 'Search Combinations',
                        'content:export' => 'Export Content',
                        'admin:sync' => 'Admin Sync',
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('rate_limit_per_minute')
                    ->numeric()
                    ->default(120),
                Forms\Components\TagsInput::make('allowed_ips'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('owner_email')->searchable(),
                Tables\Columns\TextColumn::make('environment')->badge()->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('last_used_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('rate_limit_per_minute')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Action::make('issue_token')
                    ->label('Issue New API Key')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->action(function (ApiClient $record) {
                        $tokenName = $record->name . '_' . now()->format('Ymd');
                        $token = $record->createToken(
                            $tokenName,
                            $record->abilities ?? ['medicines:read']
                        );

                        Notification::make()
                            ->title('API Key Issued')
                            ->body('Token: ' . $token->plainTextToken . ' — Copy this now, it will not be shown again.')
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                Action::make('revoke_tokens')
                    ->label('Revoke All Tokens')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ApiClient $record) {
                        $record->tokens()->delete();
                        Notification::make()
                            ->title('All tokens revoked')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListApiClients::route('/'),
            'create' => Pages\CreateApiClient::route('/create'),
            'edit' => Pages\EditApiClient::route('/{record}/edit'),
        ];
    }
}
