<?php

declare(strict_types=1);

/**
 * GEMINI-COST-001 — journal commun de consommation Gemini.
 *
 * Copie IDENTIQUE dans chaque outil ServiceProI qui appelle Gemini (site,
 * prospection, diagnostic-core, Coffre, DemoProspect) : les dépôts sont
 * séparés, mais tous tournent sous le même compte o2switch et écrivent au
 * même endroit, lu par Espace pro → Coûts Gemini.
 *
 * Une ligne JSON par réponse Gemini reçue (donc facturée), dans
 * serviceproi-bo-data/gemini-usage/AAAA-MM.jsonl : outil, opération,
 * modèle, et les compteurs de tokens renvoyés par l'API (usageMetadata).
 * Aucun contenu (prompt, réponse, clé) n'est écrit.
 *
 * Le journal n'est actif que si ce dossier EXISTE déjà (créé une fois en
 * prod) : en local et dans les tests, rien n'est écrit. La variable
 * d'environnement SP_GEMINI_JOURNAL_DIR force un autre dossier (tests).
 *
 * Ne lève jamais d'exception et ne bloque jamais l'appel Gemini : un
 * journal indisponible fait seulement perdre la mesure.
 */

if (!function_exists('sp_gemini_journal')) {

    /** Dossier du journal, ou null si le journal n'est pas activé sur cette machine. */
    function sp_gemini_journal_dir(): ?string
    {
        $force = getenv('SP_GEMINI_JOURNAL_DIR');
        if (is_string($force) && $force !== '') {
            return is_dir($force) ? $force : null;
        }
        // Remonte depuis ce fichier jusqu'au dossier personnel du compte,
        // quelle que soit la profondeur de l'outil (docroot, sous-dossier…).
        $dir = __DIR__;
        for ($i = 0; $i < 6; $i++) {
            $candidat = $dir . '/serviceproi-bo-data/gemini-usage';
            if (is_dir($candidat)) {
                return $candidat;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        return null;
    }

    /**
     * @param string       $outil     ex. 'site', 'prospection', 'diagnostic-core', 'coffre', 'demoprospect'
     * @param string       $operation ex. 'analyse-description', 'formulate-question'
     * @param string       $modele    modèle réellement interrogé
     * @param string|array $reponse   corps brut de la réponse HTTP, ou déjà décodé
     */
    function sp_gemini_journal(string $outil, string $operation, string $modele, mixed $reponse): void
    {
        try {
            $dir = sp_gemini_journal_dir();
            if ($dir === null) {
                return;
            }
            $decoded = is_string($reponse) ? json_decode($reponse, true) : $reponse;
            $usage = is_array($decoded) ? ($decoded['usageMetadata'] ?? null) : null;
            if (!is_array($usage)) {
                return;
            }
            $n = static fn(string $cle): int => is_numeric($usage[$cle] ?? null) ? (int) $usage[$cle] : 0;
            $maintenant = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
            $ligne = json_encode([
                't' => $maintenant->format('c'),
                'outil' => $outil,
                'op' => $operation,
                'modele' => $modele,
                'in' => $n('promptTokenCount'),
                'out' => $n('candidatesTokenCount'),
                'think' => $n('thoughtsTokenCount'),
                'cache' => $n('cachedContentTokenCount'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            @file_put_contents($dir . '/' . $maintenant->format('Y-m') . '.jsonl', $ligne . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Mesure perdue, appel Gemini intact.
        }
    }
}
