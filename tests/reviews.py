"""Avis clients, modération et publication sur une base temporaire."""

import base64
import http.cookiejar
import json
import re
import secrets
import urllib.request

from login_logging import NoRedirect, php, request


def main():
    name = "innovevents_test_reviews_" + secrets.token_hex(6)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);"
    prefix = connect + f"$db->exec('USE `{name}`');" + r"""
        require_once 'src/controllers/ReviewController.php';
        require_once 'src/controllers/ClientController.php';
        require_once 'src/controllers/EventController.php';
        $class=new ReflectionClass(Database::class);
        $instance=$class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance,$db);
        $class->getProperty('instance')->setValue(null,$instance);
    """ + f"$_ENV['MONGO_DATABASE']='{name}'; $_ENV['MONGO_URI']='mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=50';"

    def query(code):
        return php(prefix + code)

    def call(method, data=None, user=3, verb="POST", controller="ReviewController"):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return query(f"""
            session_start(); $_SESSION=['user_id'=>{user},'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='{verb}'; $_GET=[];
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            ob_start(); register_shutdown_function(function() {{
                $body=ob_get_clean(); echo json_encode(['body'=>base64_encode($body),'session'=>$_SESSION]);
            }});
            (new {controller}())->{method};
        """)

    php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
    try:
        query(r"""
            $db->exec(file_get_contents('scripts/schema.sql'));
            $db->exec(file_get_contents('scripts/initialise.sql'));
            $stmt=$db->prepare("INSERT INTO events(client_id,company_id,title,start_date,location,status) VALUES(4,3,?,NOW(),'Paris','terminé')");
            $stmt->execute(['Événement terminé Amandine']); $event5=(int)$db->lastInsertId();
            $stmt->execute(['Autre événement terminé']); $event6=(int)$db->lastInsertId();
            echo json_encode(['event5'=>$event5,'event6'=>$event6]);
        """)

        eligible = query("echo json_encode(array_column((new Review())->findReviewableEvents(3),'event_id'));")
        assert eligible == [1], eligible
        assert query("echo json_encode((new Review())->findReviewableEvents(4));")

        for data, user, verb in [
            ({"event_id": 1, "rating": 5, "comment": "Très belle prestation"}, 3, "GET"),
            ({"event_id": 1, "rating": 5, "comment": "Très belle prestation"}, 4, "POST"),
            ({"event_id": 3, "rating": 5, "comment": "Très belle prestation"}, 3, "POST"),
            ({"event_id": 1, "rating": 0, "comment": "Très belle prestation"}, 3, "POST"),
            ({"event_id": 1, "rating": 5, "comment": "Court"}, 3, "POST"),
        ]:
            result = call("submit()", data, user=user, verb=verb)
            assert "client_success" not in result["session"]
        assert query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM reviews')->fetchColumn());") == 0

        bad_csrf = call("submit()", {"event_id": 1, "rating": 5, "comment": "Très belle prestation", "csrf_token": "bad"})
        assert "Erreur de sécurité" in base64.b64decode(bad_csrf["body"]).decode()

        first_comment = "Une prestation réussie <script>alert(1)</script>"
        result = call("submit()", {"event_id": 1, "rating": 5, "comment": first_comment})
        assert "client_success" in result["session"]
        review = query("echo json_encode($db->query('SELECT * FROM reviews WHERE event_id=1')->fetch());")
        review_id = int(review["id"])
        assert review["status"] == "en attente" and int(review["rating"]) == 5

        duplicate = call("submit()", {"event_id": 1, "rating": 4, "comment": "Tentative de doublon valide"})
        assert "client_error" in duplicate["session"]
        assert query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM reviews WHERE event_id=1')->fetchColumn());") == 1

        denied = call("showStaff()", user=3, verb="GET")
        assert not base64.b64decode(denied["body"])
        short_reason = call("moderate()", {"review_id": review_id, "moderation_action": "reject", "rejection_reason": "non"}, user=2)
        assert "flash_error" in short_reason["session"]
        rejected = call("moderate()", {"review_id": review_id, "moderation_action": "reject", "rejection_reason": "Merci de reformuler ce passage."}, user=2)
        assert "flash_success" in rejected["session"]
        review = query(f"echo json_encode($db->query('SELECT * FROM reviews WHERE id={review_id}')->fetch());")
        assert review["status"] == "refusé" and int(review["moderated_by"]) == 2

        dashboard = base64.b64decode(call("showDashboard()", user=3, verb="GET", controller="ClientController")["body"]).decode()
        assert "Correction demandée" in dashboard and "Merci de reformuler" in dashboard
        assert "&lt;script&gt;alert(1)&lt;/script&gt;" in dashboard and "<script>alert(1)</script>" not in dashboard

        second_comment = "Une équipe attentive & un événement impeccable."
        resubmitted = call("submit()", {"event_id": 1, "rating": 4, "comment": second_comment})
        assert "client_success" in resubmitted["session"]
        review = query(f"echo json_encode($db->query('SELECT * FROM reviews WHERE id={review_id}')->fetch());")
        assert review["status"] == "en attente" and review["rejection_reason"] is None and review["moderated_by"] is None

        approved = call("moderate()", {"review_id": review_id, "moderation_action": "approve"}, user=1)
        assert "flash_success" in approved["session"]
        locked = call("submit()", {"event_id": 1, "rating": 1, "comment": "Modification désormais interdite"})
        assert "client_error" in locked["session"]

        events = query("echo json_encode($db->query(\"SELECT id FROM events WHERE client_id=4 AND status='terminé' ORDER BY id\")->fetchAll(PDO::FETCH_COLUMN));")
        query(f"""
            $stmt=$db->prepare("INSERT INTO reviews(event_id,rating,comment,status,rejection_reason) VALUES(?,?,?, ?, ?)");
            $stmt->execute([{events[0]},2,'AVIS_EN_ATTENTE','en attente',null]);
            $stmt->execute([{events[1]},1,'AVIS_REFUSE','refusé','Motif de refus']);
            echo json_encode(true);
        """)
        public_page = base64.b64decode(call("showPublic()", user=0, verb="GET")["body"]).decode()
        assert "Une équipe attentive &amp; un événement impeccable." in public_page
        assert "AVIS_EN_ATTENTE" not in public_page and "AVIS_REFUSE" not in public_page
        home = base64.b64decode(call("showHome()", user=0, verb="GET", controller="EventController")["body"]).decode()
        assert 'id="reviews"' in home and "Une équipe attentive &amp; un événement impeccable." in home

        staff = base64.b64decode(call("showStaff()", user=2, verb="GET")["body"]).decode()
        assert "Modération des avis" in staff and "AVIS_EN_ATTENTE" in staff and "Valider et publier" in staff
        client = base64.b64decode(call("showDashboard()", user=3, verb="GET", controller="ClientController")["body"]).decode()
        assert "Publié" in client and second_comment.replace("&", "&amp;") in client
        assert 'name="event_id" value="1"' not in client
        print("OK : dépôt, propriété, correction, modération, publication et vues par rôle.", flush=True)
    finally:
        php(connect + f"$db->exec('DROP DATABASE `{name}`');" +
            f"$mongo=new MongoDB\\Driver\\Manager('mongodb://mongodb:27017'); $mongo->executeCommand('{name}',new MongoDB\\Driver\\Command(['dropDatabase'=>1])); echo json_encode(true);")


def check_routes():
    public = urllib.request.build_opener(NoRedirect())
    code, _, page = request(public, "reviews")
    assert code == 200 and "Avis de nos clients" in page

    clients = []
    try:
        for email, destination, expected in [
            ("client@luxe.com", "client_dashboard", "Mes avis"),
            ("jose@innovevents.fr", "staff_reviews", "Modération des avis"),
        ]:
            client = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
            )
            clients.append(client)
            _, _, login = request(client, "login")
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', login).group(1)
            code, _, _ = request(client, "login", {
                "email": email, "password": "Password123!", "csrf_token": token,
            })
            assert code == 302
            code, _, page = request(client, destination)
            assert code == 200 and expected in page
            assert not any(marker in page for marker in ["Fatal error", "Warning:", "Deprecated:"])
        print("OK : routes publiques, client et personnel accessibles sans erreur PHP.", flush=True)
    finally:
        for client in clients:
            try:
                request(client, "logout")
            except Exception:
                pass


if __name__ == "__main__":
    main()
    check_routes()
