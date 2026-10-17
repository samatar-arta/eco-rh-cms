@php
    $record = $getRecord();
    $workflow = $record?->workflow;
    $lignes = $workflow?->timeline() ?? collect();
@endphp

<div class="space-y-3">
    @if ($workflow)
        <p class="text-sm">
            Statut : <strong>{{ $workflow->statut->value }}</strong>
            @if ($workflow->estEnRetard())
                <span class="ml-2 font-semibold text-danger-600">En retard</span>
            @endif
        </p>
    @endif

    @forelse ($lignes as $ligne)
        <div class="border-l-2 border-gray-300 pl-3 text-sm dark:border-gray-600">
            <div class="font-medium">
                {{ $ligne['date_action']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                — {{ str_replace('_', ' ', $ligne['etape']) }}
                <span class="text-gray-500">({{ $ligne['statut'] }})</span>
                @if ($ligne['en_retard'])
                    <span class="ml-1 text-danger-600">⚠ retard</span>
                @endif
            </div>
            <div class="text-gray-600 dark:text-gray-400">
                {{ $ligne['utilisateur'] ?? 'Système' }}
                @if ($ligne['role']) · {{ $ligne['role'] }} @endif
                @if ($ligne['duree_minutes'] !== null) · durée depuis l'étape précédente : {{ intdiv($ligne['duree_minutes'], 60) }} h {{ $ligne['duree_minutes'] % 60 }} min @endif
            </div>
            @if ($ligne['commentaire'])
                <div class="italic">« {{ $ligne['commentaire'] }} »</div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Aucun historique : le dossier n'est pas encore entré dans le workflow.</p>
    @endforelse
</div>
