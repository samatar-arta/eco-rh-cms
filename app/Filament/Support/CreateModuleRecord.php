<?php

namespace App\Filament\Support;

use App\Exceptions\WorkflowException;
use App\Services\WorkflowService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

/** Création d'un dossier : brouillon par défaut, ou création + soumission. */
abstract class CreateModuleRecord extends CreateRecord
{
    public bool $soumettreApresCreation = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $resource = static::getResource();
        $data[$resource::creatorColumn()] = auth()->id();

        return $resource::completerDonnees($data);
    }

    protected function afterCreate(): void
    {
        $service = app(WorkflowService::class);

        try {
            $service->ouvrir($this->record, auth()->user());
            if ($this->soumettreApresCreation) {
                $service->soumettre($this->record, auth()->user());
            }
        } catch (WorkflowException|AuthorizationException $e) {
            Notification::make()->title('Workflow')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Enregistrer en brouillon'),
            Action::make('creerEtSoumettre')
                ->label('Créer et soumettre')
                ->color('success')
                ->action(function () {
                    $this->soumettreApresCreation = true;
                    $this->create();
                }),
            $this->getCancelFormAction(),
        ];
    }
}
