<?php

namespace App\Filament\Resources\PaylistImports;

use App\Enums\PaylistImportStatut;
use App\Filament\Resources\PaylistImports\Pages\CreatePaylistImport;
use App\Filament\Resources\PaylistImports\Pages\ListPaylistImports;
use App\Filament\Resources\PaylistImports\Pages\ViewPaylistImport;
use App\Models\PaylistImport;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PaylistImportResource extends Resource
{
    protected static ?string $model = PaylistImport::class;

    protected static ?string $modelLabel = 'import Paylist';

    protected static ?string $pluralModelLabel = 'Imports Paylist';

    protected static string|UnitEnum|null $navigationGroup = 'Paylist';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 1;

    public const MOIS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fichier exporté depuis Sage')
                ->description('Archive ZIP de PDF nommés MATRICULE.pdf (ou un PDF unique). Un aperçu est affiché avant toute importation.')
                ->columnSpanFull()->columns(2)->schema([
                    Select::make('mois')->label('Mois')->options(self::MOIS)->required()->default((int) now()->subMonth()->format('n')),
                    TextInput::make('annee')->label('Année')->numeric()->required()->minValue(2000)->maxValue(2100)->default((int) now()->subMonth()->format('Y')),
                    FileUpload::make('fichier')->label('Fichier')->required()->columnSpanFull()
                        ->disk(config('rh.paylist.disk'))->directory('paylist-uploads')->visibility('private')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/pdf'])
                        ->maxSize(204800)->storeFileNamesIn('nom_fichier'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lot d\'importation')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('nom_fichier')->label('Fichier'),
                TextEntry::make('periode_affichee')->label('Période')->state(fn (PaylistImport $r) => $r->periode()),
                TextEntry::make('statut_import')->label('Statut')->badge()
                    ->formatStateUsing(fn (PaylistImportStatut $state) => $state->label())
                    ->color(fn (PaylistImportStatut $state) => $state->color()),
                TextEntry::make('total_lignes')->label('Lignes analysées'),
                TextEntry::make('lignes_importees')->label('Importées'),
                TextEntry::make('lignes_rejetees')->label('Rejetées'),
                TextEntry::make('importeur.name')->label('Par'),
                TextEntry::make('date_import')->label('Importé le')->dateTime('d/m/Y H:i')->placeholder('Pas encore confirmé'),
            ]),
            Section::make('Aperçu de l\'analyse')->columnSpanFull()->schema([
                ViewEntry::make('apercu')->hiddenLabel()->view('filament.paylist-apercu'),
            ]),
            Section::make('Rapport d\'erreurs')->columnSpanFull()
                ->visible(fn (PaylistImport $r) => ! empty($r->rapport_erreurs))
                ->schema([
                    ViewEntry::make('rapport_erreurs')->hiddenLabel()->view('filament.paylist-apercu'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('nom_fichier')->label('Fichier')->searchable()->limit(40),
                TextColumn::make('periode')->label('Période')->state(fn (PaylistImport $r) => $r->periode()),
                TextColumn::make('statut_import')->label('Statut')->badge()
                    ->formatStateUsing(fn (PaylistImportStatut $state) => $state->label())
                    ->color(fn (PaylistImportStatut $state) => $state->color()),
                TextColumn::make('lignes_importees')->label('Importées'),
                TextColumn::make('lignes_rejetees')->label('Rejetées'),
                TextColumn::make('importeur.name')->label('Par'),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaylistImports::route('/'),
            'create' => CreatePaylistImport::route('/create'),
            'view' => ViewPaylistImport::route('/{record}'),
        ];
    }
}
