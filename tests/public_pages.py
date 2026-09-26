"""Vérifie les pages publiques, le contact et le message de devis administrable."""

import base64
import email as email_module
import http.cookiejar
import json
import re
import secrets
import urllib.request

from login_logging import NoRedirect, php, request


def main():
    name = "innovevents_test_public_pages_" + secrets.token_hex(6)
    marker = "CONTACT_" + secrets.token_hex(8)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);"
    prefix = connect + f"$db->exec('USE `{name}`');" + rf"""
        require_once 'src/controllers/PublicPageController.php';
        $class=new ReflectionClass(Database::class);
        $instance=$class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance,$db);
        $class->getProperty('instance')->setValue(null,$instance);
        $_ENV['MONGO_DATABASE']='{name}';
        $_ENV['MONGO_URI']='mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=50';
    """
    created = False
    message_ids = []

    def query(code):
        return php(prefix + code)

    def messages():
        with urllib.request.urlopen("http://localhost:8025/api/v2/messages") as response:
            return [item for item in json.load(response)["items"] if marker in item["Raw"]["Data"]]

    try:
        php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
        created = True
        query("$db->exec(file_get_contents('scripts/schema.sql'));$db->exec(file_get_contents('scripts/initialise.sql'));echo json_encode(true);")

        assert query("echo json_encode((new SiteSetting())->get(SiteSetting::QUOTE_THANK_YOU));").startswith("Merci")
        custom = "Merci <script>test</script> pour votre demande personnalisée."
        encoded = base64.b64encode(custom.encode()).decode()
        assert query(f"$value=base64_decode('{encoded}');echo json_encode((new SiteSetting())->set(SiteSetting::QUOTE_THANK_YOU,$value,1));") is True
        rendered = query(r"""
            $thankYouMessage=(new SiteSetting())->get(SiteSetting::QUOTE_THANK_YOU);
            $isSuccess=true;$notificationSent=true;ob_start();
            require 'src/views/public/devis_confirmation.php';echo json_encode(ob_get_clean());
        """)
        assert "&lt;script&gt;test&lt;/script&gt;" in rendered and "<script>test</script>" not in rendered

        update = base64.b64encode(json.dumps({
            "csrf_token": "test",
            "quote_thank_you_message": "Votre demande est reçue. Notre équipe vous répondra rapidement.",
        }).encode()).decode()
        result = query(f"""
            session_start();$_SESSION=['user_id'=>1,'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='POST';$_POST=json_decode(base64_decode('{update}'),true);
            register_shutdown_function(function() use ($db){{echo json_encode(['session'=>$_SESSION,'value'=>$db->query("SELECT setting_value FROM site_settings WHERE setting_key='quote_thank_you_message'")->fetchColumn()]);}});
            (new PublicPageController())->updateSettings();
        """)
        assert "mis à jour" in result["session"]["flash_success"] and result["value"].startswith("Votre demande")

        denied = base64.b64encode(json.dumps({
            "csrf_token": "test",
            "quote_thank_you_message": "Un employé ne doit pas pouvoir remplacer ce contenu.",
        }).encode()).decode()
        result = query(f"""
            session_start();$_SESSION=['user_id'=>2,'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='POST';$_POST=json_decode(base64_decode('{denied}'),true);
            register_shutdown_function(function() use ($db){{echo json_encode($db->query("SELECT setting_value FROM site_settings WHERE setting_key='quote_thank_you_message'")->fetchColumn());}});
            (new PublicPageController())->updateSettings();
        """)
        assert result.startswith("Votre demande")

        for page, expected in [
            ("mentions_legales", "Mentions légales"), ("cgu", "Conditions générales d’utilisation"),
            ("cgv", "Conditions générales de vente"), ("politique_confidentialite", "Politique de confidentialité"),
        ]:
            body = query(f"ob_start();(new PublicPageController())->showLegal('{page}');echo json_encode(ob_get_clean());")
            assert expected in body

        invalid = base64.b64encode(json.dumps({"csrf_token": "test", "name": "A", "email": "bad"}).encode()).decode()
        result = php(rf"""
            require_once 'src/controllers/PublicPageController.php';session_start();
            $_SESSION=['csrf_token'=>'test'];$_SERVER['REQUEST_METHOD']='POST';
            $_POST=json_decode(base64_decode('{invalid}'),true);
            register_shutdown_function(function(){{echo json_encode($_SESSION);}});
            (new PublicPageController())->submitContact();
        """)
        assert "contact_error" in result and "contact_old" in result

        valid = base64.b64encode(json.dumps({
            "csrf_token": "test", "name": "Client Test", "email": "contact@example.test",
            "subject": marker, "message": "Bonjour <script>test</script>, ceci est un message valide.", "rgpd_consent": "1",
        }).encode()).decode()
        result = php(rf"""
            require_once 'src/controllers/PublicPageController.php';session_start();
            $_SESSION=['csrf_token'=>'test'];$_SERVER['REQUEST_METHOD']='POST';
            $_ENV['MONGO_URI']='mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=50';
            $_POST=json_decode(base64_decode('{valid}'),true);
            register_shutdown_function(function(){{echo json_encode($_SESSION);}});
            (new PublicPageController())->submitContact();
        """)
        assert "contact_success" in result
        captured = messages()
        message_ids.extend(item["ID"] for item in captured)
        assert len(captured) == 1
        mime = email_module.message_from_bytes(captured[0]["Raw"]["Data"].encode())
        html = next(part.get_payload(decode=True).decode() for part in mime.walk() if part.get_content_type() == "text/html")
        assert "&lt;script&gt;test&lt;/script&gt;" in html and "<script>test</script>" not in html
        print("OK : pages légales, contact SMTP, validation, droits et message de devis administrable.", flush=True)
    finally:
        if created:
            for item in messages():
                if item["ID"] not in message_ids:
                    message_ids.append(item["ID"])
            for message_id in message_ids:
                urllib.request.urlopen(urllib.request.Request("http://localhost:8025/api/v1/messages/" + message_id, method="DELETE")).close()
            php(connect + f"$db->exec('DROP DATABASE `{name}`');echo json_encode(true);")


def check_routes():
    public = urllib.request.build_opener(NoRedirect())
    for action, expected in [
        ("contact", "Contactez-nous"), ("mentions_legales", "Mentions légales"),
        ("cgu", "Conditions générales d’utilisation"), ("cgv", "Conditions générales de vente"),
        ("politique_confidentialite", "Politique de confidentialité"),
    ]:
        code, _, body = request(public, action)
        assert code == 200 and expected in body

    admin = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
    )
    _, _, login = request(admin, "login")
    token = re.search(r'name="csrf_token"\s+value="([^"]+)"', login).group(1)
    code, _, _ = request(admin, "login", {
        "email": "chloe@innovevents.fr", "password": "Password123!", "csrf_token": token,
    })
    assert code == 302
    code, _, body = request(admin, "admin_site_settings")
    assert code == 200 and "Message après une demande de devis" in body
    print("OK : routes publiques et écran administrateur accessibles.", flush=True)


if __name__ == "__main__":
    main()
    check_routes()
