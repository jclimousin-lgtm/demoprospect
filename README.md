# DemoProspect — l'outil-prétexte

Démo d'extraction de champs (fournisseur, n° document, date, HT/TVA/TTC,
lignes) depuis une facture/BL/devis en PDF ou photo. Pas un produit : sert à
faire parler des commerçants sur leurs corvées de saisie. Cadrage complet :
`/home/jcl/Téléchargements/outil-pretexte-cadrage.md`.

## Architecture : 100% local, aucune dépendance à l'exécution

Pas d'API payante, pas de compte, pas de serveur qui fait le travail : la
lecture du document (OCR) tourne **dans le navigateur**, en WebAssembly
(`tesseract.js`), et le rendu des PDF aussi (`pdf.js`). Tout est vendorisé
dans `public/vendor/` (bibliothèques + données d'entraînement français) —
une fois la page chargée une première fois, elle fonctionne même sans
réseau. `public/` est donc entièrement statique et peut être déposé tel
quel sur n'importe quel hébergement, y compris de la mutualisation basique
(vérifié : o2switch ne permet pas d'installer Tesseract côté serveur, d'où
ce choix).

L'extraction des champs (regex sur le texte OCR) est dans
`public/extraction.js`, partagée entre la page web et le script de test en
lot ci-dessous.

## Lancer en local

```bash
php -S localhost:8000 -t public
```

Ouvrir http://localhost:8000 — glisser un PDF ou une photo.

## Valider avant de montrer l'outil (règle du cadrage : ≥80% sur 20 documents inconnus)

Nécessite `tesseract-ocr`, `tesseract-ocr-fra` et `poppler-utils` installés
localement (`sudo apt install tesseract-ocr tesseract-ocr-fra poppler-utils`).

```bash
node scripts/tester-lot.js /chemin/vers/20-documents-inconnus
```

Ce script utilise le Tesseract système (pas le WASM du navigateur) — moteur
équivalent, mais pas garanti identique à 100%. Il donne une bonne estimation
rapide ; teste aussi la vraie page dans un vrai navigateur sur quelques
documents avant de te lancer, pour confirmer.

## Limite honnête sur la fiabilité

Sans IA, l'extraction est faite par règles (mots-clés + regex) sur le texte
OCR brut. Ça marche correctement sur des documents propres et bien
structurés ; ça devient fragile sur des mises en page inhabituelles, du
manuscrit, ou des photos très dégradées. C'est exactement ce que la règle
des 80% sur 20 documents inconnus sert à vérifier avant de montrer l'outil —
ne saute pas cette étape.

## Déploiement o2switch

`public/` comme docroot du sous-domaine (aucun besoin de PHP pour le
fonctionnement de l'outil lui-même, mais garder `public/` comme racine reste
la convention des autres projets). Créer le sous-domaine avec `dir` pointant
sur `demoprospect.serviceproi.fr/public`, puis déposer le contenu de
`public/` par FTP.

## Hors périmètre (volontairement)

Connexion compta, multi-utilisateurs, historique, correction manuelle dans
l'interface, traitement par lots dans l'UI (le lot, c'est
`scripts/tester-lot.js`, réservé aux tests). Ne pas ajouter.
