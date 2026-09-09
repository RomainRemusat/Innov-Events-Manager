"""Test automatisé de validation CSRF et méthodes HTTP (B06 - ECF).

Ce script vérifie :
1. Le rejet de toute mutation sans jeton CSRF ou avec jeton invalide.
2. Le blocage des requêtes GET sur les routes de mutation (ex: send_quote_to_client, delete_account, etc.).
3. La bonne exécution des mutations légitimes avec un jeton CSRF valide en méthode POST.

Couvre les périmètres de B06 :
- Suppression de compte client (RGPD) et suppression client admin
- Changement de statut prospect et événement
- Ajout de note collaborative
- Inscription client (register)
- Demande de réinitialisation de mot de passe (forgot password)
- Changement forcé de mot de passe
- Envoi de devis au client (send_quote_to_client)
- Arbitrage devis client (respond_to_quote)
- Gestion des prestations (add_prestation / delete_prestation)

Aucune donnée de démonstration existante n'est altérée (utilisation de données synthétiques nettoyées en fin de test).
"""

import http.cookiejar
import json
import pathlib
import re
import secrets
import subprocess
import traceback
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[1]
URL = "http://localhost:8081/index.php?action="
ACCOUNTS = [
    (1, "chloe@innovevents.fr", "dashboard"),
    (2, "jose@innovevents.fr", "dashboard"),
    (3, "client@luxe.com", "client_dashboard"),
    (4, "a.legrand@nextgen.io", "client_dashboard"),
]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def php(code):
    result = subprocess.run(
        ["docker", "compose", "exec", "-T", "app", "php"],
        input=("<?php\n" + code).encode("utf-8"),
        capture_output=True,
        cwd=ROOT,
    )
    if result.returncode:
        raise RuntimeError(result.stderr.decode("utf-8", errors="replace"))
    out = result.stdout.decode("utf-8", errors="replace").strip()
    if not out:
        return None
    return json.loads(out)


def make_client():
    cookies = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(cookies), NoRedirect()
    )
    return opener, cookies


def request(client, action, data=None, method=None):
    if data is not None and isinstance(data, dict):
        payload = urllib.parse.urlencode(data).encode("utf-8")
    elif data is not None and isinstance(data, bytes):
        payload = data
    else:
        payload = None

    safe_action = urllib.parse.quote(action, safe="&=?/")
    req = urllib.request.Request(URL + safe_action, data=payload)
    if method:
        req.get_method = lambda: method

    try:
        response = client.open(req, timeout=45)
    except urllib.error.HTTPError as error:
        response = error

    with response:
        body = response.read().decode("utf-8", errors="replace")
        assert not any(
            marker in body
            for marker in ("Fatal error", "TypeError", "Parse error")
        ), f"Erreur PHP fatale rencontrée sur {action} : {body[:300]}"
        return response.code, response.headers, body


def login_user(client, email, password="Password123!"):
    code, _, body = request(client, "login")
    token_match = re.search(r'name="csrf_token"\s+value="([^"]+)"', body)
    token = token_match.group(1) if token_match else ""
    code, headers, body = request(
        client,
        "login",
        {"email": email, "password": password, "csrf_token": token},
    )
    return code == 302, token


