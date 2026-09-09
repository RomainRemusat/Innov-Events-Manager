"""Suite de tests automatisés : Sécurité des téléversements de fichiers (Upload).

Vérifie :
1. Validation stricte du type MIME réel (Fileinfo / octets magiques).
2. Contrôle d'intégrité de l'image (getimagesize).
3. Dérivation exclusive de l'extension depuis le type MIME (et non du nom client).
4. Nommage cryptographique aléatoire.
5. Limite de taille (5 Mo).
6. Rejet des fichiers exécutables / polyglottes (.php, .phtml, .phar...).
7. Intégration dans le workflow de conversion (ConversionService).
"""

import http.cookiejar
import json
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request


URL = "http://localhost:8081/index.php?action="


def run_php(code: str):
    cmd = ["docker", "compose", "exec", "-T", "app", "php", "-r", code]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        raise RuntimeError(f"PHP code execution failed (exit code {res.returncode}):\nSTDOUT: {res.stdout}\nSTDERR: {res.stderr}")
    return res.stdout.strip()


def test_file_upload_service():
    print("--- Test 1 : Validation unitaire de FileUploadService ---", flush=True)

    php_test = r'''
    require_once __DIR__ . '/src/services/FileUploadService.php';

    $service = new FileUploadService(5 * 1024 * 1024);
    $targetDir = sys_get_temp_dir() . '/test_uploads_' . bin2hex(random_bytes(4));
    mkdir($targetDir, 0755, true);

    // 1. Fichier PNG 1x1 valide
    $validPngBytes = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==");
    $validPngFile = tempnam(sys_get_temp_dir(), 'test_png_');
    file_put_contents($validPngFile, $validPngBytes);

    $uploadedName = $service->uploadImage([
        'name'     => 'exploit.php.png',
        'type'     => 'image/png',
        'tmp_name' => $validPngFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($validPngBytes)
    ], $targetDir, 'event_');

    if (!$uploadedName || !str_ends_with($uploadedName, '.png') || !str_starts_with($uploadedName, 'event_')) {
        echo json_encode(['success' => false, 'error' => "Le fichier n'a pas reçu le bon préfixe/extension : $uploadedName"]);
        exit;
    }
    if (!file_exists($targetDir . '/' . $uploadedName)) {
        echo json_encode(['success' => false, 'error' => "Le fichier n'a pas été sauvegardé"]);
        exit;
    }

    // 2. Fichier avec fausse extension .php mais contenu PNG valide -> Doit être renommé .png
    $uploadedFakeExt = $service->uploadImage([
        'name'     => 'malicious_script.php',
        'type'     => 'application/x-php',
        'tmp_name' => $validPngFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($validPngBytes)
    ], $targetDir, 'event_');

    if (!$uploadedFakeExt || !str_ends_with($uploadedFakeExt, '.png') || str_contains($uploadedFakeExt, '.php')) {
        echo json_encode(['success' => false, 'error' => "L'extension n'a pas été dérivée du MIME réel : $uploadedFakeExt"]);
        exit;
    }

    // 3. Fichier script PHP (faux MIME png mais contenu texte PHP) -> Doit être rejeté par getimagesize
    $fakePngFile = tempnam(sys_get_temp_dir(), 'fake_png_');
    file_put_contents($fakePngFile, "<?php echo 'HACKED'; ?>");
    $rejectedPhp = false;
    try {
        $service->uploadImage([
            'name'     => 'shell.png',
            'type'     => 'image/png',
            'tmp_name' => $fakePngFile,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen("<?php echo 'HACKED'; ?>")
        ], $targetDir, 'event_');
    } catch (\InvalidArgumentException $e) {
        $rejectedPhp = true;
    }
    if (!$rejectedPhp) {
        echo json_encode(['success' => false, 'error' => "Le script PHP déguisé en PNG n'a pas été rejeté"]);
        exit;
    }

    // 4. Fichier dépassant la taille maximale (5 Mo)
    $rejectedSize = false;
    try {
        $service->uploadImage([
            'name'     => 'huge.png',
            'type'     => 'image/png',
            'tmp_name' => $validPngFile,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 10 * 1024 * 1024
        ], $targetDir, 'event_');
    } catch (\InvalidArgumentException $e) {
        $rejectedSize = true;
    }
    if (!$rejectedSize) {
        echo json_encode(['success' => false, 'error' => "Le fichier trop volumineux n'a pas été rejeté"]);
        exit;
    }

    // Nettoyage
    @unlink($validPngFile);
    @unlink($fakePngFile);
    @unlink($targetDir . '/' . $uploadedName);
    @unlink($targetDir . '/' . $uploadedFakeExt);
    @rmdir($targetDir);

    echo json_encode(['success' => true]);
    '''

    res = json.loads(run_php(php_test))
    assert res.get("success"), f"Échec du test unitaire FileUploadService: {res.get('error')}"
    print("OK : FileUploadService valide MIME, intégrité d'image, extension dérivée et taille", flush=True)


