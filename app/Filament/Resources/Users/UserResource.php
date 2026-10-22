<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\SiteDepartement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    public static function form(Schema $schema): Schema
    {
        $affectationsBloquees = fn (?User $record) => $record !== null && auth()->user()->is($record);

        return $schema->components([
            Section::make('Compte')->columnSpanFull()->columns(2)->schema([
                TextInput::make('name')->label('Nom')->required(),
                TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')->label('Mot de passe')->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(12)->helperText('12 caractères minimum. Laissez vide pour ne pas le changer.'),
                Toggle::make('is_active')->label('Compte actif')->default(true)->disabled($affectationsBloquees),
            ]),
            Section::make('Rôles et périmètres')->columnSpanFull()
                ->description('Vous ne pouvez pas modifier vos propres rôles ni vos propres périmètres.')
                ->schema([
                    CheckboxList::make('roles')->label('Rôles')->columns(3)->disabled($affectationsBloquees)
                        ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all()),
                    Select::make('perimetres')->label('Périmètres (site / société / département)')
                        ->multiple()->searchable()->disabled($affectationsBloquees)
                        ->options(fn () => SiteDepartement::with(['site', 'societe', 'departement'])->get()
                            ->mapWithKeys(fn ($sd) => [$sd->id => "{$sd->site->name} / {$sd->societe->name} / {$sd->departement->name}"])->all()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roleAssignments'))
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('roles')->label('Rôles')->badge()
                    ->state(fn (User $record) => array_map(fn (Role $r) => $r->label(), $record->rolesEnum())),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
