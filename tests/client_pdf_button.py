"""Bouton PDF côté client : Docker local, données temporaires, aucun email.

Exécution : python -B tests/client_pdf_button.py
"""
import http.cookiejar
import re
import secrets
import urllib.request

from login_logging import NoRedirect, URL, php, request


def main():
    marker = 'pdf_button_' + secrets.token_hex(8)
    prefix = "require 'src/config/Database.php'; $db = Database::getInstance(); "
    user_id = None
    try:
        user_id, quote_id = php(prefix + f"""
            $db->beginTransaction();
            $stmt = $db->prepare('INSERT INTO users (email, password, firstname, lastname, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(['{marker}@example.test', password_hash('Password123!', PASSWORD_BCRYPT), 'PDF', 'Test', 'CLIENT']);
            $userId = (int)$db->lastInsertId();
            $db->exec("INSERT INTO prospects (user_id, company_name, contact_name, email, phone, event_type)
                VALUES ($userId, 'Test', 'Test', '{marker}@example.test', '0000000000', 'Autre')");
            $prospectId = (int)$db->lastInsertId();
            $db->exec("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva, status)
                VALUES ($prospectId, '{marker}.pdf', 10, 20, 'accepté')");
            $quoteId = (int)$db->lastInsertId();
            $db->commit();
            echo json_encode([$userId, $quoteId]);
        """)
        client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        _, _, body = request(client, 'login')
        token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
        assert request(client, 'login', dict(email=marker+'@example.test',
                       password='Password123!', csrf_token=token))[0] == 302

        def button_visible():
            code, _, body = request(client, 'client_dashboard')
            assert code == 200 and not any(s in body for s in ['Warning:', 'Fatal error', 'TypeError'])
            return f'action=download_pdf&file={marker}.pdf' in body

        assert not button_visible(), 'Fichier absent : bouton masqué'
        php(f"file_put_contents('storage/devis/{marker}.pdf', '%PDF-1.4 test'); echo json_encode(true);")
        assert button_visible(), 'PDF présent : bouton visible'
        with client.open(URL + f'download_pdf&file={marker}.pdf') as response:
            assert response.headers.get_content_type() == 'application/pdf'
            assert response.read() == b'%PDF-1.4 test'
        php(prefix + f"$db->exec(\"UPDATE devis SET status = 'brouillon' WHERE id_devis = {quote_id}\"); echo json_encode(true);")
        assert not button_visible(), 'Brouillon : bouton masqué même si le fichier existe'
        php(prefix + f"$db->exec(\"UPDATE devis SET status = 'accepté', reference_pdf = '' WHERE id_devis = {quote_id}\"); echo json_encode(true);")
        assert not button_visible(), 'Référence vide : bouton masqué'
        print('OK : PDF absent, présent, téléchargement, brouillon et référence vide', flush=True)
    finally:
        if user_id is not None:
            php(prefix + rf"""
                $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND email = ?');
                $stmt->execute([{user_id}, '{marker}@example.test']);
                if (is_file('storage/devis/{marker}.pdf')) unlink('storage/devis/{marker}.pdf');
                $manager = new MongoDB\Driver\Manager($_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017');
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->delete(['id_utilisateur' => {user_id}], ['limit' => 0]);
                $manager->executeBulkWrite(($_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql') . '.logs', $bulk);
                echo json_encode(true);
            """)


if __name__ == '__main__':
    main()
