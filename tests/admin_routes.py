"""Routes clients et journaux sur Docker local ; client temporaire nettoyé en fin de test.

Aucun email envoyé. Les journaux normaux de connexion/suppression sont conservés.
Exécution : python -B tests/admin_routes.py
"""

import http.cookiejar
import re
import secrets
import urllib.request

from login_logging import ACCOUNTS, NoRedirect, php, request


def sql(code):
    return php("require 'src/config/Database.php'; $db = Database::getInstance(); " + code)


def checked_request(client, action, data=None):
    result = request(client, action, data)
    assert not any(error in result[2] for error in (
        'Fatal error', 'Warning:', 'Deprecated:', 'TypeError', 'ArgumentCountError'
    )), f"Erreur PHP sur {action}"
    return result


def main():
    marker = 'routes_' + secrets.token_hex(8)
    sessions = []
    user_id = None
    try:
        user_id = sql(f"""
            $stmt = $db->prepare('INSERT INTO users (email, password, firstname, lastname, role)
                VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(['{marker}@example.test', password_hash('Password123!', PASSWORD_BCRYPT),
                '{marker}', 'Routes', 'CLIENT']);
            echo json_encode((int)$db->lastInsertId());
        """)
        for account in [None, ACCOUNTS[2], ACCOUNTS[1], ACCOUNTS[0]]:
            client = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
            sessions.append(client)
            _, _, body = checked_request(client, 'login')
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            if account:
                code, _, _ = checked_request(client, 'login', {
                    'email': account[1], 'password': 'Password123!', 'csrf_token': token})
                assert code == 302
            admin = account == ACCOUNTS[0]
            staff = admin or account == ACCOUNTS[1]
            for route, allowed, expected_text in [
                ('admin_clients', staff, marker),
                ('clients', staff, marker),
                (f'view_client&id={user_id}', staff, marker),
                (f'edit_client&id={user_id}', admin, marker),
                ('mongo_logs', admin, "Journal d'Audit Système"),
            ]:
                code, headers, body = checked_request(client, route)
                assert code == (200 if allowed else 302), route
                if allowed:
                    assert expected_text in body, route
                else:
                    assert 'Location' in headers and marker not in body, route

            for route in ['edit_client', 'update_client', 'delete_client']:
                payload = dict(client_id=user_id, firstname=marker + '_' + route,
                               lastname='Routes', email=marker+'@example.test')
                if admin:
                    _, _, body = checked_request(client, route, dict(payload, csrf_token='invalid'))
                    assert 'Jeton CSRF' in body, route
                code, headers, _ = checked_request(client, route, dict(payload, csrf_token=token))
                assert code == 302, route
                state = sql(f"echo json_encode($db->query('SELECT firstname, is_deleted FROM users WHERE id={user_id}')->fetch());")
                if admin:
                    expected_name = marker + '_' + ('update_client' if route == 'delete_client' else route)
                    assert state['firstname'] == expected_name, route
                    assert int(state['is_deleted']) == (1 if route == 'delete_client' else 0), route
                    destination = 'admin_clients' if route == 'delete_client' else f'view_client&id={user_id}'
                    assert headers['Location'] == 'index.php?action=' + destination
                else:
                    assert state['firstname'] == marker and int(state['is_deleted']) == 0, route
            print('OK : ' + (account[1] if account else 'visiteur') + ', routes et permissions', flush=True)
    finally:
        if user_id is not None:
            sql(f"$stmt = $db->prepare('DELETE FROM users WHERE id = ? AND email = ?'); "
                f"$stmt->execute([{user_id}, '{marker}@example.test']); echo json_encode(true);")
        for client in sessions:
            checked_request(client, 'logout')


if __name__ == '__main__':
    main()
