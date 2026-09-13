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

**Page web** : aucune config serveur — la clé (aistudio.google.com/apikey,
gratuit, sans carte) est demandée une fois dans le navigateur au premier
usage et gardée en `localStorage`. Rien à déposer sur le serveur.

**Script de test en lot** (local uniquement) :
```bash
cp config/gemini.php.example config/gemini.php
# éditer et coller la clé
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

Racine du document du sous-domaine inchangée
(`/home/nare8592/demoprospect.serviceproi.fr/`). Mise à jour via l'outil
Git Version Control de cPanel : "Update from Remote" puis "Deploy HEAD
Commit" (le `.cpanel.yml` copie `public/` vers le docroot). Aucun fichier
de config à créer côté serveur — voir plus haut.

## Hors périmètre (volontairement)

Connexion compta, multi-utilisateurs, historique, correction manuelle dans
l'interface, traitement par lots dans l'UI.