def main():
    marker = "CSRF_" + secrets.token_hex(8)
    setup = "require 'src/config/Database.php'; $db = Database::getInstance();\n"
    clients_to_logout = []

    print("=== DÉBUT DES TESTS CSRF & MÉTHODES HTTP (B06) ===", flush=True)

    try:
        # Création des données synthétiques de test
        ids = php(
            setup
            + """
            $db->beginTransaction();
            $db->exec("INSERT INTO companies (name) VALUES ('MARKER Corp')");
            $company = (int)$db->lastInsertId();

            $hash = password_hash('Password123!', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (company_id, email, password, firstname, lastname, role, must_change_password)
                VALUES (?, 'MARKER_client@example.test', ?, 'Test', 'ClientCSRF', 'CLIENT', 0)");
            $stmt->execute([$company, $hash]);
            $client_user = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO users (company_id, email, password, firstname, lastname, role, must_change_password)
                VALUES (?, 'MARKER_reset@example.test', ?, 'Test', 'ResetCSRF', 'CLIENT', 0)");
            $stmt->execute([$company, $hash]);
            $reset_user = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO users (company_id, email, password, firstname, lastname, role, must_change_password)
                VALUES (?, 'MARKER_forced@example.test', ?, 'Test', 'ForcedCSRF', 'CLIENT', 1)");
            $stmt->execute([$company, $hash]);
            $forced_user = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO prospects (user_id, company_id, company_name, contact_name, email, phone, event_type, status)
                VALUES (?, ?, 'MARKER Corp', 'Contact CSRF', 'MARKER_prospect@example.test', '0102030405', 'Soirée', 'à contacter')");
            $stmt->execute([$client_user, $company]);
            $prospect = (int)$db->lastInsertId();

            $db->exec("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva, status)
                VALUES ($prospect, 'MARKER_dev.pdf', 100, 20, 'brouillon')");
            $quote = (int)$db->lastInsertId();

            $db->exec("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES ($quote, 'Prestation Test', 100)");
            $prestation = (int)$db->lastInsertId();

            $db->exec("INSERT INTO events (client_id, company_id, title, start_date, location, status)\n                VALUES ($client_user, $company, 'MARKER Event', '2026-12-01 10:00:00', 'Paris', 'planifié')");
            $event = (int)$db->lastInsertId();

            $db->commit();
            echo json_encode(compact('company', 'client_user', 'reset_user', 'forced_user', 'prospect', 'quote', 'prestation', 'event'));
        """.replace("MARKER", marker)
        )

        # ---------------------------------------------------------------------
        # TEST 1 : Inscription client (register)
        # ---------------------------------------------------------------------
        print("\n--- Test 1 : Inscription client (register) ---", flush=True)
        c_anon, _ = make_client()
        code, _, body = request(c_anon, "show_register")
        valid_token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)

        # 1.a Sans token CSRF
        code, _, body = request(
            c_anon,
            "register",
            {
                "firstname": "A",
                "lastname": "B",
                "username": "ab",
                "email": f"{marker}_reg1@test.com",
                "password": "Password123!",
            },
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body, "Rejet attendu sans token CSRF"

        # 1.b Avec token CSRF invalide
        code, _, body = request(
            c_anon,
            "register",
            {
                "csrf_token": "FAKETOKEN12345",
                "firstname": "A",
                "lastname": "B",
                "username": "ab",
                "email": f"{marker}_reg2@test.com",
                "password": "Password123!",
            },
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body, "Rejet attendu avec faux token CSRF"

        # 1.c Avec token CSRF valide
        code, headers, body = request(
            c_anon,
            "register",
            {
                "csrf_token": valid_token,
                "firstname": "Jean",
                "lastname": "Valide",
                "username": "jeanvalide",
                "email": f"{marker}_reg_ok@test.com",
                "password": "Password123!",
            },
        )
        assert code == 302 and "login" in headers.get("Location", ""), "Inscription réussie attendue avec token valide"
        print("OK : register protégé par CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 2 : Demande de réinitialisation de mot de passe (reset_password_request)
        # ---------------------------------------------------------------------
        print("\n--- Test 2 : Reset mot de passe (reset_password_request) ---", flush=True)
        c_reset, _ = make_client()
        code, _, body = request(c_reset, "forgot_password")
        token_reset = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)

        # 2.a Sans token CSRF
        code, _, body = request(
            c_reset,
            "reset_password_request",
            {"email": f"{marker}_reset@example.test"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 2.b Avec token CSRF invalide
        code, _, body = request(
            c_reset,
            "reset_password_request",
            {"email": f"{marker}_reset@example.test", "csrf_token": "INVALID"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 2.c Avec token valide
        code, headers, _ = request(
            c_reset,
            "reset_password_request",
            {"email": f"{marker}_reset@example.test", "csrf_token": token_reset},
        )
        assert code == 302 and "forgot_password" in headers["Location"]
        print("OK : reset_password_request protégé par CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 3 : Changement forcé de mot de passe (update_forced_password)
        # ---------------------------------------------------------------------
        print("\n--- Test 3 : Changement forcé de mot de passe (update_forced_password) ---", flush=True)
        c_forced, _ = make_client()
        code, _, body = request(c_forced, "login")
        token_login = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)

        # Login avec le compte en mot de passe temporaire -> redirection vers force_password_change
        code, headers, _ = request(
            c_forced,
            "login",
            {
                "email": f"{marker}_forced@example.test",
                "password": "Password123!",
                "csrf_token": token_login,
            },
        )
        assert code == 302 and "force_password_change" in headers["Location"]

        code, _, body = request(c_forced, "force_password_change")
        token_forced = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)

        # 3.a Sans token CSRF
        code, _, body = request(
            c_forced,
            "update_forced_password",
            {"new_password": "NewPassword123!", "confirm_password": "NewPassword123!"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 3.b Avec token invalide
        code, _, body = request(
            c_forced,
            "update_forced_password",
            {
                "new_password": "NewPassword123!",
                "confirm_password": "NewPassword123!",
                "csrf_token": "BADTOKEN",
            },
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 3.c Avec token valide
        code, headers, _ = request(
            c_forced,
            "update_forced_password",
            {
                "new_password": "NewPassword123!",
                "confirm_password": "NewPassword123!",
                "csrf_token": token_forced,
            },
        )
        assert code == 302 and "login" in headers["Location"]
        print("OK : update_forced_password protégé par CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 4 : Session ADMIN - Envoi devis (send_quote_to_client) en GET et CSRF
        # ---------------------------------------------------------------------
        print("\n--- Test 4 : Envoi devis (send_quote_to_client) ---", flush=True)
        c_admin, _ = make_client()
        clients_to_logout.append(c_admin)
        ok, admin_token = login_user(c_admin, ACCOUNTS[0][1])
        assert ok

        code, _, body = request(c_admin, f"edit_devis&id={ids['quote']}")
        admin_csrf = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)

        # 4.a Tentative en GET -> Doit être refusée (aucun changement de statut en base)
        quote_status_before = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM devis WHERE id_devis = {ids['quote']}')->fetchColumn());"
        )
        assert quote_status_before == "brouillon"

        code, headers, _ = request(c_admin, f"send_quote_to_client&id={ids['quote']}")
        # Doit rediriger sans changer le statut
        quote_status_after_get = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM devis WHERE id_devis = {ids['quote']}')->fetchColumn());"
        )
        assert quote_status_after_get == "brouillon", "L'appel en GET ne doit pas modifier le statut du devis !"

        # 4.b Tentative en POST sans CSRF ou avec faux CSRF
        code, _, body = request(
            c_admin,
            "send_quote_to_client",
            {"id": ids["quote"], "csrf_token": "BAD_CSRF"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body
        quote_status_bad = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM devis WHERE id_devis = {ids['quote']}')->fetchColumn());"
        )
        assert quote_status_bad == "brouillon"

        # 4.c Envoi valide en POST avec CSRF
        code, headers, _ = request(
            c_admin,
            "send_quote_to_client",
            {"id": ids["quote"], "csrf_token": admin_csrf},
        )
        assert code == 302 and f"edit_devis&id={ids['quote']}" in headers["Location"]
        quote_status_sent = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM devis WHERE id_devis = {ids['quote']}')->fetchColumn());"
        )
        assert quote_status_sent == "étude côté client", f"Statut attendu 'étude côté client', obtenu : {quote_status_sent}"
        print("OK : send_quote_to_client sécurisé contre GET et CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 5 : Session CLIENT - Réponse au devis (respond_to_quote)
        # ---------------------------------------------------------------------
        print("\n--- Test 5 : Réponse devis client (respond_to_quote) ---", flush=True)
        c_client, _ = make_client()
        clients_to_logout.append(c_client)
        ok, client_token = login_user(c_client, f"{marker}_client@example.test")
        assert ok, "Échec de connexion du client de test"

        code, _, body = request(c_client, "client_dashboard")
        match_client_csrf = re.search(r'name="csrf_token"\s+value="([^"]+)"', body)
        assert match_client_csrf, f"Formulaire de réponse au devis non trouvé sur client_dashboard : {body[:400]}"
        client_csrf = match_client_csrf.group(1)

        # 5.a Tentative en GET
        code, headers, _ = request(
            c_client, f"respond_to_quote&devis_id={ids['quote']}&quote_action=accept"
        )
        assert code == 302 and "client_dashboard" in headers["Location"]
        quote_status = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM devis WHERE id_devis = {ids['quote']}')->fetchColumn());"
        )
        assert quote_status == "étude côté client", "GET ne doit pas modifier le statut"

        # 5.b Tentative POST sans CSRF / Faux CSRF
        code, _, body = request(
            c_client,
            "respond_to_quote",
            {"devis_id": ids["quote"], "quote_action": "accept", "csrf_token": "WRONG"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 5.c Validation légitime en POST avec CSRF
        code, headers, _ = request(
            c_client,
            "respond_to_quote",
            {"devis_id": ids["quote"], "quote_action": "accept", "csrf_token": client_csrf},
        )
        assert code == 302 and "client_dashboard" in headers["Location"]
        quote_status_accepted = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM devis WHERE id_devis = {ids['quote']}')->fetchColumn());"
        )
        assert quote_status_accepted == "accepté", f"Statut attendu 'accepté', obtenu : {quote_status_accepted}"
        print("OK : respond_to_quote protégé contre GET et CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 6 : Changement de statut prospect (update_prospect_status)
        # ---------------------------------------------------------------------
        print("\n--- Test 6 : Statut prospect (update_prospect_status) ---", flush=True)
        # 6.a En GET
        code, headers, _ = request(
            c_admin, f"update_prospect_status&id={ids['prospect']}&status=en attente"
        )
        prospect_st = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM prospects WHERE id = {ids['prospect']}')->fetchColumn());"
        )
        assert prospect_st == "à contacter", "GET ne doit pas modifier le prospect"

        # 6.b En POST sans CSRF
        code, _, body = request(
            c_admin,
            "update_prospect_status",
            {"id": ids["prospect"], "status": "en attente", "csrf_token": "FAKE"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 6.c En POST avec CSRF valide
        code, headers, _ = request(
            c_admin,
            "update_prospect_status",
            {"id": ids["prospect"], "status": "en attente", "csrf_token": admin_csrf},
        )
        assert code == 302
        prospect_st_ok = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM prospects WHERE id = {ids['prospect']}')->fetchColumn());"
        )
        assert prospect_st_ok == "en attente", "Statut attendu : en attente"
        print("OK : update_prospect_status protégé contre GET et CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 7 : Statut événement (admin_event_update_status)
        # ---------------------------------------------------------------------
        print("\n--- Test 7 : Statut événement (admin_event_update_status) ---", flush=True)
        # 7.a En GET
        code, headers, _ = request(
            c_admin, f"admin_event_update_status&event_id={ids['event']}&status=en cours"
        )
        event_st = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM events WHERE id = {ids['event']}')->fetchColumn());"
        )
        assert event_st == "planifié", "GET ne doit pas modifier le statut événement"

        # 7.b En POST faux CSRF
        code, _, body = request(
            c_admin,
            "admin_event_update_status",
            {"event_id": ids["event"], "status": "en cours", "csrf_token": "FAKE"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 7.c En POST valide
        code, headers, _ = request(
            c_admin,
            "admin_event_update_status",
            {"event_id": ids["event"], "status": "en cours", "csrf_token": admin_csrf},
        )
        assert code == 302
        event_st_ok = php(
            setup
            + f"echo json_encode($db->query('SELECT status FROM events WHERE id = {ids['event']}')->fetchColumn());"
        )
        assert event_st_ok == "en cours", "Statut attendu : en cours"
        print("OK : admin_event_update_status protégé contre GET et CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 8 : Ajout de note projet (admin_add_note)
        # ---------------------------------------------------------------------
        print("\n--- Test 8 : Ajout de note (admin_add_note) ---", flush=True)
        # 8.a En GET
        code, headers, _ = request(
            c_admin, f"admin_add_note&event_id={ids['event']}&content=TestNoteGET"
        )
        note_count = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM notes WHERE event_id = {ids['event']}')->fetchColumn());"
        )
        assert note_count == 0, "GET ne doit pas créer de note"

        # 8.b En POST faux CSRF
        code, _, body = request(
            c_admin,
            "admin_add_note",
            {"event_id": ids["event"], "content": "TestNoteCSRF", "csrf_token": "FAKE"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 8.c En POST valide
        code, headers, _ = request(
            c_admin,
            "admin_add_note",
            {"event_id": ids["event"], "content": "Note Valide", "csrf_token": admin_csrf},
        )
        assert code == 302
        note_count_ok = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM notes WHERE event_id = {ids['event']}')->fetchColumn());"
        )
        assert note_count_ok == 1, "Une note doit être créée avec CSRF valide"
        print("OK : admin_add_note protégé contre GET et CSRF", flush=True)

        # ---------------------------------------------------------------------
        # TEST 9 : Ajout / Suppression de prestation (add_prestation / delete_prestation)
        # ---------------------------------------------------------------------
        print("\n--- Test 9 : Prestations devis (add/delete_prestation) ---", flush=True)
        # 9.a add_prestation en GET
        code, headers, _ = request(
            c_admin, f"add_prestation&devis_id={ids['quote']}&libelle=Hack&montant_ht=50"
        )
        assert code == 302 and "admin_devis" in headers["Location"]

        # 9.b add_prestation POST sans CSRF
        code, _, body = request(
            c_admin,
            "add_prestation",
            {"devis_id": ids["quote"], "libelle": "Hack", "montant_ht": 50, "csrf_token": "BAD"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 9.c add_prestation POST valide
        code, headers, _ = request(
            c_admin,
            "add_prestation",
            {"devis_id": ids["quote"], "libelle": "Prestation 2", "montant_ht": 50, "csrf_token": admin_csrf},
        )
        assert code == 302
        prest_count = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM prestations WHERE devis_id = {ids['quote']}')->fetchColumn());"
        )
        assert prest_count == 2

        # 9.d delete_prestation POST sans CSRF
        code, _, body = request(
            c_admin,
            "delete_prestation",
            {"devis_id": ids["quote"], "prestation_id": ids["prestation"], "csrf_token": "BAD"},
        )
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body

        # 9.e delete_prestation POST valide
        code, headers, _ = request(
            c_admin,
            "delete_prestation",
            {"devis_id": ids["quote"], "prestation_id": ids["prestation"], "csrf_token": admin_csrf},
        )
        assert code == 302
        prest_count_after = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM prestations WHERE devis_id = {ids['quote']}')->fetchColumn());"
        )
        assert prest_count_after == 1
        print("OK : add_prestation et delete_prestation protégés par CSRF et POST", flush=True)

        # ---------------------------------------------------------------------
        # TEST 10 : Suppression de compte client (delete_account - RGPD)
        # ---------------------------------------------------------------------
        print("\n--- Test 10 : Suppression de compte client (delete_account) ---", flush=True)
        # 10.a En GET
        code, headers, _ = request(c_client, "delete_account")
        assert code == 302 and "client_profile" in headers["Location"]
        client_exists = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM users WHERE id = {ids['client_user']}')->fetchColumn());"
        )
        assert client_exists == 1, "Le compte ne doit pas être supprimé en GET !"

        # 10.b En POST faux CSRF
        code, _, body = request(c_client, "delete_account", {"csrf_token": "FAKE"})
        assert "Erreur de sécurité" in body or "Jeton CSRF" in body
        client_exists = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM users WHERE id = {ids['client_user']}')->fetchColumn());"
        )
        assert client_exists == 1

        # 10.c En POST valide avec CSRF
        code, headers, _ = request(c_client, "delete_account", {"csrf_token": client_csrf})
        assert code == 302 and ("index.php" in headers["Location"] or headers["Location"] == "index.php")
        client_exists = php(
            setup
            + f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM users WHERE id = {ids['client_user']}')->fetchColumn());"
        )
        assert client_exists == 0, "Le compte doit être supprimé avec CSRF valide"
        print("OK : delete_account protégé contre GET et CSRF", flush=True)

        print("\n===========================================================", flush=True)
        print("TOUS LES CONTRÔLES CSRF ET MÉTHODES HTTP (B06) SONT CONFORMES ET VALIDÉS !", flush=True)
        print("===========================================================", flush=True)

    except Exception as e:
        print(f"\nERREUR DANS LE TEST : {e}", flush=True)
        traceback.print_exc()
        raise e
    finally:
        # Nettoyage complet des données synthétiques de test
        php(
            setup
            + f"""
            $stmt = $db->prepare('DELETE FROM users WHERE email LIKE ?');
            $stmt->execute(['{marker}%']);
            $stmt = $db->prepare('DELETE FROM prospects WHERE company_name LIKE ?');
            $stmt->execute(['{marker}%']);
            $stmt = $db->prepare('DELETE FROM companies WHERE name LIKE ?');
            $stmt->execute(['{marker}%']);
            $stmt = $db->prepare('DELETE FROM events WHERE title LIKE ?');
            $stmt->execute(['{marker}%']);
            echo json_encode(true);
        """
        )
        for client in clients_to_logout:
            try:
                request(client, "logout")
            except Exception:
                pass


if __name__ == "__main__":
    main()
