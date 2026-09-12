<?php

declare(strict_types=1);

/**
 * Appelle Claude (vision / document) en mode tool-use pour forcer une
 * extraction structurée. tool_choice force le modèle à répondre via l'outil
 * plutôt qu'en texte libre, ce qui évite le parsing fragile de JSON.
 */
function dp_extraire(string $cheminFichier, string $mimeType, string $cleApi): array
{
    if ($cleApi === '') {
        return ['succes' => false, 'erreur' => "Clé API Anthropic absente (config/anthropic.php)."];
    }

    $donnees = base64_encode((string) file_get_contents($cheminFichier));

    $blocContenu = $mimeType === 'application/pdf'
        ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $donnees]]
        : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $donnees]];

    $outil = [
        'name' => 'extraire_facture',
        'description' => "Extrait les champs d'un document commercial (facture, bon de livraison ou devis) tel qu'il apparaît réellement sur le document, sans corriger ni deviner les valeurs manquantes.",
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'fournisseur' => ['type' => 'string', 'description' => "Nom de l'émetteur du document"],
                'numero_document' => ['type' => 'string'],
                'date' => ['type' => 'string', 'description' => "Date telle qu'écrite sur le document"],
                'total_ht' => ['type' => 'number'],
                'tva' => ['type' => 'number'],
                'total_ttc' => ['type' => 'number'],
                'lignes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'designation' => ['type' => 'string'],
                            'quantite' => ['type' => 'number'],
                            'prix_unitaire' => ['type' => 'number'],
                            'montant' => ['type' => 'number'],
                        ],
                        'required' => ['designation'],
                    ],
                ],
                'confiance' => [
                    'type' => 'string',
                    'enum' => ['haute', 'moyenne', 'basse'],
                    'description' => "Ta confiance globale dans la lecture de ce document",
                ],
            ],
            'required' => ['lignes', 'confiance'],
        ],
    ];

    $payload = [
        'model' => 'claude-sonnet-5',
        'max_tokens' => 2048,
        'tools' => [$outil],
        'tool_choice' => ['type' => 'tool', 'name' => 'extraire_facture'],
        'messages' => [[
            'role' => 'user',
            'content' => [
                $blocContenu,
                [
                    'type' => 'text',
                    'text' => "Lis ce document commercial et extrais les champs demandés via l'outil. "
                        . "N'invente aucune valeur : si un champ n'est pas lisible sur le document, omets-le simplement.",
                ],
            ],
        ]],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . $cleApi,
            'anthropic-version: 2023-06-01',
        ],
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

    foreach ($reponse['content'] ?? [] as $bloc) {
        if (($bloc['type'] ?? null) === 'tool_use' && ($bloc['name'] ?? null) === 'extraire_facture') {
            return ['succes' => true, 'champs' => $bloc['input']];
        }
    }

    return ['succes' => false, 'erreur' => "Le modèle n'a pas renvoyé de données structurées."];
}
