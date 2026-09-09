"""Sur Docker local : refus des mutations et conservation des droits employés.

Dossiers synthétiques supprimés en fin de test. Journaux et mail de test MailHog
conservés ; aucun dossier existant modifié. L'envoi SMTP utilise le MailHog local.
"""
import base64
import http.cookiejar
import re
import secrets
import urllib.request
import urllib.error

from login_logging import ACCOUNTS, NoRedirect, URL, php, request


def main():
    marker = 'B05_' + secrets.token_hex(10)
    setup = "require 'src/config/Database.php'; $db = Database::getInstance();\n"
    clients = []
    ids = {}

    def sql(code):
        return php(setup + code.replace('MARKER', marker))

    def snapshot():
        return sql("""
            $state = [];
            foreach (['users','companies','prospects','events','devis','prestations','notes'] as $table) {
                $state[$table] = hash('sha256', json_encode($db->query("SELECT * FROM $table ORDER BY 1")->fetchAll()));
            }
            echo json_encode($state);
        """)

    try:
        ids = sql("""
            $db->beginTransaction();
            $db->exec("INSERT INTO companies (name) VALUES ('MARKER')");
            $company = (int)$db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO users (company_id,email,password,firstname,lastname,role)
                VALUES (?, 'MARKER@example.test', 'unused', 'Test', 'Permissions', 'CLIENT')");
            $stmt->execute([$company]); $user = (int)$db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO prospects (user_id,company_id,company_name,contact_name,email,phone,event_type)
                VALUES (?, ?, 'MARKER', 'Test Permissions', 'MARKER@example.test', '0102030405', 'Séminaire')");
            $stmt->execute([$user,$company]); $prospect = (int)$db->lastInsertId();
            $db->exec("INSERT INTO devis (id_prospect,reference_pdf,montant_ht,tva,status) VALUES ($prospect,'MARKER.pdf',0,0,'brouillon')");
            $quote = (int)$db->lastInsertId();
            $db->exec("INSERT INTO prestations (devis_id,libelle,montant_ht) VALUES ($quote,'Test',10)");
            $prestation = (int)$db->lastInsertId();
            $db->exec("INSERT INTO events (client_id,company_id,title,start_date,location)
                VALUES ($user,$company,'MARKER','2026-12-01 10:00:00','Paris')");
            $event = (int)$db->lastInsertId();
            $db->commit();
            echo json_encode(compact('company','user','prospect','quote','prestation','event'));
        """)
        before = snapshot()
        for account in [None, ACCOUNTS[2], ACCOUNTS[1], ACCOUNTS[0]]:
            client = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
            clients.append(client)
            _, _, body = request(client, 'login')
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            if account:
                code, headers, _ = request(client, 'login', {
                    'email': account[1], 'password': 'Password123!', 'csrf_token': token})
                assert code == 302 and headers['Location'] == 'index.php?action=' + account[2]
            payloads = [
                ('process_conversion', dict(prospect_id=ids['prospect'], company_name=marker,
                    contact_name='Test Permissions', email=marker+'@example.test', event_title=marker,
                    start_date='2026-12-02T10:00', location='Paris')),
                ('update_client', dict(client_id=ids['user'], firstname='Modifié', lastname='Permissions', email=marker+'@example.test')),
                ('delete_client', dict(client_id=ids['user'])),
                ('add_prestation', dict(devis_id=ids['quote'], libelle='Ajout test', montant_ht=20)),
                ('delete_prestation', dict(devis_id=ids['quote'], prestation_id=ids['prestation'])),
                ('admin_event_update_status', dict(event_id=ids['event'], status='en cours')),
                ('admin_upload_image', dict(event_id=ids['event'])),
            ]
            screens = [f"show_convert_form&id={ids['prospect']}", f"edit_client&id={ids['user']}",
                       'admin_devis', f"edit_devis&id={ids['quote']}"]
            admin = account == ACCOUNTS[0]
            for route in screens:
                code, headers, _ = request(client, route)
                if admin:
                    assert code == 200, route
                else:
                    expected = 'login' if account is None else 'admin_events' if account == ACCOUNTS[1] else 'client_dashboard'
                    assert code == 302 and headers['Location'] == 'index.php?action='+expected, route
            for route, payload in payloads:
                if route == 'admin_upload_image':
                    boundary = marker
                    body = b''
                    for key, value in dict(payload, csrf_token=token).items():
                        body += f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
                    body += f'--{boundary}\r\nContent-Disposition: form-data; name="event_image"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n'.encode()
                    body += base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF1sAAAAASUVORK5CYII=')
                    body += f'\r\n--{boundary}--\r\n'.encode()
                    upload = urllib.request.Request(URL+route, data=body, headers={'Content-Type': 'multipart/form-data; boundary='+boundary})
                    try:
                        response = client.open(upload, timeout=45)
                    except urllib.error.HTTPError as error:
                        response = error
                    with response:
                        code, headers = response.code, response.headers
                        response.read()
                else:
                    code, headers, _ = request(client, route, dict(payload, csrf_token=token))
                assert code == 302, route
                if not admin:
                    expected = 'login' if account is None else 'admin_events' if account == ACCOUNTS[1] else 'client_dashboard'
                    assert headers['Location'] == 'index.php?action='+expected, route
                elif route == 'admin_upload_image':
                    assert f"admin_event_detail&id={ids['event']}&success=image_updated" in headers['Location']
                    assert sql(f"$path = $db->query('SELECT image_path FROM events WHERE id={ids['event']}')->fetchColumn(); echo json_encode(is_file('public/' . $path));")
            code, headers, _ = request(client, f"send_quote_to_client&id={ids['quote']}")
            assert code == 302
            assert headers['Location'] == (f"index.php?action=edit_devis&id={ids['quote']}" if admin else 'index.php?action=login')
            if not admin:
                assert snapshot() == before, 'Une action refusée a modifié les données SQL'
            if account == ACCOUNTS[1]:
                for route in ['admin_clients', f"view_client&id={ids['user']}", 'admin_events', f"admin_event_detail&id={ids['event']}"]:
                    code, _, body = request(client, route)
                    assert code == 200 and marker in body, route
                    for action in ['edit_client', 'delete_client', 'admin_upload_image', 'admin_event_update_status', 'edit_devis', 'mongo_logs']:
                        assert 'action='+action not in body, (route, action)
                code, headers, _ = request(client, 'admin_add_note', {'content': marker})
                assert code == 302 and headers['Location'] == 'index.php?action=admin_events'
                assert snapshot() == before, 'Note globale créée par un employé'
                code, _, _ = request(client, 'admin_add_note', {'event_id': ids['event'], 'content': marker})
                assert code == 302
                notes = sql(f"echo json_encode($db->query(\"SELECT content FROM notes WHERE event_id={ids['event']} AND user_id=2\")->fetchAll(PDO::FETCH_COLUMN));")
                assert marker in notes, 'Note événement employé absente'
                before = snapshot()
            print(f"OK : {account[1] if account else 'visiteur'}, routes et droits vérifiés", flush=True)
        state = sql(f"""
            echo json_encode([
                $db->query("SELECT firstname,is_deleted FROM users WHERE id={ids['user']}")->fetch(),
                $db->query("SELECT status FROM prospects WHERE id={ids['prospect']}")->fetchColumn(),
                $db->query("SELECT COUNT(*) FROM devis WHERE id_prospect={ids['prospect']}")->fetchColumn(),
                $db->query("SELECT libelle FROM prestations WHERE devis_id={ids['quote']}")->fetchAll(PDO::FETCH_COLUMN),
                $db->query("SELECT status FROM events WHERE id={ids['event']}")->fetchColumn(),
                $db->query("SELECT status FROM devis WHERE id_devis={ids['quote']}")->fetchColumn()
            ]);
        """)
        assert state[0]['firstname'] == 'Modifié' and int(state[0]['is_deleted']) == 1
        assert state[1] == 'converti' and int(state[2]) == 2 and state[3] == ['Ajout test']
        assert state[4:] == ['en cours', 'étude côté client']
        print('OK : effets SQL des mutations administrateur vérifiés', flush=True)
    finally:
        sql("""
            $path = 'storage/devis/MARKER.pdf';
            if (is_file($path)) { unlink($path); }
            $images = $db->query("SELECT e.image_path FROM events e JOIN users u ON u.id=e.client_id
                WHERE u.email='MARKER@example.test'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($images as $image) {
                if ($image && preg_match('~^uploads/events/event_[a-z0-9]+\\.png$~', $image) && is_file('public/'.$image)) {
                    unlink('public/'.$image);
                }
            }
            $db->exec("DELETE FROM prospects WHERE company_name='MARKER'");
            $db->exec("DELETE FROM users WHERE email='MARKER@example.test'");
            $db->exec("DELETE FROM companies WHERE name='MARKER'");
            echo json_encode(true);
        """)
        for client in clients:
            request(client, 'logout')


if __name__ == '__main__':
    main()
