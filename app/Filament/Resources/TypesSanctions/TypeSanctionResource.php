<?php

namespace App\Filament\Resources\TypesSanctions;

use App\Filament\Resources\TypesSanctions\Pages\ManageTypesSanctions;
use App\Filament\Support\AdminOnlyResource;
use App\Models\TypeSanction;
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

class TypeSanctionResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = TypeSanction::class;

    protected static ?string $slug = 'types-sanctions';

    protected static ?string $modelLabel = 'type de sanction';

    protected static ?string $pluralModelLabel = 'Types de sanctions';

    protected static string|UnitEnum|null $navigationGroup = 'Types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->rule('regex:/^[A-Z0-9_]+$/')->helperText('Majuscules, chiffres et _ uniquement.'),
            TextInput::make('name')->label('Libellé')->required(),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            Toggle::make('requires_conseil_discipline')->label('Conseil de discipline requis'),
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
        return ['index' => ManageTypesSanctions::route('/')];
    }
}
