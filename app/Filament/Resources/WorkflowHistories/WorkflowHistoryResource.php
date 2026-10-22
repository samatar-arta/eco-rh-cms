<?php

namespace App\Filament\Resources\WorkflowHistories;

use App\Filament\Resources\WorkflowHistories\Pages\ListWorkflowHistories;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\WorkflowHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class WorkflowHistoryResource extends Resource
{
    protected static ?string $model = WorkflowHistory::class;

    protected static ?string $slug = 'workflow-histories';

    protected static ?string $modelLabel = 'entrée d\'historique';

    protected static ?string $pluralModelLabel = 'Historique des workflows';

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'workflow']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('date_action')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('workflow.workflowable_type')->label('Type')->badge()
                    ->formatStateUsing(fn ($state) => WorkflowResource::typeOptions()[$state] ?? $state),
                TextColumn::make('workflow.workflowable_id')->label('Dossier #'),
                TextColumn::make('user.name')->label('Utilisateur')->placeholder('Système')->searchable(),
                TextColumn::make('role')->label('Rôle')->placeholder('—'),
                TextColumn::make('etape')->label('Étape')->badge()->searchable(),
                TextColumn::make('statut')->label('Statut')->badge(),
                TextColumn::make('commentaire')->label('Commentaire')->limit(60)->tooltip(fn (WorkflowHistory $r) => $r->commentaire)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('etape')
                    ->label('Étape')
                    ->options(fn () => WorkflowHistory::query()->distinct()->orderBy('etape')->pluck('etape', 'etape')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWorkflowHistories::route('/')];
    }
}
