<?php

namespace App\Filament\Resources\TypesConges;

use App\Filament\Resources\TypesConges\Pages\ManageTypesConges;
use App\Filament\Support\AdminOnlyResource;
use App\Models\TypeConge;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TypeCongeResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = TypeConge::class;

    protected static ?string $slug = 'types-conges';

    protected static ?string $modelLabel = 'type de congé';

    protected static ?string $pluralModelLabel = 'Types de congés';

    protected static string|UnitEnum|null $navigationGroup = 'Types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->rule('regex:/^[A-Z0-9_]+$/')->helperText('Majuscules, chiffres et _ uniquement.'),
            TextInput::make('name')->label('Libellé')->required(),
            ColorPicker::make('color')->label('Couleur'),
            TextInput::make('default_days_per_year')->label('Jours par an')->numeric()->minValue(0)->default(0)->required(),
            Toggle::make('requires_justificatif')->label('Justificatif obligatoire'),
            Toggle::make('requires_workflow')->label('Workflow obligatoire')->default(true),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('name')->label('Libellé')->searchable(),
                TextColumn::make('default_days_per_year')->label('Jours/an'),
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
        return ['index' => ManageTypesConges::route('/')];
    }
}
