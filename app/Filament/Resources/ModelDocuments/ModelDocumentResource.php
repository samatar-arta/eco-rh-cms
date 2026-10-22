<?php

namespace App\Filament\Resources\ModelDocuments;

use App\Enums\RhModule;
use App\Filament\Resources\ModelDocuments\Pages\ManageModelDocuments;
use App\Filament\Support\AdminOnlyResource;
use App\Models\ModelDocument;
use App\Models\TypeDocument;
use App\Services\TemplateRendererService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ModelDocumentResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = ModelDocument::class;

    protected static ?string $slug = 'model-documents';

    protected static ?string $modelLabel = 'modèle de documents';

    protected static ?string $pluralModelLabel = 'Modèles de documents';

    protected static string|UnitEnum|null $navigationGroup = 'Modèles HTML';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type_document_id')->label('Type')->required()->searchable()
                ->options(fn () => TypeDocument::orderBy('name')->pluck('name', 'id')),
            TextInput::make('titre')->label('Titre')->required(),
            Textarea::make('contenu')->label('Contenu HTML')->required()->rows(14)->columnSpanFull()
                ->helperText('Variables autorisées entre doubles accolades, ex. {{ employe.name }}. Aucun code PHP n\'est exécuté.')
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    $inconnues = app(TemplateRendererService::class)->variablesInconnues((string) $value);
                    if ($inconnues !== []) {
                        $fail('Variable(s) non autorisée(s) : '.implode(', ', $inconnues));
                    }
                }),
            Toggle::make('is_default')->label('Modèle par défaut du type'),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->label('Titre')->searchable(),
                TextColumn::make('type.name')->label('Type'),
                TextColumn::make('version')->label('Version'),
                IconColumn::make('is_default')->label('Défaut')->boolean(),
                IconColumn::make('is_active')->label('Actif')->boolean(),

            ])
            ->recordActions([
                Action::make('apercu')->label('Prévisualiser')->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn ($record) => $record->titre.' — v'.$record->version)
                    ->modalContent(fn ($record) => new HtmlString('<div class="prose max-w-none">'.app(TemplateRendererService::class)->apercu($record->contenu, RhModule::Document).'</div>'))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageModelDocuments::route('/')];
    }
}
