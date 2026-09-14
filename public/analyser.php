<?php

declare(strict_types=1);

// Le php.ini du serveur limite l'exécution à 30s par défaut, mais l'appel
// Gemini (vision, gros documents) peut dépasser ça — cf. CURLOPT_TIMEOUT
// dans _extract.php. On relève la limite pour ce script précis.
set_time_limit(90);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/_extract.php';

$cheminConfig = __DIR__ . '/../demoprospect-config-prive/gemini.php';
if (!file_exists($cheminConfig)) {
    http_response_code(500);
    echo json_encode(['succes' => false, 'erreur' => "Clé API non configurée côté serveur (demoprospect-config-prive/gemini.php manquant)."]);
    exit;
}

$config = require $cheminConfig;
$cleApi = trim((string) ($config['api_key'] ?? ''));

if ($cleApi === '') {
    http_response_code(500);
    echo json_encode(['succes' => false, 'erreur' => "Clé API vide dans la configuration serveur."]);
    exit;
}

if (empty($_FILES['document'])) {
    http_response_code(400);
    echo json_encode(['succes' => false, 'erreur' => "Aucun fichier reçu."]);
    exit;
}

if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $messagesErreur = [
        UPLOAD_ERR_INI_SIZE => "Fichier trop volumineux pour le serveur (limite d'envoi dépassée).",
        UPLOAD_ERR_FORM_SIZE => "Fichier trop volumineux pour le serveur (limite d'envoi dépassée).",
        UPLOAD_ERR_PARTIAL => "Envoi interrompu, réessayez.",
        UPLOAD_ERR_NO_FILE => "Aucun fichier reçu.",
    ];
    $message = $messagesErreur[$_FILES['document']['error']] ?? "Échec de l'envoi (code {$_FILES['document']['error']}).";
    http_response_code(400);
    echo json_encode(['succes' => false, 'erreur' => $message]);
    exit;
}

$fichier = $_FILES['document'];
$typesAcceptes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic'];
$mimeType = mime_content_type($fichier['tmp_name']);

if (!in_array($mimeType, $typesAcceptes, true)) {
    http_response_code(400);
    echo json_encode(['succes' => false, 'erreur' => "Type de fichier non pris en charge ($mimeType). PDF, JPG, PNG ou WEBP uniquement."]);
    exit;
}

if ($fichier['size'] > 15 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['succes' => false, 'erreur' => "Fichier trop volumineux (15 Mo max)."]);
    exit;
}

$resultat = dp_extraire($fichier['tmp_name'], $mimeType, $cleApi);
echo json_encode($resultat, JSON_UNESCAPED_UNICODE);
