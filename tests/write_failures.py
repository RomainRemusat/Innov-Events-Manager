"""Échecs SQL, SMTP et MongoDB sur une base temporaire, sans envoyer d’email.
Exécution : python -B tests/write_failures.py
"""
import base64
import json
import secrets

from login_logging import php


def main():
    name = 'innovevents_test_failures_' + secrets.token_hex(6)
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);"
    prefix = connect + f"$db->exec('USE `{name}`');" + r"""
        require 'src/controllers/ClientController.php';
        require 'src/controllers/AuthController.php';
        require 'src/controllers/QuoteController.php';
        require 'src/controllers/AdminClientController.php';
        require 'src/models/sql/Event.php';
        require 'src/services/ConversionService.php';
        $class=new ReflectionClass(Database::class);
        $instance=$class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance,$db);
        $class->getProperty('instance')->setValue(null,$instance);
        $_ENV['SMTP_HOST']='127.0.0.1'; $_ENV['SMTP_PORT']=1;
        $_ENV['MONGO_URI']='mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=20';
    """

    def query(code):
        return php(prefix + code)

    def call(action, data=None, user=3):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return query(f"""
            session_start(); $_SESSION=['user_id'=>{user},'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='POST';
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            ob_start(); register_shutdown_function(function() use ($db) {{
                echo json_encode(['body'=>base64_encode(ob_get_clean()),'session'=>$_SESSION,
                    'quote'=>$db->query('SELECT * FROM devis WHERE id_devis=1')->fetch()]);
            }});
            {action}
        """)

    php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
    try:
        query("""
            $db->exec(file_get_contents('scripts/schema.sql'));
            $db->exec(file_get_contents('scripts/initialise.sql'));
            $db->exec('ALTER TABLE devis DROP COLUMN change_reason');
            for ($i=0;$i<2;$i++) $db->exec(file_get_contents('scripts/update_quote_change_reason.sql'));
            echo json_encode(true);
        """)
        password_before = query("echo json_encode($db->query('SELECT password,must_change_password FROM users WHERE id=3')->fetch());")
        result = call('(new AuthController())->resetPasswordRequest();', {'email': 'client@luxe.com'})
        password_after = query("echo json_encode($db->query('SELECT password,must_change_password FROM users WHERE id=3')->fetch());")
        assert password_before == password_after, 'Mot de passe perdu malgré l’échec SMTP'
        missing = call('(new AuthController())->resetPasswordRequest();', {'email': 'absent@example.test'})
        assert result['session']['auth_message'] == missing['session']['auth_message']
        print('OK : échec SMTP sans perte du mot de passe ni divulgation du compte.', flush=True)

        assert query(r"""
            $mailer = new class extends MailService {
                public int $calls=0;
                public string $password='';
                public function sendResetPasswordEmail(string $email,string $firstname,string $tempPassword): bool {
                    $this->calls++; $this->password=$tempPassword; return true;
                }
            };
            $service=new PasswordResetService($mailer);
            $db->exec("CREATE TRIGGER fail_password BEFORE UPDATE ON users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test SQL'");
            try { $service->reset('client@luxe.com'); throw new RuntimeException('Écriture acceptée'); }
            catch (PDOException $e) { if ($mailer->calls!==0 || $db->inTransaction()) throw new RuntimeException('Email envoyé avant écriture'); }
            $db->exec('DROP TRIGGER fail_password');
            $id=$service->reset('client@luxe.com');
            $user=$db->query('SELECT * FROM users WHERE id=3')->fetch();
            $db->exec('UPDATE users SET must_change_password=0 WHERE id=3');
            echo json_encode($id===3 && $mailer->calls===1 && password_verify($mailer->password,$user['password']) && (int)$user['must_change_password']===1);
        """)
        reason = 'Merci de modifier le menu <test>.'
        result = call('(new ClientController())->handleQuoteResponse($_POST);', dict(devis_id=1, quote_action='request_change', revision=1, change_reason=reason))
        assert result['quote']['status'] == 'modification' and result['quote']['change_reason'] == reason
        assert 'client_success' in result['session'] and 'client_warning' in result['session']
        page = call('(new QuoteController())->editDevis(1);', user=1)
        assert 'Merci de modifier le menu &lt;test&gt;.' in base64.b64decode(page['body']).decode()
        query("$db->exec(\"UPDATE devis SET status='étude côté client' WHERE id_devis=1\"); echo json_encode(true);")
        assert query("echo json_encode(!(new Devis())->updateStatus(1,'modification',3,1));")
        query("$db->exec(\"CREATE TRIGGER fail_decision BEFORE UPDATE ON devis FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test SQL'\"); echo json_encode(true);")
        result = call('(new ClientController())->handleQuoteResponse($_POST);', dict(devis_id=1, quote_action='request_change', revision=1, change_reason='Autre motif'))
        assert result['quote']['status'] == 'étude côté client' and result['quote']['change_reason'] == reason
        assert 'client_error' in result['session'] and 'client_success' not in result['session']
        query("$db->exec('DROP TRIGGER fail_decision'); echo json_encode(true);")
        for action, status in [('accept', 'accepté'), ('reject', 'refusé')]:
            query("$db->exec(\"UPDATE devis SET status='étude côté client' WHERE id_devis=1\"); echo json_encode(true);")
            result = call('(new ClientController())->handleQuoteResponse($_POST);', dict(devis_id=1, quote_action=action, revision=1))
            assert result['quote']['status'] == status and 'client_warning' in result['session']
        print('OK : motif et statut atomiques, lecture sans MongoDB, notifications échouées signalées.', flush=True)

        assert query("echo json_encode(!(new Event())->updateImage(99999,'uploads/events/absent.png') && !(new User())->updatePassword(99999,'hash',false));")
        result = call('(new AdminClientController())->updateClient($_POST);', dict(client_id=3, firstname='Alice', lastname='Vancort', email='a.legrand@nextgen.io'), 1)
        assert 'flash_error' in result['session'] and 'flash_success' not in result['session']
        result = call('(new AdminClientController())->updateClient($_POST);', dict(client_id=3, firstname='Alice', lastname='Vancort', email='client@luxe.com'), 1)
        assert 'flash_success' in result['session']
        result = call('(new AdminClientController())->deleteClient($_POST);', dict(client_id=99999), 1)
        assert 'flash_error' in result['session']

        assert query(r"""
            $db->exec("INSERT INTO prospects (company_name,contact_name,email,phone,event_type) VALUES ('Test','Test Client','new@example.test','0102030405','Autre')");
            $service=new ConversionService();
            $id=$service->convertProspectToClient([
                'prospect_id'=>(int)$db->lastInsertId(),'company_name'=>'Test','contact_name'=>'Test Client',
                'email'=>'new@example.test','phone'=>'0102030405','event_title'=>'Test',
                'start_date'=>'2027-10-01T12:00','location'=>'Paris','estimated_participants'=>10,'description'=>'Test projet',
            ]);
            echo json_encode($id>0 && $service->wasCredentialsEmailSent()===false);
        """)
        registration = call('(new AuthController())->register($_POST);', dict(firstname='Test', lastname='Client', username='test', email='register@example.test', password='Password123!'))
        assert 'créé' in registration['session']['login_success'] and 'pas pu' in registration['session']['login_success']
        request = call('(new QuoteController())->submitQuote($_POST);', dict(company_name='NextGen Software', contact_name='Test Client', email='request@example.test', phone='0102030405', event_type='Autre', event_date='2099-01-01', location='Paris', estimated_participants='10', description='Projet de test'))
        assert 'Demande reçue' in base64.b64decode(request['body']).decode()
        assert 'inutile de la soumettre à nouveau' in base64.b64decode(request['body']).decode()
        print('OK : erreurs d’écriture signalées et conversion conservée après échec d’envoi des accès.', flush=True)
    finally:
        php(connect + f"$db->exec('DROP DATABASE `{name}`'); echo json_encode(true);")


if __name__ == '__main__':
    main()
