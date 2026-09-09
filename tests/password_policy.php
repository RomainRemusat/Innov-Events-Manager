<?php

// Contrôle sans base de données : politique ECF et comptes de démonstration.
require_once __DIR__ . '/../src/controllers/AuthController.php';

$controller = new AuthController();
$validate = new ReflectionMethod(AuthController::class, 'isValidPassword');
$cases = [
    'Abcdef1!' => true,       // Minimum de huit caractères.
    'Password123!' => true,
    'Abcd1!x' => false,       // Trop court.
    'abcdef1!' => false,      // Pas de majuscule.
    'ABCDEF1!' => false,      // Pas de minuscule.
    'Abcdefg!' => false,      // Pas de chiffre.
    'Abcdef12' => false,      // Pas de caractère spécial.
    'password' => false,
];
foreach ($cases as $password => $expected) {
    if ($validate->invoke($controller, $password) !== $expected) {
        throw new RuntimeException('Politique de mot de passe non conforme à l’ECF.');
    }
}

$hashes = [];
foreach (file(__DIR__ . '/../scripts/initialise.sql') as $line) {
    $parts = explode("'", $line);
    if (count($parts) < 4 || !str_starts_with($parts[3], '$2y$')) {
        continue;
    }
    if (!password_verify('Password123!', $parts[3]) || password_verify('password', $parts[3])) {
        throw new RuntimeException('Hash de démonstration incorrect.');
    }
    $hashes[] = $parts[3];
}
if (count($hashes) !== 4 || count(array_unique($hashes)) !== 4) {
    throw new RuntimeException('Quatre comptes avec des hashes distincts sont attendus.');
}
echo "OK : politique ECF, quatre mots de passe conformes et sels distincts.\n";
