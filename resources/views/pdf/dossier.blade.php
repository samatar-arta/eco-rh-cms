<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $reference }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #111; }
        .entete { background: #1e4a8c; color: #fff; padding: 12px 16px; margin-bottom: 24px; }
        .entete h1 { font-size: 16px; margin: 0; }
        .entete p { font-size: 10px; margin: 2px 0 0; }
        .signature { margin-top: 48px; border: 1px solid #888; padding: 10px; font-size: 10px; }
        .signature p { margin: 2px 0; }
        .mention { color: #555; font-style: italic; }
    </style>
</head>
<body>
    <div class="entete">
        <h1>DJIBOUTI LABOUR SERVICES FZCO (DLS)</h1>
        <p>Direction de l'Administration et des Ressources Humaines</p>
    </div>

    <div class="contenu">{!! $contenu !!}</div>

    <div class="signature">
        <p><strong>Signature électronique interne</strong></p>
        <p>Approuvé par : {{ $signataire }} — {{ $roleSignataire }}</p>
        <p>Date d'approbation : {{ $dateApprobation }}</p>
        <p>Identifiant du dossier : {{ $identifiant }}</p>
        <p>Empreinte du contenu (SHA-256) : {{ $empreinte }}</p>
        <p class="mention">Signature interne DLS tracée dans le système ; elle ne constitue pas une signature électronique qualifiée.</p>
    </div>
</body>
</html>
