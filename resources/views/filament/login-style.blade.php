{{-- Page de connexion : fond photo du port, carte centrée, boutons bien visibles. Chargé par les panneaux rh et cms (scope Login). --}}
<style>
    .fi-simple-layout {
        background:
            linear-gradient(180deg, rgba(8, 25, 50, 0.55) 0%, rgba(8, 25, 50, 0.72) 100%),
            url('{{ asset('images/bg-login.jpg') }}') center / cover no-repeat fixed;
    }

    .fi-simple-main-ctn {
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem;
    }

    .fi-simple-main {
        margin: 0 auto;
        max-width: 28rem;
        padding: 2.5rem 2rem;
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.93);
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.55);
        border: 1px solid rgba(255, 255, 255, 0.35);
    }

    .dark .fi-simple-main {
        background: rgba(15, 23, 42, 0.9);
        border-color: rgba(255, 255, 255, 0.12);
    }

    /* Titre et logo centrés */
    .fi-simple-header {
        align-items: center;
        text-align: center;
    }

    .fi-simple-header-heading {
        font-size: 1.6rem;
        font-weight: 800;
    }

    /* Champs sur toute la largeur de la carte */
    .fi-simple-main .fi-input-wrp {
        box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.18);
        border-radius: 0.6rem;
    }

    .fi-simple-main .fi-fo-field-label-content {
        font-weight: 600;
    }

    /* Boutons : larges, contrastés, lisibles */
    .fi-simple-main .fi-btn {
        width: 100%;
        justify-content: center;
        padding: 0.85rem 1.25rem;
        border-radius: 0.65rem;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #fff;
        background: linear-gradient(180deg, #0f6bc4 0%, #0a4f96 100%);
        box-shadow: 0 6px 16px -4px rgba(10, 79, 150, 0.65);
        transition: transform 0.15s ease, filter 0.15s ease, box-shadow 0.15s ease;
    }

    .fi-simple-main .fi-btn:hover {
        filter: brightness(1.12);
        transform: translateY(-1px);
        box-shadow: 0 10px 22px -6px rgba(10, 79, 150, 0.75);
    }

    .fi-simple-main .fi-btn:focus-visible {
        outline: 3px solid #fbbf24;
        outline-offset: 2px;
    }

    .fi-simple-main .fi-btn .fi-btn-label {
        color: #fff;
    }

    @media (max-width: 480px) {
        .fi-simple-main {
            padding: 2rem 1.25rem;
        }
    }
</style>
