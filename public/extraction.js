/**
 * Extraction des champs d'un document commercial à partir du texte brut
 * renvoyé par l'OCR (Tesseract). Approche par règles/regex — pas d'IA,
 * pas d'appel réseau. Fonctionne aussi bien en navigateur (window) qu'en
 * Node (script de test en lot).
 */
(function (racine, fabrique) {
  if (typeof module === 'object' && module.exports) {
    module.exports = fabrique();
  } else {
    racine.DemoProspect = fabrique();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  const MOTS_CLES_HT = /(total\s*h\.?t\.?|montant\s*h\.?t\.?|sous[\s-]*total)/i;
  const MOTS_CLES_TVA = /\btva\b/i;
  const MOTS_CLES_TTC = /(total\s*t\.?t\.?c\.?|net\s*[àa]\s*payer|montant\s*d[uû])/i;
  const MOTS_CLES_NUMERO = /(facture|devis|bon\s*de\s*livraison|bon\s*de\s*commande|commande)\s*n[°o]?\s*[:\-]?\s*([A-Z0-9][\w\-\/\.]{1,24})/i;
  const MOTS_CLES_NUMERO_GENERIQUE = /n[°o]\s*[:\-]?\s*([A-Z0-9][\w\-\/\.]{1,24})/i;
  const MOTS_CLES_ENTETE_TABLEAU = /(d[ée]signation|description|libell[ée]).*(qt[ée]|quantit[ée])?/i;
  const DATE_REGEX = /\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{1,2}\s+(?:janvier|f[ée]vrier|mars|avril|mai|juin|juillet|ao[ûu]t|septembre|octobre|novembre|d[ée]cembre)\s+\d{4})\b/i;

  function normaliserNombre(brut) {
    let s = String(brut).trim().replace(/\s/g, '').replace(/€|EUR/gi, '');
    if (s.includes(',') && s.includes('.')) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else if (s.includes(',')) {
      s = s.replace(',', '.');
    }
    const n = parseFloat(s);
    return Number.isFinite(n) ? n : null;
  }

  function nombresDansLigne(ligne) {
    const sansDates = ligne.replace(DATE_REGEX, ' ');
    const resultats = [];
    const regexPourcentage = /\d+(?:[.,]\d+)?\s*%/g;
    const pourcentages = sansDates.match(regexPourcentage) || [];
    let texteRestant = sansDates.replace(regexPourcentage, ' ');

    const regexNombre = /\d{1,3}(?:[ .]\d{3})+(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?/g;
    let m;
    while ((m = regexNombre.exec(texteRestant)) !== null) {
      const valeur = normaliserNombre(m[0]);
      if (valeur !== null) resultats.push(valeur);
    }
    return { nombres: resultats, nbPourcentages: pourcentages.length };
  }

  function chercherMontant(lignes, motsCles) {
    for (const ligne of lignes) {
      if (motsCles.test(ligne)) {
        const { nombres } = nombresDansLigne(ligne);
        if (nombres.length > 0) return nombres[nombres.length - 1];
      }
    }
    return null;
  }

  function chercherNumeroDocument(lignes) {
    for (const ligne of lignes) {
      const m = MOTS_CLES_NUMERO.exec(ligne) || MOTS_CLES_NUMERO_GENERIQUE.exec(ligne);
      if (m) return m[m.length - 1];
    }
    return null;
  }

  function chercherDate(lignes) {
    for (const ligne of lignes) {
      const m = DATE_REGEX.exec(ligne);
      if (m) return m[1];
    }
    return null;
  }

  function chercherFournisseur(lignes) {
    for (const ligne of lignes) {
      const propre = ligne.trim();
      if (propre.length >= 3 && /[A-Za-zÀ-ÿ]{3,}/.test(propre) && !DATE_REGEX.test(propre)) {
        return propre;
      }
    }
    return null;
  }

  function extraireLignesTableau(lignes) {
    let indexDebut = lignes.findIndex((l) => MOTS_CLES_ENTETE_TABLEAU.test(l));
    if (indexDebut === -1) indexDebut = 0;

    const resultat = [];
    for (let i = indexDebut + 1; i < lignes.length; i++) {
      const ligne = lignes[i];
      if (MOTS_CLES_HT.test(ligne) || MOTS_CLES_TVA.test(ligne) || MOTS_CLES_TTC.test(ligne)) break;

      const { nombres } = nombresDansLigne(ligne);
      if (nombres.length === 0) continue;

      const designation = ligne.replace(/\d{1,3}(?:[ .]\d{3})*(?:[,\.]\d{1,2})?/g, '').trim();
      if (designation.length < 2) continue;

      const ligneExtraite = { designation };
      if (nombres.length >= 3) {
        [ligneExtraite.quantite, ligneExtraite.prix_unitaire, ligneExtraite.montant] = nombres.slice(-3);
      } else if (nombres.length === 2) {
        [ligneExtraite.quantite, ligneExtraite.montant] = nombres;
      } else {
        [ligneExtraite.montant] = nombres;
      }
      resultat.push(ligneExtraite);
    }
    return resultat;
  }

  function extraireChampsDepuisTexte(texteOCR) {
    const lignes = String(texteOCR)
      .split(/\r?\n/)
      .map((l) => l.trim())
      .filter((l) => l.length > 0);

    const champs = {
      fournisseur: chercherFournisseur(lignes),
      numero_document: chercherNumeroDocument(lignes),
      date: chercherDate(lignes),
      total_ht: chercherMontant(lignes, MOTS_CLES_HT),
      tva: chercherMontant(lignes, MOTS_CLES_TVA),
      total_ttc: chercherMontant(lignes, MOTS_CLES_TTC),
      lignes: extraireLignesTableau(lignes),
    };

    const champsTrouves = [champs.numero_document, champs.date, champs.total_ttc].filter((v) => v !== null).length;
    if (champsTrouves >= 3 && champs.lignes.length > 0) {
      champs.confiance = 'haute';
    } else if (champsTrouves >= 1 || champs.lignes.length > 0) {
      champs.confiance = 'moyenne';
    } else {
      champs.confiance = 'basse';
    }

    return champs;
  }

  return { extraireChampsDepuisTexte };
}));
