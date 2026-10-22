<?php

namespace App\Filament\Resources\TypesMutations;

use App\Filament\Resources\TypesMutations\Pages\ManageTypesMutations;
use App\Filament\Support\AdminOnlyResource;
use App\Models\TypeMutation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TypeMutationResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = TypeMutation::class;

    protected static ?string $slug = 'types-mutations';

    protected static ?string $modelLabel = 'type de mutation';

    protected static ?string $pluralModelLabel = 'Types de mutations';

    protected static string|UnitEnum|null $navigationGroup = 'Types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->rule('regex:/^[A-Z0-9_]+$/')->helperText('Majuscules, chiffres et _ uniquement.'),
            TextInput::make('name')->label('Libellé')->required(),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            Toggle::make('is_active')->label('Actif')->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('name')->label('Libellé')->searchable(),
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
        return ['index' => ManageTypesMutations::route('/')];
    }
}
