"""Parcours d'administration sur une base SQL/MongoDB jetable, sans email sortant."""
import base64
import json
import secrets

from login_logging import php


def main():
    name = 'innovevents_test_admin_' + secrets.token_hex(6)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);"
    prefix = connect + f"$db->exec('USE `{name}`');" + """
        require 'src/controllers/AdminAccountController.php';
        require 'src/controllers/AdminEventController.php';
        require 'src/controllers/AdminClientController.php';
        $class=new ReflectionClass(Database::class);
        $instance=$class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance,$db);
        $class->getProperty('instance')->setValue(null,$instance);
        $_ENV['SMTP_HOST']='127.0.0.1'; $_ENV['SMTP_PORT']='1';
    """ + f"$_ENV['MONGO_DATABASE']='{name}';"

    def query(code):
        return php(prefix + code)

    def call(controller, method, data=None, user=1, verb='POST'):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return query(f"""
            session_start(); $_SESSION=['user_id'=>{user},'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='{verb}';
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            ob_start(); register_shutdown_function(function() {{
                $body=ob_get_clean(); echo json_encode(['body'=>base64_encode($body),'session'=>$_SESSION]);
            }});
            (new {controller}())->{method};
        """)

    php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
    try:
        query("$db->exec(file_get_contents('scripts/schema.sql')); $db->exec(file_get_contents('scripts/initialise.sql')); echo json_encode(true);")
        valid = dict(client_id='3', title='Projet <test>', location='Paris', event_type='Séminaire',
                     start_date='2027-01-02T10:00', end_date='2027-01-02T18:00', estimated_participants='20', status='brouillon')
        before = query("echo json_encode((new Event())->countDrafts());")
        for change in [dict(start_date='2027-02-30T10:00'), dict(end_date='2027-01-01T10:00'),
                       dict(estimated_participants='-1'), dict(client_id='2'), dict(title=[]),
                       dict(status='en cours'), dict(publish='1')]:
            result = call('AdminEventController', 'saveEvent($_POST)', valid | change)
            assert 'flash_error' in result['session'], change
            assert query("echo json_encode((new Event())->countDrafts());") == before
        result = call('AdminEventController', 'saveEvent($_POST)', valid)
        assert 'flash_success' in result['session'], result
        event_id = query("echo json_encode((int)$db->query('SELECT MAX(id) FROM events')->fetchColumn());")
        assert query("echo json_encode((new Event())->countDrafts());") == before + 1
        saved = valid | dict(event_id=str(event_id), title='Projet modifié', publish='1', publication_consent='1')
        assert 'flash_success' in call('AdminEventController', 'saveEvent($_POST)', saved)['session']
        row = query(f"echo json_encode((new Event())->findByIdAdmin({event_id}));")
        assert row['title'] == 'Projet modifié' and row['publication_consent_at'] and int(row['is_published']) == 1
        assert 'flash_error' in call('AdminEventController', 'saveEvent($_POST)', saved | dict(client_id='4'))['session']
        for user in [0, 2, 3]:
            for controller, method, data in [
                ('AdminEventController', 'saveEvent($_POST)', saved | dict(title='Interdit')),
                ('AdminEventController', 'deleteEvent($_POST)', dict(event_id=event_id, confirm_delete='1')),
                ('AdminAccountController', 'manage($_POST)', dict(operation='delete', account_id=3, confirm_delete='1')),
            ]:
                assert 'flash_success' not in call(controller, method, data, user)['session']
        for change, verb in [(dict(csrf_token='bad'), 'POST'), ({}, 'GET')]:
            assert 'flash_success' not in call('AdminEventController', 'saveEvent($_POST)', saved | change, verb=verb)['session']
        query("$db->exec(\"CREATE TRIGGER fail_event BEFORE UPDATE ON events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test SQL'\"); echo json_encode(true);")
        assert 'flash_error' in call('AdminEventController', 'saveEvent($_POST)', saved | dict(title='Échec'))['session']
        assert query(f"echo json_encode((new Event())->findByIdAdmin({event_id})['title']);") == 'Projet modifié'
        query("$db->exec('DROP TRIGGER fail_event'); echo json_encode(true);")
        for controller, method in [('AdminAccountController', 'index()'), ('AdminEventController', 'editEvent()'),
                                   ('AdminEventController', f'editEvent({event_id})'),
                                   ('AdminClientController', 'showClientsList()'), ('AdminClientController', 'showClientDetails(3)')]:
            page = base64.b64decode(call(controller, method)['body']).decode()
            assert '<h1' in page and 'Warning' not in page and 'Fatal error' not in page, method
        history = query("echo json_encode((new Event())->findByClientId(3));")
        assert event_id in [int(row['id']) for row in history]
        assert query("echo json_encode(array_column((new User())->findAllClients('client@luxe.com',true),'id'));") == [3]
        print('OK : recherche, historique, formulaires, brouillons, validation, publication, rôles, CSRF et rollback.', flush=True)

        quote_data = dict(event_id=event_id, phone='0102030405')
        for user in [0, 2, 3]:
            assert 'flash_success' not in call('AdminEventController', 'createQuote($_POST)', quote_data, user)['session']
        for change, verb in [(dict(csrf_token='bad'), 'POST'), ({}, 'GET'), (dict(phone='invalide'), 'POST')]:
            assert 'flash_success' not in call('AdminEventController', 'createQuote($_POST)', quote_data | change, verb=verb)['session']
        original_count = query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM prospects')->fetchColumn());")
        query("$db->exec(\"CREATE TRIGGER fail_quote BEFORE INSERT ON devis FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test SQL'\"); echo json_encode(true);")
        assert 'flash_error' in call('AdminEventController', 'createQuote($_POST)', quote_data)['session']
        assert query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM prospects')->fetchColumn());") == original_count
        query("$db->exec('DROP TRIGGER fail_quote'); echo json_encode(true);")
        assert 'flash_success' in call('AdminEventController', 'createQuote($_POST)', quote_data)['session']
        quote = query(f"echo json_encode((new Devis())->findByEventIdWithPrestations({event_id}));")
        details = query(f"echo json_encode((new Devis())->findWithProspect({quote['id_devis']}));")
        assert int(details['user_id']) == 3 and details['status'] == 'brouillon' and details['phone'] == '0102030405'
        assert details['location'] == 'Paris' and details['event_date'] == '2027-01-02'
        assert 'flash_error' in call('AdminEventController', 'createQuote($_POST)', quote_data)['session']
        assert query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM prospects')->fetchColumn());") == original_count + 1
        print('OK : premier devis associé au bon projet/client, sans doublon, permissions et rollback du dossier commercial.', flush=True)

        account = dict(operation='create', firstname='Compte', lastname='Test', email='admin-test@example.test', role='EMPLOYEE')
        assert 'flash_error' in call('AdminAccountController', 'manage($_POST)', account | dict(role='ADMIN'))['session']
        result = call('AdminAccountController', 'manage($_POST)', account)
        assert 'flash_success' in result['session'] and 'flash_warning' in result['session'], result
        created = query("echo json_encode((new User())->findByEmail('admin-test@example.test'));")
        assert created['role'] == 'EMPLOYEE' and int(created['must_change_password']) == 1
        assert 'flash_error' in call('AdminAccountController', 'manage($_POST)', account)['session']
        for operation, suspended in [('suspend', 1), ('restore', 0)]:
            assert 'flash_success' in call('AdminAccountController', 'manage($_POST)', dict(operation=operation, account_id=created['id']))['session']
            assert query(f"echo json_encode((int)(new User())->findById({created['id']})['is_deleted']);") == suspended
        for operation in ['suspend', 'restore', 'delete']:
            assert 'flash_error' in call('AdminAccountController', 'manage($_POST)', dict(operation=operation, account_id=1, confirm_delete='1'))['session']
        assert 'flash_error' in call('AdminAccountController', 'manage($_POST)', dict(operation='delete', account_id=created['id']))['session']
        assert 'flash_success' in call('AdminAccountController', 'manage($_POST)', dict(operation='delete', account_id=created['id'], confirm_delete='1'))['session']
        assert not query(f"echo json_encode((new User())->findById({created['id']}));")
        print('OK : comptes, mot de passe obligatoire, échec email signalé, suspension, réactivation et protection administrateur.', flush=True)

        query(f"$db->exec(\"UPDATE devis SET event_id={event_id} WHERE id_devis=1\"); $db->exec(\"INSERT INTO notes(event_id,user_id,content) VALUES({event_id},2,'Test')\"); echo json_encode(true);")
        assert 'flash_error' in call('AdminEventController', 'deleteEvent($_POST)', dict(event_id=event_id))['session']
        assert 'flash_success' in call('AdminEventController', 'deleteEvent($_POST)', dict(event_id=event_id, confirm_delete='1'))['session']
        assert not query(f"echo json_encode((new Event())->findByIdAdmin({event_id}));")
        assert query("echo json_encode($db->query('SELECT event_id FROM devis WHERE id_devis=1')->fetch());") == {'event_id': None}
        assert query(f"echo json_encode((int)$db->query('SELECT COUNT(*) FROM notes WHERE event_id={event_id}')->fetchColumn());") == 0
        print('OK : suppression confirmée, devis conservé et notes supprimées par cascade.', flush=True)
        images = query(r"""
            $service = new EventManagementService();
            $data = ['client_id'=>'3','title'=>'Images','location'=>'Paris','event_type'=>'Autre',
                'start_date'=>'2027-01-02T10:00','status'=>'brouillon'];
            $source = tempnam(sys_get_temp_dir(), 'admin_image_');
            file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF1sAAAAASUVORK5CYII='));
            $file = ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$source,'name'=>'image.png','size'=>filesize($source)];
            $paths = []; $checks = [];
            try {
                $first = $service->save(null,$data,$file,1)['id'];
                $old = (new Event())->findByIdAdmin($first)['image_path']; $paths[]=$old;
                $service->save($first,$data,$file,1);
                $new = (new Event())->findByIdAdmin($first)['image_path']; $paths[]=$new;
                $checks[] = !file_exists('public/'.$old) && is_file('public/'.$new);
                $second = $service->save(null,$data,null,1)['id'];
                $stmt=$db->prepare('UPDATE events SET image_path=? WHERE id=?'); $stmt->execute([$new,$second]);
                $service->delete($first,1);
                $checks[] = is_file('public/'.$new);
                $service->delete($second,1);
                $checks[] = !file_exists('public/'.$new);
                $before = glob('public/uploads/events/*');
                try { $service->save(null,array_replace($data,['status'=>'en cours']),$file,1); }
                catch (InvalidArgumentException $expected) {}
                $checks[] = glob('public/uploads/events/*') === $before;
            } finally {
                unlink($source);
                foreach ($paths as $path) if (is_file('public/'.$path)) unlink('public/'.$path);
            }
            echo json_encode($checks);
        """)
        assert images == [True, True, True, True], images
        print('OK : remplacement des images, conservation des fichiers partagés et nettoyage après rollback.', flush=True)
    finally:
        php(connect + f"$db->exec('DROP DATABASE `{name}`');" +
            f"$mongo=new MongoDB\\Driver\\Manager('mongodb://mongodb:27017'); $mongo->executeCommand('{name}',new MongoDB\\Driver\\Command(['dropDatabase'=>1])); echo json_encode(true);")


if __name__ == '__main__':
    main()
