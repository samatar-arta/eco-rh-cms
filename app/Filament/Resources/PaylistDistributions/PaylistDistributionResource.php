<?php

namespace App\Filament\Resources\PaylistDistributions;

use App\Enums\DistributionStatut;
use App\Exceptions\PaylistException;
use App\Filament\Resources\PaylistDistributions\Pages\ListPaylistDistributions;
use App\Models\PaylistDistribution;
use App\Services\PaylistDistributionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaylistDistributionResource extends Resource
{
    protected static ?string $model = PaylistDistribution::class;

    protected static ?string $modelLabel = 'distribution';

    protected static ?string $pluralModelLabel = 'Distributions';

    protected static string|UnitEnum|null $navigationGroup = 'Paylist';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['paylist.employe', 'contact.societe', 'distributeur']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('paylist.matricule')->label('Matricule')->searchable(),
                TextColumn::make('paylist.employe.name')->label('Employé'),
                TextColumn::make('periode')->label('Période')->state(fn (PaylistDistribution $r) => $r->paylist->periode()),
                TextColumn::make('contact.nom')->label('Contact')->searchable(),
                TextColumn::make('contact.societe.name')->label('Société'),
                TextColumn::make('canal')->label('Canal')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (DistributionStatut $state) => $state->label())
                    ->color(fn (DistributionStatut $state) => $state->color()),
                TextColumn::make('tentatives')->label('Essais'),
                TextColumn::make('date_distribution')->label('Date')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('commentaire')->label('Erreur')->limit(50)->tooltip(fn (PaylistDistribution $r) => $r->commentaire)->placeholder('—'),
                TextColumn::make('distributeur.name')->label('Par'),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->options(collect(DistributionStatut::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                Action::make('relancer')->label('Relancer')->icon('heroicon-o-arrow-path')->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (PaylistDistribution $r) => $r->statut === DistributionStatut::Echec)
                    ->action(function (PaylistDistribution $r) {
                        try {
                            app(PaylistDistributionService::class)->relancer(auth()->user(), $r);
                            Notification::make()->title('Nouvelle tentative lancée')->success()->send();
                        } catch (PaylistException $e) {
                            Notification::make()->title('Relance refusée')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPaylistDistributions::route('/')];
    }
}
