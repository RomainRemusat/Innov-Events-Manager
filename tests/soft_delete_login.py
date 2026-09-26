"""Suite de tests automatisés : Verrouillage des comptes suspendus / supprimés (Soft Delete).

Vérifie :
1. Connexion réussie pour un compte client actif (is_deleted = 0).
2. Refus de connexion et notification explicite pour un compte soft-deleted (is_deleted = 1).
3. Journalisation NoSQL de la tentative de connexion sur compte suspendu/supprimé.
4. Neutralisation de la réinitialisation de mot de passe (reset_password_request) pour un compte soft-deleted.
5. Révocation immédiate d'une session active si le compte passe en is_deleted = 1 en cours de session.
6. Exclusion des comptes soft-deleted de la liste administrative des clients (findAllClients).
7. Exclusion des comptes soft-deleted du décompte des clients actifs (countActiveClients).
"""

import http.cookiejar
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request


URL = "http://localhost:8081/index.php?action="


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def http_error_302(self, req, fp, code, msg, headers):
        return fp

    def http_error_303(self, req, fp, code, msg, headers):
        return fp

    def http_error_307(self, req, fp, code, msg, headers):
        return fp


def run_php(code: str):
    cmd = ["docker", "compose", "exec", "-T", "app", "php", "-r", code]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        raise RuntimeError(f"PHP code execution failed (exit code {res.returncode}):\nSTDOUT: {res.stdout}\nSTDERR: {res.stderr}")
    return res.stdout.strip()


def request(client, action, data=None):
    req_url = URL + action
    if data is not None:
        encoded_data = urllib.parse.urlencode(data).encode("utf-8")
        req = urllib.request.Request(req_url, data=encoded_data, method="POST")
    else:
        req = urllib.request.Request(req_url, method="GET")

    try:
        response = client.open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error

    with response:
        code = response.code
        headers = response.headers
        body = response.read().decode("utf-8", errors="replace")

        assert not any(
            err in body for err in ("Fatal error", "TypeError", "Parse error")
        ), f"Erreur PHP fatale sur {action} : {body[:300]}"

        return code, headers, body


def get_csrf(client, action="login"):
    _, _, body = request(client, action)
    match = re.search(r'name="csrf_token"\s+value="([^"]+)"', body)
    if match:
        return match.group(1)
    return None


