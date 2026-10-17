<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/** Moteur PDF (dompdf). Ressources distantes désactivées : aucun chargement externe. */
class PdfGenerationService
{
    public function depuisHtml(string $html): string
    {
        return Pdf::setOptions([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ])->loadHTML($html)->setPaper('a4')->output();
    }
}
