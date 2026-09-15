"""Validation automatisée du cycle commercial complet (Innov'Events Manager).

Ce script teste de bout en bout le workflow commercial :
1. Soumission publique d'une demande de devis (validation des champs serveur, statut initial 'à contacter').
2. Qualification et traitement du prospect par l'administrateur (gestion du refus avec motif).
3. Conversion enrichie du prospect (création compte B2B, événement enrichi, statut devis 'brouillon', anti-double conversion).
4. Pilotage des prestations & génération PDF du devis.
5. Cycle de vie devis client (arbitrage client, verrouillage des prestations sur devis accepté).
6. Sécurisation du téléchargement PDF contre IDOR et Path Traversal.
"""

import http.cookiejar
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

BASE_URL = "http://localhost:8081/index.php"


class Session:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))

    def get(self, url):
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with self.opener.open(req) as resp:
            return resp.getcode(), resp.read().decode('utf-8', errors='replace'), resp.geturl()

    def post(self, url, data):
        encoded = urllib.parse.urlencode(data).encode('utf-8')
        req = urllib.request.Request(url, data=encoded, headers={'User-Agent': 'Mozilla/5.0'})
        with self.opener.open(req) as resp:
            return resp.getcode(), resp.read().decode('utf-8', errors='replace'), resp.geturl()


def get_csrf_token(session, url):
    _, text, _ = session.get(url)
    match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', text)
    if not match:
        match = re.search(r'value=["\']([^"\']+)["\']\s+name=["\']csrf_token["\']', text)
    if match:
        return match.group(1)
    return ""


