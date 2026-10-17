<?php

namespace App\Filament\Resources\Evaluations;

use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Pages\ViewEvaluation;
use App\Filament\Support\ModuleResource;
use App\Models\Evaluation;
use App\Models\ModelEvaluation;
use App\Models\TypeEvaluation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
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

class EvaluationResource extends ModuleResource
{
    protected static ?string $model = Evaluation::class;

    protected static ?string $modelLabel = 'évaluation';

    protected static ?string $pluralModelLabel = 'Évaluations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 4;

    public static function creatorColumn(): string
    {
        return 'evaluateur_id';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Évaluations')->columnSpanFull()->columns(2)->schema([
                static::employeSelect(),
                static::employeInfo(),
                static::typeSelect('type_evaluation_id', 'model_evaluation_id', TypeEvaluation::class, ModelEvaluation::class),
                static::modelSelect('type_evaluation_id', 'model_evaluation_id', ModelEvaluation::class)
                    ->afterStateUpdated(function (Set $set, $state) {
                        $criteres = ModelEvaluation::find($state)?->criteres ?? [];
                        $set('resultats_criteres', collect($criteres)->map(fn ($c) => ['libelle' => $c['libelle'], 'note' => null])->all());
                    }),
                static::modelPreview('model_evaluation_id', ModelEvaluation::class)->columnSpanFull(),
                DatePicker::make('date_evaluation')->label("Date de l'évaluation")->required(),
                DatePicker::make('periode_debut')->label('Début de période'),
                DatePicker::make('periode_fin')->label('Fin de période')->afterOrEqual('periode_debut'),
                Repeater::make('resultats_criteres')->label('Notes par critère (coefficients du modèle)')->columnSpanFull()
                    ->visible(fn (Get $get) => ! empty(ModelEvaluation::find($get('model_evaluation_id'))?->criteres))
                    ->addable(false)->deletable(false)->reorderable(false)->columns(2)
                    ->schema([
                        TextInput::make('libelle')->label('Critère')->readOnly()->dehydrated(),
                        TextInput::make('note')->label('Note')->numeric()->minValue(0)->maxValue(20)->required(),
                    ]),
                TextInput::make('note')->label('Note finale (/20)')->numeric()->minValue(0)->maxValue(20)
                    ->helperText("Calculée automatiquement à partir des critères lorsqu'un modèle à critères est choisi."),
                Textarea::make('appreciation')->label('Appréciation')->columnSpanFull(),
                Textarea::make('objectifs')->label('Objectifs')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Évaluations')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Non attribuée (avant approbation)'),
                TextEntry::make('employe.name')->label('Employé'),
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('modele.titre')->label('Modèle')->placeholder('—'),
                TextEntry::make('date_evaluation')->label('Date')->date('d/m/Y'),
                TextEntry::make('evaluateur.name')->label('Évaluateur')->placeholder('—'),
                TextEntry::make('note')->label('Note')->placeholder('—'),
                TextEntry::make('appreciation')->label('Appréciation')->placeholder('—'),
                TextEntry::make('objectifs')->label('Objectifs')->placeholder('—'),
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
                TextColumn::make('date_evaluation')->label('Date')->date('d/m/Y'),
                TextColumn::make('note')->label('Note')->placeholder('—'),
                static::statutColumn(),
            ])
            ->filters([static::statutFilter()])
            ->recordActions(static::recordActions())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluations::route('/'),
            'create' => CreateEvaluation::route('/create'),
            'view' => ViewEvaluation::route('/{record}'),
            'edit' => EditEvaluation::route('/{record}/edit'),
        ];
    }
}
