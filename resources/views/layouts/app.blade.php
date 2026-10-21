<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    {{-- Applique les préférences avant l'affichage (pas de flash de thème). --}}
    <script>
        (function () {
            var get = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };
            var root = document.documentElement;
            var theme = get('rh.theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            root.classList.toggle('dark', theme === 'dark');
            root.dataset.accent = get('rh.accent') || 'indigo';
            root.dataset.density = get('rh.density') || 'comfortable';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-b from-accent-50/70 via-slate-50 to-slate-50 text-slate-800 antialiased dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 dark:text-slate-200">
    <header class="sticky top-0 z-20 border-b border-white/60 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 text-sm font-bold text-white shadow-md shadow-accent-500/30">RH</span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">ECOSYSTEME RH DLS</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Djibouti Labour Services</p>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                {{-- Couleur d'accent --}}
                <div class="flex items-center gap-1.5" role="group" aria-label="Couleur d'accent">
                    @foreach (['indigo' => 'bg-indigo-500', 'emerald' => 'bg-emerald-500', 'rose' => 'bg-rose-500', 'amber' => 'bg-amber-500'] as $nom => $classe)
                        <button type="button" data-set-accent="{{ $nom }}" title="Accent {{ $nom }}" aria-label="Accent {{ $nom }}"
                                class="h-5 w-5 rounded-full {{ $classe }} ring-offset-2 ring-offset-white transition hover:scale-110 dark:ring-offset-slate-900"></button>
                    @endforeach
                </div>

                {{-- Densité --}}
                <button type="button" id="toggle-density" title="Densité du tableau" aria-label="Basculer la densité du tableau"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </button>

                {{-- Thème --}}
                <button type="button" id="toggle-theme" title="Thème clair / sombre" aria-label="Basculer le thème clair ou sombre"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                <a href="{{ url('/rh') }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Panneau RH
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        @yield('content')
    </main>

    <script>
        (function () {
            var root = document.documentElement;
            var set = function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} };

            document.getElementById('toggle-theme').addEventListener('click', function () {
                var dark = root.classList.toggle('dark');
                set('rh.theme', dark ? 'dark' : 'light');
            });

            document.getElementById('toggle-density').addEventListener('click', function () {
                var next = root.dataset.density === 'compact' ? 'comfortable' : 'compact';
                root.dataset.density = next;
                set('rh.density', next);
            });

            var marquer = function () {
                document.querySelectorAll('[data-set-accent]').forEach(function (b) {
                    b.classList.toggle('ring-2', b.dataset.setAccent === root.dataset.accent);
                    b.classList.toggle('ring-slate-400', b.dataset.setAccent === root.dataset.accent);
                });
            };
            document.querySelectorAll('[data-set-accent]').forEach(function (b) {
                b.addEventListener('click', function () {
                    root.dataset.accent = b.dataset.setAccent;
                    set('rh.accent', b.dataset.setAccent);
                    marquer();
                });
            });
            marquer();
        })();
    </script>
</body>
</html>