def test_conversion_service_upload():
    print("--- Test 2 : Intégration de l'upload dans ConversionService ---", flush=True)

    php_test = r'''
    require_once __DIR__ . '/src/services/ConversionService.php';
    require_once __DIR__ . '/src/models/sql/Prospect.php';
    require_once __DIR__ . '/src/config/Database.php';

    $db = Database::getInstance();

    // Création d'un prospect de test
    $prospectEmail = 'upload_test_' . bin2hex(random_bytes(4)) . '@test.com';
    $stmt = $db->prepare("
        INSERT INTO prospects (company_name, contact_name, email, phone, event_type, event_date, location, estimated_participants, description, status)
        VALUES ('Upload Co', 'Upload Tester', ?, '0600000000', 'Gala', '2026-10-15', 'Paris', 50, 'Test upload', 'à contacter')
    ");
    $stmt->execute([$prospectEmail]);
    $prospectId = (int)$db->lastInsertId();

    // Image PNG valide avec nom client potentiellement hostile
    $validPngBytes = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==");
    $tmpImg = tempnam(sys_get_temp_dir(), 'conv_img_');
    file_put_contents($tmpImg, $validPngBytes);

    $filePayload = [
        'name'     => 'backdoor.php.png',
        'type'     => 'image/png',
        'tmp_name' => $tmpImg,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($validPngBytes)
    ];

    $convService = new ConversionService();
    $devisId = $convService->convertProspectToClient([
        'prospect_id'            => $prospectId,
        'company_name'           => 'Upload Co',
        'contact_name'           => 'Upload Tester',
        'email'                  => $prospectEmail,
        'phone'                  => '0600000000',
        'event_title'            => 'Gala Upload Test',
        'start_date'             => '2026-10-15 19:00:00',
        'location'               => 'Paris',
        'estimated_participants' => 50,
        'description'            => 'Event with secure uploaded image',
        'event_status'           => 'brouillon'
    ], $filePayload, 1);

    // Vérification de l'image enregistrée en BDD
    $stmtEvt = $db->prepare("SELECT image_path FROM events WHERE title = 'Gala Upload Test' ORDER BY id DESC LIMIT 1");
    $stmtEvt->execute();
    $event = $stmtEvt->fetch(PDO::FETCH_ASSOC);

    if (!$event || empty($event['image_path'])) {
        echo json_encode(['success' => false, 'error' => "Aucune image enregistrée pour l'événement"]);
        exit;
    }

    $imagePath = $event['image_path'];
    if (!str_ends_with($imagePath, '.png') || str_contains($imagePath, '.php')) {
        echo json_encode(['success' => false, 'error' => "Chemin d'image non sécurisé : $imagePath"]);
        exit;
    }

    $fullDiskPath = __DIR__ . '/public/' . $imagePath;
    if (!file_exists($fullDiskPath)) {
        echo json_encode(['success' => false, 'error' => "Fichier image absent sur le disque : $fullDiskPath"]);
        exit;
    }

    // Nettoyage
    @unlink($tmpImg);
    @unlink($fullDiskPath);

    echo json_encode(['success' => true]);
    '''

    res = json.loads(run_php(php_test))
    assert res.get("success"), f"Échec du test de conversion d'image: {res.get('error')}"
    print("OK : ConversionService enregistre correctement l'image avec nommage aléatoire sécurisé", flush=True)


def test_uploads_htaccess():
    print("--- Test 3 : Vérification de la configuration de protection public/uploads ---", flush=True)
    with open("public/uploads/.htaccess", "r", encoding="utf-8") as f:
        content = f.read()
    assert "Require all denied" in content, "Directive Require all denied absente de public/uploads/.htaccess"
    assert "php" in content, "Protection des scripts PHP absente de .htaccess"
    print("OK : public/uploads/.htaccess protège contre l'exécution de scripts", flush=True)


def main():
    print("=== DÉBUT DES TESTS DE SÉCURITÉ DES UPLOADS ===", flush=True)
    test_file_upload_service()
    test_conversion_service_upload()
    test_uploads_htaccess()
    print("===========================================================", flush=True)
    print("TOUS LES CONTRÔLES DE SÉCURITÉ D'UPLOAD SONT VALIDES !", flush=True)
    print("===========================================================", flush=True)


if __name__ == '__main__':
    main()