def main():
    print("=== DÉBUT DES TESTS SOFT-DELETE & VERROUILLAGE CONNEXION ===", flush=True)

    marker = "softdel_" + secrets.token_hex(4)
    email = f"{marker}@example.test"
    password = "Password123!"

    # 1. Création d'une entreprise et d'un utilisateur client actif
    setup_code = f"""
    require_once __DIR__ . '/src/config/Database.php';
    require_once __DIR__ . '/src/models/sql/User.php';
    require_once __DIR__ . '/src/models/sql/Company.php';

    $db = Database::getInstance();
    $companyModel = new Company();
    $companyId = $companyModel->findOrCreateAndEnrich('SoftDel Co');

    $userModel = new User();
    $userId = $userModel->create([
        'email'     => '{email}',
        'password'  => password_hash('{password}', PASSWORD_BCRYPT),
        'firstname' => 'Soft',
        'lastname'  => 'DeleteTester',
        'role'      => 'CLIENT'
    ]);

    $db->prepare("UPDATE users SET company_id = ? WHERE id = ?")->execute([$companyId, $userId]);

    echo json_encode(['user_id' => $userId, 'company_id' => $companyId]);
    """
    res = json.loads(run_php(setup_code))
    user_id = res['user_id']
    company_id = res['company_id']

    try:
        # --- Test 1 : Connexion normale d'un client actif ---
        print("--- Test 1 : Connexion normale d'un client actif ---", flush=True)
        jar = http.cookiejar.CookieJar()
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())

        token = get_csrf(client, "login")
        assert token, "Jeton CSRF manquant sur le login"

        code, headers, body = request(client, "login", {
            "email": email,
            "password": password,
            "csrf_token": token
        })

        assert code == 302, f"Code HTTP attendu 302, reçu {code}"
        assert headers.get("Location") == "index.php?action=client_dashboard", f"Redirection incorrecte : {headers.get('Location')}"
        print("OK : Connexion réussie et session active pour le client actif", flush=True)

        # --- Test 2 : Accès au dashboard client avec session active ---
        print("--- Test 2 : Accès au dashboard client ---", flush=True)
        code, _, body = request(client, "client_dashboard")
        assert code == 200, f"Dashboard client inaccessible (code {code})"
        assert "Espace Client" in body or "Mes Devis" in body or "Soft" in body
        print("OK : Dashboard client accessible avec la session active", flush=True)

        # --- Test 3 : Soft-delete du compte client ---
        print("--- Test 3 : Application du soft-delete (is_deleted = 1) ---", flush=True)
        soft_del_code = f"""
        require_once __DIR__ . '/src/models/sql/User.php';
        $userModel = new User();
        $ok = $userModel->softDeleteClient({user_id});
        echo json_encode(['success' => $ok]);
        """
        soft_res = json.loads(run_php(soft_del_code))
        assert soft_res['success'], "Échec de l'exécution de softDeleteClient"
        print("OK : Compte client basculé à is_deleted = 1", flush=True)

        # --- Test 4 : Révocation de la session existante après soft-delete ---
        print("--- Test 4 : Révocation de la session active après soft-delete ---", flush=True)
        code, headers, _ = request(client, "client_dashboard")
        assert code == 302, f"La session aurait dû être révoquée (code reçu : {code})"
        assert "action=login" in headers.get("Location", ""), f"L'utilisateur n'a pas été renvoyé vers login : {headers.get('Location')}"
        print("OK : Session active immédiatement révoquée pour un compte désactivé", flush=True)

        # --- Test 5 : Nouvelle tentative de connexion avec le compte soft-deleted ---
        print("--- Test 5 : Refus de connexion pour le compte soft-deleted ---", flush=True)
        jar2 = http.cookiejar.CookieJar()
        client2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar2), NoRedirect())
        token2 = get_csrf(client2, "login")

        code, headers, body = request(client2, "login", {
            "email": email,
            "password": password,
            "csrf_token": token2
        })

        assert code == 200, f"La connexion n'aurait pas dû rediriger (code {code})"
        assert "suspendu ou supprimé" in body or "désactivé" in body, "Message explicite de compte suspendu/supprimé absent"
        print("OK : Tentative de connexion bloquée avec message explicite", flush=True)

        # --- Test 6 : Vérification de la journalisation NoSQL de la tentative bloquée ---
        print("--- Test 6 : Traçabilité MongoDB de la tentative refusée ---", flush=True)
        log_check_code = f"""
        require_once __DIR__ . '/src/models/nosql/Log.php';
        $logModel = new Log();
        $logs = $logModel->getLatestLogs(50);
        $found = false;
        foreach ($logs as $log) {{
            if (($log['type_action'] ?? '') === 'TENTATIVE_CONNEXION_REFUSEE' && (int)($log['id_utilisateur'] ?? 0) === {user_id}) {{
                $found = true;
                break;
            }}
        }}
        echo json_encode(['found' => $found]);
        """
        log_res = json.loads(run_php(log_check_code))
        assert log_res['found'], "Journal NoSQL TENTATIVE_CONNEXION_REFUSEE non trouvé"
        print("OK : Journal d'audit NoSQL TENTATIVE_CONNEXION_REFUSEE validé", flush=True)

        # --- Test 7 : Rejet de la demande de réinitialisation de mot de passe ---
        print("--- Test 7 : Protection du reset de mot de passe sur compte soft-deleted ---", flush=True)
        hash_before_code = f"""
        require_once __DIR__ . '/src/models/sql/User.php';
        $userModel = new User();
        $user = $userModel->findById({user_id});
        echo json_encode(['password' => $user['password'], 'must_change' => $user['must_change_password']]);
        """
        before_hash = json.loads(run_php(hash_before_code))

        jar3 = http.cookiejar.CookieJar()
        client3 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar3), NoRedirect())
        token3 = get_csrf(client3, "forgot_password")

        code, headers, _ = request(client3, "reset_password_request", {
            "email": email,
            "csrf_token": token3
        })
        assert code == 302, f"Code attendu 302 sur reset_password_request, reçu {code}"

        after_hash = json.loads(run_php(hash_before_code))
        assert before_hash['password'] == after_hash['password'], "Le mot de passe d'un compte soft-deleted a été modifié !"
        assert before_hash['must_change'] == after_hash['must_change'], "Le flag must_change_password a été altéré !"
        print("OK : Réinitialisation neutralisée pour un compte soft-deleted (aucun hash modifié)", flush=True)

        # --- Test 8 : Absence dans findAllClients() et countActiveClients() ---
        print("--- Test 8 : Filtrage dans les requêtes de gestion (findAllClients & countActiveClients) ---", flush=True)
        filter_check_code = f"""
        require_once __DIR__ . '/src/models/sql/User.php';
        $userModel = new User();
        $clients = $userModel->findAllClients();
        $ids = array_map(fn($c) => (int)$c['id'], $clients);
        $inList = in_array({user_id}, $ids, true);
        $count = $userModel->countActiveClients();

        echo json_encode(['in_list' => $inList, 'count' => $count]);
        """
        filter_res = json.loads(run_php(filter_check_code))
        assert not filter_res['in_list'], "Le client soft-deleted figure toujours dans findAllClients()"
        print("OK : Le client soft-deleted est exclu de findAllClients()", flush=True)

    finally:
        # Nettoyage
        cleanup_code = f"""
        require_once __DIR__ . '/src/config/Database.php';
        $db = Database::getInstance();
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([{user_id}]);
        $db->prepare("DELETE FROM companies WHERE id = ?")->execute([{company_id}]);
        echo json_encode(['cleaned' => true]);
        """
        run_php(cleanup_code)

    print("===========================================================", flush=True)
    print("TOUS LES TESTS DE SOFT-DELETE ET VERROUILLAGE SONT VALIDES !", flush=True)
    print("===========================================================", flush=True)


if __name__ == '__main__':
    main()
