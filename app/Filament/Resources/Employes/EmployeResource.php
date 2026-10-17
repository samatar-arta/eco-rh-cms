<?php

namespace App\Filament\Resources\Employes;

use App\Enums\Role;
use App\Enums\StatutEmploye;
use App\Filament\Resources\Employes\Pages\CreateEmploye;
use App\Filament\Resources\Employes\Pages\EditEmploye;
use App\Filament\Resources\Employes\Pages\ListEmployes;
use App\Models\Contrat;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\SiteAffectation;
use App\Models\SiteDepartement;
use App\Models\Societe;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EmployeResource extends Resource
{
    protected static ?string $model = Employe::class;

    protected static ?string $modelLabel = 'employé';

    protected static ?string $pluralModelLabel = 'Employés';

    protected static string|UnitEnum|null $navigationGroup = 'Personnel';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    /** Périmètre appliqué à la requête : ni la liste ni l'URL directe ne contournent la Policy. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité et emploi')->columnSpanFull()->columns(2)->schema([
                TextInput::make('matricule')->label('Matricule')->required()->maxLength(30)
                    ->unique(ignoreRecord: true)->disabled(fn (string $operation) => $operation === 'edit'),
                TextInput::make('name')->label('Nom complet')->required(),
                TextInput::make('fonction')->label('Fonction'),
                TextInput::make('categorie')->label('Catégorie'),
                DatePicker::make('date_naissance')->label('Date de naissance')->maxDate(now()),
                DatePicker::make('date_embauche')->label("Date d'embauche"),
                Select::make('contrat_id')->label('Contrat')
                    ->options(fn () => Contrat::where('is_active', true)->pluck('name', 'id')),
                Select::make('statut_employe')->label('Statut')->required()->default(StatutEmploye::Actif->value)
                    ->options(collect(StatutEmploye::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])->all()),
                DatePicker::make('date_sortie')->label('Date de sortie')->afterOrEqual('date_embauche'),
            ]),
            Section::make('Affectation')->columnSpanFull()->columns(3)->schema([
                Select::make('societe_id')->label('Société')->required()->live()
                    ->options(fn () => Societe::where('is_active', true)->pluck('name', 'id'))
                    ->afterStateUpdated(fn (Set $set) => $set('departement_id', null)),
                Select::make('departement_id')->label('Département')->required()->live()
                    ->options(fn (Get $get) => $get('societe_id')
                        ? Departement::where('societe_id', $get('societe_id'))->where('is_active', true)->pluck('name', 'id')
                        : []),
                Select::make('site_id')->label("Site d'affectation")->required()->live()
                    ->options(fn () => SiteAffectation::where('is_active', true)->pluck('name', 'id'))
                    ->rule(fn (Get $get) => static::regleAffectation($get)),
            ]),
            Section::make('Coordonnées et situation')->columnSpanFull()->columns(2)->collapsed()->schema([
                TextInput::make('telephone')->label('Téléphone')->tel(),
                TextInput::make('num_cnss')->label('N° CNSS'),
                TextInput::make('num_cni')->label('N° CNI'),
                TextInput::make('niveau_etude')->label("Niveau d'étude"),
                TextInput::make('situation_familiale')->label('Situation familiale'),
                Textarea::make('adresse')->label('Adresse'),
                TextInput::make('personne_contacter')->label('Personne à contacter'),
                TextInput::make('lien_parental')->label('Lien parental'),
                TextInput::make('telephone_parental')->label('Téléphone de la personne à contacter')->tel(),
                TextInput::make('formation_certificat')->label('Formations / certificats'),
                TextInput::make('permis_conduite')->label('Permis de conduire'),
                Toggle::make('handicap')->label('Situation de handicap'),
            ]),
        ]);
    }

    /**
     * Le trio site / société / département doit exister dans site_departements,
     * et (hors ADMIN) appartenir au périmètre de l'utilisateur : on ne crée pas
     * un employé que l'on ne pourrait ensuite plus voir.
     */
    public static function regleAffectation(Get $get): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get) {
            $existe = SiteDepartement::query()
                ->where('site_id', $value)
                ->where('societe_id', $get('societe_id'))
                ->where('departement_id', $get('departement_id'))
                ->first();

            if (! $existe) {
                return $fail("Ce département n'est pas rattaché à ce site pour cette société.");
            }

            $user = auth()->user();
            if (! $user->hasRole(Role::Admin) && ! $user->siteDepartements()->whereKey($existe->getKey())->exists()) {
                $fail('Cette affectation est hors de votre périmètre.');
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['societe', 'departement', 'site', 'contrat']))
            ->columns([
                TextColumn::make('matricule')->label('Matricule')->searchable()->sortable(),
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('fonction')->label('Fonction')->searchable(),
                TextColumn::make('societe.name')->label('Société'),
                TextColumn::make('departement.name')->label('Département'),
                TextColumn::make('site.name')->label('Site'),
                TextColumn::make('contrat.name')->label('Contrat'),
                TextColumn::make('statut_employe')->label('Statut')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof StatutEmploye ? $state->value : $state)
                    ->color(fn ($state) => match ($state instanceof StatutEmploye ? $state->value : $state) {
                        'ACTIF' => 'success', 'SUSPENDU' => 'warning', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('societe_id')->label('Société')->options(fn () => Societe::pluck('name', 'id')),
                SelectFilter::make('statut_employe')->label('Statut')
                    ->options(collect(StatutEmploye::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])->all()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployes::route('/'),
            'create' => CreateEmploye::route('/create'),
            'edit' => EditEmploye::route('/{record}/edit'),
        ];
    }
}
