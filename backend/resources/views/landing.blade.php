<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Gestion de copropriété simple pour syndics bénévoles</title>
    <meta name="description" content="Cotisations, dépenses, trésorerie et rappels WhatsApp pour la gestion de votre copropriété au Maroc. Essai gratuit 15 jours, plan gratuit permanent jusqu'à 6 appartements.">
    <link rel="icon" type="image/svg+xml" href="/favicon-brand.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-50: #effefa; --brand-100: #c8fdf0; --brand-200: #92fae2; --brand-300: #55efd1;
            --brand-400: #22d9bc; --brand-500: #0abda3; --brand-600: #059685; --brand-700: #08786c;
            --brand-800: #0b5f57; --brand-900: #0d4e48; --brand-950: #002d2a;
            --slate-50: #f8fafc; --slate-100: #f1f5f9; --slate-200: #e2e8f0; --slate-300: #cbd5e1;
            --slate-400: #94a3b8; --slate-500: #64748b; --slate-600: #475569; --slate-700: #334155;
            --slate-800: #1e293b; --slate-900: #0f172a;
            --amber-500: #f59e0b;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            color: var(--slate-800); background: #ffffff; -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        a { color: inherit; }
        img, svg { max-width: 100%; }

        /* Nav */
        header.nav {
            position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.92);
            backdrop-filter: blur(8px); border-bottom: 1px solid var(--slate-200);
        }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; height: 72px; }
        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-badge {
            display: flex; align-items: center; justify-content: center; width: 38px; height: 38px;
            border-radius: 11px; background: var(--brand-600); color: white; flex-shrink: 0;
        }
        .logo-text { font-size: 18px; font-weight: 800; letter-spacing: -0.01em; color: var(--slate-900); }
        .logo-text span { color: var(--brand-600); }
        .nav-links { display: flex; gap: 32px; font-size: 14px; font-weight: 500; color: var(--slate-600); }
        .nav-links a:hover { color: var(--slate-900); }
        .nav-cta { display: flex; align-items: center; gap: 12px; }
        @media (max-width: 800px) { .nav-links { display: none; } }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px 22px; border-radius: 10px; font-weight: 600; font-size: 14.5px;
            text-decoration: none; border: 1px solid transparent; cursor: pointer; white-space: nowrap;
        }
        .btn-primary { background: var(--brand-600); color: #fff; box-shadow: 0 1px 2px rgba(5,150,133,0.15); }
        .btn-primary:hover { background: var(--brand-700); }
        .btn-secondary { background: #fff; color: var(--slate-700); border-color: var(--slate-300); }
        .btn-secondary:hover { background: var(--slate-50); }
        .btn-lg { padding: 14px 28px; font-size: 16px; border-radius: 12px; }
        .btn-ghost { color: var(--slate-600); font-weight: 600; font-size: 15px; text-decoration: none; }
        .btn-ghost:hover { color: var(--slate-900); }

        /* Hero */
        .hero { padding: 88px 0 72px; }
        .hero-grid { display: grid; grid-template-columns: 1fr; gap: 48px; align-items: center; }
        @media (min-width: 960px) { .hero-grid { grid-template-columns: 1.05fr 0.95fr; } }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px; background: var(--brand-50); color: var(--brand-800);
            border: 1px solid var(--brand-200); padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
        }
        h1 { font-size: 44px; line-height: 1.12; letter-spacing: -0.02em; color: var(--slate-900); margin: 20px 0 18px; }
        @media (max-width: 640px) { h1 { font-size: 32px; } }
        .hero p.lead { font-size: 18px; line-height: 1.6; color: var(--slate-600); max-width: 520px; margin: 0 0 28px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin-bottom: 16px; }
        .hero-note { font-size: 13.5px; color: var(--slate-500); }
        .hero-note strong { color: var(--slate-700); }

        /* Hero mockup card */
        .mockup {
            background: var(--slate-900); border-radius: 20px; padding: 18px;
            box-shadow: 0 24px 48px -12px rgba(15,23,42,0.35);
        }
        .mockup-card { background: #fff; border-radius: 13px; overflow: hidden; }
        .mockup-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--slate-100); }
        .mockup-title { font-size: 13px; font-weight: 700; color: var(--slate-800); }
        .mockup-badge { font-size: 11px; font-weight: 700; background: var(--brand-50); color: var(--brand-700); padding: 3px 9px; border-radius: 999px; }
        .mockup-row { display: flex; align-items: center; justify-content: space-between; padding: 11px 18px; border-bottom: 1px solid var(--slate-50); font-size: 12.5px; }
        .mockup-row:last-child { border-bottom: none; }
        .mockup-name { font-weight: 600; color: var(--slate-700); }
        .mockup-dots { display: flex; gap: 5px; }
        .dot { width: 16px; height: 16px; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 800; color: #fff; }
        .dot-ok { background: var(--brand-500); }
        .dot-bad { background: #f43f5e; }
        .dot-mid { background: var(--amber-500); }

        /* Sections */
        section { padding: 76px 0; }
        .section-head { text-align: center; max-width: 640px; margin: 0 auto 48px; }
        .kicker { color: var(--brand-700); font-weight: 700; font-size: 13px; letter-spacing: 0.06em; text-transform: uppercase; }
        h2 { font-size: 32px; letter-spacing: -0.01em; color: var(--slate-900); margin: 10px 0 12px; }
        .section-head p { color: var(--slate-600); font-size: 16px; line-height: 1.6; }

        .problem-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .problem-card { background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 14px; padding: 26px; }
        .problem-card .icon { font-size: 24px; margin-bottom: 12px; }
        .problem-card h3 { font-size: 16px; margin: 0 0 8px; color: var(--slate-900); }
        .problem-card p { font-size: 14.5px; color: var(--slate-600); line-height: 1.55; margin: 0; }

        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .feature-card { border: 1px solid var(--slate-200); border-radius: 16px; padding: 26px; transition: box-shadow .15s, border-color .15s; }
        .feature-card:hover { border-color: var(--brand-200); box-shadow: 0 8px 24px -8px rgba(5,150,133,0.15); }
        .feature-icon {
            width: 42px; height: 42px; border-radius: 11px; background: var(--brand-50); color: var(--brand-700);
            display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 14px;
        }
        .feature-card h3 { font-size: 16px; margin: 0 0 8px; color: var(--slate-900); }
        .feature-card p { font-size: 14.5px; color: var(--slate-600); line-height: 1.55; margin: 0; }

        .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 32px; counter-reset: step; }
        .step { position: relative; padding-left: 52px; }
        .step .num {
            position: absolute; left: 0; top: 0; width: 38px; height: 38px; border-radius: 50%;
            background: var(--brand-600); color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 15px;
        }
        .step h3 { font-size: 16px; margin: 4px 0 8px; color: var(--slate-900); }
        .step p { font-size: 14.5px; color: var(--slate-600); line-height: 1.55; margin: 0; }

        /* Pricing */
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 18px; }
        .plan { border: 1px solid var(--slate-200); border-radius: 16px; padding: 26px 22px; display: flex; flex-direction: column; }
        .plan.featured { border-color: var(--brand-500); box-shadow: 0 12px 32px -12px rgba(5,150,133,0.28); position: relative; }
        .plan-badge {
            position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
            background: var(--brand-600); color: #fff; font-size: 11.5px; font-weight: 700;
            padding: 4px 12px; border-radius: 999px; white-space: nowrap;
        }
        .plan-name { font-size: 15px; font-weight: 700; color: var(--slate-900); }
        .plan-range { font-size: 13px; color: var(--slate-500); margin: 4px 0 18px; }
        .plan-price { font-size: 30px; font-weight: 800; color: var(--slate-900); }
        .plan-price span { font-size: 13px; font-weight: 500; color: var(--slate-500); }
        .plan-cta { margin-top: 20px; }
        .pricing-note { text-align: center; color: var(--slate-500); font-size: 13.5px; margin-top: 28px; }

        /* Trust */
        .trust { background: var(--slate-50); border-radius: 24px; padding: 48px; }
        .trust-grid { display: grid; grid-template-columns: 1fr; gap: 32px; }
        @media (min-width: 860px) { .trust-grid { grid-template-columns: 1fr 1fr; } }
        .trust h3 { font-size: 17px; color: var(--slate-900); margin: 0 0 10px; }
        .trust p { font-size: 14.5px; color: var(--slate-600); line-height: 1.6; margin: 0; }

        /* FAQ */
        .faq-list { max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; }
        details { border: 1px solid var(--slate-200); border-radius: 12px; padding: 16px 20px; }
        summary { cursor: pointer; font-weight: 600; font-size: 15px; color: var(--slate-900); list-style: none; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: '+'; float: right; color: var(--brand-600); font-weight: 700; font-size: 18px; }
        details[open] summary::after { content: '−'; }
        details p { font-size: 14.5px; color: var(--slate-600); line-height: 1.6; margin: 12px 0 0; }

        /* Final CTA */
        .final-cta {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-900));
            border-radius: 24px; padding: 56px 40px; text-align: center; color: #fff;
        }
        .final-cta h2 { color: #fff; }
        .final-cta p { color: var(--brand-100); font-size: 16px; max-width: 480px; margin: 0 auto 28px; }
        .final-cta .btn-primary { background: #fff; color: var(--brand-800); }
        .final-cta .btn-primary:hover { background: var(--brand-50); }

        /* Footer */
        footer { border-top: 1px solid var(--slate-200); padding: 40px 0; }
        .footer-inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px; }
        .footer-links { display: flex; gap: 22px; font-size: 14px; color: var(--slate-600); }
        .footer-copy { font-size: 13px; color: var(--slate-400); }
    </style>
</head>
<body>

<header class="nav">
    <div class="wrap nav-inner">
        <a href="/" class="logo">
            <span class="logo-badge">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M9 6h.01M9 10h.01M9 14h.01M15 6h.01M15 10h.01M15 14h.01"/></svg>
            </span>
            <span class="logo-text">Atlasoft <span>Syndic</span></span>
        </a>
        <nav class="nav-links">
            <a href="#fonctionnalites">Fonctionnalités</a>
            <a href="#tarifs">Tarifs</a>
            <a href="#faq">FAQ</a>
        </nav>
        <div class="nav-cta">
            <a class="btn-ghost" href="{{ config('app.frontend_url') }}/login">Se connecter</a>
            <a class="btn btn-primary" href="{{ config('app.frontend_url') }}/register">Essai gratuit</a>
        </div>
    </div>
</header>

<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <span class="eyebrow">🇲🇦 Conçu pour les syndics bénévoles au Maroc</span>
            <h1>La gestion de votre copropriété, enfin simple.</h1>
            <p class="lead">Cotisations, dépenses, trésorerie et rappels WhatsApp — tout au même endroit. Pas besoin d'être informaticien, ni de payer pour des outils que vous n'utiliserez jamais.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="{{ config('app.frontend_url') }}/register">Démarrer gratuitement</a>
                <a class="btn-ghost" href="#tarifs">Voir les tarifs →</a>
            </div>
            <p class="hero-note"><strong>Essai gratuit de 15 jours</strong>, sans carte bancaire. <strong>Plan gratuit permanent</strong> jusqu'à 6 appartements.</p>
        </div>
        <div class="mockup" aria-hidden="true">
            <div class="mockup-card">
                <div class="mockup-header">
                    <span class="mockup-title">Cotisations — Août 2026</span>
                    <span class="mockup-badge">12 appartements</span>
                </div>
                <div class="mockup-row">
                    <span class="mockup-name">101 — B. Alaoui</span>
                    <span class="mockup-dots"><span class="dot dot-ok">✓</span><span class="dot dot-ok">✓</span><span class="dot dot-ok">✓</span></span>
                </div>
                <div class="mockup-row">
                    <span class="mockup-name">102 — K. Idrissi</span>
                    <span class="mockup-dots"><span class="dot dot-ok">✓</span><span class="dot dot-mid">½</span><span class="dot dot-bad">✕</span></span>
                </div>
                <div class="mockup-row">
                    <span class="mockup-name">103 — S. Bennani</span>
                    <span class="mockup-dots"><span class="dot dot-ok">✓</span><span class="dot dot-ok">✓</span><span class="dot dot-ok">✓</span></span>
                </div>
                <div class="mockup-row">
                    <span class="mockup-name">104 — O. Fassi</span>
                    <span class="mockup-dots"><span class="dot dot-bad">✕</span><span class="dot dot-bad">✕</span><span class="dot dot-bad">✕</span></span>
                </div>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">Le quotidien d'un syndic bénévole</span>
            <h2>Cette situation vous parle ?</h2>
        </div>
        <div class="problem-grid">
            <div class="problem-card">
                <div class="icon">📊</div>
                <h3>Un tableur qui déborde</h3>
                <p>Des fichiers Excel différents chaque année, personne ne sait vraiment qui a payé quoi.</p>
            </div>
            <div class="problem-card">
                <div class="icon">📞</div>
                <h3>Des relances qui s'oublient</h3>
                <p>Relancer chaque copropriétaire en retard, un par un, par téléphone — et perdre le fil.</p>
            </div>
            <div class="problem-card">
                <div class="icon">❓</div>
                <h3>Zéro visibilité sur la caisse</h3>
                <p>Impossible de dire d'un coup d'œil combien il reste en caisse, ni où part l'argent.</p>
            </div>
        </div>
    </div>
</section>

<section id="fonctionnalites" style="background: var(--slate-50);">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">Fonctionnalités</span>
            <h2>Tout ce qu'il faut, rien de superflu</h2>
            <p>Pensé pour être utilisable dès le premier jour, sans formation.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Cotisations & paiements</h3>
                <p>Grille mensuelle par appartement : payé, partiel ou impayé, en un coup d'œil.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>Rappels WhatsApp</h3>
                <p>Relancez un impayé en un clic via votre propre WhatsApp — pas d'API coûteuse.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🧾</div>
                <h3>Dépenses catégorisées</h3>
                <p>Eau, électricité, gardiennage, entretien... chaque dépense avec son justificatif.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📈</div>
                <h3>Trésorerie en temps réel</h3>
                <p>Solde de caisse, recettes et dépenses mois par mois, sans tableur à mettre à jour.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📄</div>
                <h3>Reçus PDF automatiques</h3>
                <p>Un reçu professionnel généré pour chaque paiement, prêt à partager.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏢</div>
                <h3>Multi-immeubles</h3>
                <p>Une résidence avec plusieurs bâtiments ? Gérée nativement, sans bricolage.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Portail copropriétaire</h3>
                <p>Copropriétaires et conseil syndical consultent leurs paiements, en lecture seule.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🌐</div>
                <h3>Français & Arabe</h3>
                <p>Interface bilingue, avec mise en page adaptée (droite à gauche) en arabe.</p>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">Mise en route</span>
            <h2>Opérationnel en trois étapes</h2>
        </div>
        <div class="steps">
            <div class="step">
                <span class="num">1</span>
                <h3>Inscrivez votre résidence</h3>
                <p>Nom, nombre d'appartements, numéro WhatsApp. L'essai gratuit démarre automatiquement.</p>
            </div>
            <div class="step">
                <span class="num">2</span>
                <h3>Configurez en quelques minutes</h3>
                <p>Immeubles, types de lot et montants de cotisation, liste des appartements.</p>
            </div>
            <div class="step">
                <span class="num">3</span>
                <h3>Suivez au quotidien</h3>
                <p>Cotisations, dépenses, trésorerie et impayés, à jour en permanence.</p>
            </div>
        </div>
    </div>
</section>

<section id="tarifs" style="background: var(--slate-50);">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">Tarifs</span>
            <h2>Un prix qui suit la taille de votre résidence</h2>
            <p>Pas de mauvaise surprise : tarifs fixes affichés d'emblée, jamais présentés comme une "promo de lancement".</p>
        </div>
        <div class="pricing-grid">
            <div class="plan">
                <div class="plan-name">Gratuit</div>
                <div class="plan-range">Jusqu'à 6 appartements</div>
                <div class="plan-price">0 DH</div>
                <a class="btn btn-secondary plan-cta" href="{{ config('app.frontend_url') }}/register">Commencer</a>
            </div>
            <div class="plan">
                <div class="plan-name">Starter</div>
                <div class="plan-range">7 à 15 appartements</div>
                <div class="plan-price">50 DH <span>/ mois</span></div>
                <a class="btn btn-secondary plan-cta" href="{{ config('app.frontend_url') }}/register">Essayer 15 jours</a>
            </div>
            <div class="plan featured">
                <span class="plan-badge">Le plus choisi</span>
                <div class="plan-name">Standard</div>
                <div class="plan-range">16 à 40 appartements</div>
                <div class="plan-price">100 DH <span>/ mois</span></div>
                <a class="btn btn-primary plan-cta" href="{{ config('app.frontend_url') }}/register">Essayer 15 jours</a>
            </div>
            <div class="plan">
                <div class="plan-name">Plus</div>
                <div class="plan-range">41 à 70 appartements</div>
                <div class="plan-price">160 DH <span>/ mois</span></div>
                <a class="btn btn-secondary plan-cta" href="{{ config('app.frontend_url') }}/register">Essayer 15 jours</a>
            </div>
            <div class="plan">
                <div class="plan-name">Premium</div>
                <div class="plan-range">71 à 100 appartements</div>
                <div class="plan-price">220 DH <span>/ mois</span></div>
                <a class="btn btn-secondary plan-cta" href="{{ config('app.frontend_url') }}/register">Essayer 15 jours</a>
            </div>
        </div>
        <p class="pricing-note">~20% de remise en engagement annuel. Plus de 100 appartements ? <a href="#faq" style="color: var(--brand-700); font-weight: 600;">Contactez-nous</a> pour un tarif sur devis.</p>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="trust">
            <div class="trust-grid">
                <div>
                    <h3>💬 Pourquoi pas de relances WhatsApp automatiques ?</h3>
                    <p>Parce que l'API WhatsApp Business coûte cher et complique l'expérience. Nos rappels ouvrent directement une conversation depuis votre propre WhatsApp, pré-remplie — vous gardez le contrôle, sans frais cachés.</p>
                </div>
                <div>
                    <h3>🏦 Comment se passe le paiement de l'abonnement ?</h3>
                    <p>Un simple virement bancaire, puis vous nous envoyez le reçu par WhatsApp. Validation et activation sous 24 à 48h. Pas de carte bancaire à saisir en ligne.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="faq">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">FAQ</span>
            <h2>Questions fréquentes</h2>
        </div>
        <div class="faq-list">
            <details>
                <summary>Dois-je payer pour essayer ?</summary>
                <p>Non. Si votre résidence compte 6 appartements ou moins, le plan gratuit est permanent. Au-delà, vous démarrez avec un essai complet de 15 jours, sans carte bancaire.</p>
            </details>
            <details>
                <summary>Puis-je changer de forfait plus tard ?</summary>
                <p>Oui, votre forfait s'ajuste au nombre d'appartements de votre résidence à chaque renouvellement — jamais automatiquement en cours de période, pour éviter toute mauvaise surprise.</p>
            </details>
            <details>
                <summary>Mes données sont-elles en sécurité ?</summary>
                <p>Chaque résidence est strictement isolée des autres, les mots de passe sont chiffrés, et toutes les connexions passent en HTTPS.</p>
            </details>
            <details>
                <summary>L'application fonctionne-t-elle en arabe ?</summary>
                <p>Oui, l'interface bascule intégralement en arabe avec une mise en page adaptée de droite à gauche, en un clic.</p>
            </details>
            <details>
                <summary>Comment vous contacter ?</summary>
                <p>
                    @if(config('app.contact_whatsapp'))
                        Par WhatsApp au <a href="https://wa.me/{{ str_replace('+', '', config('app.contact_whatsapp')) }}" style="color: var(--brand-700); font-weight: 600;">{{ config('app.contact_whatsapp') }}</a>.
                    @else
                        Écrivez-nous depuis le formulaire d'essai gratuit — nous vous répondons rapidement par WhatsApp.
                    @endif
                </p>
            </details>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="final-cta">
            <h2>Prêt à simplifier la gestion de votre copropriété ?</h2>
            <p>Essai gratuit de 15 jours, sans engagement ni carte bancaire.</p>
            <a class="btn btn-primary btn-lg" href="{{ config('app.frontend_url') }}/register">Créer mon compte gratuitement</a>
        </div>
    </div>
</section>

<footer>
    <div class="wrap footer-inner">
        <a href="/" class="logo">
            <span class="logo-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M9 6h.01M9 10h.01M9 14h.01M15 6h.01M15 10h.01M15 14h.01"/></svg>
            </span>
            <span class="logo-text" style="font-size: 15px;">Atlasoft <span>Syndic</span></span>
        </a>
        <div class="footer-links">
            <a href="{{ config('app.frontend_url') }}/login">Se connecter</a>
            <a href="{{ config('app.frontend_url') }}/register">Essai gratuit</a>
            @if(config('app.contact_whatsapp'))
                <a href="https://wa.me/{{ str_replace('+', '', config('app.contact_whatsapp')) }}">Contact WhatsApp</a>
            @endif
        </div>
        <span class="footer-copy">© {{ date('Y') }} Atlasoft Syndic — Fait pour les syndics bénévoles marocains.</span>
    </div>
</footer>

</body>
</html>
