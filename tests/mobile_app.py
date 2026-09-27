"""Parcours fonctionnel de la PWA mobile réservée au personnel."""

import http.cookiejar
import json
import re
import secrets
import urllib.request

from login_logging import NoRedirect, php, request


def client():
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
    )


def login(browser, email):
    code, _, page = request(browser, "login")
    assert code == 200
    token = re.search(r'name="csrf_token"\s+value="([^"]+)"', page).group(1)
    return request(browser, "login", {
        "email": email, "password": "Password123!", "csrf_token": token,
    })


def main():
    visitor = client()
    code, headers, _ = request(visitor, "mobile_dashboard")
    assert code == 302 and headers["Location"].endswith("login")

    customer = client()
    login(customer, "client@luxe.com")
    code, headers, _ = request(customer, "mobile_dashboard")
    assert code == 302 and headers["Location"].endswith("client_dashboard")
    print("OK : application mobile réservée au personnel authentifié.", flush=True)

    staff = client()
    code, headers, _ = login(staff, "jose@innovevents.fr")
    assert code == 302 and headers["Location"].endswith("dashboard")
    code, _, dashboard = request(staff, "mobile_dashboard")
    assert code == 200
    assert "Événements à venir" in dashboard and "mobile-manifest.webmanifest" in dashboard
    event_id = int(re.search(r'action=mobile_event&amp;id=(\d+)', dashboard).group(1))

    code, _, detail = request(staff, f"mobile_event&id={event_id}")
    assert code == 200
    for expected in ["Ajouter une note rapide", "mailto:", "tel:", "google.com/maps/search"]:
        assert expected in detail, expected
    token = re.search(r'name="csrf_token"\s+value="([^"]+)"', detail).group(1)

    marker = "Note mobile " + secrets.token_hex(5)
    try:
        code, headers, _ = request(staff, "mobile_add_note", {
            "csrf_token": token, "event_id": str(event_id), "content": marker,
        })
        assert code == 302 and headers["Location"].endswith(f"mobile_event&id={event_id}")
        sql = "require 'src/config/Database.php';$db=Database::getInstance();"
        count = php(sql + "$s=$db->prepare('SELECT COUNT(*) FROM notes WHERE content=?');"
                    f"$s->execute(['{marker}']);echo json_encode((int)$s->fetchColumn());")
        assert count == 1
        print("OK : liste, fiche contact, liens natifs et ajout rapide de note.", flush=True)
    finally:
        php("require 'src/config/Database.php';$db=Database::getInstance();"
            "$s=$db->prepare('DELETE FROM notes WHERE content=?');"
            f"$s->execute(['{marker}']);echo json_encode(true);")

    with urllib.request.urlopen("http://localhost:8081/mobile-manifest.webmanifest") as response:
        manifest = json.load(response)
    assert manifest["display"] == "standalone" and len(manifest["icons"]) == 2
    for asset in ["mobile/app.css", "mobile/app.js", "service-worker.js", "mobile/icon-192.png"]:
        with urllib.request.urlopen("http://localhost:8081/" + asset) as response:
            assert response.status == 200 and response.read()
    print("OK : manifeste, service worker, styles, script et icône PWA accessibles.", flush=True)

    # Une consultation ponctuelle du tableau de bord complet ne doit pas faire
    # perdre le contexte PWA lors du changement de compte du personnel.
    code, _, full_dashboard = request(staff, "dashboard")
    assert code == 200 and "Mon espace employé" in full_dashboard
    code, headers, _ = request(staff, "logout")
    assert code == 302 and headers["Location"].endswith("login")
    # Même une route protégée du site complet ne doit pas écraser l'accueil PWA.
    code, headers, _ = request(staff, "admin_accounts")
    assert code == 302 and headers["Location"].endswith("login")
    code, headers, _ = login(staff, "chloe@innovevents.fr")
    assert code == 302 and headers["Location"].endswith("mobile_dashboard")
    print("OK : préférence PWA conservée après espace complet, déconnexion et changement de compte.", flush=True)


if __name__ == "__main__":
    main()
