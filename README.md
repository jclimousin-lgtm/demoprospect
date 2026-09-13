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

`public/` = docroot du sous-domaine, entièrement autonome (aucun fichier
hors de `public/` n'est requis en prod — la clé Gemini vit côté navigateur,
jamais sur le serveur). `config/` ne sert qu'au script de test en lot local.

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

```bash
tools/deploy/deploy-o2switch.sh
```

Pousse `public/` par FTP directement vers le docroot du sous-domaine
(`demoprospect.serviceproi.fr/`), sans passer par cPanel — même mécanisme
que les autres projets (Jarnac, Convergences). Rien à cliquer côté cPanel,
aucun fichier de config à créer sur le serveur.

## Hors périmètre (volontairement)

Connexion compta, multi-utilisateurs, historique, correction manuelle dans
l'interface, traitement par lots dans l'UI.
