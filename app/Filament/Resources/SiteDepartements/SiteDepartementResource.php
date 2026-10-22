<?php

namespace App\Filament\Resources\SiteDepartements;

use App\Filament\Resources\SiteDepartements\Pages\ManageSiteDepartements;
use App\Filament\Support\AdminOnlyResource;
use App\Models\Departement;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\Societe;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SiteDepartementResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = SiteDepartement::class;

    protected static ?string $slug = 'site-departements';

    protected static ?string $modelLabel = 'site-département';

    protected static ?string $pluralModelLabel = 'Site-départements';

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('site_id')->label('Site')->required()->options(fn () => SiteAffectation::pluck('name', 'id')),
            Select::make('societe_id')->label('Société')->required()->live()->options(fn () => Societe::pluck('name', 'id')),
            Select::make('departement_id')->label('Département')->required()->options(fn (Get $get) => $get('societe_id') ? Departement::where('societe_id', $get('societe_id'))->pluck('name', 'id') : []),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('societe.name')->label('Société'),
                TextColumn::make('departement.name')->label('Département'),

            ])
            ->recordActions([

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSiteDepartements::route('/')];
    }
}
