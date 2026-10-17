<?php

namespace App\Models;

use App\Enums\RhModule;
use Illuminate\Database\Eloquent\Model;

class ReferenceSequence extends Model
{
    protected $fillable = ['module', 'annee', 'dernier_numero', 'prefixe', 'format', 'largeur'];

    protected function casts(): array
    {
        return [
            'module' => RhModule::class,
            'annee' => 'integer',
            'dernier_numero' => 'integer',
            'largeur' => 'integer',
        ];
    }

    /** Référence correspondant au dernier numéro attribué. */
    public function formater(): string
    {
        return strtr($this->format, [
            '{numero}' => str_pad((string) $this->dernier_numero, $this->largeur, '0', STR_PAD_LEFT),
            '{prefixe}' => $this->prefixe,
            '{annee}' => (string) $this->annee,
        ]);
    }
}
