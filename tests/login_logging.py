"""Régression connexion + MongoDB sur le Docker local et ses quatre comptes de test.

N'altère pas les données SQL. Crée les sessions et journaux de connexion normaux,
puis déconnecte les sessions ouvertes par le test. Python standard uniquement.
"""

import http.cookiejar
import json
import pathlib
import re
import subprocess
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
        input=("<?php\n" + code).encode("utf-8"), capture_output=True, cwd=ROOT,
    )
    if result.returncode:
        raise RuntimeError(result.stderr.decode("utf-8", errors="replace"))
    return json.loads(result.stdout)


def request(client, action, data=None):
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        response = client.open(URL + action, data=payload, timeout=45)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        body = response.read().decode("utf-8")
        assert not any(marker in body for marker in ("Fatal error", "TypeError", "Warning:", "Notice:")), "Erreur PHP dans la réponse"
        return response.code, response.headers, body


def main():
    since = php("echo json_encode((string)new MongoDB\\BSON\\UTCDateTime());")
    clients = []
    try:
        for user_id, email, destination in ACCOUNTS:
            cookies = http.cookiejar.CookieJar()
            client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies), NoRedirect())
            clients.append(client)
            code, _, body = request(client, "login")
            assert code == 200
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            old_session = next(cookie.value for cookie in cookies if cookie.name == "PHPSESSID")
            code, headers, _ = request(client, "login", {
                "email": email, "password": "Password123!", "csrf_token": token,
            })
            assert code == 302, f"Connexion non redirigée pour {email}"
            assert headers["Location"] == "index.php?action=" + destination
            new_session = next(cookie.value for cookie in cookies if cookie.name == "PHPSESSID")
            assert new_session != old_session, "La session n'a pas été régénérée"
            code, headers, body = request(client, destination)
            if user_id == 2:
                assert code == 302 and headers["Location"] == "index.php?action=admin_events"
                code, _, body = request(client, "admin_events")
            assert code == 200
            if user_id == 1:
                assert email in body, "Le flux du dashboard n'affiche pas le message de connexion"
            print(f"OK : connexion, session régénérée et redirection pour {email}", flush=True)

        logs = php(r'''
            $manager = new MongoDB\Driver\Manager($_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017');
            $database = $_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql';
            $query = new MongoDB\Driver\Query([
                'type_action' => 'CONNEXION_REUSSIE',
                'Horodatage' => ['$gte' => new MongoDB\BSON\UTCDateTime(SINCE)],
            ]);
            $logs = [];
            foreach ($manager->executeQuery($database . '.logs', $query) as $doc) {
                $logs[] = [
                    'user_id' => $doc->id_utilisateur,
                    'date_bson' => $doc->Horodatage instanceof MongoDB\BSON\UTCDateTime,
                    'message' => $doc->details->message ?? null,
                    'ip' => $doc->details->ip_address ?? null,
                ];
            }
            echo json_encode($logs);
        '''.replace("SINCE", str(int(since))))
        for user_id, email, _ in ACCOUNTS:
            matching = [log for log in logs if log["user_id"] == user_id and email in (log["message"] or "")]
            assert matching, f"Journal MongoDB absent pour {email}"
            assert all(log["date_bson"] and log["ip"] for log in matching)
        code, _, body = request(clients[0], "mongo_logs")
        assert code == 200 and "CONNEXION_REUSSIE" in body
        assert all(email in body for _, email, _ in ACCOUNTS)
        print("OK : quatre journaux BSON avec acteur, message et IP ; affichage admin", flush=True)
    finally:
        for client in clients:
            request(client, "logout")


if __name__ == "__main__":
    main()
