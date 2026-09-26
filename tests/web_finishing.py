"""Recette ciblée : pseudo, retour après connexion, formulaires et accessibilité HTML."""

import http.cookiejar
import json
import pathlib
import re
import secrets
import urllib.parse
import urllib.request
from html.parser import HTMLParser

from login_logging import NoRedirect, php, request


ROOT = pathlib.Path(__file__).resolve().parents[1]


class PageAudit(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = []
        self.images_without_alt = 0
        self.h1 = 0
        self.labels = set()
        self.controls = []
        self.forms = []
        self.current_form = None

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if "id" in values:
            self.ids.append(values["id"])
        if tag == "img" and "alt" not in values:
            self.images_without_alt += 1
        if tag == "h1":
            self.h1 += 1
        if tag == "label" and values.get("for"):
            self.labels.add(values["for"])
        if tag == "form":
            self.current_form = {"method": values.get("method", "get").lower(), "csrf": False}
            self.forms.append(self.current_form)
        if tag == "input" and self.current_form is not None and values.get("name") == "csrf_token":
            self.current_form["csrf"] = True
        if tag in ("input", "select", "textarea") and values.get("type", "") != "hidden" and values.get("id"):
            self.controls.append(values["id"])

    def handle_endtag(self, tag):
        if tag == "form":
            self.current_form = None


def contrast(foreground, background):
    def luminance(value):
        channels = [int(value[i:i + 2], 16) / 255 for i in (1, 3, 5)]
        channels = [channel / 12.92 if channel <= 0.04045 else ((channel + 0.055) / 1.055) ** 2.4 for channel in channels]
        return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
    first, second = sorted((luminance(foreground), luminance(background)), reverse=True)
    return (first + 0.05) / (second + 0.05)


def opener():
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
    )


def main():
    marker = "fin_" + secrets.token_hex(5)
    email = marker + "@example.test"
    sql_prefix = "require 'src/config/Database.php';$db=Database::getInstance();"
    mail_ids = []

    def messages():
        url = "http://localhost:8025/api/v2/search?kind=to&query=" + urllib.parse.quote(email)
        with urllib.request.urlopen(url) as response:
            return json.load(response)["items"]

    try:
        visitor = opener()
        _, _, register = request(visitor, "show_register")
        token = re.search(r'name="csrf_token"\s+value="([^"]+)"', register).group(1)
        data = {
            "csrf_token": token, "firstname": "Test", "lastname": "Finitions",
            "username": marker, "email": email, "password": "Password123!",
        }
        code, headers, _ = request(visitor, "register", data)
        assert code == 302 and headers["Location"].endswith("show_register")
        assert php(sql_prefix + f"echo json_encode((int)$db->query(\"SELECT COUNT(*) FROM users WHERE email='{email}'\")->fetchColumn());") == 0

        data["rgpd_consent"] = "on"
        code, headers, _ = request(visitor, "register", data)
        assert code == 302 and headers["Location"].endswith("login")
        user = php(sql_prefix + f"echo json_encode($db->query(\"SELECT username FROM users WHERE email='{email}'\")->fetch());")
        assert user == {"username": marker}
        mail_ids.extend(message["ID"] for message in messages())
        print("OK : pseudo unique persisté et consentement d’inscription contrôlé côté serveur.", flush=True)

        quote = opener()
        _, _, page = request(quote, "devis")
        quote_token = re.search(r'name="csrf_token"\s+value="([^"]+)"', page).group(1)
        quote_email = marker + "_quote@example.test"
        code, headers, _ = request(quote, "devis", {
            "csrf_token": quote_token, "company_name": "Test", "contact_name": "Test Finitions",
            "email": quote_email, "phone": "0102030405", "event_type": "Autre",
            "event_date": "2099-01-01", "location": "Paris", "estimated_participants": "10",
            "description": "Projet valide sans consentement",
        })
        assert code == 302 and headers["Location"].endswith("devis")
        assert php(sql_prefix + f"echo json_encode((int)$db->query(\"SELECT COUNT(*) FROM prospects WHERE email='{quote_email}'\")->fetchColumn());") == 0
        print("OK : absence de consentement devis rejetée par le serveur.", flush=True)

        admin = opener()
        code, headers, _ = request(admin, "admin_site_settings")
        assert code == 302 and headers["Location"].endswith("login")
        _, _, login = request(admin, "login")
        login_token = re.search(r'name="csrf_token"\s+value="([^"]+)"', login).group(1)
        code, headers, _ = request(admin, "login", {
            "email": "chloe@innovevents.fr", "password": "Password123!", "csrf_token": login_token,
        })
        assert code == 302 and headers["Location"] == "index.php?action=admin_site_settings"
        print("OK : retour à la page initialement demandée après connexion.", flush=True)

        public = opener()
        for action in ["home", "events", "reviews", "contact", "devis", "login", "show_register",
                       "mentions_legales", "cgu", "cgv", "politique_confidentialite"]:
            code, _, body = request(public, action)
            assert code == 200, action
            audit = PageAudit()
            audit.feed(body)
            assert len(audit.ids) == len(set(audit.ids)), f"Identifiants HTML dupliqués : {action}"
            assert audit.images_without_alt == 0, f"Image sans alt : {action}"
            assert audit.h1 == 1, f"Un h1 attendu sur {action}, obtenu {audit.h1}"
            assert not set(audit.controls) - audit.labels, f"Champ sans label sur {action}: {set(audit.controls)-audit.labels}"
            assert all(form["csrf"] for form in audit.forms if form["method"] == "post"), f"POST sans CSRF : {action}"

        css = (ROOT / "public/css/style.css").read_text(encoding="utf-8")
        assert ":focus-visible" in css and ".skip-link:focus" in css and "@media (max-width: 767.98px)" in css
        assert contrast("#2563EB", "#FFFFFF") >= 4.5
        assert contrast("#CBD5E1", "#0F172A") >= 4.5
        print("OK : structure HTML, labels, CSRF, focus visible, contrast et règle responsive.", flush=True)

        sources = "\n".join((ROOT / path).read_text(encoding="utf-8") for path in [
            "src/controllers/QuoteController.php", "src/controllers/PdfController.php",
            "src/services/EventManagementService.php", "src/controllers/AdminAccountController.php",
            "src/controllers/ReviewController.php", "src/controllers/PublicPageController.php",
        ])
        for action in ["CREATION_PRESTATION", "SUPPRESSION_PRESTATION", "TELECHARGEMENT_DEVIS",
                       "CREATION_EVENEMENT", "SUPPRESSION_EVENEMENT", "'CREATION_' . $role",
                       "AVIS_MODERE", "CONTENU_PUBLIC_MODIFIE"]:
            assert action in sources, action
        print("OK : inventaire des mutations sensibles couvert par la journalisation.", flush=True)
    finally:
        for message in messages():
            if message["ID"] not in mail_ids:
                mail_ids.append(message["ID"])
        for message_id in mail_ids:
            urllib.request.urlopen(urllib.request.Request(
                "http://localhost:8025/api/v1/messages/" + message_id, method="DELETE"
            )).close()
        php(sql_prefix + f"$db->exec(\"DELETE FROM users WHERE email='{email}'\");$db->exec(\"DELETE FROM prospects WHERE email LIKE '{marker}%@example.test'\");echo json_encode(true);")


if __name__ == "__main__":
    main()
