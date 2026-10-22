<?php

namespace App\Filament\Resources\LogsActions;

use App\Filament\Resources\LogsActions\Pages\ListLogsActions;
use App\Models\LogAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class LogActionResource extends Resource
{
    protected static ?string $model = LogAction::class;

    protected static ?string $slug = 'journal-audit';

    protected static ?string $modelLabel = 'entrée du journal';

    protected static ?string $pluralModelLabel = 'Journal d\'audit';

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Utilisateur')->placeholder('Système')->searchable(),
                TextColumn::make('action')->label('Action')->badge()->searchable(),
                TextColumn::make('entite')->label('Entité')->state(fn (LogAction $r) => $r->entite_type ? "{$r->entite_type} #{$r->entite_id}" : '—'),
                TextColumn::make('description')->label('Détail')->limit(80)->tooltip(fn (LogAction $r) => $r->description),
                TextColumn::make('adresse_ip')->label('IP')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => LogAction::query()->distinct()->orderBy('action')->pluck('action', 'action')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLogsActions::route('/')];
    }
}
