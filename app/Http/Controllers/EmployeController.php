<?php

namespace App\Http\Controllers;

use App\Enums\StatutEmploye;
use App\Models\Employe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeController extends Controller
{
    private const PAR_PAGE = 20;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employe::class);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'in:'.implode(',', array_column(StatutEmploye::cases(), 'value'))],
        ]);
        $q = trim($data['q'] ?? '');

        $employes = Employe::query()
            ->visibleTo($request->user())
            ->with(['departement:id,name,societe_id', 'departement.societe:id,code'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '\\%_').'%';
                $query->where(fn ($w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('matricule', 'like', $like)
                    ->orWhere('fonction', 'like', $like));
            })
            ->when($data['statut'] ?? null, fn ($query, $statut) => $query->where('statut_employe', $statut))
            ->orderBy('name')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        return view('employes.index', [
            'employes' => $employes,
            'q' => $q,
            'statut' => $data['statut'] ?? '',
            'statuts' => StatutEmploye::cases(),
        ]);
    }
}
