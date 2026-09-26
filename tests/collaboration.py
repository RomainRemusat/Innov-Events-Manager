"""Notes et tâches sur une base SQL/MongoDB temporaire."""
import base64
import json
import secrets

from login_logging import php


def main():
    name = 'innovevents_test_collaboration_' + secrets.token_hex(6)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);"
    prefix = connect + f"$db->exec('USE `{name}`');" + """
        require 'src/controllers/AdminEventController.php';
        require 'src/controllers/DashboardController.php';
        $class=new ReflectionClass(Database::class);
        $instance=$class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance,$db);
        $class->getProperty('instance')->setValue(null,$instance);
    """ + f"$_ENV['MONGO_DATABASE']='{name}'; $_ENV['MONGO_URI']='mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=50';"

    def query(code):
        return php(prefix + code)

    def call(method, data=None, user=1, verb='POST', controller='AdminEventController'):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return query(f"""
            session_start(); $_SESSION=['user_id'=>{user},'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='{verb}'; $_GET=['id'=>{event}];
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            ob_start(); register_shutdown_function(function() {{
                $body=ob_get_clean(); echo json_encode(['body'=>base64_encode($body),'session'=>$_SESSION]);
            }});
            (new {controller}())->{method};
        """)

    php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
    try:
        ids = query("""
            $db->exec(file_get_contents('scripts/schema.sql')); $db->exec(file_get_contents('scripts/initialise.sql'));
            $stmt=$db->prepare("INSERT INTO users(email,password,firstname,lastname,role) VALUES('second@example.test','hash','Second','Employé','EMPLOYEE')");
            $stmt->execute(); $second=(int)$db->lastInsertId();
            echo json_encode(['event'=>1,'second'=>$second]);
        """)
        event = ids['event']
        second = ids['second']

        assert 'flash_success' in call('addNote()', dict(content='Information globale'))['session']
        global_note = query("echo json_encode($db->query('SELECT MAX(id) FROM notes WHERE event_id IS NULL')->fetchColumn());")
        assert global_note
        assert 'flash_success' not in call('addNote()', dict(content='Interdite'), user=2)['session']
        assert query("echo json_encode((int)$db->query(\"SELECT COUNT(*) FROM notes WHERE content='Interdite'\")->fetchColumn());") == 0

        assert 'flash_success' in call('addNote()', dict(event_id=event, content='Note employé'), user=2)['session']
        note = query(f"echo json_encode((int)$db->query(\"SELECT MAX(id) FROM notes WHERE event_id={event} AND user_id=2\")->fetchColumn());")
        assert 'flash_error' in call('updateNote()', dict(note_id=note, content='Usurpation'), user=second)['session']
        assert 'flash_success' in call('updateNote()', dict(note_id=note, content='Note employé modifiée'), user=2)['session']
        assert 'flash_success' in call('updateNote()', dict(note_id=note, content='Correction admin'))['session']
        assert query(f"echo json_encode($db->query('SELECT content FROM notes WHERE id={note}')->fetchColumn());") == 'Correction admin'
        assert 'flash_error' in call('deleteNote()', dict(note_id=note), user=second)['session']
        assert 'flash_success' in call('deleteNote()', dict(note_id=note), user=2)['session']
        assert not query(f"echo json_encode((new Note())->findById({note}));")
        assert 'flash_success' not in call('deleteNote()', dict(note_id=global_note), user=3)['session']
        assert 'flash_success' in call('deleteNote()', dict(note_id=global_note))['session']
        print('OK : notes de projet et globales, propriété, rôles, modification et suppression.', flush=True)

        create = dict(event_id=event, assigned_user_id=2, title='Confirmer le prestataire')
        assert 'flash_success' not in call('createTask()', create, verb='GET')['session']
        invalid_csrf = call('createTask()', create | dict(csrf_token='incorrect'))
        assert 'Erreur de sécurité' in base64.b64decode(invalid_csrf['body']).decode()
        assert query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM tasks')->fetchColumn());") == 0
        for user in [0, 2, 3]:
            assert 'flash_success' not in call('createTask()', create, user=user)['session']
        assert 'flash_error' in call('createTask()', create | dict(assigned_user_id=3))['session']
        assert 'flash_success' in call('createTask()', create)['session']
        task = query("echo json_encode((int)$db->query('SELECT MAX(id) FROM tasks')->fetchColumn());")
        status = lambda: query(f"echo json_encode($db->query('SELECT status FROM tasks WHERE id={task}')->fetchColumn());")
        assert status() == 'à faire'
        assert 'flash_error' in call('updateTaskStatus()', dict(task_id=task, event_id=event, status='en cours'), user=second)['session']
        assert 'flash_error' in call('updateTaskStatus()', dict(task_id=task, event_id=event, status='terminée'), user=2)['session']
        assert status() == 'à faire'
        assert 'flash_success' in call('updateTaskStatus()', dict(task_id=task, event_id=event, status='en cours'), user=2)['session']
        assert 'flash_success' in call('updateTaskStatus()', dict(task_id=task, event_id=event, status='terminée'), user=2)['session']
        assert status() == 'terminée'
        assert 'flash_success' in call('updateTaskStatus()', dict(task_id=task, event_id=event, status='à faire'))['session']
        assert status() == 'à faire'
        assert 'flash_success' not in call('deleteTask()', dict(task_id=task), user=2)['session']
        assert 'flash_success' in call('deleteTask()', dict(task_id=task))['session']
        assert query("echo json_encode((int)$db->query('SELECT COUNT(*) FROM tasks')->fetchColumn());") == 0
        print('OK : assignation, transitions séquentielles, isolation des employés et gestion administrateur.', flush=True)

        call('createTask()', create)
        admin_page = base64.b64decode(call('showEventDetail()', {}, verb='GET')['body']).decode()
        employee_page = base64.b64decode(call('showEventDetail()', {}, user=2, verb='GET')['body']).decode()
        other_employee_page = base64.b64decode(call('showEventDetail()', {}, user=second, verb='GET')['body']).decode()
        assert 'Nouvelle tâche' in admin_page and 'admin_delete_task' in admin_page
        assert 'Passer à' in employee_page and 'admin_create_task' not in employee_page and 'admin_delete_task' not in employee_page
        assert 'Passer à' not in other_employee_page and 'admin_create_task' not in other_employee_page
        dashboard = base64.b64decode(call('showDashboard()', {}, verb='GET', controller='DashboardController')['body']).decode()
        assert 'Nouvelle note globale' in dashboard and 'admin_update_note' in dashboard
        employee_dashboard = base64.b64decode(
            call('showDashboard()', {}, user=2, verb='GET', controller='DashboardController')['body']).decode()
        assert 'Mon espace employé' in employee_dashboard
        assert 'Confirmer le prestataire' in employee_dashboard
        assert 'Passer à « En cours »' in employee_dashboard
        assert 'Prospects' not in employee_dashboard and 'Comptes clients et employés' not in employee_dashboard
        print('OK : tableaux de bord administrateur et employé adaptés à leurs opérations.', flush=True)
    finally:
        php(connect + f"$db->exec('DROP DATABASE `{name}`');"
            + f"$mongo=new MongoDB\\Driver\\Manager('mongodb://mongodb:27017'); $mongo->executeCommand('{name}',new MongoDB\\Driver\\Command(['dropDatabase'=>1])); echo json_encode(true);")


if __name__ == '__main__':
    main()
