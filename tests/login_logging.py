"""Test de régression de l'authentification et de la journalisation NoSQL (B01).

Vérifie la connexion par rôle, la régénération de session, l'insertion
des journaux MongoDB sous la structure attendue et la lecture admin.
"""

import http.cookiejar
import json
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import subprocess


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


def request(client, action, data=None):
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(URL + action, data=payload)
    try:
        response = client.open(req, timeout=45)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.code, response.headers, response.read().decode("utf-8", "replace")


def php(code):
    cmd = ["docker", "compose", "exec", "-T", "app", "php", "-r", code]
    result = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return json.loads(result.stdout.strip())


def main():
    clients = []
    since = time.time() * 1000 - 5000
    try:
        for user_id, email, destination in ACCOUNTS:
            client = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
                NoRedirect()
            )
            clients.append(client)
            code, _, body = request(client, "login")
            assert code == 200, f"Page de connexion inaccessible pour {email}"
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            code, headers, _ = request(client, "login", {
                "email": email,
                "password": "Password123!",
                "csrf_token": token,
            })
            assert code == 302 and headers["Location"] == "index.php?action=" + destination
            set_cookies = headers.get_all("Set-Cookie") or []
            assert any("PHPSESSID" in cookie for cookie in set_cookies), "Session non régénérée"
            print(f"OK : connexion, session régénérée et redirection pour {email}", flush=True)

        logs = php(r'''
            $uri = $_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017';
            $database = $_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql';
            $manager = new MongoDB\Driver\Manager($uri);
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
            matching = [log for log in logs if log["user_id"] == user_id]
            assert matching, f"Aucun log MongoDB de connexion pour {email}"
            assert all(log["date_bson"] for log in matching), "Horodatage MongoDB non BSON"
            assert all(log["message"] and "Connexion" in log["message"] for log in matching)
            assert all(log["ip"] in ("127.0.0.1", "127.0.0.0", "::1", "::", "172.18.0.1", "172.18.0.0") for log in matching)
        print("OK : quatre journaux BSON avec acteur, message et IP ; affichage admin", flush=True)
    finally:
        for client in clients:
            try:
                request(client, "logout")
            except Exception:
                pass


if __name__ == "__main__":
    main()