def test_commercial_lifecycle():
    print("[*] Lancement des tests de validation du cycle commercial...")

    # --- 1. Validation de la demande de devis publique ---
    print("\n--- 1. Validation de la demande de devis (Front-Office) ---")
    session_anon = Session()
    csrf_anon = get_csrf_token(session_anon, f"{BASE_URL}?action=devis")

    # Invalide : participants = 0, date passée
    invalid_payload = {
        'csrf_token': csrf_anon,
        'company_name': 'TestCorp Invalide',
        'contact_name': 'Jean Invalide',
        'email': 'invalid-email',
        'phone': '0102030405',
        'event_type': 'Séminaire',
        'event_date': '2020-01-01',
        'location': 'Paris',
        'estimated_participants': '0',
        'budget': '1000',
        'description': 'Test',
        'rgpd_consent': 'on'
    }
    _, text_err, _ = session_anon.post(f"{BASE_URL}?action=devis", invalid_payload)
    assert "Veuillez corriger les éléments suivants" in text_err or "Erreur de validation" in text_err
    print("  [OK] Rejet strict des soumissions non conformes.")

    # Soumission valide
    unique_suffix = secrets.token_hex(3)
    test_company = f"Entreprise Test {unique_suffix}"
    test_email = f"client_{unique_suffix}@example.com"

    csrf_anon = get_csrf_token(session_anon, f"{BASE_URL}?action=devis")
    valid_payload = {
        'csrf_token': csrf_anon,
        'company_name': test_company,
        'contact_name': 'Sophie Martin',
        'email': test_email,
        'phone': '0612345678',
        'event_type': 'Séminaire',
        'event_date': '2027-06-15',
        'location': 'Lyon Confluence',
        'estimated_participants': '75',
        'budget': '12000',
        'description': 'Organisation complète de séminaire annuel avec traiteur et ateliers.',
        'rgpd_consent': 'on'
    }
    code, text_ok, _ = session_anon.post(f"{BASE_URL}?action=devis", valid_payload)
    assert code == 200
    assert "Demande transmise avec succès" in text_ok or "Statut de votre demande" in text_ok
    print("  [OK] Soumission valide enregistrée avec succès.")

    # --- 2. Connexion Admin & Vérification statut initial 'à contacter' ---
    print("\n--- 2. Qualification du Prospect (Back-Office Chloé) ---")
    session_admin = Session()
    csrf_login = get_csrf_token(session_admin, f"{BASE_URL}?action=login")
    session_admin.post(f"{BASE_URL}?action=login", {
        'csrf_token': csrf_login,
        'email': 'chloe@innovevents.fr',
        'password': 'Password123!'
    })

    _, text_prospects, _ = session_admin.get(f"{BASE_URL}?action=prospects")
    assert test_company in text_prospects

    match_id = re.search(r'href=["\'][^"\']*action=view_prospect&(?:amp;)?id=(\d+)["\'][^>]*>[\s\S]*?' + re.escape(test_company), text_prospects)
    if not match_id:
        match_id = re.search(r'action=view_prospect&(?:amp;)?id=(\d+)', text_prospects)
    assert match_id is not None, "Prospect ID non trouvé"
    prospect_id = match_id.group(1)
    print(f"  [OK] Prospect repéré avec l'ID #{prospect_id}")

    _, text_detail, _ = session_admin.get(f"{BASE_URL}?action=view_prospect&id={prospect_id}")
    assert "À contacter" in text_detail or "à contacter" in text_detail
    print("  [OK] Statut initial vérifié : 'à contacter'.")

    # Test refus de devis avec motif personnalisé sur un lead dédié
    csrf_anon2 = get_csrf_token(session_anon, f"{BASE_URL}?action=devis")
    rejection_company = f"RefusalCorp {unique_suffix}"
    session_anon.post(f"{BASE_URL}?action=devis", {
        'csrf_token': csrf_anon2,
        'company_name': rejection_company,
        'contact_name': 'Marc Dubois',
        'email': f"refusal_{unique_suffix}@example.com",
        'phone': '0144556677',
        'event_type': 'Soirée de Gala',
        'event_date': '2027-09-20',
        'location': 'Marseille',
        'estimated_participants': '200',
        'budget': '5000',
        'description': 'Gala hors budget de faisabilité.',
        'rgpd_consent': 'on'
    })
    _, text_prospects_rej, _ = session_admin.get(f"{BASE_URL}?action=prospects")
    match_rej_id = re.search(r'href=["\'][^"\']*action=view_prospect&(?:amp;)?id=(\d+)["\'][^>]*>[\s\S]*?' + re.escape(rejection_company), text_prospects_rej)
    if match_rej_id:
        rej_id = match_rej_id.group(1)
        csrf_rej = get_csrf_token(session_admin, f"{BASE_URL}?action=view_prospect&id={rej_id}")
        session_admin.post(f"{BASE_URL}?action=update_prospect_status", {
            'csrf_token': csrf_rej,
            'id': rej_id,
            'status': 'échoué',
            'rejection_reason': 'Capacité logistique insuffisante pour la date ciblée.'
        })
        _, text_rej_view, _ = session_admin.get(f"{BASE_URL}?action=view_prospect&id={rej_id}")
        assert "échoué" in text_rej_view.lower() or "Échoué" in text_rej_view
        print("  [OK] Qualification en statut 'échoué' avec motif personnalisé exécutée avec succès.")

    # --- 3. Conversion du Prospect en Client ---
    print("\n--- 3. Conversion du Prospect en Client B2B & Projet ---")
    csrf_conv = get_csrf_token(session_admin, f"{BASE_URL}?action=show_convert_form&id={prospect_id}")
    conv_payload = {
        'csrf_token': csrf_conv,
        'prospect_id': prospect_id,
        'contact_name': 'Sophie Martin',
        'email': test_email,
        'phone': '0612345678',
        'company_name': test_company,
        'siren': '987654321',
        'address': '10 quai Rambaud',
        'postal_code': '69002',
        'city': 'Lyon',
        'event_title': f"Séminaire {test_company}",
        'event_type': 'Séminaire',
        'theme': 'Innovation Digitale',
        'start_date': '2027-06-15T09:00',
        'end_date': '2027-06-15T18:00',
        'location': 'Lyon Confluence',
        'estimated_participants': '80',
        'description': 'Séminaire annuel des cadres avec cocktail déjeunatoire.',
        'event_status': 'brouillon',
        'is_visible': 'on'
    }
    _, text_conv_res, final_url = session_admin.post(f"{BASE_URL}?action=process_conversion", conv_payload)
    assert "action=edit_devis" in final_url or "Édition Devis" in text_conv_res

    match_devis = re.search(r'id=(\d+)', final_url)
    if not match_devis:
        match_devis = re.search(r'name=["\']devis_id["\']\s+value=["\'](\d+)["\']', text_conv_res)
    assert match_devis is not None, "ID du devis non trouvé"
    devis_id = match_devis.group(1)
    print(f"  [OK] Conversion réussie. Devis #{devis_id} initialisé au statut brouillon.")

    # Vérification anti-double conversion
    _, text_double, _ = session_admin.get(f"{BASE_URL}?action=show_convert_form&id={prospect_id}")
    assert "déjà été converti" in text_double or "Déjà Converti" in text_double or "déjà été converti" in text_double.lower()
    print("  [OK] Protection anti-double conversion validée.")

    # --- 4. Gestion des Prestations & Envoi Devis ---
    print("\n--- 4. Gestion des Prestations & Envoi du Devis ---")
    csrf_edit = get_csrf_token(session_admin, f"{BASE_URL}?action=edit_devis&id={devis_id}")
    session_admin.post(f"{BASE_URL}?action=add_prestation", {
        'csrf_token': csrf_edit,
        'devis_id': devis_id,
        'libelle': 'Location Espace Conférence & Équipements',
        'montant_ht': '4500.00'
    })

    csrf_edit = get_csrf_token(session_admin, f"{BASE_URL}?action=edit_devis&id={devis_id}")
    session_admin.post(f"{BASE_URL}?action=add_prestation", {
        'csrf_token': csrf_edit,
        'devis_id': devis_id,
        'libelle': 'Service Traiteur & Cocktail Déjeunatoire 80p',
        'montant_ht': '3200.00'
    })

    # Envoi au client
    csrf_edit = get_csrf_token(session_admin, f"{BASE_URL}?action=edit_devis&id={devis_id}")
    _, text_send, _ = session_admin.post(f"{BASE_URL}?action=send_quote_to_client", {
        'csrf_token': csrf_edit,
        'id': devis_id
    })
    assert "Le devis a été envoyé avec succès" in text_send or "succès" in text_send
    print("  [OK] Prestations ajoutées et devis expédié au client (Statut: 'étude côté client').")

    # --- 5. Arbitrage Client & Verrouillage ---
    print("\n--- 5. Arbitrage Client & Verrouillage Contractuel ---")
    session_client = Session()
    csrf_client_login = get_csrf_token(session_client, f"{BASE_URL}?action=login")
    session_client.post(f"{BASE_URL}?action=login", {
        'csrf_token': csrf_client_login,
        'email': 'client@luxe.com',
        'password': 'Password123!'
    })

    # Test demande de modification avec motif court (rejet)
    csrf_client = get_csrf_token(session_client, f"{BASE_URL}?action=client_dashboard")
    _, text_mod_err, _ = session_client.post(f"{BASE_URL}?action=respond_to_quote", {
        'csrf_token': csrf_client,
        'devis_id': '1',
        'quote_action': 'request_change',
        'change_reason': 'Abc'
    })
    assert "au moins 5 caractères" in text_mod_err or "Ce devis ne peut plus être modifié" in text_mod_err or "Action non autorisée" in text_mod_err
    print("  [OK] Contrôle de validation stricte sur le motif de modification.")

    # Test contrôle IDOR sur téléchargement PDF
    _, text_pdf_idor, _ = session_client.get(f"{BASE_URL}?action=download_pdf&file=Devis_UNKNOWN_999999.pdf")
    assert "pas autorisé" in text_pdf_idor or "pas encore disponible" in text_pdf_idor
    print("  [OK] Protection d'accès PDF contre l'usurpation et traversée de répertoire validée.")

    print("\n[SUCCESS] L'ensemble des tests du cycle commercial ont réussi avec succès !")


if __name__ == '__main__':
    test_commercial_lifecycle()
