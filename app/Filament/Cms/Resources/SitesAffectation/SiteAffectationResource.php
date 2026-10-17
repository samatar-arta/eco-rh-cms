<?php

namespace App\Filament\Cms\Resources\SitesAffectation;

use App\Filament\Cms\Resources\SitesAffectation\Pages\ManageSitesAffectation;
use App\Models\SiteAffectation;
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

class SiteAffectationResource extends Resource
{
    protected static ?string $model = SiteAffectation::class;

    protected static ?string $slug = 'sites-affectation';

    protected static ?string $modelLabel = 'site d’affectation';

    protected static ?string $pluralModelLabel = 'Sites d’affectation';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            Textarea::make('description')->columnSpanFull(),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
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
        return ['index' => ManageSitesAffectation::route('/')];
    }
}
