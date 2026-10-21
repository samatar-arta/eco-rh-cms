{{-- Habillage commun des panneaux rh et cms : cartes arrondies, ombres douces, navigation lisible. Sans build : variables Filament (--primary-*, --gray-*). --}}
<style id="rh-panel-style">
    :root {
        --rh-radius: 1rem;
        --rh-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px -12px rgba(15, 23, 42, 0.18);
        --rh-shadow-hover: 0 2px 4px rgba(15, 23, 42, 0.06), 0 16px 32px -12px rgba(15, 23, 42, 0.28);
    }

    /* Fond de l'application */
    .fi-body {
        background:
            radial-gradient(1200px 500px at 100% -10%, color-mix(in srgb, var(--primary-500) 10%, transparent), transparent 70%),
            var(--gray-50);
    }

    .dark .fi-body {
        background:
            radial-gradient(1200px 500px at 100% -10%, color-mix(in srgb, var(--primary-500) 14%, transparent), transparent 70%),
            var(--gray-950);
    }

    /* Barre du haut : verre dépoli */
    .fi-topbar {
        -webkit-backdrop-filter: blur(10px);
        backdrop-filter: blur(10px);
    }

    /* Titres de page */
    .fi-header-heading {
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    /* Navigation latérale */
    .fi-sidebar {
        box-shadow: 4px 0 24px -16px rgba(15, 23, 42, 0.35);
    }

    .fi-sidebar-item-btn {
        border-radius: 0.65rem;
        transition: background-color 0.15s ease, transform 0.15s ease;
    }

    .fi-sidebar-item-btn:hover {
        transform: translateX(2px);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        box-shadow: inset 3px 0 0 var(--primary-600);
        font-weight: 600;
    }

    .fi-sidebar-group-label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    /* Cartes : sections, tables, widgets */
    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat {
        border-radius: var(--rh-radius);
        box-shadow: var(--rh-shadow);
    }

    .fi-wi-stats-overview-stat {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-2px);
        box-shadow: var(--rh-shadow-hover);
    }

    .fi-wi-stats-overview-stat-value {
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    /* Tables : en-têtes discrets, lignes réactives */
    .fi-ta-header-cell {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .fi-ta-row {
        transition: background-color 0.12s ease;
    }

    /* Boutons principaux : relief léger */
    .fi-btn.fi-color-primary:not(.fi-outlined) {
        box-shadow: 0 4px 12px -4px color-mix(in srgb, var(--primary-600) 70%, transparent);
    }

    /* Champs : coins arrondis cohérents */
    .fi-input-wrp {
        border-radius: 0.65rem;
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-item-btn,
        .fi-wi-stats-overview-stat {
            transition: none;
        }

        .fi-sidebar-item-btn:hover,
        .fi-wi-stats-overview-stat:hover {
            transform: none;
        }
    }
</style>
