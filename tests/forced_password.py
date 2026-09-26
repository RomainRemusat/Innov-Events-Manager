"""Restriction du mot de passe temporaire sur Docker local, sans email.

Comptes temporaires uniquement ; données et journaux nettoyés en fin de test.
Exécution : python -B tests/forced_password.py
"""
import http.cookiejar
import re
import secrets
import urllib.request

from login_logging import NoRedirect, php, request


def main():
    marker = 'forced_' + secrets.token_hex(8)
    prefix = "require 'src/config/Database.php'; $db = Database::getInstance(); "
    ids = []
    try:
        for role in ['CLIENT', 'EMPLOYEE', 'ADMIN']:
            user_id = php(prefix + f"""
                $stmt = $db->prepare('INSERT INTO users (email, password, firstname, lastname, role, must_change_password) VALUES (?, ?, ?, ?, ?, 1)');
                $stmt->execute(['{marker}_{role}@example.test', password_hash('Temporary123!', PASSWORD_BCRYPT), 'Test', 'Password', '{role}']);
                echo json_encode((int)$db->lastInsertId());
            """)
            ids.append(user_id)

            def login(password='Temporary123!'):
                client = urllib.request.build_opener(
                    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
                _, _, body = request(client, 'login')
                token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
                code, headers, _ = request(client, 'login', dict(email=f'{marker}_{role}@example.test',
                    password=password, csrf_token=token))
                assert code == 302
                return client, token, headers['Location']

            client, token, destination = login()
            assert destination.endswith('=force_password_change')
            routes = ['client_dashboard', 'client_profile', 'dashboard', 'admin_events', 'mongo_logs',
                      'generate_pdf&id=1', 'download_pdf&file=test.pdf', 'download_devis&id=1']
            for route in routes:
                code, headers, body = request(client, route)
                assert code == 302 and headers['Location'].endswith('=force_password_change'), route
                assert '%PDF-' not in body and 'Fatal error' not in body
            for route in ['client_delete_account', 'admin_update_event_status', 'submit_quote']:
                assert request(client, route, dict(csrf_token=token))[1]['Location'].endswith('=force_password_change'), route
            # L'exception autorise l'écran et sa soumission, pas les autres actions protégées.
            assert request(client, 'force_password_change')[0] == 200
            assert 'Jeton CSRF' in request(client, 'update_forced_password', dict(
                csrf_token='invalid', new_password='Personal123!', confirm_password='Personal123!'))[2]
            for password, confirmation, error in [
                ('abc', 'abc', 'critères'), ('Personal123!', 'different', 'correspondent'),
                ('Temporary123!', 'Temporary123!', 'différent'),
            ]:
                request(client, 'update_forced_password', dict(csrf_token=token,
                    new_password=password, confirm_password=confirmation))
                assert error in request(client, 'force_password_change')[2]
            assert php(prefix + f"echo json_encode((int)$db->query('SELECT must_change_password FROM users WHERE id={user_id}')->fetchColumn());") == 1
            assert request(client, 'update_forced_password', dict(csrf_token=token,
                new_password='Personal123!', confirm_password='Personal123!'))[1]['Location'].endswith('=login')
            assert request(client, 'client_profile')[1]['Location'].endswith('=login'), 'Session encore authentifiée'
            assert 'personnalisé' in request(client, 'login')[2]
            assert php(prefix + f"""
                $user = $db->query('SELECT * FROM users WHERE id={user_id}')->fetch();
                echo json_encode((int)$user['must_change_password'] === 0 && password_verify('Personal123!', $user['password']));
            """)
            client, token, destination = login('Personal123!')
            assert destination.endswith('=client_dashboard' if role == 'CLIENT' else '=dashboard')

            # Une restriction ajoutée en SQL doit affecter une session déjà ouverte.
            php(prefix + f"$db->exec('UPDATE users SET must_change_password=1 WHERE id={user_id}'); echo json_encode(true);")
            assert request(client, 'generate_pdf&id=1')[1]['Location'].endswith('=force_password_change')
            assert request(client, 'logout')[1]['Location'].endswith('=login')
            client, _, _ = login('Personal123!')
            # La désactivation prime même sur l'exception de changement de mot de passe.
            php(prefix + f"$db->exec('UPDATE users SET is_deleted=1 WHERE id={user_id}'); echo json_encode(true);")
            assert request(client, 'force_password_change')[1]['Location'].endswith('=login')

            php(prefix + f"$db->exec('UPDATE users SET is_deleted=0, must_change_password=0 WHERE id={user_id}'); echo json_encode(true);")
            client, _, _ = login('Personal123!')
            php(prefix + f"$db->exec(\"UPDATE users SET role='EMPLOYEE' WHERE id={user_id}\"); echo json_encode(true);")
            assert request(client, 'generate_pdf&id=1')[0] == 403, 'Rôle SQL non répercuté sur le PDF'
            assert request(client, 'mongo_logs')[1]['Location'].endswith('=admin_events')
            php(prefix + f"$db->exec('UPDATE users SET is_deleted=1 WHERE id={user_id}'); echo json_encode(true);")
            assert request(client, 'download_pdf&file=test.pdf')[1]['Location'].endswith('=login')
            print(f'OK : {role}, restriction, validation, reconnexion, révocation et rôle SQL', flush=True)
    finally:
        if ids:
            php(prefix + rf"""
                $ids = [{','.join(map(str, ids))}];
                $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND firstname = ? AND email LIKE ?');
                foreach ($ids as $id) $stmt->execute([$id, 'Test', '{marker}%']);
                $manager = new MongoDB\Driver\Manager($_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017');
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['id_utilisateur' => ['$in' => $ids]], ['limit' => 0]);
                $manager->executeBulkWrite(($_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql') . '.logs', $bulk);
                echo json_encode(true);
            """)


if __name__ == '__main__':
    main()
