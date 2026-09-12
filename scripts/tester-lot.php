<?php

declare(strict_types=1);

/**
 * Teste l'extraction sur un dossier de documents inconnus et calcule le taux
 * de réussite. Règle du cadrage : ne pas montrer l'outil en dessous de 80%.
 *
 * Usage : php scripts/tester-lot.php /chemin/vers/documents
 */

require __DIR__ . '/../app/_extract.php';

$dossier = $argv[1] ?? null;
if ($dossier === null || !is_dir($dossier)) {
    fwrite(STDERR, "Usage: php scripts/tester-lot.php <dossier-de-documents>\n");
    exit(1);
}

$config = require __DIR__ . '/../config/anthropic.php';
$cleApi = $config['api_key'] ?? '';

$typesAcceptes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
$fichiers = array_values(array_filter(
    scandir($dossier),
    fn($f) => is_file("$dossier/$f")
));

$total = 0;
$reussites = 0;

foreach ($fichiers as $nomFichier) {
    $chemin = "$dossier/$nomFichier";
    $mimeType = mime_content_type($chemin);
    if (!in_array($mimeType, $typesAcceptes, true)) {
        continue;
    }

    $total++;
    $resultat = dp_extraire($chemin, $mimeType, $cleApi);

    if ($resultat['succes'] && !empty($resultat['champs']['lignes'])) {
        $reussites++;
        $confiance = $resultat['champs']['confiance'] ?? '?';
        $nbLignes = count($resultat['champs']['lignes']);
        echo "[OK]    $nomFichier — confiance: $confiance — $nbLignes ligne(s)\n";
    } else {
        $erreurTexte = $resultat['erreur'] ?? 'aucune ligne détectée';
        echo "[ECHEC] $nomFichier — $erreurTexte\n";
    }
}

if ($total === 0) {
    echo "Aucun document trouvé dans $dossier.\n";
    exit(0);
}

$taux = round(100 * $reussites / $total, 1);
echo "\n--- $reussites / $total réussites — taux : $taux% ---\n";
echo $taux >= 80
    ? "Seuil de 80% atteint : tu peux montrer l'outil.\n"
    : "Sous 80% : ne montre pas encore, corrige d'abord.\n";
