<?php

namespace App\Filament\Support;

use App\Exceptions\WorkflowException;
use App\Services\DocumentGenerationService;
use App\Services\TemplateRendererService;
use App\Services\WorkflowService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Actions Soumettre / Valider / Rejeter / Approuver. La visibilité suit la
 * Policy ; l'autorisation réelle est revérifiée par WorkflowService.
 */
class WorkflowActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [self::soumettre(), self::valider(), self::rejeter(), self::approuver(), self::apercu(), self::regenerer(), self::telecharger()];
    }

    public static function soumettre(): Action
    {
        return Action::make('soumettre')
            ->label('Soumettre')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (Model $record) => auth()->user()->can('soumettre', $record))
            ->action(fn (Model $record) => self::executer($record, 'Dossier soumis.',
                fn (WorkflowService $s) => $s->soumettre($record, auth()->user())));
    }

    public static function valider(): Action
    {
        return Action::make('valider')
            ->label('Valider')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Model $record) => auth()->user()->can('valider', $record))
            ->schema([Textarea::make('commentaire')->label('Observations (facultatif)')])
            ->action(fn (Model $record, array $data) => self::executer($record, 'Dossier validé, en attente d\'approbation.',
                fn (WorkflowService $s) => $s->valider($record, auth()->user(), $data['commentaire'] ?? null)));
    }

    public static function rejeter(): Action
    {
        return Action::make('rejeter')
            ->label('Rejeter')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Model $record) => auth()->user()->can('rejeter', $record))
            ->schema([Textarea::make('commentaire')->label('Motif du rejet')->required()])
            ->action(fn (Model $record, array $data) => self::executer($record, 'Dossier rejeté.',
                fn (WorkflowService $s) => $s->rejeter($record, auth()->user(), $data['commentaire'])));
    }

    public static function approuver(): Action
    {
        return Action::make('approuver')
            ->label('Approuver')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('La référence officielle sera attribuée à l\'approbation. Cette action est définitive.')
            ->visible(fn (Model $record) => auth()->user()->can('approuver', $record))
            ->schema([Textarea::make('commentaire')->label('Observations (facultatif)')])
            ->action(fn (Model $record, array $data) => self::executer($record, 'Dossier approuvé et référencé.',
                fn (WorkflowService $s) => $s->approuver($record, auth()->user(), $data['commentaire'] ?? null)));
    }

    /** Aperçu du rendu : copie figée si le dossier est finalisé, sinon rendu courant du modèle. */
    public static function apercu(): Action
    {
        return Action::make('apercu')
            ->label('Prévisualiser')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (Model $record) => auth()->user()->can('view', $record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalHeading(fn (Model $record) => $record->reference ?? 'Aperçu (non référencé)')
            ->modalContent(function (Model $record) {
                $html = $record->contenu_genere
                    ?? app(TemplateRendererService::class)->rendre($record->modele?->contenu ?? '<p>Aucun modèle sélectionné.</p>', $record);

                return new HtmlString('<div class="prose max-w-none">'.$html.'</div>');
            });
    }

    public static function regenerer(): Action
    {
        return Action::make('regenerer')
            ->label('Relancer le PDF')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Reprend la génération du PDF ; la référence attribuée ne change pas.')
            ->visible(fn (Model $record) => auth()->user()->can('regenerer', $record))
            ->action(function (Model $record) {
                try {
                    app(DocumentGenerationService::class)->finaliser($record);
                    Notification::make()->title('PDF généré.')->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Échec de la génération')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function telecharger(): Action
    {
        return Action::make('telecharger')
            ->label('Télécharger le PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (Model $record) => auth()->user()->can('telecharger', $record))
            ->url(fn (Model $record) => route('dossiers.fichier', [$record::rhModule()->morphAlias(), $record->getKey()]))
            ->openUrlInNewTab();
    }

    /** @param Closure(WorkflowService): mixed $operation */
    public static function executer(Model $record, string $succes, Closure $operation): void
    {
        try {
            $operation(app(WorkflowService::class));
            Notification::make()->title($succes)->success()->send();
        } catch (WorkflowException|AuthorizationException $e) {
            Notification::make()->title('Action refusée')->body($e->getMessage())->danger()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Données invalides')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();
        } finally {
            $record->unsetRelation('workflow');
        }
    }
}
