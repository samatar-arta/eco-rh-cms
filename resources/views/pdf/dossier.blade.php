<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $reference }}</title>
    <style>
        @page { margin: 30px 40px 70px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #111; }
        .entete { background-color: #002060; color: #FFFFFF; font-weight: bold; font-size: 10px; line-height: 1.4; padding: 8px 12px; text-align: left; margin-bottom: 24px; }
        .pied { position: fixed; left: 0; right: 0; bottom: -45px; background-color: #002060; color: #FFFFFF; font-weight: bold; font-size: 9px; }
        .pied table { width: 100%; border-collapse: collapse; }
        .pied td { padding: 6px 12px; color: #FFFFFF; }
    </style>
</head>
<body>
    <div class="entete">
        DJIBOUTI LABOUR SERVICES. FZCO (DLS)<br>
        Doraleh – République de Djibouti<br>
        Tel : +253 21 317 831<br>
        PO Box 2107<br>
        Licence N° 035
    </div>

    <div class="pied">
        <table>
            <tr>
                <td>Approuvé par : {{ $signataire }} - {{ $roleSignataire }}</td>
                <td style="text-align:right">Date d'approbation : {{ $dateApprobation }}</td>
            </tr>
        </table>
    </div>

    <div class="contenu">{!! $contenu !!}</div>
</body>
</html>
