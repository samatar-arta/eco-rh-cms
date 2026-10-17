<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Pages\ViewPromotion;
use App\Filament\Support\ModuleResource;
use App\Models\ModelPromotion;
use App\Models\Promotion;
use App\Models\TypePromotion;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionResource extends ModuleResource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $modelLabel = 'promotion';

    protected static ?string $pluralModelLabel = 'Promotions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Promotions')->columnSpanFull()->columns(2)->schema([
                static::employeSelect(),
                static::employeInfo(),
                static::typeSelect('type_promotion_id', 'model_promotion_id', TypePromotion::class, ModelPromotion::class),
                static::modelSelect('type_promotion_id', 'model_promotion_id', ModelPromotion::class),
                static::modelPreview('model_promotion_id', ModelPromotion::class)->columnSpanFull(),
                DatePicker::make('date_effet')->label("Date d'effet")->required(),
                TextInput::make('ancienne_fonction')->label('Ancienne fonction'),
                TextInput::make('nouvelle_fonction')->label('Nouvelle fonction'),
                TextInput::make('ancien_grade')->label('Ancien grade'),
                TextInput::make('nouveau_grade')->label('Nouveau grade'),
                TextInput::make('ancien_salaire')->label('Ancien salaire')->numeric()->minValue(0),
                TextInput::make('nouveau_salaire')->label('Nouveau salaire')->numeric()->minValue(0),
                Textarea::make('motif')->label('Motif')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Promotions')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('Non attribuée (avant approbation)'),
                TextEntry::make('employe.name')->label('Employé'),
                TextEntry::make('matricule')->label('Matricule'),
                TextEntry::make('type.name')->label('Type'),
                TextEntry::make('modele.titre')->label('Modèle')->placeholder('—'),
                TextEntry::make('date_effet')->label("Date d'effet")->date('d/m/Y'),
                TextEntry::make('ancienne_fonction')->label('Ancienne fonction')->placeholder('—'),
                TextEntry::make('nouvelle_fonction')->label('Nouvelle fonction')->placeholder('—'),
                TextEntry::make('ancien_grade')->label('Ancien grade')->placeholder('—'),
                TextEntry::make('nouveau_grade')->label('Nouveau grade')->placeholder('—'),
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
                TextColumn::make('date_effet')->label("Date d'effet")->date('d/m/Y'),
                TextColumn::make('nouvelle_fonction')->label('Nouvelle fonction')->placeholder('—'),
                static::statutColumn(),
            ])
            ->filters([static::statutFilter()])
            ->recordActions(static::recordActions())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'view' => ViewPromotion::route('/{record}'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }
}
