"""Accès HTTP aux trois routes prospects (Docker local, comptes de démonstration).

Crée deux prospects synthétiques puis les supprime. Les journaux normaux du test
sont conservés. Aucun prospect existant n'est modifié et aucun mail n'est envoyé.
"""

import http.cookiejar
import re
import secrets
import urllib.request

from login_logging import ACCOUNTS, NoRedirect, php, request


def main():
    marker = "B03_" + secrets.token_hex(12)
    clients = []
    setup = "require 'src/config/Database.php'; $db = Database::getInstance();\n"
    ids = []
    try:
        ids = php(setup + """
            $ids = [];
            $stmt = $db->prepare("INSERT INTO prospects
                (user_id, company_name, contact_name, email, phone, event_type, status)
                VALUES (?, ?, 'Test accès', 'access@example.test', '0102030405', 'Séminaire', 'à contacter')");
            foreach ([3, 4] as $owner) {
                $stmt->execute([$owner, 'MARKER']);
                $ids[] = (int)$db->lastInsertId();
            }
            echo json_encode($ids);
        """.replace("MARKER", marker))

        def statuses():
            return php(setup + "echo json_encode($db->query(\"SELECT status FROM prospects WHERE company_name = '"
                       + marker + "' ORDER BY id\")->fetchAll(PDO::FETCH_COLUMN));")

        # Visiteur, deux clientes, employé : refus avant toute lecture ou écriture métier.
        for account in [None, ACCOUNTS[2], ACCOUNTS[3], ACCOUNTS[1], ACCOUNTS[0]]:
            client = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
            )
            clients.append(client)
            code, _, body = request(client, "login")
            assert code == 200
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            if account:
                code, headers, _ = request(client, "login", {
                    "email": account[1], "password": "Password123!", "csrf_token": token,
                })
                assert code == 302 and headers["Location"] == "index.php?action=" + account[2]

            admin = account == ACCOUNTS[0]
            destination = "index.php?action=" + ("client_dashboard" if account else "login")
            for route in ["prospects"] + [f"view_prospect&id={id_}" for id_ in ids]:
                code, headers, body = request(client, route)
                if admin:
                    assert code == 200 and marker in body, "L'administrateur doit lire les prospects"
                else:
                    assert code == 302 and headers["Location"] == destination, f"Accès non bloqué : {route}"
                    assert marker not in body, "Données de prospect exposées"

            for id_ in ids:
                code, headers, _ = request(client, "update_prospect_status", {
                    "id": id_, "status": "en attente", "csrf_token": token,
                })
                expected = f"index.php?action=view_prospect&id={id_}" if admin else destination
                assert code == 302 and headers["Location"] == expected, "POST non protégé ou admin bloqué"
            assert statuses() == (["en attente"] * 2 if admin else ["à contacter"] * 2), "Statuts SQL inattendus"
            label = account[1] if account else "visiteur"
            print(f"OK : {label}, lectures et POST {'autorisés' if admin else 'refusés'}, statuts SQL vérifiés", flush=True)
    finally:
        # Supprime exclusivement les lignes identifiées par le marqueur aléatoire de ce test.
        php(setup + "$stmt = $db->prepare('DELETE FROM prospects WHERE company_name = ?');"
            + "$stmt->execute(['" + marker + "']); echo json_encode($stmt->rowCount());")
        for client in clients:
            request(client, "logout")


if __name__ == "__main__":
    main()
