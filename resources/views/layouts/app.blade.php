<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
            <span class="text-sm font-semibold tracking-wide text-slate-900">ECOSYSTEME RH DLS</span>
            <a href="{{ url('/rh') }}" class="text-sm text-slate-500 hover:text-slate-900">Retour au panneau RH</a>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        @yield('content')
    </main>
</body>
</html>
