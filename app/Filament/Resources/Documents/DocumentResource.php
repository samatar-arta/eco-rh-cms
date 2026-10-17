<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Support\ModuleResource;
use App\Models\Document;
use App\Models\ModelDocument;
use App\Models\TypeDocument;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentResource extends ModuleResource
{
    protected static ?string $model = Document::class;

    protected static ?string $modelLabel = 'document';

    protected static ?string $pluralModelLabel = 'Documents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Documents')->columnSpanFull()->columns(2)->schema([
                static::employeSelect(),
                static::employeInfo(),
                static::typeSelect('type_document_id', 'model_document_id', TypeDocument::class, ModelDocument::class),
                static::modelSelect('type_document_id', 'model_document_id', ModelDocument::class),
                static::modelPreview('model_document_id', ModelDocument::class)->columnSpanFull(),
                DatePicker::make('date_production')->label('Date de production'),
                Textarea::make('commentaire')->label('Commentaire')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Documents')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Non attribuée (avant approbation)'),
                TextEntry::make('employe.name')->label('Employé'),
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('modele.titre')->label('Modèle')->placeholder('—'),
                TextEntry::make('date_production')->label('Date de production')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('commentaire')->label('Commentaire')->placeholder('—'),
            ]),
            static::timelineSection(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employe', 'type', 'workflow'])->latest('id'))
            ->columns([
                static::referenceColumn(),
                TextColumn::make('matricule')->label('Matricule')->searchable(),
                TextColumn::make('employe.name')->label('Employé')->searchable(),
                TextColumn::make('type.name')->label('Type'),
                TextColumn::make('date_production')->label('Production')->date('d/m/Y')->placeholder('—'),
                static::statutColumn(),
            ])
            ->filters([static::statutFilter()])
            ->recordActions(static::recordActions())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
