# DemoProspect — l'outil-prétexte

Démo d'extraction de champs (fournisseur, n° document, date, HT/TVA/TTC,
lignes) depuis une facture/BL/devis en PDF ou photo. Pas un produit : sert à
faire parler des commerçants sur leurs corvées de saisie. Cadrage complet :
`/home/jcl/Téléchargements/outil-pretexte-cadrage.md`.

## Configuration

```bash
cp config/anthropic.php.example config/anthropic.php
# éditer config/anthropic.php et coller la clé (console.anthropic.com)
```

## Lancer en local

```bash
php -S localhost:8000 -t public
```

Ouvrir http://localhost:8000

## Valider avant de montrer l'outil (règle du cadrage : ≥80% sur 20 documents inconnus)

```bash
php scripts/tester-lot.php /chemin/vers/20-documents-inconnus
```

Le script traite chaque PDF/JPG/PNG/WEBP du dossier, affiche succès/échec par
fichier et le taux de réussite global. En dessous de 80%, ne pas montrer.

## Déploiement o2switch

Même convention que Convergences/Jarnac : `public/` comme docroot du
sous-domaine, `config/` et `app/` restent hors du web (non accessibles
publiquement). Créer le sous-domaine avec `dir` pointant sur
`demoprospect.serviceproi.fr/public` (voir la référence cPanel UAPI en
mémoire), puis déposer `config/anthropic.php` par FTP — jamais dans le repo
git.

## Hors périmètre (volontairement)

Connexion compta, multi-utilisateurs, historique, correction manuelle dans
l'interface, traitement par lots dans l'UI (le lot, c'est `tester-lot.php`,
réservé aux tests). Ne pas ajouter.
