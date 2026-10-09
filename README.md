# HopHop — application auto-hébergée de recettes de bière

Application 100 % PHP + HTML/CSS/JavaScript vanilla. Aucune base de données :
toutes les données sont stockées dans des fichiers JSON, sous `data/`.

## Installation

1. Copiez tout le dossier `hophop/` sur votre hébergement (mutualisé ou
   serveur perso), via FTP/SFTP ou `git`.
2. Assurez-vous que PHP 7.4 ou plus récent est disponible (aucune extension
   particulière requise — `json`, `session` et `hash` sont incluses en
   standard dans PHP).
3. Donnez au serveur web le droit d'écrire dans le dossier `data/`
   (et ses sous-dossiers) :
   ```
   chmod -R 775 data
   chown -R www-data:www-data data   # adaptez l'utilisateur à votre serveur
   ```
4. Ouvrez `https://votre-domaine/chemin/vers/hophop/` dans un
   navigateur : la page de connexion s'affiche. Créez un compte, c'est prêt.

Aucune étape de build, aucune dépendance à installer (`composer`, `npm`…) :
les 6 fichiers du dossier suffisent.

## Structure

```
hophop/
├── index.php            page unique (interface complète, HTML+CSS+JS)
├── api.php               point d'entrée API (JSON), gère comptes et données
├── includes/
│   ├── config.php        chemins, session
│   ├── storage.php        lecture/écriture JSON avec verrouillage de fichier
│   ├── auth.php           comptes, mots de passe, jeu de données par défaut
│   └── img/logo.svg       logo de l'application (inliné côté serveur, recoloré selon le thème actif)
├── data/                 créé et rempli automatiquement — NE PAS exposer
│   ├── users.json         identifiants + mots de passe hachés
│   ├── store/<compte>.json  recettes, brassages, stock, bibliothèque, profils
│   ├── .htaccess           bloque l'accès direct (Apache)
│   └── store/.htaccess
└── includes/.htaccess     bloque l'accès direct au code interne (Apache)
```

Sur **Nginx**, les `.htaccess` sont ignorés : ajoutez plutôt, dans le bloc
`server {}` de votre site, un blocage équivalent :
```nginx
location ~ ^/(data|includes)/ { deny all; return 404; }
```

## Sécurité et bonnes pratiques

- Les mots de passe sont hachés avec `password_hash()` (bcrypt) — jamais
  stockés en clair.
- Servez l'application en HTTPS : le cookie de session est marqué
  automatiquement `Secure` dès que `$_SERVER['HTTPS']` est détecté.
- `data/` contient des données personnelles (recettes ; en mode standalone,
  aussi `users.json`) : chaque niveau est protégé par `.htaccess`
  (`data/`, `data/store/`). Test : ouvrir `…/hophop/data/users.json` ou
  `…/hophop/data/store/un-fichier.json` → **403/404**. Sous Nginx, bloquer
  `…/hophop/data/` explicitement (les `.htaccess` sont ignorés).
- En mode forum + MySQL, les recettes ne devraient plus être servies depuis
  `data/` ; ce dossier peut rester vide ou pour migration legacy uniquement.
- Il n'y a pas de limite de tentatives de connexion intégrée. Si votre
  application est exposée publiquement, envisagez une protection
  complémentaire (fail2ban, limite au niveau du serveur web).
- Sauvegardez régulièrement le dossier `data/` (ou utilisez, depuis l'onglet
  Compte de l'application, l'export JSON par utilisateur).

## Fonctionnalités

 · Comptes utilisateurs
 · profils matériel multiples (rendement, pertes, évaporation…)
 · éditeur de recette avec calculs en direct (densités, ABV, IBU, EBC, eau d'empâtage/rinçage, sucre de conditionnement)
 · bibliothèque d'ingrédients personnalisable par utilisateur
 · gestion de stock avec alerte de manque
 · redimensionnement de recette
 · historique de brassage (visé vs mesuré)
 · fiche recette imprimable
 · export BeerXML 1.0 et BeerJSON 1.0.

Unités européennes partout (L, kg, g, °C, EBC, densité SG).

## Intégration phpBB (BrassageAmateur)

Pour le forum (`/forum/hophop/`) : session forum (SSO) + stockage MySQL (JSON par `user_id`).

1. Copier `includes/config.local.example.php` → `includes/config.local.php` (`auth_mode` = `phpbb`, `storage` = `mysql`).
2. Exécuter `docs/sql/hophop_store.sql` sur la base phpBB (adapter le préfixe de table).
3. Vérifier que `https://…/forum/hophop/data/users.json` renvoie **403/404** (voir sécurité ci-dessous).

Au premier accès, si un ancien fichier `data/store/<pseudo>.json` existe pour le même pseudo forum, il est importé une fois en MySQL.

Sans `config.local.php`, HopHop reste en mode **standalone** (comptes locaux + fichiers JSON).

## Limites connues

- Mode standalone : un fichier JSON par compte. Mode forum : blob JSON MySQL par `user_id`.
- Pas de récupération de mot de passe oublié par e-mail (pas de serveur SMTP
  supposé disponible) : en cas d'oubli, un administrateur du serveur peut
  supprimer l'entrée correspondante dans `data/users.json` pour permettre
  une nouvelle inscription avec le même identifiant.

## Licence

**Hophop** Penn-Maen & claude.ai — [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/deed.fr)
(Attribution – Pas d'Utilisation Commerciale – Partage dans les Mêmes Conditions).
Texte complet dans [`LICENSE.md`](LICENSE.md).
