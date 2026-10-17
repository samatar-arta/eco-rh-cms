<?php

namespace App\Filament\Resources\Conges;

use App\Filament\Resources\Conges\Pages\CreateConge;
use App\Filament\Resources\Conges\Pages\EditConge;
use App\Filament\Resources\Conges\Pages\ListConges;
use App\Filament\Resources\Conges\Pages\ViewConge;
use App\Filament\Support\ModuleResource;
use App\Models\Conge;
use App\Models\ModelConge;
use App\Models\TypeConge;
use App\Services\CongeDayCounter;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CongeResource extends ModuleResource
{
    protected static ?string $model = Conge::class;

    protected static ?string $modelLabel = 'congé';

    protected static ?string $pluralModelLabel = 'Congés';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    public static function creatorColumn(): string
    {
        return 'user_id';
    }

    /** Le nombre de jours est toujours calculé côté serveur (règle configurable). */
    public static function completerDonnees(array $data): array
    {
        $data['nbre_jours'] = app(CongeDayCounter::class)->count(
            Carbon::parse($data['date_debut']),
            Carbon::parse($data['date_fin'])
        );

        return $data;
    }

    public static function donneesAEdition(array $data): array
    {
        return static::completerDonnees($data);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Congés')->columnSpanFull()->columns(2)->schema([
                static::employeSelect(),
                static::employeInfo(),
                static::typeSelect('type_conge_id', 'model_conge_id', TypeConge::class, ModelConge::class),
                static::modelSelect('type_conge_id', 'model_conge_id', ModelConge::class),
                static::modelPreview('model_conge_id', ModelConge::class)->columnSpanFull(),
                DatePicker::make('date_debut')->label('Début')->required()->live(),
                DatePicker::make('date_fin')->label('Fin')->required()->live()->afterOrEqual('date_debut'),
                Textarea::make('motif')->label('Motif')->columnSpanFull(),
                FileUpload::make('fichier_path')
                    ->label('Justificatif')
                    ->disk('local')->directory('conges')->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->required(fn (Get $get) => (bool) TypeConge::find($get('type_conge_id'))?->requires_justificatif)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Congés')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Non attribuée (avant approbation)'),
                TextEntry::make('employe.name')->label('Employé'),
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('modele.titre')->label('Modèle')->placeholder('—'),
                TextEntry::make('date_debut')->label('Début')->date('d/m/Y'),
                TextEntry::make('date_fin')->label('Fin')->date('d/m/Y'),
                TextEntry::make('nbre_jours')->label('Jours'),
                TextEntry::make('motif')->label('Motif')->placeholder('—'),
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
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y'),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y'),
                TextColumn::make('nbre_jours')->label('Jours'),
                static::statutColumn(),
            ])
            ->filters([static::statutFilter()])
            ->recordActions(static::recordActions())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConges::route('/'),
            'create' => CreateConge::route('/create'),
            'view' => ViewConge::route('/{record}'),
            'edit' => EditConge::route('/{record}/edit'),
        ];
    }
}
