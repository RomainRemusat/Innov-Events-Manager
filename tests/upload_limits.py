"""Vérifie les limites HTTP des illustrations ; nettoie uniquement son événement temporaire."""
import binascii
import http.cookiejar
import re
import secrets
import struct
import urllib.error
import urllib.request
import zlib

from login_logging import NoRedirect, URL, php, request


def main():
    marker = 'UPLOAD_' + secrets.token_hex(8)
    prefix = "require 'src/config/Database.php'; $db=Database::getInstance(); "
    event = php(prefix + f"$db->exec(\"INSERT INTO events(client_id,title,start_date,location) VALUES(3,'{marker}','2027-01-01','Paris')\"); echo json_encode((int)$db->lastInsertId());")
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
    try:
        _, _, page = request(client, 'login')
        token = re.search(r'name="csrf_token"\s+value="([^"]+)"', page).group(1)
        assert request(client, 'login', dict(email='chloe@innovevents.fr', password='Password123!', csrf_token=token))[0] == 302

        def chunk(kind, data):
            return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', binascii.crc32(kind + data))

        # Image de 16 millions de pixels ; sa résolution ne doit pas provoquer de refus.
        png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!IIBBBBB', 4096, 4096, 8, 0, 0, 0, 0))
        png += chunk(b'IDAT', zlib.compress(b'\0' * (4097 * 4096)))
        for size, accepted in [(3 * 1024**2, True), (5 * 1024**2, True), (6 * 1024**2, False), (9 * 1024**2, False)]:
            # Bloc auxiliaire PNG : contrôle du poids sans changer les dimensions.
            content = png + chunk(b'tEXt', b'Comment\0' + b'a' * (size - len(png) - 32)) + chunk(b'IEND', b'')
            body = b''
            for field, value in dict(event_id=event, csrf_token=token).items():
                body += f'--{marker}\r\nContent-Disposition: form-data; name="{field}"\r\n\r\n{value}\r\n'.encode()
            body += f'--{marker}\r\nContent-Disposition: form-data; name="event_image"; filename="image.png"\r\nContent-Type: image/png\r\n\r\n'.encode()
            body += content + f'\r\n--{marker}--\r\n'.encode()
            before = php(prefix + f"echo json_encode($db->query('SELECT image_path FROM events WHERE id={event}')->fetchColumn());")
            upload = urllib.request.Request(URL + 'admin_upload_image', data=body, headers={'Content-Type': 'multipart/form-data; boundary=' + marker})
            try:
                response = client.open(upload, timeout=45)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                code, feedback = response.code, response.read().decode()
            if code == 302:
                _, _, feedback = request(client, f'admin_event_detail&id={event}')
            assert code == (413 if size > 8 * 1024**2 else 302), (size, code)
            if accepted:
                assert 'Illustration mise à jour.' in feedback, size
            else:
                assert '5 Mo' in feedback and 'CSRF' not in feedback, feedback
                assert php(prefix + f"echo json_encode($db->query('SELECT image_path FROM events WHERE id={event}')->fetchColumn());") == before
        print('OK : images 4096 × 4096 acceptées à 3 et 5 Mo ; refus explicites à 6 et 9 Mo sans modifier l’illustration.')
    finally:
        php(prefix + f"require 'src/services/EventManagementService.php'; (new EventManagementService())->delete({event},1); echo json_encode(true);")
        request(client, 'logout')


if __name__ == '__main__':
    main()
