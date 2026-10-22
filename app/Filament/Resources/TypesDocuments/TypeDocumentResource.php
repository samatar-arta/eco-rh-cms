<?php

namespace App\Filament\Resources\TypesDocuments;

use App\Filament\Resources\TypesDocuments\Pages\ManageTypesDocuments;
use App\Filament\Support\AdminOnlyResource;
use App\Models\TypeDocument;
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

class TypeDocumentResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = TypeDocument::class;

    protected static ?string $slug = 'types-documents';

    protected static ?string $modelLabel = 'type de document';

    protected static ?string $pluralModelLabel = 'Types de documents';

    protected static string|UnitEnum|null $navigationGroup = 'Types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->rule('regex:/^[A-Z0-9_]+$/')->helperText('Majuscules, chiffres et _ uniquement.'),
            TextInput::make('name')->label('Libellé')->required(),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            Toggle::make('requires_model')->label('Modèle obligatoire')->default(true),
            Toggle::make('requires_workflow')->label('Workflow obligatoire')->default(true),
            TextInput::make('delai_renouvellement_mois')->label('Délai minimal entre deux productions (mois)')->numeric()->minValue(1)->helperText('Vide = aucune règle de renouvellement.'),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('name')->label('Libellé')->searchable(),
                TextColumn::make('delai_renouvellement_mois')->label('Délai (mois)')->placeholder('—'),
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
        return ['index' => ManageTypesDocuments::route('/')];
    }
}
