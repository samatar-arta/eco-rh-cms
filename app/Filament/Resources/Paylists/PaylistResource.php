<?php

namespace App\Filament\Resources\Paylists;

use App\Enums\DistributionCanal;
use App\Enums\PaylistStatut;
use App\Exceptions\PaylistException;
use App\Filament\Resources\PaylistImports\PaylistImportResource;
use App\Filament\Resources\Paylists\Pages\ListPaylists;
use App\Models\Contact;
use App\Models\Paylist;
use App\Models\Societe;
use App\Services\PaylistDistributionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class PaylistResource extends Resource
{
    protected static ?string $model = Paylist::class;

    protected static ?string $modelLabel = 'bulletin';

    protected static ?string $pluralModelLabel = 'Bulletins Paylist';

    protected static string|UnitEnum|null $navigationGroup = 'Paylist';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('employe.societe'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('matricule')->label('Matricule')->searchable(),
                TextColumn::make('employe.name')->label('Employé')->searchable(),
                TextColumn::make('employe.societe.name')->label('Société'),
                TextColumn::make('periode')->label('Période')->state(fn (Paylist $r) => $r->periode()),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (PaylistStatut $state) => $state === PaylistStatut::Distribue ? 'Distribué' : 'Importé')
                    ->color(fn (PaylistStatut $state) => $state === PaylistStatut::Distribue ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('mois')->label('Mois')->options(PaylistImportResource::MOIS),
                SelectFilter::make('annee')->label('Année')
                    ->options(fn () => Paylist::query()->distinct()->orderByDesc('annee')->pluck('annee', 'annee')),
                SelectFilter::make('societe')->label('Société')->relationship('employe.societe', 'name'),
                SelectFilter::make('statut')->options(['IMPORTE' => 'Importé', 'DISTRIBUE' => 'Distribué']),
            ])
            ->recordActions([
                Action::make('telecharger')->label('Télécharger')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Paylist $r) => route('paylists.fichier', $r))->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('distribuer')
                        ->label('Distribuer')->icon('heroicon-o-paper-airplane')
                        ->deselectRecordsAfterCompletion()
                        ->schema([
                            Select::make('societe_id')->label('Société cliente')->required()->live()
                                ->options(fn () => Societe::orderBy('name')->pluck('name', 'id')),
                            Select::make('contact_id')->label('Contact destinataire')->required()
                                ->options(fn (Get $get) => $get('societe_id')
                                    ? Contact::where('societe_id', $get('societe_id'))->where('is_active', true)->get()->pluck('nom', 'id')
                                    : []),
                            Select::make('canal')->label('Canal')->required()->live()->default(DistributionCanal::Email->value)
                                ->options(collect(DistributionCanal::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                            TextInput::make('preuve')->label('Preuve de remise (n° bordereau)')
                                ->visible(fn (Get $get) => $get('canal') === DistributionCanal::Remise->value)
                                ->required(fn (Get $get) => $get('canal') === DistributionCanal::Remise->value),
                        ])
                        ->action(function (Collection $records, array $data) {
                            try {
                                $resultat = app(PaylistDistributionService::class)->distribuer(
                                    auth()->user(), $records->modelKeys(), Contact::findOrFail($data['contact_id']),
                                    DistributionCanal::from($data['canal']), $data['preuve'] ?? null,
                                );
                            } catch (PaylistException $e) {
                                Notification::make()->title('Distribution refusée')->body($e->getMessage())->danger()->send();

                                return;
                            }

                            Notification::make()->title(count($resultat).' distribution(s) enregistrée(s)')
                                ->body('Suivez leur statut dans « Distributions ».')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPaylists::route('/')];
    }
}
