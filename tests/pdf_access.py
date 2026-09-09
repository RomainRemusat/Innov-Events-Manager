"""Accès directs au PDF : administrateur, propriétaire, autre client et employé.

Docker local et comptes de démonstration requis. Lecture seule des devis existants ;
les connexions produisent leurs journaux habituels. Aucun mail ni PDF sur disque.
"""

import http.cookiejar
import re
import urllib.error
import urllib.request

from login_logging import ACCOUNTS, NoRedirect, URL, php, request


def pdf_request(client, id_):
    try:
        response = client.open(URL + f"generate_pdf&id={id_}", timeout=60)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        body = response.read()
        assert not any(marker in body for marker in (b"Fatal error", b"Warning:", b"TypeError"))
        return response.code, response.headers, body


def main():
    quotes = php("""
        require 'src/config/Database.php';
        $db = Database::getInstance();
        $quotes = $db->query('SELECT p.user_id, MIN(d.id_devis) AS id
            FROM devis d JOIN prospects p ON p.id = d.id_prospect
            WHERE p.user_id IN (3, 4) GROUP BY p.user_id')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($quotes);
    """)
    assert {int(q['user_id']) for q in quotes} == {3, 4}, 'Un devis par cliente de démonstration est nécessaire'
    clients = []
    try:
        for account in [None, ACCOUNTS[1], ACCOUNTS[2], ACCOUNTS[3], ACCOUNTS[0]]:
            client = urllib.request.build_opener(
                urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
            )
            clients.append(client)
            if account:
                code, _, body = request(client, 'login')
                assert code == 200
                token = re.search(r'name="csrf_token"\s+value="([^"]+)"', body).group(1)
                code, headers, _ = request(client, 'login', {
                    'email': account[1], 'password': 'Password123!', 'csrf_token': token,
                })
                assert code == 302 and headers['Location'] == 'index.php?action=' + account[2]

            for quote in quotes + [{'id': 0, 'user_id': None}, {'id': -1, 'user_id': None}]:
                code, headers, body = pdf_request(client, quote['id'])
                allowed = quote['user_id'] is not None and account and (
                    account == ACCOUNTS[0] or account[0] == int(quote['user_id'])
                )
                if allowed:
                    assert code == 200 and headers.get_content_type() == 'application/pdf'
                    assert body.startswith(b'%PDF-') and b'%%EOF' in body[-100:], 'PDF invalide'
                else:
                    expected = 302 if account is None else (403 if account == ACCOUNTS[1] else 404)
                    assert code == expected, f"Accès incorrect pour {account} au devis {quote['id']} : {code}"
                    assert b'%PDF-' not in body and headers.get_content_type() != 'application/pdf'
                    if account is None:
                        assert headers['Location'] == 'index.php?action=login'
                    elif expected == 404:
                        assert body == b'Document introuvable.', 'Réponse différente selon le propriétaire'
            print(f"OK : {account[1] if account else 'visiteur'}, autorisations et contenu PDF vérifiés", flush=True)
    finally:
        for client in clients:
            request(client, 'logout')


if __name__ == '__main__':
    main()
