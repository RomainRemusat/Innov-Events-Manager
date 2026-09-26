"""Qualification réelle du contrôleur sur bases SQL/MongoDB temporaires.

Les seuls emails émis visent une adresse aléatoire example.test et sont capturés
par MailHog local. Aucune migration ni modification de la base de travail.
Exécution : python -B tests/prospect_qualification.py
"""
import base64
import json
import secrets
import urllib.request

from login_logging import php


def main():
    name = 'innovevents_test_qualification_' + secrets.token_hex(6)
    email = name + '@example.test'
    connection = "$db = new PDO('mysql:host=db;charset=utf8mb4', 'root', 'root_password', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); "
    prefix = connection + f"$db->exec('USE `{name}`'); " + rf"""
        require 'src/controllers/DashboardController.php';
        $class = new ReflectionClass(Database::class);
        $instance = $class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance, $db);
        $class->getProperty('instance')->setValue(null, $instance);
        $_ENV['MONGO_DATABASE'] = '{name}';
    """
    created = False
    message_ids = []

    def messages():
        with urllib.request.urlopen('http://localhost:8025/api/v2/search?kind=to&query=' + email) as response:
            return json.load(response)['items']

    def qualify(status, reason='', role_id=1, token='test'):
        payload = base64.b64encode(json.dumps(dict(id=99, status=status, rejection_reason=reason, csrf_token=token)).encode()).decode()
        return php(prefix + f"""
            session_start();
            $_SESSION = ['user_id'=>{role_id}, 'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = json_decode(base64_decode('{payload}'), true);
            ob_start();
            register_shutdown_function(function () use ($db) {{
                echo json_encode(['body'=>ob_get_clean(), 'session'=>$_SESSION,
                    'prospect'=>$db->query('SELECT * FROM prospects WHERE id=99')->fetch()]);
            }});
            (new DashboardController())->updateProspectStatus();
        """)

    try:
        php(connection + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
        created = True
        php(prefix + f"""
            $db->exec(file_get_contents('scripts/schema.sql'));
            $db->exec(file_get_contents('scripts/initialise.sql'));
            $db->exec("INSERT INTO prospects (id, company_name, contact_name, email, phone, event_type)
                VALUES (99, 'Test', '<b>Contact</b>', '{email}', '0000000000', 'Autre')");
            echo json_encode(true);
        """)
        reason = 'Dates indisponibles <script>test</script>\nBudget insuffisant.'
        for status, reason_value, role, token in [
            ('échoué', '', 1, 'test'), ('inconnu', reason, 1, 'test'),
            ('converti', reason, 1, 'test'), ('échoué', reason, 2, 'test'),
            ('échoué', reason, 3, 'test'), ('échoué', reason, 1, 'invalid'),
        ]:
            result = qualify(status, reason_value, role, token)
            assert result['prospect']['status'] == 'à contacter'
            assert result['prospect']['rejection_reason'] is None
        assert messages() == []
        result = qualify('échoué', reason)
        assert result['prospect']['rejection_reason'] == reason and result['prospect']['status'] == 'échoué'
        assert 'serveur de messagerie' in result['session']['flash_success']
        captured = messages()
        message_ids.extend(m['ID'] for m in captured)
        assert len(captured) == 1
        # Le transport peut encoder le HTML en quoted-printable : vérifier le MIME décodé.
        import email as email_module
        raw = captured[0]['Raw']['Data']
        message = email_module.message_from_bytes(raw.encode('utf-8'))
        html = next(part.get_payload(decode=True).decode('utf-8') for part in message.walk() if part.get_content_type() == 'text/html')
        assert '&lt;script&gt;test&lt;/script&gt;' in html and '&lt;b&gt;Contact&lt;/b&gt;' in html
        assert '<script>test</script>' not in html

        # Échec d'envoi sans perturber le serveur SMTP partagé : destinataire invalide.
        php(prefix + "$db->exec(\"UPDATE prospects SET email='invalid' WHERE id=99\"); echo json_encode(true);")
        result = qualify('échoué', 'Nouveau motif conservé')
        assert 'pas pu être envoyé' in result['session']['flash_error']
        assert result['prospect']['rejection_reason'] == 'Nouveau motif conservé'
        php(prefix + f"$db->exec(\"UPDATE prospects SET email='{email}' WHERE id=99\"); echo json_encode(true);")
        assert 'flash_success' in qualify('échoué', 'Nouveau motif conservé')['session']
        assert php(prefix + rf"""
            $mongo = new MongoDB\Driver\Manager('mongodb://mongodb:27017');
            $logs = $mongo->executeQuery('{name}.logs', new MongoDB\Driver\Query([
                'type_action'=>'QUALIFICATION_PROSPECT', 'details.prospect_id'=>99
            ], ['sort'=>['Horodatage'=>1]]))->toArray();
            echo json_encode(count($logs) === 3 && $logs[0]->details->email_sent === true
                && $logs[1]->details->email_sent === false && $logs[2]->details->email_sent === true
                && $logs[2]->details->motif_refus === 'Nouveau motif conservé');
        """)
        assert qualify('en attente')['prospect']['rejection_reason'] == 'Nouveau motif conservé'
        php(prefix + "$db->exec(\"UPDATE prospects SET status='converti' WHERE id=99\"); echo json_encode(true);")
        assert qualify('à contacter')['prospect']['status'] == 'converti'
        php(prefix + "$db->exec(\"UPDATE prospects SET status='en attente' WHERE id=99\"); $db->exec(\"INSERT INTO devis (id_prospect,reference_pdf) VALUES (99,'test.pdf')\"); echo json_encode(true);")
        assert qualify('échoué', reason)['prospect']['status'] == 'en attente'
        print('OK : droits, CSRF, transitions, motif SQL, email échappé, échec et nouvelle tentative', flush=True)
    finally:
        if created:
            for message in messages():
                if message['ID'] not in message_ids:
                    message_ids.append(message['ID'])
            for message_id in message_ids:
                urllib.request.urlopen(urllib.request.Request('http://localhost:8025/api/v1/messages/' + message_id, method='DELETE')).close()
            php(connection + rf"""
                $db->exec('DROP DATABASE `{name}`');
                $mongo = new MongoDB\Driver\Manager('mongodb://mongodb:27017');
                $mongo->executeCommand('{name}', new MongoDB\Driver\Command(['dropDatabase'=>1]));
                echo json_encode(true);
            """)


if __name__ == '__main__':
    main()
