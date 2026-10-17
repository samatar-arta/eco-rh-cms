<?php

namespace App\Mail;

use App\Models\Contact;
use App\Models\Paylist;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BulletinPaieMail extends Mailable
{
    public function __construct(public Paylist $paylist, public Contact $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Bulletin de paie {$this->paylist->periode()} - {$this->paylist->matricule}");
    }

    public function content(): Content
    {
        return new Content(htmlString: sprintf(
            '<p>Bonjour %s,</p><p>Veuillez trouver ci-joint le bulletin de paie de l\'employé <strong>%s</strong> pour la période %s.</p><p>Ce message est confidentiel et ne doit pas être transféré.</p><p>DLS - Djibouti Labour Service</p>',
            e(trim($this->contact->prenom.' '.$this->contact->nom)),
            e($this->paylist->matricule),
            e($this->paylist->periode()),
        ));
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(config('rh.paylist.disk'), $this->paylist->fichier_path)
                ->as(sprintf('bulletin-%s-%d-%02d.pdf', $this->paylist->matricule, $this->paylist->annee, $this->paylist->mois))
                ->withMime('application/pdf'),
        ];
    }
}
