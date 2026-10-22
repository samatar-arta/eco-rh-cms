<?php

namespace App\Filament\Resources\Societes;

use App\Filament\Resources\Societes\Pages\ManageSocietes;
use App\Filament\Support\AdminOnlyResource;
use App\Models\Societe;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SocieteResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = Societe::class;

    protected static ?string $slug = 'societes';

    protected static ?string $modelLabel = 'société';

    protected static ?string $pluralModelLabel = 'Sociétés';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('code')->label('Code')->unique(ignoreRecord: true),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('code')->label('Code'),
                IconColumn::make('is_active')->label('Actif')->boolean(),

            ])
            ->recordActions([

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSocietes::route('/')];
    }
}
