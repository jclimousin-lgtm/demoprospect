<?php

declare(strict_types=1);

/**
 * Appelle Gemini (vision / document) avec un schéma de sortie forcé
 * (responseSchema) pour garantir une extraction structurée fiable plutôt
 * qu'un parsing fragile de texte libre.
 */
function dp_extraire(string $cheminFichier, string $mimeType, string $cleApi): array
{
    if ($cleApi === '') {
        return ['succes' => false, 'erreur' => "Clé API Gemini absente (config/gemini.php)."];
    }

    $donnees = base64_encode((string) file_get_contents($cheminFichier));

    $schema = [
        'type' => 'OBJECT',
        'properties' => [
            'fournisseur' => ['type' => 'STRING', 'description' => "Nom de l'émetteur du document"],
            'numero_document' => ['type' => 'STRING'],
            'date' => ['type' => 'STRING', 'description' => "Date telle qu'écrite sur le document"],
            'total_ht' => ['type' => 'NUMBER'],
            'tva' => ['type' => 'NUMBER'],
            'total_ttc' => ['type' => 'NUMBER'],
            'lignes' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'designation' => ['type' => 'STRING'],
                        'quantite' => ['type' => 'NUMBER'],
                        'prix_unitaire' => ['type' => 'NUMBER'],
                        'montant' => ['type' => 'NUMBER'],
                    ],
                    'required' => ['designation'],
                ],
            ],
            'confiance' => [
                'type' => 'STRING',
                'enum' => ['haute', 'moyenne', 'basse'],
                'description' => "Ta confiance globale dans la lecture de ce document",
            ],
        ],
        'required' => ['lignes', 'confiance'],
    ];

    $payload = [
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $donnees]],
                ['text' => "Lis ce document commercial (facture, bon de livraison ou devis) et extrais les champs "
                    . "demandés. N'invente aucune valeur : si un champ n'est pas lisible sur le document, omets-le "
                    . "simplement plutôt que de deviner."],
            ],
        ]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema,
        ],
    ];

    $modele = 'gemini-2.5-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modele}:generateContent?key=" . urlencode($cleApi);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['content-type: application/json'],
        CURLOPT_TIMEOUT => 60,
    ]);
    $reponseBrute = curl_exec($ch);
    $erreurCurl = curl_error($ch);
    $codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($reponseBrute === false) {
        return ['succes' => false, 'erreur' => "Appel API impossible : $erreurCurl"];
    }

    $reponse = json_decode($reponseBrute, true);

    if ($codeHttp !== 200) {
        $message = $reponse['error']['message'] ?? $reponseBrute;
        return ['succes' => false, 'erreur' => "Erreur API ($codeHttp) : $message"];
    }

    $texteJson = $reponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($texteJson === null) {
        return ['succes' => false, 'erreur' => "Réponse inattendue du modèle."];
    }

    $champs = json_decode($texteJson, true);
    if (!is_array($champs)) {
        return ['succes' => false, 'erreur' => "Le modèle n'a pas renvoyé de JSON exploitable."];
    }

    return ['succes' => true, 'champs' => $champs];
}
