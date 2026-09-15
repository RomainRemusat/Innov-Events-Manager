"""Cycle commercial sur SQL/MongoDB temporaires et MailHog local uniquement.

Vérifie le PDF envoyé, le verrouillage contractuel et une course modification/décision.
Exécution : python -B tests/quote_lifecycle.py
"""
import base64
import concurrent.futures
import json
import secrets
import urllib.request

from login_logging import php


def main():
    name = 'innovevents_test_quote_' + secrets.token_hex(6)
    email = name + '@example.test'
    connect = "$db=new PDO('mysql:host=db;charset=utf8mb4','root','root_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); "
    prefix = connect + f"$db->exec('USE `{name}`'); " + rf"""
        require 'src/controllers/PdfController.php';
        require 'src/controllers/ClientController.php';
        $class = new ReflectionClass(Database::class);
        $instance = $class->newInstanceWithoutConstructor();
        $class->getProperty('pdo')->setValue($instance, $db);
        $class->getProperty('instance')->setValue(null, $instance);
        $_ENV['MONGO_DATABASE'] = '{name}';
    """
    created = False

    def call(action, data=None, user=1):
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        return php(prefix + f"""
            session_start(); $_SESSION=['user_id'=>{user},'csrf_token'=>'test'];
            $_SERVER['REQUEST_METHOD']='POST';
            $_POST=json_decode(base64_decode('{payload}'),true)+['csrf_token'=>'test'];
            ob_start();
            register_shutdown_function(function() use ($db) {{
                echo json_encode(['body'=>base64_encode(ob_get_clean()),'session'=>$_SESSION,
                    'quote'=>$db->query('SELECT * FROM devis WHERE id_devis=99')->fetch()]);
            }});
            {action}
        """)

    try:
        php(connect + f"$db->exec('CREATE DATABASE `{name}`'); echo json_encode(true);")
        created = True
        php(prefix + f"""
            $db->exec(file_get_contents('scripts/schema.sql'));
            $db->exec(file_get_contents('scripts/initialise.sql'));
            $db->exec("INSERT INTO prospects (id,user_id,company_name,contact_name,email,phone,event_type)
                VALUES (99,3,'Test','Test','{email}','0000000000','Autre')");
            $db->exec("INSERT INTO devis (id_devis,id_prospect,reference_pdf) VALUES (99,99,'{name}.pdf')");
            echo json_encode(true);
        """)
        response = call('(new ClientController())->handleQuoteResponse($_POST);', dict(devis_id=99, quote_action='accept', revision=1), 3)
        assert response['quote']['status'] == 'brouillon' and 'client_error' in response['session']
        assert php(prefix + "echo json_encode((new Prestation())->create(99,'Première prestation',100));")
        assert php(prefix + "echo json_encode((new Prestation())->create(99,'À retirer',25));")
        removable = php(prefix + "echo json_encode((int)$db->query('SELECT MAX(id) FROM prestations WHERE devis_id=99')->fetchColumn());")
        assert php(prefix + f"echo json_encode((new Prestation())->delete({removable},99));")
        php(prefix + "$db->exec(\"UPDATE prospects SET email='invalid' WHERE id=99\"); echo json_encode(true);")
        failed = call('(new PdfController())->sendQuoteToClient(99);')
        assert failed['quote']['status'] == 'brouillon' and 'flash_error' in failed['session']
        php(prefix + f"$db->exec(\"UPDATE prospects SET email='{email}' WHERE id=99\"); echo json_encode(true);")
        first = call('(new PdfController())->sendQuoteToClient(99);')
        assert first['quote']['status'] == 'étude côté client' and first['body'] == '', first
        version = int(first['quote']['revision'])
        page = call('(new ClientController())->showDashboard();', user=3)
        assert f'name="revision" value="{version}"' in base64.b64decode(page['body']).decode('utf-8')
        pdf = call(f"(new PdfController())->downloadPdf('{name}.pdf');", user=3)
        original_pdf = base64.b64decode(pdf['body'])
        assert original_pdf.startswith(b'%PDF-')
        assert php(prefix + "echo json_encode((new Prestation())->create(99,'Deuxième prestation',75));")
        hidden = call(f"(new PdfController())->downloadPdf('{name}.pdf');", user=3)
        assert hidden['body'] == '' and hidden['quote']['montant_ht'] == '175.00' and hidden['quote']['tva'] == '35.00'
        preview = call("$_GET['id']=99; (new PdfController())->generatePdfAction();", user=3)
        assert preview['body'] == '', 'Le client ne doit pas générer un brouillon'
        assert php(prefix + f"echo json_encode(!(new Devis())->updateStatus(99,'accepté',3,{version}));")
        resent = call('(new PdfController())->sendQuoteToClient(99);')
        assert resent['quote']['status'] == 'étude côté client' and resent['body'] == ''
        assert int(resent['quote']['revision']) > version
        current_pdf = base64.b64decode(call(f"(new PdfController())->downloadPdf('{name}.pdf');", user=3)['body'])
        assert current_pdf.startswith(b'%PDF-') and current_pdf != original_pdf
        response = call('(new ClientController())->handleQuoteResponse($_POST);', dict(devis_id=99, quote_action='accept', revision=version), 3)
        assert response['quote']['status'] == 'étude côté client' and 'client_error' in response['session']
        current_version = int(resent['quote']['revision'])
        assert php(prefix + f"echo json_encode(!(new Devis())->updateStatus(99,'accepté',4,{current_version}));")
        assert php(prefix + f"echo json_encode((new Devis())->updateStatus(99,'accepté',3,{current_version}));")
        assert not php(prefix + "echo json_encode((new Prestation())->create(99,'Interdit',1));")
        line = php(prefix + "echo json_encode((int)$db->query('SELECT id FROM prestations WHERE devis_id=99 LIMIT 1')->fetchColumn());")
        assert not php(prefix + f"echo json_encode((new Prestation())->delete({line},99));")
        locked = call('(new PdfController())->sendQuoteToClient(99);')
        assert locked['quote']['status'] == 'accepté' and 'flash_error' in locked['session']
        page = call("require 'src/controllers/QuoteController.php'; (new QuoteController())->editDevis(99);")
        assert 'action=send_quote_to_client' not in base64.b64decode(page['body']).decode('utf-8')
        assert base64.b64decode(call(f"(new PdfController())->downloadPdf('{name}.pdf');", user=3)['body']) == current_pdf

        # Le serveur SMTP local doit avoir reçu exactement les deux propositions autorisées.
        with urllib.request.urlopen('http://localhost:8025/api/v2/search?kind=to&query=' + email) as response:
            assert len(json.load(response)['items']) == 2

        # Deux connexions SQL distinctes : soit la décision gagne, soit la modification gagne.
        php(prefix + "$db->exec(\"UPDATE devis SET status='étude côté client',revision=50 WHERE id_devis=99\"); echo json_encode(true);")
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            edit = pool.submit(php, prefix + "echo json_encode((new Prestation())->create(99,'Concurrente',1));")
            accept = pool.submit(php, prefix + "echo json_encode((new Devis())->updateStatus(99,'accepté',3,50));")
            assert edit.result() != accept.result(), 'Les deux opérations concurrentes ont réussi ou échoué ensemble'
        print('OK : brouillon, envois/PDF, version périmée, propriété, verrou accepté et course SQL', flush=True)
    finally:
        if created:
            with urllib.request.urlopen('http://localhost:8025/api/v2/search?kind=to&query=' + email) as response:
                messages = json.load(response)['items']
            for message in messages:
                urllib.request.urlopen(urllib.request.Request('http://localhost:8025/api/v1/messages/' + message['ID'], method='DELETE')).close()
            php(connect + rf"""
                $db->exec('DROP DATABASE `{name}`');
                $mongo=new MongoDB\Driver\Manager('mongodb://mongodb:27017');
                $mongo->executeCommand('{name}',new MongoDB\Driver\Command(['dropDatabase'=>1]));
                if (is_file('storage/devis/{name}.pdf')) unlink('storage/devis/{name}.pdf');
                echo json_encode(true);
            """)


if __name__ == '__main__':
    main()
