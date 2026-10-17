<?php

namespace App\Filament\Resources\Sanctions;

use App\Enums\Role;
use App\Filament\Resources\Sanctions\Pages\CreateSanction;
use App\Filament\Resources\Sanctions\Pages\EditSanction;
use App\Filament\Resources\Sanctions\Pages\ListSanctions;
use App\Filament\Resources\Sanctions\Pages\ViewSanction;
use App\Filament\Support\ModuleResource;
use App\Models\ModelSanction;
use App\Models\NiveauSanction;
use App\Models\Sanction;
use App\Models\Sanction as SanctionModel;
use App\Models\TypeSanction;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SanctionResource extends ModuleResource
{
    protected static ?string $model = Sanction::class;

    protected static ?string $modelLabel = 'sanction';

    protected static ?string $pluralModelLabel = 'Sanctions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 3;

    /** Accès renforcé : un Junior ne voit que les sanctions qu'il a créées. */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        return $user->hasAnyRole(Role::RhManager, Role::RhSenior, Role::RhOfficer)
            ? $query
            : $query->where('cree_par', $user->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sanctions')->columnSpanFull()->columns(2)->schema([
                static::employeSelect(),
                static::employeInfo(),
                static::typeSelect('type_sanction_id', 'model_sanction_id', TypeSanction::class, ModelSanction::class),
                static::modelSelect('type_sanction_id', 'model_sanction_id', ModelSanction::class),
                static::modelPreview('model_sanction_id', ModelSanction::class)->columnSpanFull(),
                DatePicker::make('date_sanction')->label('Date de la sanction')->required(),
                Select::make('niveau_sanction_id')->label('Niveau appliqué')
                    ->options(fn () => NiveauSanction::where('is_active', true)->orderBy('ordre')->pluck('name', 'id')),
                Select::make('sanction_precedente_id')->label('Sanction précédente')
                    ->options(fn (Get $get) => $get('matricule')
                        ? SanctionModel::where('matricule', $get('matricule'))->orderByDesc('date_sanction')->get()
                            ->mapWithKeys(fn ($s) => [$s->id => ($s->reference ?? 'brouillon').' — '.$s->date_sanction->format('d/m/Y')])->all()
                        : []),
                Textarea::make('description')->label('Description des faits')->columnSpanFull(),
                Textarea::make('motif')->label('Motif')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sanctions')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Non attribuée (avant approbation)'),
                TextEntry::make('employe.name')->label('Employé'),
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('modele.titre')->label('Modèle')->placeholder('—'),
                TextEntry::make('date_sanction')->label('Date')->date('d/m/Y'),
                TextEntry::make('niveau.name')->label('Niveau appliqué')->placeholder('—'),
                TextEntry::make('description')->label('Description')->placeholder('—'),
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
                TextColumn::make('date_sanction')->label('Date')->date('d/m/Y'),
                static::statutColumn(),
            ])
            ->filters([static::statutFilter()])
            ->recordActions(static::recordActions())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSanctions::route('/'),
            'create' => CreateSanction::route('/create'),
            'view' => ViewSanction::route('/{record}'),
            'edit' => EditSanction::route('/{record}/edit'),
        ];
    }
}
