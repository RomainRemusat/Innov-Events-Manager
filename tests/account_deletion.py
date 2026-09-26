"""Suppression de comptes temporaires sur Docker local, sans envoi d'email.

Exécution : python -B tests/account_deletion.py
"""
import http.cookiejar
import re
import secrets
import urllib.request

from login_logging import NoRedirect, php, request


def main():
    marker = 'delete_' + secrets.token_hex(8)
    prefix = "require 'src/services/AccountDeletionService.php'; $db = Database::getInstance(); "
    mongo = r"""
        $manager = new MongoDB\Driver\Manager($_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017');
        $namespace = ($_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql') . '.logs';
    """
    ids = []
    company = None
    try:
        company, ids, prospect, quote, event = php(prefix + f"""
            $db->beginTransaction();
            $db->exec("INSERT INTO companies (name) VALUES ('{marker}')");
            $company = (int)$db->lastInsertId();
            $ids = [];
            foreach (['CLIENT', 'CLIENT', 'EMPLOYEE'] as $i => $role) {{
                $stmt = $db->prepare('INSERT INTO users (company_id, email, password, firstname, lastname, role) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$company, '{marker}' . $i . '@example.test', password_hash('Password123!', PASSWORD_BCRYPT), '{marker}', 'Test', $role]);
                $ids[] = (int)$db->lastInsertId();
            }}
            $stmt = $db->prepare("INSERT INTO prospects (user_id, company_id, company_name, contact_name, email, phone, event_type) VALUES (?, ?, ?, 'Test', ?, '0000000000', 'Autre')");
            $stmt->execute([$ids[0], $company, '{marker}', '{marker}0@example.test']);
            $prospect = (int)$db->lastInsertId();
            $db->exec("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva) VALUES ($prospect, '{marker}.pdf', 10, 20)");
            $quote = (int)$db->lastInsertId();
            $db->exec("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES ($quote, 'Test', 10)");
            $stmt = $db->prepare("INSERT INTO events (client_id, company_id, title, start_date, location, image_path) VALUES (?, ?, ?, NOW(), 'Test', ?)");
            $stmt->execute([$ids[0], $company, '{marker}', 'uploads/events/{marker}.png']);
            $event = (int)$db->lastInsertId();
            $stmt->execute([$ids[0], $company, '{marker}', 'uploads/events/{marker}_shared.png']);
            $stmt->execute([$ids[1], $company, '{marker}', 'uploads/events/{marker}_shared.png']);
            $db->exec("INSERT INTO notes (event_id, user_id, content) VALUES ($event, {{$ids[2]}}, 'Test')");
            $db->commit();
            echo json_encode([$company, $ids, $prospect, $quote, $event]);
        """)
        php(prefix + f"""
            file_put_contents('storage/devis/{marker}.pdf', 'test');
            file_put_contents('public/uploads/events/{marker}.png', 'test');
            file_put_contents('public/uploads/events/{marker}_shared.png', 'test');
            $log = new Log();
            foreach ([['client_id' => {ids[0]}], ['prospect_id' => '{prospect}'],
                ['devis_id' => {quote}], ['event_id' => '{event}'],
                ['email' => strtoupper('{marker}0@example.test')], ['recipient' => '{marker}0@example.test']] as $details) {{
                if (!$log->addLog('{marker}', {ids[2]}, $details)) throw new RuntimeException('Fixture MongoDB');
            }}
            $log->addLog('{marker}', {ids[1]}, ['message' => 'à conserver']);
            echo json_encode(true);
        """)

        def client(email=None):
            opener = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
            _, _, body = request(opener, 'login')
            token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
            if email:
                assert request(opener, 'login', dict(email=email, password='Password123!', csrf_token=token))[0] == 302
            return opener, token

        visitor, token = client()
        assert request(visitor, 'client_delete_account', dict(csrf_token=token))[1]['Location'].endswith('=login')
        staff, token = client(f'{marker}2@example.test')
        assert request(staff, 'client_delete_account', dict(csrf_token=token))[0] == 302
        owner, token = client(f'{marker}0@example.test')
        second_session, _ = client(f'{marker}0@example.test')
        assert 'action=client_delete_account' in request(owner, 'client_profile')[2]
        assert request(owner, 'client_delete_account')[1]['Location'].endswith('=client_profile')
        assert 'Jeton CSRF' in request(owner, 'client_delete_account', dict(csrf_token='invalid'))[2]
        assert php(prefix + f"echo json_encode((bool)(new User())->findById({ids[0]}));")
        print('OK : formulaire, méthode POST, CSRF et permissions', flush=True)

        # Panne MongoDB simulée dans ce seul processus : SQL et fichiers restent présents.
        assert php(prefix + f"""
            $_ENV['MONGO_URI'] = 'mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=100';
            $result = (new AccountDeletionService())->delete({ids[0]});
            echo json_encode(!$result && (bool)(new User())->findById({ids[0]})
                && file_exists('storage/devis/{marker}.pdf'));
        """)
        # Un chemin sortant du dossier autorisé bloque sans effacer le fichier cible.
        php(prefix + f"$db->exec(\"UPDATE events SET image_path = '../storage/devis/{marker}.pdf' WHERE id = {event}\"); echo json_encode(true);")
        assert request(owner, 'client_delete_account', dict(csrf_token=token, confirm_delete='1'))[1]['Location'].endswith('=client_profile')
        assert "certains éléments ont pu être effacés" in request(owner, 'client_profile')[2]
        assert php(prefix + f"echo json_encode((bool)(new User())->findById({ids[0]}) && file_exists('storage/devis/{marker}.pdf'));")
        php(prefix + f"$db->exec(\"UPDATE events SET image_path = 'uploads/events/{marker}.png' WHERE id = {event}\"); echo json_encode(true);")
        print('OK : échecs contrôlés, rollback SQL et session conservée', flush=True)

        code, headers, body = request(owner, 'client_delete_account', dict(csrf_token=token, user_id=ids[1], confirm_delete='1'))
        assert code == 302 and headers['Location'].endswith('=login'), body
        assert any('PHPSESSID=deleted' in value for value in headers.get_all('Set-Cookie', []))
        assert request(second_session, 'client_profile')[1]['Location'].endswith('=login')
        assert php(prefix + mongo + rf"""
            $ok = !(new User())->findById({ids[0]}) && (bool)(new User())->findById({ids[1]});
            foreach (['prospects' => 'id = {prospect}', 'devis' => 'id_devis = {quote}',
                'prestations' => 'devis_id = {quote}', 'events' => 'client_id = {ids[0]}',
                'notes' => 'event_id = {event}'] as $table => $where) {{
                $ok = $ok && (int)$db->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn() === 0;
            }}
            $ok = $ok && (int)$db->query('SELECT COUNT(*) FROM companies WHERE id = {company}')->fetchColumn() === 1;
            $docs = $manager->executeQuery($namespace, new MongoDB\Driver\Query(['type_action' => strtoupper('{marker}')]))->toArray();
            $ok = $ok && count($docs) === 1 && $docs[0]->id_utilisateur === {ids[1]};
            $docs = $manager->executeQuery($namespace, new MongoDB\Driver\Query(['id_utilisateur' => {ids[0]}]))->toArray();
            echo json_encode($ok && count($docs) === 0 && !file_exists('storage/devis/{marker}.pdf')
                && !file_exists('public/uploads/events/{marker}.png')
                && file_exists('public/uploads/events/{marker}_shared.png')
                && !(new User())->deleteAccount({ids[0]}));
        """)
        print('OK : cascades SQL, fichiers, journaux, isolation des clients et invalidation des sessions', flush=True)
    finally:
        if company is not None:
            php(prefix + mongo + rf"""
                $stmt = $db->prepare('DELETE FROM companies WHERE id = ? AND name = ?');
                $stmt->execute([{company}, '{marker}']);
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['$or' => [['type_action' => strtoupper('{marker}')],
                    ['id_utilisateur' => ['$in' => [{','.join(map(str, ids))}]]]]], ['limit' => 0]);
                $manager->executeBulkWrite($namespace, $bulk);
                foreach (['storage/devis/{marker}.pdf', 'public/uploads/events/{marker}.png',
                    'public/uploads/events/{marker}_shared.png'] as $file) {{
                    if (is_file($file)) unlink($file);
                }}
                echo json_encode(true);
            """)


if __name__ == '__main__':
    main()
