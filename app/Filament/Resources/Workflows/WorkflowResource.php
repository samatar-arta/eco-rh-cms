<?php

namespace App\Filament\Resources\Workflows;

use App\Enums\RhModule;
use App\Enums\WorkflowStatut;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Models\Workflow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $slug = 'workflows';

    protected static ?string $modelLabel = 'workflow';

    protected static ?string $pluralModelLabel = 'Workflows';

    protected static string|UnitEnum|null $navigationGroup = 'Suivi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<string, string> alias de morph map => module */
    public static function typeOptions(): array
    {
        return collect(RhModule::cases())->mapWithKeys(fn (RhModule $m) => [$m->morphAlias() => $m->value])->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['createur', 'validateur', 'approbateur']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('workflowable_type')->label('Type')->badge()
                    ->formatStateUsing(fn ($state) => self::typeOptions()[$state] ?? $state),
                TextColumn::make('workflowable_id')->label('Dossier #')->sortable(),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof WorkflowStatut ? $state->value : $state)
                    ->color(fn ($state) => match ($state instanceof WorkflowStatut ? $state->value : $state) {
                        'APPROUVE' => 'success',
                        'REJETE', 'EXPIRE' => 'danger',
                        'EN_ATTENTE_VALIDATION', 'EN_ATTENTE_APPROBATION', 'DOCUMENT_SOUMIS', 'VALIDE' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('createur.name')->label('Créé par')->placeholder('—'),
                TextColumn::make('validateur.name')->label('Validé par')->placeholder('—'),
                TextColumn::make('approbateur.name')->label('Approuvé par')->placeholder('—'),
                TextColumn::make('date_soumission')->label('Soumis le')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('date_limite_validation')->label('Échéance validation')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('date_limite_approbation')->label('Échéance approbation')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(WorkflowStatut::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])->all()),
                SelectFilter::make('workflowable_type')->label('Type')->options(self::typeOptions()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWorkflows::route('/')];
    }
}
