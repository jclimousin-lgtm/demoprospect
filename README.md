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

`public/` = docroot du sous-domaine. La clé Gemini vit uniquement côté
serveur, dans `demoprospect-config-prive/gemini.php` — un dossier **sibling**
de `public/` (jamais dans le docroot, donc jamais servi au web), même
convention que `poesie-config-prive`/`convergences-config-prive`. Rien
côté navigateur, la clé est permanente et invisible pour la personne qui
utilise la démo.

## Configuration

```bash
cp demoprospect-config-prive/gemini.php.example demoprospect-config-prive/gemini.php
# éditer et coller la clé (aistudio.google.com/apikey — gratuit, sans carte)
```

En prod, ce fichier est déposé une seule fois à la main (FTP) à
`demoprospect-config-prive/gemini.php`, au même niveau que
`demoprospect.serviceproi.fr/` — il n'est jamais touché par
`tools/deploy/deploy-o2switch.sh`, qui ne pousse que `public/`.

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
