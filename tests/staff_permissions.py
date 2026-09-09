"""Test de régression des permissions employé / admin / client / visiteur (B05).

Vérifie l'interdiction des mutations structurelles pour un compte employé,
la séparation stricte des interfaces de gestion, l'isolation des rôles
et l'absence d'effets de bord sur les données SQL.
"""

import base64
import http.cookiejar
import pathlib
import re
import secrets
import urllib.error
import urllib.request
from login_logging import ACCOUNTS, NoRedirect, URL, php, request


ROOT = pathlib.Path(__file__).resolve().parents[1]


def sql(code):
    return php("require 'src/config/Database.php'; $db = Database::getInstance(); " + code)


def snapshot():
    return sql(r"""
        $data = [];
        foreach (['users', 'companies', 'prospects', 'devis', 'prestations', 'events', 'notes'] as $table) {
            $stmt = $db->query("SELECT * FROM {$table} ORDER BY 1");
            $data[$table] = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }
        echo json_encode($data);
    """)


def main():
    marker = 'PERM_' + secrets.token_hex(8)
    clients = []
    try:
        ids = sql(f"""
            $db->beginTransaction();
            $db->exec("INSERT INTO companies (name) VALUES ('{marker}')");
            $company = (int)$db->lastInsertId();
            $hash = password_hash('Password123!', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (company_id, email, password, firstname, lastname, role, must_change_password)
                VALUES (?, '{marker}@example.test', ?, 'Test', 'Permissions', 'CLIENT', 0)");
            $stmt->execute([$company, $hash]);
            $user = (int)$db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO prospects (user_id, company_id, company_name, contact_name, email, phone, event_type, status)
                VALUES (?, ?, '{marker}', 'Test Permissions', '{marker}@example.test', '0102030405', 'Séminaire', 'à contacter')");
            $stmt->execute([$user, $company]);
            $prospect = (int)$db->lastInsertId();
            $db->exec("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva, status)
                VALUES ($prospect, '{marker}.pdf', 100, 20, 'brouillon')");
            $quote = (int)$db->lastInsertId();
            $db->exec("INSERT INTO prestations (devis_id, libelle, montant_ht)
                VALUES ($quote, 'Initiale', 100)");
            $prestation = (int)$db->lastInsertId();
            $db->exec("INSERT INTO events (client_id, company_id, title, start_date, location)
                VALUES ($user,$company,'{marker}','2026-12-01 10:00:00','Paris')");
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
            if admin:
                code, headers, _ = request(client, "send_quote_to_client", {'id': ids['quote'], 'csrf_token': token})
            else:
                code, headers, _ = request(client, f"send_quote_to_client&id={ids['quote']}")
            assert code == 302
            expected_send = f"index.php?action=edit_devis&id={ids['quote']}" if admin else 'index.php?action=' + ('login' if account is None else 'admin_events' if account == ACCOUNTS[1] else 'client_dashboard')
            assert headers['Location'] == expected_send
            if not admin:
                assert snapshot() == before, 'Une action refusée a modifié les données SQL'
            if account == ACCOUNTS[1]:
                for route in ['admin_clients', f"view_client&id={ids['user']}", 'admin_events', f"admin_event_detail&id={ids['event']}"]:
                    code, _, body = request(client, route)
                    assert code == 200 and marker in body, route
                    for action in ['edit_client', 'delete_client', 'admin_upload_image', 'admin_event_update_status', 'edit_devis', 'mongo_logs']:
                        assert 'action='+action not in body, (route, action)
                code, headers, _ = request(client, 'admin_add_note', {'content': marker, 'csrf_token': token})
                assert code == 302 and headers['Location'] == 'index.php?action=admin_events'
                assert snapshot() == before, 'Note globale créée par un employé'
                code, _, _ = request(client, 'admin_add_note', {'event_id': ids['event'], 'content': marker, 'csrf_token': token})
                notes = sql(f"$stmt = $db->query('SELECT content FROM notes WHERE event_id={ids['event']}'); echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));")
                assert notes == [marker]
                sql(f"$db->exec('DELETE FROM notes WHERE event_id={ids['event']}'); echo json_encode(true);")
            label = 'visiteur' if account is None else ('admin' if admin else 'employé' if account == ACCOUNTS[1] else 'client')
            print(f'OK : {label}, routes et droits vérifiés', flush=True)

        prospects = sql(f"$stmt = $db->query('SELECT status FROM prospects WHERE id={ids['prospect']}'); echo json_encode($stmt->fetchColumn());")
        assert prospects == 'converti'
        quote = sql(f"$stmt = $db->query('SELECT status, montant_ht, tva FROM devis WHERE id_devis={ids['quote']}'); echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));")
        assert quote == {'status': 'étude côté client', 'montant_ht': '20.00', 'tva': '4.00'}
        prestations = sql(f"$stmt = $db->query('SELECT libelle, montant_ht FROM prestations WHERE devis_id={ids['quote']}'); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));")
        assert prestations == [{'libelle': 'Ajout test', 'montant_ht': '20.00'}]
        event = sql(f"$stmt = $db->query('SELECT status FROM events WHERE id={ids['event']}'); echo json_encode($stmt->fetchColumn());")
        assert event == 'en cours'
        user_deleted = sql(f"$stmt = $db->query('SELECT is_deleted FROM users WHERE id={ids['user']}'); echo json_encode((int)$stmt->fetchColumn());")
        assert user_deleted == 1
        pdf_path = ROOT / 'storage' / 'devis' / f"{marker}.pdf"
        assert pdf_path.is_file(), 'PDF non généré lors du test admin'
        pdf_path.unlink(missing_ok=True)
        img_path = ROOT / 'public' / 'assets' / 'img' / 'events' / f"event_{ids['event']}.png"
        img_path.unlink(missing_ok=True)
        print('OK : mutations admin exécutées, PDF généré et image uploadée', flush=True)
    finally:
        sql(f"""
            $stmt = $db->prepare('DELETE FROM users WHERE email LIKE ?');
            $stmt->execute(['{marker}%']);
            $stmt = $db->prepare('DELETE FROM prospects WHERE company_name LIKE ?');
            $stmt->execute(['{marker}%']);
            $stmt = $db->prepare('DELETE FROM companies WHERE name LIKE ?');
            $stmt->execute(['{marker}%']);
            $stmt = $db->prepare('DELETE FROM events WHERE title LIKE ?');
            $stmt->execute(['{marker}%']);
            echo json_encode(true);
        """)
        for client in clients:
            try:
                request(client, 'logout')
            except Exception:
                pass


if __name__ == '__main__':
    main()
