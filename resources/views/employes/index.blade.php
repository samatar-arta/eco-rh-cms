@extends('layouts.app')

@section('title', 'Employés')

@php
    // libellé, badge, pastille, couleur du chiffre
    $statuts_ui = [
        'ACTIF' => ['Actif', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20', 'bg-emerald-500', 'text-emerald-600 dark:text-emerald-400'],
        'SUSPENDU' => ['Suspendu', 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20', 'bg-amber-500', 'text-amber-600 dark:text-amber-400'],
        'SORTI' => ['Sorti', 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-400/20', 'bg-slate-400', 'text-slate-500 dark:text-slate-400'],
    ];
    $defaut = ['—', 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-400/20', 'bg-slate-400', 'text-slate-500'];
    $palette = [
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
        'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    ];
    $total = $effectifs->sum();
    $carte = 'rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-900';
    $champ = 'rounded-xl border-slate-200 bg-white text-sm shadow-sm focus:border-accent-400 focus:ring-2 focus:ring-accent-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:ring-accent-500/30';
@endphp

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Employés</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Consultez, recherchez et filtrez le personnel de votre périmètre.</p>
    </div>

    {{-- Effectifs --}}
    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="{{ $carte }} border-slate-200/70 dark:border-slate-800">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Effectif total</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($total, 0, ',', ' ') }}</p>
        </div>
        @foreach ($statuts as $s)
            @php [$libelle, , $pastille, $texte] = $statuts_ui[$s->value] ?? $defaut; @endphp
            <a href="{{ route('employes.index', ['statut' => $s->value]) }}"
               class="{{ $carte }} transition hover:-translate-y-0.5 hover:shadow-md {{ $statut === $s->value ? 'border-accent-300 ring-2 ring-accent-200 dark:ring-accent-500/40' : 'border-slate-200/70 dark:border-slate-800' }}">
                <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <span class="h-2 w-2 rounded-full {{ $pastille }}"></span>{{ $libelle }}
                </p>
                <p class="mt-2 text-3xl font-bold {{ $texte }}">{{ number_format($effectifs[$s->value] ?? 0, 0, ',', ' ') }}</p>
            </a>
        @endforeach
    </div>

    {{-- Recherche --}}
    <form method="GET" action="{{ route('employes.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            <input type="search" name="q" value="{{ $q }}" maxlength="100" placeholder="Rechercher par nom, matricule ou fonction…"
                   class="{{ $champ }} w-full py-2.5 pl-10 pr-4 placeholder:text-slate-400">
        </div>
        <select name="statut" class="{{ $champ }} py-2.5 pl-4 pr-9">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $s)
                <option value="{{ $s->value }}" @selected($statut === $s->value)>{{ ($statuts_ui[$s->value] ?? $defaut)[0] }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-accent-500/25 transition hover:brightness-110">Rechercher</button>
        @if ($q !== '' || $statut !== '')
            <a href="{{ route('employes.index') }}" class="rounded-xl px-4 py-2.5 text-center text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">Réinitialiser</a>
        @endif
    </form>

    <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ number_format($employes->total(), 0, ',', ' ') }}</span> résultat(s)
        @if ($employes->total() > 0)
            · affichage {{ $employes->firstItem() }}–{{ $employes->lastItem() }}
        @endif
    </p>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="cell">Employé</th>
                        <th scope="col" class="cell">Matricule</th>
                        <th scope="col" class="cell">Fonction</th>
                        <th scope="col" class="cell">Département</th>
                        <th scope="col" class="cell">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($employes as $employe)
                        @php
                            [$libelle, $badge, $pastille] = $statuts_ui[$employe->statut_employe->value] ?? $defaut;
                            $mots = preg_split('/\s+/', trim(preg_replace('/^(M|MME|MLLE)\s+/i', '', $employe->name)));
                            $initiales = mb_strtoupper(mb_substr($mots[0] ?? '?', 0, 1).mb_substr($mots[1] ?? '', 0, 1));
                            $couleur = $palette[crc32($employe->matricule) % count($palette)];
                        @endphp
                        <tr class="transition hover:bg-accent-50/50 dark:hover:bg-slate-800/60">
                            <td class="cell">
                                <div class="flex items-center gap-3">
                                    <span class="avatar flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $couleur }}">{{ $initiales }}</span>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $employe->name }}</span>
                                </div>
                            </td>
                            <td class="cell whitespace-nowrap">
                                <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $employe->matricule }}</span>
                            </td>
                            <td class="cell text-slate-600 dark:text-slate-300">{{ $employe->fonction ?? '—' }}</td>
                            <td class="cell text-slate-600 dark:text-slate-300">
                                <span>{{ $employe->departement?->name ?? '—' }}</span>
                                @if ($employe->departement?->societe)
                                    <span class="ml-1 rounded bg-accent-50 px-1.5 py-0.5 text-[11px] font-semibold text-accent-600 dark:bg-accent-500/15 dark:text-accent-400">{{ $employe->departement->societe->code }}</span>
                                @endif
                            </td>
                            <td class="cell whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $badge }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $pastille }}"></span>{{ $libelle }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <p class="text-base font-medium text-slate-700 dark:text-slate-200">Aucun employé trouvé</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Modifiez votre recherche ou réinitialisez les filtres.</p>
                            </td>
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
