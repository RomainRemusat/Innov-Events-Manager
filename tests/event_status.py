"""Statuts événement : formulaires, autorisations et journalisation sur Docker local.

Un dossier temporaire est créé puis supprimé ; aucun email n'est envoyé.
Exécution : python -B tests/event_status.py
"""
import http.cookiejar
import re
import secrets
import urllib.request

from login_logging import ACCOUNTS, NoRedirect, php, request


def main():
    marker = 'status_' + secrets.token_hex(8)
    prefix = "require 'src/config/Database.php'; $db = Database::getInstance(); "
    user_id = event_id = None
    sessions = []
    try:
        user_id, event_id, prospect_id, quote_id = php(prefix + f"""
            $db->beginTransaction();
            $db->exec("INSERT INTO users (email, password, firstname, lastname) VALUES ('{marker}@example.test', 'unused', 'Test', 'Status')");
            $user = (int)$db->lastInsertId();
            $db->exec("INSERT INTO prospects (user_id, company_name, contact_name, email, phone, event_type)
                VALUES ($user, 'Test', 'Test', '{marker}@example.test', '0000000000', 'Autre')");
            $prospect = (int)$db->lastInsertId();
            $db->exec("INSERT INTO events (client_id, title, start_date, location) VALUES ($user, '{marker}', '2027-01-01', 'Test')");
            $event = (int)$db->lastInsertId();
            $db->exec("INSERT INTO devis (id_prospect, event_id, reference_pdf, montant_ht, tva, status)
                VALUES ($prospect, $event, '{marker}.pdf', 10, 20, 'accepté')");
            $quote = (int)$db->lastInsertId();
            $db->commit();
            echo json_encode([$user, $event, $prospect, $quote]);
        """)

        for account in [None, ACCOUNTS[2], ACCOUNTS[1], ACCOUNTS[0]]:
            client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
            sessions.append(client)
            _, _, body = request(client, 'login')
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            if account:
                assert request(client, 'login', dict(email=account[1], password='Password123!', csrf_token=token))[0] == 302
            if account != ACCOUNTS[0]:
                assert request(client, 'admin_event_update_status', dict(event_id=event_id, status='accepté', csrf_token=token))[0] == 302
                assert php(prefix + f"echo json_encode($db->query('SELECT status FROM events WHERE id={event_id}')->fetchColumn());") == 'brouillon'
                if account == ACCOUNTS[1]:
                    assert 'name="status"' not in request(client, f'admin_event_detail&id={event_id}')[2]
                continue

            for route in ['admin_events', f'admin_event_detail&id={event_id}', f'show_convert_form&id={prospect_id}']:
                code, _, body = request(client, route)
                assert code == 200 and not any(s in body for s in ['Fatal error', 'Warning:', 'TypeError'])
                for status in ['brouillon', 'planifié', 'accepté', 'terminé', 'annulé']:
                    assert f'value="{status}"' in body, (route, status)
                assert 'value="annuler"' not in body
            assert 'Jeton CSRF' in request(client, 'admin_event_update_status', dict(event_id=event_id, status='accepté', csrf_token='invalid'))[2]
            expected = 'brouillon'
            for i, status in enumerate(['planifié', 'accepté', 'en cours', 'terminé', 'annuler']):
                route = ['admin_event_update_status', 'admin_update_event_status'][i % 2]
                code, headers, _ = request(client, route, dict(event_id=event_id, status=status, csrf_token=token))
                assert code == 302 and headers['Location'].endswith(f'admin_event_detail&id={event_id}')
                expected = 'annulé' if status == 'annuler' else status
                assert php(prefix + f"echo json_encode($db->query('SELECT status FROM events WHERE id={event_id}')->fetchColumn());") == expected
            request(client, 'admin_event_update_status', dict(event_id=event_id, status='inconnu', csrf_token=token))
            assert 'statut non autorisé' in request(client, f'admin_event_detail&id={event_id}')[2]
            php(prefix + f"$db->exec(\"UPDATE devis SET status='refusé' WHERE id_devis={quote_id}\"); echo json_encode(true);")
            request(client, 'admin_event_update_status', dict(event_id=event_id, status='en cours', csrf_token=token))
            assert 'Statut non modifié' in request(client, f'admin_event_detail&id={event_id}')[2]
            assert php(prefix + f"echo json_encode($db->query('SELECT status FROM events WHERE id={event_id}')->fetchColumn());") == 'annulé'

        logs = php(rf"""
            $manager = new MongoDB\Driver\Manager($_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017');
            $query = new MongoDB\Driver\Query(['type_action' => 'MODIFICATION_STATUT_EVENEMENT', 'details.event_id' => {event_id}], ['sort' => ['Horodatage' => 1]]);
            $logs = [];
            foreach ($manager->executeQuery(($_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql') . '.logs', $query) as $log)
                $logs[] = [$log->details->old_status, $log->details->new_status];
            echo json_encode($logs);
        """)
        assert logs == [['brouillon', 'planifié'], ['planifié', 'accepté'], ['accepté', 'en cours'],
                        ['en cours', 'terminé'], ['terminé', 'annulé']], logs
        print('OK : formulaires, droits, CSRF, deux routes, refus métier et anciens/nouveaux statuts journalisés', flush=True)
    finally:
        if user_id is not None:
            php(prefix + rf"""
                $stmt = $db->prepare('DELETE FROM users WHERE id=? AND email=?');
                $stmt->execute([{user_id}, '{marker}@example.test']);
                $manager = new MongoDB\Driver\Manager($_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017');
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['details.event_id' => {event_id}], ['limit' => 0]);
                $manager->executeBulkWrite(($_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql') . '.logs', $bulk);
                echo json_encode(true);
            """)
        for client in sessions:
            request(client, 'logout')


if __name__ == '__main__':
    main()
