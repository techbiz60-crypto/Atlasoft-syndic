# Cahier des charges — SaaS de gestion de syndicat de copropriété (Maroc)

> Document vivant, mis à jour au fil de nos échanges. Statut des sections : ✅ validé, 🟡 en discussion, ⬜ pas encore abordé.

## 0. Avancement du développement

- **Lot 1 (Fondations)** ✅ : Docker Compose (Nginx/PHP-FPM 8.3/MySQL 8), Laravel 13 + Sanctum (API register/login/logout/user), React (Vite/TS) avec écrans register/login/dashboard fonctionnels, isolation tenant, 3 rôles.
- **Lot 2 (Résidence & Appartements)** ✅ : CRUD résidence (paramétrage), types de lot, appartements — écriture réservée à l'administrateur (middleware `admin`), lecture pour tous les rôles. Étendu ensuite avec la notion d'**Immeuble** (résidences multi-bâtiments) : Résidence → Immeubles → Appartements, numéro de lot unique par immeuble.
- **Lot 3 (Cotisations & paiements)** ✅ : appels de fonds mensuels (génération auto par type de lot, idempotente), paiements complets/partiels avec statut calculé (payé/partiel/impayé), écran Impayés trié par ancienneté avec bouton de rappel WhatsApp (lien `wa.me`, sans API payante). Commande Artisan `fund-calls:generate` planifiée mensuellement pour la production.
- **Historique versionné du montant de cotisation** ✅ : décision révisée après retour terrain (cas réel : montant différent d'une année sur l'autre, besoin de retrouver quel montant s'appliquait à quelle période, y compris pour des reçus papier). Remplace l'approche simplifiée initiale par une vraie table `lot_type_rates` (montant + date d'effet), inspirée de l'écran équivalent chez eDary. Un type de lot garde toujours au moins un montant ; on ne peut pas supprimer le dernier. Un montant à date d'effet future peut être préparé à l'avance sans affecter le montant actuellement appliqué. La génération des appels de fonds recherche automatiquement le barème en vigueur pour la période demandée (pas seulement "le barème actuel"), donc un appel généré pour une période passée ou future utilise toujours le bon montant.
- **Bug subtil trouvé et corrigé — cast `date` de Laravel** : le cast `'date'` (et sa variante paramétrée `'date:Y-m-d'`) ne garantit PAS que la base de données stocke une date pure sans heure — ce paramètre ne contrôle que la sérialisation JSON en sortie d'API, pas l'écriture réelle en base. MySQL masque le problème car ses colonnes `DATE` tronquent silencieusement toute valeur ; SQLite (utilisé par les tests) ne le fait pas et stocke l'heure telle quelle, cassant les contraintes d'unicité et comparaisons de date. **Règle à appliquer pour tout nouveau champ de type date pur (sans heure)** : définir un mutateur explicite via `Attribute::make(get: ..., set: fn ($value) => Carbon::parse($value)->format('Y-m-d'))` plutôt que de se fier au cast seul — voir `LotTypeRate::effectiveDate()`, `FundCall::period()`, `Payment::paidAt()` comme modèles à suivre.
- **Bug récurrent trouvé et corrigé (x4)** : plusieurs modèles (`Building`, `FundCall`, `Payment`, puis `Lot`/`LotType` par précaution) omettaient `residence_id` dans leur attribut `#[Fillable]`. Sans utilisateur authentifié (ex : tâche planifiée, script), l'auto-remplissage du `BelongsToTenant` ne s'applique pas et la valeur explicite passée à `create()` est silencieusement ignorée → erreur NOT NULL. **Règle à appliquer systématiquement pour tout nouveau modèle tenant-scopé : toujours inclure `residence_id` dans `#[Fillable]`, même si l'auto-remplissage le couvre déjà dans le flux HTTP normal.**
- **Bug critique trouvé et corrigé** : le `TenantScope` provoquait une récursion infinie quand l'utilisateur était authentifié via un vrai cookie de session (au lieu de `actingAs()` en test) — Laravel devait charger le `User` depuis la session, ce qui redéclenchait le même scope, qui rappelait `Auth::user()`, etc. Corrigé par un verrou de ré-entrance dans `TenantScope`. Un test de non-régression dédié (`SessionBasedTenantScopeTest`) a été ajouté car les tests `actingAs()` ne peuvent pas détecter ce genre de bug — **le pattern doit être répliqué pour tout futur test touchant à l'authentification**, pas juste `actingAs()`.
- **Autre point d'attention** : les commandes `artisan`/`composer`/`pint` lancées via `docker compose exec` tournent en `root` par défaut, ce qui peut redonner la propriété de fichiers de `storage/`/`bootstrap/cache` à `root` et casser l'écriture pour `www-data` (le vrai utilisateur PHP-FPM) → 500 silencieux sans log. Utiliser `docker compose exec --user www-data` pour les commandes qui écrivent des fichiers applicatifs, ou re-exécuter le chown de `entrypoint.sh` après un batch de commandes root.
- **Performance en développement local** : le montage Windows (bind mount) du code dans le conteneur Docker est lent en lecture de fichiers (chaque requête PHP lisait des centaines de fichiers vendor/), causant des temps de réponse de 8-11s. Corrigé en désactivant `opcache.validate_timestamps` (voir `docker/php/php.ini`) — gain à ~0,4-0,7s par requête après un premier appel de préchauffe plus lent (~15-20s après un restart du conteneur). **Contrepartie à connaître** : un changement de code PHP backend ne sera pris en compte qu'après `docker compose restart app` (le cache de compilation n'est plus revalidé automatiquement). Ce réglage est spécifique au dev local sous Windows ; sur le VPS Hetzner en production (disque natif Linux, pas de bind mount), ce problème ne se posera pas — mais garder `opcache.enable=1` reste une bonne pratique de perf en prod aussi (avec `validate_timestamps=1` cette fois, car pas de déploiement à chaud à gérer).

## 1. Positionnement ✅

- **Marché** : gestion de syndicat de copropriété au Maroc (secteur fragmenté : eDary, Syndy, Syndicat.ma/Smart Condominium, SyndicApp, SyndicConnect, SyndicGroup, SyndicMa, App-Syndic, VotreSyndic.ma, Gestis, GestCopMaroc...)
- **Segment ciblé** : syndics **bénévoles** (non professionnels), par opposition aux syndics professionnels multi-résidences (positionnement de Syndy)
- **Philosophie produit** : simplicité d'abord, pas de sur-ingénierie en V1
- **Différenciateur visé** : usage de WhatsApp comme canal naturel (déjà utilisé par les syndics bénévoles) plutôt que d'imposer un nouvel outil de communication

### Concurrents analysés

| Acteur | Cible | Pricing | Points forts | Points faibles |
|---|---|---|---|---|
| **eDary** (edary.net) | Syndic bénévole / autogestion | 5→2 DH/appartement/mois dégressif, 15j gratuit | Pricing transparent, forte preuve sociale (+400 résidences, +5000 users, 5/5) | — |
| **Syndy.ma** | Syndic professionnel multi-résidences | Non public | Assistant IA intégré, AG, portail copropriétaire | Pricing opaque |
| **Syndicat.ma** (Smart Condominium) | Syndic pro, vente sur devis | Sur devis | Multilingue (ar/fr/en/es) | Vente traditionnelle non scalable, peu de preuve sociale |

### Angles non exploités par la concurrence
- Intégration paiement mobile marocain natif
- Conformité explicite à la loi 18-00 sur la copropriété
- Réconciliation de paiement via WhatsApp

## 2. Modèle économique ✅

### Paiement des copropriétaires (côté client final)
- **Hors ligne uniquement en V1** : virement bancaire ou espèces, pas de passerelle de paiement en ligne
- Le syndic déclare manuellement les paiements reçus dans l'app

### Facturation de l'abonnement SaaS (côté administrateur/client payant) ✅ implémenté
- Virement bancaire + envoi du reçu par WhatsApp pour validation manuelle et activation du compte
- Pas de paiement en ligne pour l'abonnement non plus, au démarrage
- **Statut calculé, pas stocké** : une table `subscriptions` (une par résidence) garde `plan`, `billing_cycle`, `trial_ends_at`, `current_period_end` ; le statut (`free`/`trial`/`active`/`expired`) et `is_writable` sont des accesseurs calculés à la volée à partir des dates — même principe que `FundCall::status`, pas besoin de tâche planifiée pour "faire passer" un abonnement à expiré
- **Back-office = commandes Artisan**, pas d'écran dédié pour l'instant (volume de clients trop faible pour le justifier) :
  - `subscriptions:list [--due=N]` : tableau de tous les abonnements, triés par jours restants, filtrable sur les échéances à N jours (sert à la fois pour la relance de fin d'essai et le suivi des renouvellements)
  - `subscriptions:activate {residence} {cycle} [--plan=]` : valide un virement reçu, prolonge la période à partir d'aujourd'hui (ou de la fin de période en cours si renouvellement anticipé), efface le statut d'essai
- **Résidence sans ligne d'abonnement = toujours inscriptible** (fail-open) : les résidences créées avant ce module n'ont pas de ligne `subscriptions` et restent en écriture libre tant qu'on ne leur en crée pas une — évite de casser les comptes existants (dont le compte de test) rétroactivement
- Blocage en écriture via un middleware (`subscription.active`), sur le même principe que le middleware `admin` déjà en place — bloque les routes POST/PUT/DELETE, la lecture (y compris reçus PDF déjà émis) reste toujours possible
- Bannière dans l'app (React) qui affiche l'essai finissant sous 5 jours ou l'abonnement expiré, via `GET /api/subscription`
- **Point non tranché volontairement laissé de côté** : les reçus PDF et les rappels WhatsApp (impayés), construits avant ce module, restent disponibles pour tous les plans y compris le gratuit — la restriction de fonctionnalités du plan gratuit prévue plus bas n'a pas été rétro-appliquée à ces deux features déjà livrées, pour ne pas complexifier a posteriori des écrans qui fonctionnaient déjà pour tout le monde

### Grille tarifaire ✅

| Pack | Nombre d'appartements | Prix mensuel | Remise annuelle |
|---|---|---|---|
| Gratuit | ≤ 6 | 0 DH | — |
| Starter | 7 à 15 | 50 DH | ~20% |
| Standard | 16 à 40 | 100 DH | ~20% |
| Plus | 41 à 70 | 160 DH | ~20% |
| Premium | 71 à 100 | 220 DH | ~20% |
| Sur devis | 100+ | Contact direct | — |

- **Plan gratuit permanent** (pas un simple trial) pour ≤6 appartements, mais fonctionnalités limitées (pas d'export PDF, historique réduit, pas de rappels automatiques) pour inciter à l'upgrade
- **Essai gratuit de 15 jours** sur les plans payants avant le premier virement
- Prix affichés comme tarifs normaux, pas comme "promo de lancement", pour éviter l'anxiété d'une future hausse chez une cible peu technophile
- Montée en gamme future via nouveaux paliers/fonctionnalités, pas via hausse du prix de base existant

## 3. Modules — priorisation

### Phase 1 — MVP 🟡

- ⬜ Gestion résidence & appartements
- 🟡 **Cotisations & suivi des paiements** (en cours de détail, voir section 4)
- ⬜ Enregistrement des dépenses (catégorisées)
- ⬜ Génération de reçu simple (PDF/texte, partageable via WhatsApp)
- ⬜ Dashboard trésorerie (solde, impayés)
- ⬜ Gating du plan gratuit (≤6 lots)

- ⬜ Portail copropriétaire en lecture seule (voir section 15 — remonté en Phase 1 suite à discussion)

Exclus volontairement de la V1 : gestion des AG, gestion d'incidents.

### Phase 2 — après premiers clients payants ⬜

- Rappels automatiques WhatsApp (échéances impayées) — nécessite l'API WhatsApp Business
- Bilans exportables PDF sur période
- Version arabe de l'interface

### Phase 3 — différenciation long terme ⬜

- Gestion des Assemblées Générales (convocations, PV, votes)
- Automatisation du rapprochement des reçus WhatsApp (OCR/matching)
- Montée en gamme vers syndics professionnels multi-résidences
- Conformité explicite loi 18-00

## 3bis. Résidences multi-immeubles ✅

**Cas réel identifié** : certaines copropriétés se composent de plusieurs immeubles (ex : résidence "Sahil Ouad" comportant les bâtiments "Sahil Ouad 5" (20 appartements), "Sahil Ouad 6" (27 appartements), etc.). Le modèle initial (Résidence → Appartements directement) ne le permettait pas.

**Modèle retenu** : Résidence → **Immeubles** → Appartements
- Toute résidence a **au moins un immeuble**, même les résidences à un seul bâtiment (un "Bâtiment principal" est créé automatiquement à l'inscription, invisible pour l'utilisateur qui n'en a pas besoin) — évite d'avoir deux chemins de code différents selon le nombre d'immeubles
- Le numéro d'appartement est **unique par immeuble**, pas par résidence (deux immeubles peuvent chacun avoir un "Appartement 1")
- Écran "Immeubles" ajouté (CRUD, admin uniquement pour l'écriture), avec compteur d'appartements par immeuble
- L'écran Appartements a un sélecteur d'immeuble (obligatoire) en plus du type de lot, et un filtre par immeuble dans la liste

## 4. Module détaillé : Cotisations & suivi des paiements 🟡

### Modèle de données (conceptuel, pas encore technique)

- **Type de lot** : par résidence, l'admin définit des types (ex : Studio, Appartement, Duplex, Local commercial), ou un seul type "Standard" par défaut si pas besoin de granularité
- **Appartement/lot** : rattaché à un seul type de lot, pas de montant personnalisé en dehors du type (décision confirmée : pas de surcharge individuelle)
- **Barème de cotisation** : montant mensuel par type de lot, **versionné dans le temps** avec date d'effet — permet de refléter un changement voté en AG sans réécrire l'historique
- **Appel de fonds** : généré automatiquement chaque mois pour chaque lot, au montant du barème en vigueur à cette date
- **Paiement** : montant reçu, date, méthode déclarée (virement/espèces/chèque), lot concerné, statut (payé complet / partiel / en attente)

### Flux principal
1. L'admin définit les types de lot et leur montant mensuel (une fois, à la création de la résidence)
2. Chaque mois, l'app génère automatiquement les appels de fonds pour tous les lots
3. Quand un paiement est reçu, l'admin le déclare en 2-3 clics (lot, montant, date)
4. L'app génère un reçu que l'admin transmet par WhatsApp au copropriétaire

### Règles de gestion actées
- Un changement de montant voté en AG crée une nouvelle version du barème avec date d'effet ; les appels de fonds déjà émis gardent l'ancien montant
- Les paiements partiels doivent être gérés dès la V1 (pas de statut binaire payé/non payé)
- Pas de calcul par tantièmes/superficie proportionnel — le montant est fixé directement par type de lot, décision plus simple et plus naturelle pour l'admin bénévole

### Points encore ouverts
- Que se passe-t-il si un lot change de type en cours de route (rénovation, fusion de lots) ? — non tranché, probablement hors scope V1
- Détail de l'écran "vue impayés" (tri, ancienneté du retard)

## 5. Module détaillé : Dépenses ✅

### Modèle de données
- **Catégorie de dépense** : prédéfinies (eau, électricité, gardiennage, entretien, assurance) + catégories personnalisées ajoutables par l'admin
- **Dépense** : montant, date, catégorie, description courte, justificatif optionnel (photo de facture)

### Flux
Ajout en quelques champs, justificatif pris en photo directement depuis le téléphone. Pas de workflow de validation/approbation en V1 — l'admin bénévole est seul décisionnaire.

## 6. Module détaillé : Dashboard trésorerie ✅

Volontairement minimal en V1 :
- Solde de trésorerie (total cotisations encaissées − total dépenses)
- Montant total des impayés
- Liste des lots en retard, triée par ancienneté du retard
- Dépenses du mois en cours

Pas de graphiques ni de comparatifs pluriannuels en V1 (reporté en Phase 2 avec les bilans exportables).

## 7. Parcours d'onboarding ✅

1. **Inscription** : nom de la résidence, nombre approximatif d'appartements, numéro WhatsApp de l'administrateur (donnée obligatoire, canal principal de communication)
2. **Assistant de configuration** : création des types de lot + montants, ajout des appartements et copropriétaires (nom, téléphone)
   - ≤6 lots → plan gratuit activé automatiquement, pas de paiement requis
   - >6 lots → essai complet de 15 jours démarré automatiquement
3. **Usage pendant l'essai** : l'admin génère au moins un mois d'appels de fonds réels avant toute demande de paiement
4. **Relance à J-3 avant fin d'essai** (WhatsApp) : instructions de virement (RIB, montant du pack, **référence unique = identifiant résidence** à inclure dans le libellé pour le rapprochement)
5. **Paiement** : virement + photo du reçu envoyée par WhatsApp → validation manuelle en back-office → statut "actif"
6. **Non-paiement à l'échéance** : bascule en **mode lecture seule** (données déjà saisies restent consultables, mais plus d'ajout possible) plutôt qu'un blocage total ou une suppression — préserve la confiance et laisse la porte ouverte à la reconversion

## 8. Changement de type de lot / fusion ✅

- Changer le type d'un lot n'affecte que les futurs appels de fonds (montant figé à la génération) — aucune complexité additionnelle requise
- Fusion de deux lots (rénovation) : archiver les anciens lots (statut inactif, historique conservé), créer un nouveau lot plutôt que de gérer une migration de données

## 9. Écran "impayés" ✅

- Liste triée par ancienneté du retard (nombre de mois impayés décroissant)
- Colonnes : lot, copropriétaire, téléphone, montant dû cumulé, date du dernier paiement
- **Bouton "Envoyer un rappel WhatsApp"** via lien `wa.me/<numéro>?text=<message pré-rempli>` — remonté en Phase 1 (coût de développement quasi nul, cohérent avec le positionnement WhatsApp-first)

## 10. Renouvellement d'abonnement récurrent ✅ implémenté

- Vue back-office interne (pour l'équipe, pas le client) : `subscriptions:list [--due=N]`, triée par jours restants
- Relance manuelle via lien `wa.me` pré-rempli (montant du pack + RIB)
- Automatisation via API WhatsApp Business reportée en Phase 2, une fois le volume de clients trop important pour un suivi manuel
- **Côté client** (nouveau) : écran "Mon abonnement" (visible par l'admin de la résidence) affichant le pack actuel, son prix, le statut (gratuit/essai/actif/expiré) et le nombre de jours restants bien en évidence, ainsi qu'un historique des factures
- **Table `subscription_invoices`** : une ligne créée automatiquement par `subscriptions:activate` à chaque validation de virement — montant (calculé depuis le prix du pack, remise de 20% appliquée automatiquement en cycle annuel, `--amount=` pour les cas "sur devis"), période couverte, date de validation. Sert à la fois l'historique client et une future consolidation comptable côté équipe
- Pas de PDF de facture pour l'instant (juste un tableau dans l'écran) — décision volontaire de garder simple tant que le besoin d'un justificatif formel n'est pas confirmé

## 11. Stack technique ✅

**Infrastructure cible** : VPS Hetzner (plan le moins cher, 4 Go RAM / 40 Go disque)

**Alternatives évaluées et écartées** :
- *Next.js + Vercel* : SSR inutile pour une app de back-office derrière login ; coût ~215 MAD/mois en plus sans bénéfice réel
- *Supabase + Vercel (full managed)* : ~490 MAD/mois minimum, 4-5x plus cher qu'un VPS à cette échelle ; il faudrait ~10 clients payants juste pour couvrir l'infra

**Stack retenue** (cohérente avec les compétences : Laravel backend, React frontend) :

- **OS** : Ubuntu 24.04 LTS
- **Conteneurisation** : Docker / Docker Compose (portabilité, environnements reproductibles) — surcoût RAM minime (~150-250 Mo), jugé rentable à cette échelle
- **Serveur web** : Nginx + PHP-FPM
- **Backend** : Laravel + PHP 8.3, exposé en **API REST pure** (pas de vues Blade métier)
- **Authentification** : Laravel Sanctum en mode SPA (cookies same-domain, pas de gestion manuelle de JWT)
- **Base de données** : MySQL/MariaDB
- **Multi-tenant** : base unique, `residence_id` en clé étrangère sur les tables métier
- **Frontend** : React (Vite), **SPA classique sans SSR** — compilé en fichiers statiques, servis par Nginx sur le même domaine que l'API (évite les soucis CORS, aucun runtime Node.js requis en production)
- **Cache / sessions / queue** : driver `database` au démarrage, pas de Redis tant que le volume ne le justifie pas
- **PDF (reçus)** : `barryvdh/laravel-dompdf`
- **Stockage fichiers** (justificatifs de dépenses) : Hetzner Object Storage (S3-compatible)
- **Sauvegardes** : `mysqldump` quotidien compressé vers le stockage objet
- **SSL** : Certbot (Let's Encrypt), renouvellement automatique

**Budget mensuel estimé** : ~110 MAD (VPS + stockage objet + domaine amorti) — inchangé par rapport à la version Blade/Livewire, puisque React est compilé en statique et n'a pas besoin d'un hébergement séparé.

**Budget mémoire approximatif (4 Go RAM)** : MySQL ~500 Mo-1 Go, PHP-FPM (pool limité, `pm.max_children` ~10-15), Docker + Nginx + OS ~500-750 Mo — marge confortable pour plusieurs dizaines de résidences clientes.

## 12. Site vitrine public ✅ implémenté

- Page séparée en HTML/Blade classique (`resources/views/landing.blade.php`, route `/`), pas dans le SPA React, pour un meilleur référencement SEO
- CSS autonome (pas de Tailwind/build), même palette de couleurs que le SPA — zéro dépendance JS, chargement rapide
- Découplée du SPA applicatif derrière login : les CTA ("Essai gratuit", "Se connecter") pointent vers `config('app.frontend_url')` (`FRONTEND_URL` en `.env`), à mettre à jour avec le vrai sous-domaine en production
- Reprend telles quelles la grille tarifaire (section 2) et le positionnement (section 1)
- Numéro WhatsApp de contact configurable via `CONTACT_WHATSAPP_NUMBER` (`.env`) — **laissé vide pour l'instant**, la FAQ affiche un message générique tant qu'il n'est pas renseigné ; à compléter avec le vrai numéro avant mise en production

## 13. Module Sécurité ✅

### Non-négociable dès la V1

- **Isolation stricte des tenants** (point le plus critique) : global scope Eloquent sur tous les modèles métier filtré par `residence_id` de l'utilisateur authentifié (jamais une valeur fournie par le client) + tests automatisés dédiés vérifiant qu'aucune donnée d'une résidence n'est accessible depuis une autre
- HTTPS (Certbot, déjà couvert en section 11)
- Mots de passe hashés (natif Laravel, bcrypt/argon2id)
- Protection CSRF (natif avec Sanctum SPA)
- Validation serveur systématique via Form Requests Laravel, jamais de confiance dans la validation front seule
- Protection XSS (React échappe par défaut, éviter `dangerouslySetInnerHTML` ; Blade échappe par défaut)
- Protection SQL injection (natif via Eloquent/Query Builder, éviter les requêtes brutes non paramétrées)
- Rate limiting (middleware `throttle`, notamment login et formulaires publics)
- Logs techniques + **table d'audit dédiée** pour les actions sensibles (validation de paiement, changement de barème de cotisation) — utile en cas de litige entre copropriétaires
- Sauvegardes automatiques (déjà couvert en section 11)
- Restauration testée régulièrement (ex : trimestriel) — processus documenté, pas seulement technique

### Phase 2 (non bloquant pour le lancement)

- **MFA optionnel** : pertinent surtout avec plusieurs utilisateurs/rôles par résidence (portail copropriétaire) ; peut être avancé plus tôt comme argument de confiance marketing si souhaité
- **RBAC** : en V1 un seul rôle (admin résidence), donc pas de besoin réel de gestion de rôles complexe ; à mettre en place avec `spatie/laravel-permission` lors de l'introduction du portail copropriétaire en lecture seule
- **Chiffrement des données sensibles** : à appliquer si stockage d'IBAN/RIB en base (casts de chiffrement natifs Laravel) ; non nécessaire pour de simples montants/noms/téléphones

## 14. Rôles et permissions ✅

**Décision** : le portail résident (chaque copropriétaire) est inclus dès la V1, pas reporté en Phase 2 — pour une parité de transparence avec eDary dès le lancement.

### Rôles

| Rôle | Accès | Authentification |
|---|---|---|
| **Administrateur** | Complet : saisie paiements/dépenses, paramétrage, gestion des accès | Email + mot de passe (Sanctum) |
| **Membre du conseil syndical** | Lecture seule : dashboard, historique paiements/dépenses de toute la résidence | Email + mot de passe (Sanctum) |
| **Copropriétaire** | Lecture seule : ses propres paiements/statut + vue globale trésorerie de la résidence | Email + mot de passe (Sanctum) |

### Authentification — décision finale : pas d'API tierce payante

Ni WhatsApp Business API ni passerelle SMS ne sont utilisées par la plateforme (rejeté explicitement) — coût variable et complexité jugés non nécessaires pour la V1.

- **Login classique email + mot de passe** pour les trois rôles
- L'administrateur crée le compte du résident/membre du conseil dans l'app (mot de passe temporaire généré automatiquement)
- L'administrateur **communique lui-même** ce mot de passe au résident, hors plateforme (message WhatsApp personnel, SMS personnel, oral) — aucune automatisation ni coût côté produit
- **Réinitialisation de mot de passe oublié** : lien par email, SMTP standard natif Laravel, quasi gratuit
- Le canal WhatsApp reste réservé aux rappels manuels (liens `wa.me` pré-remplis, déclenchés par l'administrateur), jamais à l'authentification

### Point de vigilance
Chaque résident doit disposer d'un email valide (au moins pour la réinitialisation de mot de passe) — à vérifier sur le terrain que ce n'est pas un frein pour une partie de la cible (copropriétaires plus âgés, moins équipés).

### Impact sur le périmètre V1
- Ajout d'un flux d'invitation par l'administrateur pour chaque copropriétaire/membre du conseil (email associé au lot, mot de passe temporaire généré)
- Aucune intégration externe payante requise — budget mensuel inchangé (~110 MAD)
- Le RBAC reste volontairement simple (3 rôles fixes, pas de permissions granulaires personnalisables) — `spatie/laravel-permission` seulement si le besoin de rôles personnalisés apparaît réellement plus tard

## 15. Module Paramétrages ✅

- Informations résidence (nom, adresse, nombre de lots)
- RIB de la résidence (compte bancaire du syndicat, affiché sur les reçus et rappels)
- Types de lot & barème de cotisation (section 4)
- Catégories de dépenses personnalisées (section 5)
- Numéro WhatsApp de l'administrateur (modifiable après onboarding)
- Gestion des accès : ajout/retrait des membres du conseil syndical et des copropriétaires (numéro de téléphone par lot)

## 16. Environnement Docker local ✅

État constaté sur la machine de développement (Docker Desktop) avant mise en place du projet :

- **`atlasoft_commerce`** (actif) : ports hôte occupés → `3000`, `3001`, `3307` (MySQL), `6380` (Redis), `8000` (Nginx)
- **`droguerie`** : réseau `droguerie_erp_net` et volumes existants (conteneurs arrêtés actuellement)

**Ports réservés pour `atlasoft-syndic`** (à ne pas modifier sans re-vérifier) :

| Service | Port hôte |
|---|---|
| Nginx (web) | `8081` |
| MySQL | `3308` |
| Redis (si utilisé plus tard) | `6381` |

**Convention de nommage** : préfixer tous les conteneurs/volumes/réseaux Docker par `atlasoft_syndic_` (cohérent avec la convention déjà utilisée par `atlasoft_commerce`), pour garantir l'isolation complète vis-à-vis des autres projets sur la même machine.

## 17. Feuille de route de développement — V1 ✅

Découpage en lots séquentiels, chacun livrant quelque chose de testable :

1. **Fondations** : Docker Compose, squelette Laravel API + React (Vite), auth Sanctum (3 rôles), isolation tenant + tests dédiés posés dès ce lot
2. **Résidence & Appartements** : CRUD résidence/types de lot/appartements
3. **Cotisations & paiements** : barème versionné, génération auto des appels de fonds, saisie paiement (complet/partiel), écran impayés + bouton rappel `wa.me`
4. **Dépenses & Dashboard** : CRUD dépenses catégorisées, dashboard trésorerie
5. **Reçus & Paramétrages** : génération PDF (dompdf), écran paramétrages complet
6. **Portail résident & conseil syndical** : flux d'invitation, vues lecture seule par rôle
7. **Abonnement SaaS** : back-office suivi des statuts, essai 15 jours, mode lecture seule à expiration, vue des renouvellements à venir
8. **Landing page & mise en production** : page vitrine Blade/SEO, déploiement VPS Hetzner (SSL, backups, restauration testée)

## 18. Points encore ouverts

- Choix du fournisseur SMS pour l'OTP (impact sur le budget mensuel, à chiffrer)
- Détail du flux d'invitation d'un copropriétaire par l'administrateur (saisie manuelle un par un, ou import en masse depuis la liste des lots ?)
