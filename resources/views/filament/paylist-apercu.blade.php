@php($lignes = $getState() ?? [])
@if ($lignes === [])
    <p class="text-sm text-gray-500">Aucune ligne.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="border-b">
                    <th class="py-2 pe-4">Fichier</th>
                    <th class="py-2 pe-4">Matricule</th>
                    <th class="py-2 pe-4">Résultat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lignes as $ligne)
                    <tr class="border-b">
                        <td class="py-1 pe-4">{{ $ligne['entree'] }}</td>
                        <td class="py-1 pe-4">{{ $ligne['matricule'] ?? '—' }}</td>
                        <td class="py-1 pe-4 {{ ($ligne['statut'] ?? '') === 'OK' ? 'text-success-600' : 'text-danger-600' }}">{{ $ligne['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
