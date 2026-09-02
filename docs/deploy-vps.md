# Déploiement sur le VPS

Ce guide déploie l'app à côté de la stack `atlasoft-commerce` déjà présente sur
le VPS, sans toucher à ses conteneurs. Port choisi : **8082** (le commerce
utilise déjà 8000, 3000, 3001, 3307, 6380).

Toutes les commandes `docker compose ...` ci-dessous supposent que vous êtes
dans le dossier du projet sur le VPS.

## 1. Cloner le projet sur le VPS

```bash
ssh root@VOTRE_IP_VPS
cd /opt   # ou l'emplacement de votre choix
git clone https://github.com/techbiz60-crypto/Atlasoft-syndic.git
cd Atlasoft-syndic
```

## 2. Configurer les mots de passe MySQL

```bash
cp .env.prod.example .env
nano .env
```

Remplacez les deux valeurs par des mots de passe forts et différents
(`openssl rand -base64 24` en génère un rapidement).

## 3. Configurer le backend Laravel

```bash
cp backend/.env.production.example backend/.env
nano backend/.env
```

À modifier dans ce fichier :
- `APP_URL`, `FRONTEND_URLS`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` :
  remplacez `VPS_IP` par l'IP publique réelle du VPS (gardez `:8082`).
- `DB_PASSWORD` : **la même valeur** que celle mise dans `.env` à l'étape 2.
- `MAIL_USERNAME` et `MAIL_PASSWORD` : vos identifiants Mailtrap (ceux
  utilisés en dev) — ou une vraie config SMTP si vous en avez une.

## 4. Construire le frontend

Pas besoin d'installer Node sur le VPS — on utilise un conteneur Docker
temporaire juste pour la construction :

```bash
cp frontend/.env.production.example frontend/.env.production
docker run --rm -v "$(pwd)/frontend":/app -w /app node:20-alpine \
  sh -c "npm ci && npm run build"
```

Ça crée `frontend/dist/`, servi ensuite par Nginx.

## 5. Démarrer les conteneurs

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

## 6. Générer la clé d'application Laravel

```bash
docker compose -f docker-compose.prod.yml exec app php artisan key:generate
```

## 7. Importer vos données existantes

Le fichier `atlasoft_syndic_dump.sql` a été généré depuis votre environnement
de dev local — il contient déjà toutes vos résidences, appartements et
paiements. **Ne le mettez jamais sur GitHub** (déjà exclu via `.gitignore`),
transférez-le directement au VPS :

Depuis votre PC Windows (Git Bash), à la racine du projet :

```bash
scp atlasoft_syndic_dump.sql root@VOTRE_IP_VPS:/opt/Atlasoft-syndic/
```

Puis sur le VPS, importez-le dans le conteneur MySQL (remplacez
`VOTRE_MOT_DE_PASSE_ROOT` par la valeur `DB_ROOT_PASSWORD` mise à l'étape 2) :

```bash
docker compose -f docker-compose.prod.yml exec -T db \
  mysql -u root -pVOTRE_MOT_DE_PASSE_ROOT atlasoft_syndic < atlasoft_syndic_dump.sql
```

Puis, par sécurité (aucun changement attendu, le dump contient déjà tout) :

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Une fois l'import confirmé, supprimez le dump du VPS (il contient des
données personnelles réelles) :

```bash
rm atlasoft_syndic_dump.sql
```

## 8. Ouvrir le port dans le pare-feu (si applicable)

```bash
ufw allow 8082/tcp   # si ufw est actif
```

Si vous utilisez le pare-feu réseau Hetzner/OVH côté panneau de contrôle,
ouvrez le port 8082 (TCP) là-bas aussi.

## 9. Planifier la tâche mensuelle (génération des appels de fonds)

Laravel a une tâche planifiée (génération automatique des appels de fonds le
1er de chaque mois). Ajoutez une tâche cron sur le VPS :

```bash
crontab -e
```

Ajoutez cette ligne (adaptez le chemin si vous n'avez pas cloné dans
`/opt/Atlasoft-syndic`) :

```
* * * * * cd /opt/Atlasoft-syndic && docker compose -f docker-compose.prod.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

## 10. Vérifier

Ouvrez `http://VOTRE_IP_VPS:8082` dans un navigateur — vous devriez voir
l'écran de connexion avec vos données existantes.

## Pour un futur déploiement (mise à jour du code)

```bash
cd /opt/Atlasoft-syndic
git pull
docker run --rm -v "$(pwd)/frontend":/app -w /app node:20-alpine \
  sh -c "npm ci && npm run build"
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

## Limites actuelles à connaître

- **Pas de HTTPS** (pas de nom de domaine pour l'instant) — les mots de passe
  circulent en clair sur le réseau à la connexion. À corriger dès qu'un nom
  de domaine est disponible (Nginx + Let's Encrypt).
- **Mailtrap sandbox** : les emails (vérification de compte) n'arrivent pas
  dans de vraies boîtes mail tant que ce n'est pas remplacé par un vrai
  fournisseur SMTP.
