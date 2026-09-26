"""Profil, demandes et prochains événements sur SQL/MongoDB temporaires.
Exécution : python -B tests/client_space.py. Aucun email envoyé.
"""
import base64
import json
import http.cookiejar
import re
import urllib.request
import secrets

from login_logging import NoRedirect, php, request


def main():
    name = 'innovevents_test_client_' + secrets.token_hex(6)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);"
    prefix = connect + f"$db->exec('USE `{name}`');" + r"""
        require 'src/controllers/ClientController.php';
        $class=new ReflectionClass(Database::class);
        $instance=$class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance,$db);
        $class->getProperty('instance')->setValue(null,$instance);
    """ + f"$_ENV['MONGO_DATABASE']='{name}';"

    def query(code):
        return php(prefix + code)

    def call(method, data=None, user=3, verb='POST'):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return query(f"""
            session_start(); $_SESSION=['user_id'=>{user},'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='{verb}';
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            ob_start(); register_shutdown_function(function() use ($db) {{
                $body=ob_get_clean();
                echo json_encode(['body'=>base64_encode($body),'session'=>$_SESSION,
                    'client'=>$db->query('SELECT * FROM users WHERE id=3')->fetch()]);
            }});
            (new ClientController())->{method};
        """)

    php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
    try:
        query("""
            $db->exec(file_get_contents('scripts/schema.sql'));
            $db->exec(file_get_contents('scripts/initialise.sql'));
            $db->exec("UPDATE events SET start_date=DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $insert=$db->prepare("INSERT INTO events(client_id,title,start_date,location,status,is_published) VALUES (?,?,DATE_ADD(NOW(), INTERVAL ? HOUR),'Paris',?,0)");
            foreach ([[3,'Troisième',72,'planifié'],[3,'Premier privé',24,'brouillon'],[3,'Deuxième',48,'accepté'],
                [3,'Quatrième',96,'en cours'],[3,'Annulé',1,'annulé'],[3,'Ancien annulé',2,'annuler'],
                [3,'Terminé',3,'terminé'],[4,'Autre client',1,'planifié']] as $event) $insert->execute($event);
            $insert=$db->prepare("INSERT INTO prospects(id,user_id,company_name,contact_name,email,phone,event_type,status,rejection_reason) VALUES (?,?,?,'Test','client@luxe.com','0102030405','Autre',?,?)");
            foreach ([[101,3,'Demande initiale','à contacter',null],[102,3,'Demande en attente','en attente',null],
                [103,3,'Demande refusée','échoué','Motif <script>test</script>'],
                [104,4,'Demande autre client','à contacter',null],[105,null,'Demande non affectée','à contacter',null]] as $row) $insert->execute($row);
            echo json_encode(true);
        """)
        events = query("echo json_encode(array_column((new Event())->findUpcomingEvents(3,3),'title'));")
        assert events == ['Premier privé', 'Deuxième', 'Troisième'], events
        requests = query("echo json_encode((new Prospect())->findClientRequests(3));")
        assert {int(row['prospect_id']) for row in requests} == {2, 4, 101, 102, 103}
        initial = [row for row in requests if int(row['prospect_id']) == 101][0]
        assert initial['id_devis'] is None and initial['status'] == 'à contacter'
        page = base64.b64decode(call('showDashboard()')['body']).decode()
        for label in ['Demande reçue', 'En cours de qualification', 'Demande non retenue', 'Demande #101', 'Devis #1', 'Premier privé', 'Troisième', 'client_profile', 'Motif &lt;script&gt;test&lt;/script&gt;']:
            assert label in page, label
        for label in ['Autre client', 'Quatrième', 'Demande non affectée', 'Demande autre client', '<script>test</script>', 'name="devis_id" value="101"']:
            assert label not in page, label
        query("$db->exec(\"INSERT INTO devis(id_prospect,reference_pdf,status) VALUES(101,'test.pdf','brouillon')\"); echo json_encode(true);")
        after = query("echo json_encode((new Prospect())->findClientRequests(3));")
        assert len([row for row in after if int(row['prospect_id']) == 101]) == 1
        print('OK : demandes avant/après devis, isolation et trois événements triés, privés compris.', flush=True)

        valid = dict(firstname='Alice', lastname='Vancort', email='client@luxe.com')
        original = query("echo json_encode($db->query('SELECT * FROM users WHERE id=3')->fetch());")
        for change in [dict(firstname=''), dict(lastname='a'*101), dict(firstname=['invalide']), dict(email='invalide'),
                       dict(email='new@example.test'), dict(email='new@example.test', current_password='incorrect'),
                       dict(email='a.legrand@nextgen.io', current_password='Password123!')]:
            result = call('updateProfile($_POST)', valid | change)
            assert result['client'] == original and 'client_error' in result['session'], change
            assert 'current_password' not in result['session'].get('profile_inputs', {})
        for user, data, verb in [(0, valid, 'POST'), (1, valid, 'POST'), (2, valid, 'POST'),
                                 (3, valid | {'csrf_token': 'bad'}, 'POST'), (3, valid, 'GET')]:
            result = call('updateProfile($_POST)', data, user, verb)
            assert result['client'] == original and 'client_success' not in result['session']
        query("$db->exec(\"CREATE TRIGGER fail_profile BEFORE UPDATE ON users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test SQL'\"); echo json_encode(true);")
        result = call('updateProfile($_POST)', valid | {'firstname': 'Autre'})
        assert result['client'] == original and 'client_error' in result['session']
        query("$db->exec('DROP TRIGGER fail_profile'); echo json_encode(true);")
        result = call('updateProfile($_POST)', valid | dict(firstname='Élodie <test>', lastname='Durand', email='new@example.test', current_password='Password123!', user_id=4, role='ADMIN'))
        assert result['client']['email'] == 'new@example.test' and result['client']['role'] == 'CLIENT'
        assert result['session']['user_firstname'] == 'Élodie <test>' and result['session']['user_lastname'] == 'Durand'
        assert result['session']['user_email'] == 'new@example.test' and 'client_success' in result['session']
        assert query("echo json_encode($db->query('SELECT email FROM users WHERE id=4')->fetchColumn());") == 'a.legrand@nextgen.io'
        profile = base64.b64decode(call('showProfile()')['body']).decode()
        assert 'Élodie &lt;test&gt;' in profile and 'new@example.test' in profile and 'action=client_update_profile' in profile
        assert 'value="Password123!"' not in profile
        dashboard = base64.b64decode(call('showDashboard()')['body']).decode()
        assert 'Élodie &lt;test&gt; Durand' in dashboard
        assert query("echo json_encode(count((new Prospect())->findClientRequests(3)));") == len(after)
        assert query("echo json_encode(password_verify('Password123!',(new User())->findByEmail('new@example.test')['password']));")
        print('OK : profil enregistré, validation, mot de passe pour email, CSRF, rôles, erreur SQL et session actualisée.', flush=True)

        empty = call('showDashboard()', user=1)
        assert not base64.b64decode(empty['body']), 'Un administrateur ne doit pas accéder à l’espace client'
        query("$db->exec(\"INSERT INTO users(id,email,password,firstname,lastname,role) VALUES(99,'empty@example.test','hash','Vide','Client','CLIENT')\"); echo json_encode(true);")
        empty = base64.b64decode(call('showDashboard()', user=99)['body']).decode()
        assert 'Aucun événement à venir' in empty and 'pas encore de devis' in empty
    finally:
        php(connect + f"$db->exec('DROP DATABASE `{name}`');" +
            f"$mongo=new MongoDB\\Driver\\Manager('mongodb://mongodb:27017'); $mongo->executeCommand('{name}',new MongoDB\\Driver\\Command(['dropDatabase'=>1])); echo json_encode(true);")


