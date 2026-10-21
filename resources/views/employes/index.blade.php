@extends('layouts.app')

@section('title', 'Employés')

@php
    $badges = [
        'ACTIF' => ['Actif', 'bg-green-100 text-green-800 ring-green-600/20'],
        'SUSPENDU' => ['Suspendu', 'bg-amber-100 text-amber-800 ring-amber-600/20'],
        'SORTI' => ['Sorti', 'bg-slate-100 text-slate-600 ring-slate-500/20'],
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Employés</h1>
            <p class="mt-1 text-sm text-slate-500">{{ number_format($employes->total(), 0, ',', ' ') }} résultat(s)</p>
        </div>

        <form method="GET" action="{{ route('employes.index') }}" class="flex flex-col gap-2 sm:flex-row">
            <input type="search" name="q" value="{{ $q }}" maxlength="100"
                   placeholder="Nom, matricule ou fonction"
                   class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-72">
            <select name="statut" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $s)
                    <option value="{{ $s->value }}" @selected($statut === $s->value)>{{ $badges[$s->value][0] }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Rechercher</button>
            @if ($q !== '' || $statut !== '')
                <a href="{{ route('employes.index') }}" class="rounded-md px-3 py-2 text-center text-sm text-slate-500 hover:text-slate-900">Réinitialiser</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Matricule</th>
                        <th scope="col" class="px-4 py-3">Nom</th>
                        <th scope="col" class="px-4 py-3">Fonction</th>
                        <th scope="col" class="px-4 py-3">Département</th>
                        <th scope="col" class="px-4 py-3">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employes as $employe)
                        @php
                            [$libelle, $classes] = $badges[$employe->statut_employe->value]
                                ?? [$employe->statut_employe->value, 'bg-slate-100 text-slate-600 ring-slate-500/20'];
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-slate-600">{{ $employe->matricule }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $employe->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $employe->fonction ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $employe->departement?->name ?? '—' }}
                                @if ($employe->departement?->societe)
                                    <span class="ml-1 text-xs text-slate-400">{{ $employe->departement->societe->code }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $classes }}">{{ $libelle }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-500">Aucun employé ne correspond à la recherche.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $employes->links() }}
    </div>
@endsection
