<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification interne unique (base de données, e-mail si activé dans
 * `rh.notifications.mail`). `kind` : soumis, a_valider, a_approuver, rejete,
 * approuve, retard, import_termine, import_erreur, distribution_echec...
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return config('rh.notifications.mail') && filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title($this->title)
            ->body($this->body)
            ->actions($this->url ? [Action::make('voir')->label('Ouvrir')->url($this->url)->markAsRead()] : [])
            ->getDatabaseMessage() + ['kind' => $this->kind];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->body);

        return $this->url ? $mail->action('Ouvrir', $this->url) : $mail;
    }
}
