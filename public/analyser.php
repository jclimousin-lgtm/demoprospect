<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../app/_extract.php';

$cleApi = trim((string) ($_POST['cle_api'] ?? ''));

if ($cleApi === '') {
    http_response_code(400);
    echo json_encode(['succes' => false, 'erreur' => "Clé API manquante."]);
    exit;
}

if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['succes' => false, 'erreur' => "Aucun fichier reçu."]);
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
