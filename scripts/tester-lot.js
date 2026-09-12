#!/usr/bin/env node
'use strict';

/**
 * Teste l'extraction sur un dossier de documents inconnus et calcule le taux
 * de réussite. Règle du cadrage : ne pas montrer l'outil en dessous de 80%.
 *
 * Utilise le tesseract système + poppler (pdftoppm) en local, avec la même
 * logique d'extraction (public/extraction.js) que la page web. L'OCR système
 * n'est pas strictement identique au moteur WASM embarqué dans le
 * navigateur, mais s'appuie sur le même moteur Tesseract — un bon indicateur
 * avant de tester la vraie page dans un navigateur sur quelques documents.
 *
 * Usage : node scripts/tester-lot.js <dossier-de-documents>
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { extraireChampsDepuisTexte } = require('../public/extraction.js');

const dossier = process.argv[2];
if (!dossier || !fs.existsSync(dossier)) {
  console.error('Usage: node scripts/tester-lot.js <dossier-de-documents>');
  process.exit(1);
}

const extensionsAcceptees = new Set(['.pdf', '.jpg', '.jpeg', '.png', '.webp']);

function texteOcr(cheminFichier, dossierTmp) {
  const ext = path.extname(cheminFichier).toLowerCase();
  let cheminImage = cheminFichier;

  if (ext === '.pdf') {
    const prefixe = path.join(dossierTmp, 'page');
    execFileSync('pdftoppm', ['-png', '-r', '300', '-f', '1', '-l', '1', cheminFichier, prefixe]);
    const genere = fs.readdirSync(dossierTmp).find((f) => f.startsWith('page') && f.endsWith('.png'));
    if (!genere) throw new Error('conversion PDF → image échouée');
    cheminImage = path.join(dossierTmp, genere);
  }

  return execFileSync('tesseract', [cheminImage, 'stdout', '-l', 'fra'], {
    encoding: 'utf8',
    maxBuffer: 10 * 1024 * 1024,
    stdio: ['ignore', 'pipe', 'ignore'],
  });
}

let total = 0;
let reussites = 0;

for (const nomFichier of fs.readdirSync(dossier).sort()) {
  const cheminFichier = path.join(dossier, nomFichier);
  if (!fs.statSync(cheminFichier).isFile()) continue;
  if (!extensionsAcceptees.has(path.extname(nomFichier).toLowerCase())) continue;

  total += 1;
  const dossierTmp = fs.mkdtempSync(path.join(os.tmpdir(), 'demoprospect-'));

  try {
    const texte = texteOcr(cheminFichier, dossierTmp);
    const champs = extraireChampsDepuisTexte(texte);
    const aReussi = champs.lignes.length > 0 || champs.total_ttc !== null || champs.numero_document !== null;

    if (aReussi) {
      reussites += 1;
      console.log(`[OK]    ${nomFichier} — confiance: ${champs.confiance} — ${champs.lignes.length} ligne(s)`);
    } else {
      console.log(`[ECHEC] ${nomFichier} — aucun champ exploitable détecté`);
    }
  } catch (e) {
    console.log(`[ECHEC] ${nomFichier} — ${e.message}`);
  } finally {
    fs.rmSync(dossierTmp, { recursive: true, force: true });
  }
}

if (total === 0) {
  console.log(`Aucun document trouvé dans ${dossier}.`);
  process.exit(0);
}

const taux = Math.round((reussites / total) * 1000) / 10;
console.log(`\n--- ${reussites} / ${total} réussites — taux : ${taux}% ---`);
console.log(taux >= 80
  ? "Seuil de 80% atteint : tu peux montrer l'outil."
  : "Sous 80% : ne montre pas encore, corrige d'abord.");
