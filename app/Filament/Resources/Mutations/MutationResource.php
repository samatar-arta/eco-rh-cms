<?php

namespace App\Filament\Resources\Mutations;

use App\Filament\Resources\Mutations\Pages\CreateMutation;
use App\Filament\Resources\Mutations\Pages\EditMutation;
use App\Filament\Resources\Mutations\Pages\ListMutations;
use App\Filament\Resources\Mutations\Pages\ViewMutation;
use App\Filament\Support\ModuleResource;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ModelMutation;
use App\Models\Mutation;
use App\Models\SiteAffectation;
use App\Models\Societe;
use App\Models\TypeMutation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MutationResource extends ModuleResource
{
    protected static ?string $model = Mutation::class;

    protected static ?string $modelLabel = 'mutation';

    protected static ?string $pluralModelLabel = 'Mutations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 6;

    /**
     * Les valeurs actuelles de l'employé sont figées côté serveur comme
     * « anciennes » affectations ; rien n'est modifié sur l'employé avant
     * l'approbation (Phase 8).
     */
    public static function completerDonnees(array $data): array
    {
        $employe = Employe::where('matricule', $data['matricule'])->firstOrFail();

        $data['ancien_site_id'] = $employe->site_id;
        $data['ancien_departement_id'] = $employe->departement_id;
        $data['ancienne_societe_id'] = $employe->societe_id;
        $data['ancienne_fonction'] = $employe->fonction;

        if (! empty($data['nouveau_departement_id']) && empty($data['nouvelle_societe_id'])) {
            $data['nouvelle_societe_id'] = $employe->societe_id;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mutations')->columnSpanFull()->columns(2)->schema([
                static::employeSelect(),
                static::employeInfo(),
                static::typeSelect('type_mutation_id', 'model_mutation_id', TypeMutation::class, ModelMutation::class),
                static::modelSelect('type_mutation_id', 'model_mutation_id', ModelMutation::class),
                static::modelPreview('model_mutation_id', ModelMutation::class)->columnSpanFull(),
                DatePicker::make('date_effet')->label("Date d'effet")->required(),
                TextInput::make('nouvelle_fonction')->label('Nouvelle fonction'),
                Select::make('nouveau_site_id')->label('Nouveau site')->searchable()
                    ->options(fn () => SiteAffectation::where('is_active', true)->pluck('name', 'id')),
                Select::make('nouvelle_societe_id')->label('Nouvelle société')->live()
                    ->options(fn () => Societe::where('is_active', true)->pluck('name', 'id'))
                    ->afterStateUpdated(fn (Set $set) => $set('nouveau_departement_id', null)),
                Select::make('nouveau_departement_id')->label('Nouveau département')->searchable()
                    ->options(function (Get $get) {
                        $societe = $get('nouvelle_societe_id')
                            ?? Employe::where('matricule', $get('matricule'))->value('societe_id');

                        return $societe
                            ? Departement::where('societe_id', $societe)->where('is_active', true)->pluck('name', 'id')
                            : [];
                    }),
                Textarea::make('motif')->label('Motif')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mutations')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Non attribuée (avant approbation)'),
                TextEntry::make('employe.name')->label('Employé'),
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('modele.titre')->label('Modèle')->placeholder('—'),
                TextEntry::make('date_effet')->label("Date d'effet")->date('d/m/Y'),
                TextEntry::make('ancienne_fonction')->label('Ancienne fonction')->placeholder('—'),
                TextEntry::make('nouvelle_fonction')->label('Nouvelle fonction')->placeholder('—'),
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
                TextColumn::make('date_effet')->label("Date d'effet")->date('d/m/Y'),
                TextColumn::make('nouvelle_fonction')->label('Nouvelle fonction')->placeholder('—'),
                static::statutColumn(),
            ])
            ->filters([static::statutFilter()])
            ->recordActions(static::recordActions())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutations::route('/'),
            'create' => CreateMutation::route('/create'),
            'view' => ViewMutation::route('/{record}'),
            'edit' => EditMutation::route('/{record}/edit'),
        ];
    }
}
