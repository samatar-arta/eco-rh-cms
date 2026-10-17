<?php

namespace App\Filament\Resources\Contacts;

use App\Filament\Resources\Contacts\Pages\ManageContacts;
use App\Models\Contact;
use App\Models\Departement;
use App\Models\Societe;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $modelLabel = 'contact';

    protected static ?string $pluralModelLabel = 'Contacts';

    protected static string|UnitEnum|null $navigationGroup = 'Paylist';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required(),
            TextInput::make('prenom')->label('Prénom'),
            TextInput::make('fonction')->label('Fonction'),
            TextInput::make('email')->label('E-mail')->email(),
            TextInput::make('telephone')->label('Téléphone')->tel(),
            Select::make('societe_id')->label('Société cliente')->required()->live()->options(fn () => Societe::pluck('name', 'id')),
            Select::make('departement_id')->label('Département')->options(fn (Get $get) => $get('societe_id') ? Departement::where('societe_id', $get('societe_id'))->pluck('name', 'id') : []),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable(),
                TextColumn::make('fonction')->label('Fonction'),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('societe.name')->label('Société'),
                TextColumn::make('departement.name')->label('Département')->placeholder('—'),
                IconColumn::make('is_active')->label('Actif')->boolean(),

            ])
            ->recordActions([

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageContacts::route('/')];
    }
}