def check_routes():
    """Vérifie le routeur HTTP avec un compte temporaire, supprimé en fin de scénario."""
    marker = 'client_route_' + secrets.token_hex(8)
    prefix = "require 'src/config/Database.php'; $db=Database::getInstance(); "
    user_id = php(prefix + f"""
        $query=$db->prepare('INSERT INTO users(email,password,firstname,lastname,role) VALUES (?,?,?,?,?)');
        $query->execute(['{marker}@example.test',password_hash('Password123!',PASSWORD_BCRYPT),'Profil','Test','CLIENT']);
        echo json_encode((int)$db->lastInsertId());
    """)
    try:
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        _, _, page = request(client, 'login')
        token = re.search(r'name="csrf_token"\s+value="([^"]+)"', page).group(1)
        assert request(client, 'login', dict(email=marker+'@example.test', password='Password123!', csrf_token=token))[0] == 302
        code, _, page = request(client, 'client_profile')
        assert code == 200 and 'name="firstname"' in page and 'name="lastname"' in page
        token = re.search(r'name="csrf_token"\s+value="([^"]+)"', page).group(1)
        code, headers, _ = request(client, 'client_update_profile', dict(firstname='Prénom modifié', lastname='Test', email=marker+'@example.test', csrf_token=token))
        assert code == 302 and headers['Location'].endswith('=client_profile')
        _, _, page = request(client, 'client_profile')
        assert 'Prénom modifié' in page and 'ont été enregistrées' in page
        _, _, page = request(client, 'client_dashboard')
        assert 'Prénom modifié Test' in page and 'Aucun événement à venir' in page
        assert not any(text in page for text in ['Fatal error', 'Warning:', 'Deprecated:'])
        request(client, 'logout')
        print('OK : connexion, routes HTTP du profil, sauvegarde et tableau de bord.', flush=True)
    finally:
        php(prefix + f"""
            $query=$db->prepare('DELETE FROM users WHERE id=? AND email=?');
            $query->execute([{user_id},'{marker}@example.test']);
            $mongo=new MongoDB\\Driver\\Manager('mongodb://mongodb:27017');
            $bulk=new MongoDB\\Driver\\BulkWrite();
            $bulk->delete(['id_utilisateur'=>{user_id}],['limit'=>0]);
            $mongo->executeBulkWrite('innovevents_nosql.logs',$bulk); echo json_encode(true);
        """)


if __name__ == '__main__':
    main()
    check_routes()
