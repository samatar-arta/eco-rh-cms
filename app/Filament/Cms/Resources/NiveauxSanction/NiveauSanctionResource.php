<?php

namespace App\Filament\Cms\Resources\NiveauxSanction;

use App\Filament\Cms\Resources\NiveauxSanction\Pages\ManageNiveauxSanction;
use App\Models\NiveauSanction;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class NiveauSanctionResource extends Resource
{
    protected static ?string $model = NiveauSanction::class;

    protected static ?string $slug = 'niveaux-sanction';

    protected static ?string $modelLabel = 'niveau de sanction';

    protected static ?string $pluralModelLabel = 'Niveaux de sanction';

    protected static string|UnitEnum|null $navigationGroup = 'Types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->rule('regex:/^[A-Z0-9_]+$/')->helperText('Majuscules, chiffres et _ uniquement.'),
            TextInput::make('name')->label('Libellé')->required(),
            TextInput::make('ordre')->label('Ordre')->numeric()->default(0)->required(),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('name')->label('Libellé')->searchable(),
                TextColumn::make('ordre')->label('Ordre')->sortable(),
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
        return ['index' => ManageNiveauxSanction::route('/')];
    }
}
