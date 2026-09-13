# DemoProspect — l'outil-prétexte

Démo d'extraction de champs (fournisseur, n° document, date, HT/TVA/TTC,
lignes) depuis une facture/BL/devis en PDF ou photo. Cadrage complet :
`/home/jcl/Téléchargements/outil-pretexte-cadrage.md`.

## Architecture

PHP + API Gemini (`gemini-2.5-flash`, vision) avec sortie structurée forcée
(`responseSchema`). Choisi après avoir testé et écarté deux alternatives
gratuites sans IA (regex maison ~83% de réussite, invoice2data 0% sur
documents inconnus, PaddleOCR trop lourd pour du CPU sans GPU) : sur cette
tâche, seul un modèle vision comprend un document jamais vu. Le palier
gratuit de Gemini (pas de carte bancaire, quotas quotidiens très au-dessus
du besoin réel) permet de rester à coût nul.

`public/` = docroot du sous-domaine, `app/` et `config/` restent en dehors
du web (le clone git doit avoir `public/` comme sous-dossier du docroot,
pas la racine — voir Déploiement).

## Configuration

```bash
cp config/gemini.php.example config/gemini.php
# éditer et coller la clé (aistudio.google.com/apikey — gratuit, sans carte)
```

## Lancer en local

```bash
php -S localhost:8000 -t public
```

## Valider avant de montrer l'outil (règle du cadrage : ≥80% sur 20 documents inconnus)

```bash
php scripts/tester-lot.php /chemin/vers/20-documents-inconnus
```

## Déploiement o2switch

Le sous-domaine `demoprospect.serviceproi.fr` doit avoir pour racine du
document `/home/nare8592/repositories/demoprospect/public` (le dossier
`public/` du dépôt cloné via l'outil Git Version Control de cPanel) — pas
un dossier séparé copié par rsync. Ainsi `config/gemini.php`, créé une
seule fois à la main dans le Gestionnaire de fichiers à
`/home/nare8592/repositories/demoprospect/config/gemini.php`, reste hors
du web tout en étant trouvé par `analyser.php` (chemin relatif
`../config/gemini.php`).

Mise à jour ensuite : juste "Update from Remote" dans Git Version Control
(pas de bouton "Deploy" nécessaire, le docroot est directement le dépôt).

## Hors périmètre (volontairement)

Connexion compta, multi-utilisateurs, historique, correction manuelle dans
l'interface, traitement par lots dans l'UI.
