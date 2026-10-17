<?php

namespace App\Providers;

use App\Enums\RhModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Morph map explicite : jamais de noms de classes en base.
        Relation::enforceMorphMap(
            ['user' => User::class] + collect(RhModule::cases())
                ->mapWithKeys(fn (RhModule $m) => [$m->morphAlias() => $m->recordClass()])
                ->all()
        );
    }
}
