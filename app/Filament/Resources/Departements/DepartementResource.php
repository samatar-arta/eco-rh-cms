<?php

namespace App\Filament\Resources\Departements;

use App\Filament\Resources\Departements\Pages\ManageDepartements;
use App\Filament\Support\AdminOnlyResource;
use App\Models\Departement;
use App\Models\Societe;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DepartementResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = Departement::class;

    protected static ?string $slug = 'departements';

    protected static ?string $modelLabel = 'département';

    protected static ?string $pluralModelLabel = 'Départements';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('abrege')->label('Abrégé'),
            Select::make('societe_id')->label('Société')->required()->options(fn () => Societe::pluck('name', 'id')),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('abrege')->label('Abrégé'),
                TextColumn::make('societe.name')->label('Société'),
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
        return ['index' => ManageDepartements::route('/')];
    }
}
