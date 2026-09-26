"""Publication : contrôleur, formulaires, droits et logs sur bases temporaires.

Exécution : python -B tests/publication_consent.py (Docker local, aucun email).
"""
import base64
import json
import pathlib
import secrets

from login_logging import php


def main():
    name = 'innovevents_test_publication_' + secrets.token_hex(6)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); "
    prefix = connect + f"$db->exec('USE `{name}`'); " + rf"""
        require 'src/controllers/AdminEventController.php';
        $class = new ReflectionClass(Database::class);
        $instance = $class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance, $db);
        $class->getProperty('instance')->setValue(null, $instance);
        $_ENV['MONGO_DATABASE'] = '{name}';
    """
    created = False

    def call(data=None, user=1, method='POST', render=False):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return php(prefix + f"""
            session_start(); $_SESSION=['csrf_token'=>'test'];
            if ({user}) $_SESSION['user_id']={user};
            $_SERVER['REQUEST_METHOD']='{method}';
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            $_GET['id']=3;
            ob_start();
            register_shutdown_function(function() use ($db) {{
                echo json_encode(['body'=>base64_encode(ob_get_clean()),'session'=>$_SESSION,
                    'event'=>$db->query('SELECT * FROM events WHERE id=3')->fetch()]);
            }});
            (new AdminEventController())->{'showEventDetail' if render else 'togglePublish'}();
        """)

    try:
        php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
        created = True
        php(prefix + """
            $db->exec(file_get_contents('scripts/schema.sql'));
            $db->exec(file_get_contents('scripts/initialise.sql'));
            echo json_encode(true);
        """)
        valid = dict(event_id=3, publish='1', publication_consent='1')
        for user in [0, 2, 3]:
            assert int(call(valid, user=user)['event']['is_published']) == 0
        assert int(call(valid, method='GET')['event']['is_published']) == 0
        invalid = call(dict(valid, csrf_token='invalid'))
        assert int(invalid['event']['is_published']) == 0
        assert 'CSRF' in base64.b64decode(invalid['body']).decode()
        for data in [dict(event_id=3), dict(event_id=3, publish='yes'), dict(event_id=3, publish='1')]:
            result = call(data)
            assert int(result['event']['is_published']) == 0 and 'flash_error' in result['session']
        page = base64.b64decode(call(render=True)['body']).decode()
        assert 'action=admin_event_toggle_publish' in page and 'name="publication_consent"' in page
        assert 'action=admin_event_toggle_publish' not in base64.b64decode(call(user=2, render=True)['body']).decode()
        published = call(valid)
        assert published['event']['publication_consent_at'] and int(published['event']['publication_consent_by']) == 1
        assert 'flash_success' in published['session']
        assert call(valid)['event']['publication_consent_at'] == published['event']['publication_consent_at']
        withdrawn = call(dict(event_id=3, publish='0'))
        assert int(withdrawn['event']['is_published']) == 0 and withdrawn['event']['publication_consent_at'] is None
        assert int(call(dict(event_id=3, publish='1'))['event']['is_published']) == 0
        logs = php(prefix + rf"""
            $mongo = new MongoDB\Driver\Manager('mongodb://mongodb:27017');
            $logs = $mongo->executeQuery('{name}.logs', new MongoDB\Driver\Query([
                'type_action'=>'MODIFICATION_PUBLICATION_EVENEMENT', 'details.event_id'=>3
            ]))->toArray();
            echo json_encode(array_map(fn($log)=>[$log->id_utilisateur,$log->details->is_published],$logs));
        """)
        assert sorted(logs) == [[1, False], [1, True]], logs
        root = pathlib.Path(__file__).resolve().parents[1]
        form = (root / 'src/views/admin/convert_prospect.php').read_text(encoding='utf-8')
        for field in ['is_visible', 'publication_consent']:
            tag = next(line for line in form.splitlines() if '<input' in line and f'name="{field}"' in line)
            assert 'checked' not in tag
        assert "case ($action === 'admin_event_toggle_publish')" in (root / 'public/index.php').read_text(encoding='utf-8')
        print('OK : droits, POST, CSRF, accord obligatoire, formulaires, répétition, retrait et journaux.', flush=True)
    finally:
        if created:
            php(connect + rf"""
                $db->exec('DROP DATABASE `{name}`');
                $mongo = new MongoDB\Driver\Manager('mongodb://mongodb:27017');
                $mongo->executeCommand('{name}', new MongoDB\Driver\Command(['dropDatabase'=>1]));
                echo json_encode(true);
            """)


if __name__ == '__main__':
    main()
