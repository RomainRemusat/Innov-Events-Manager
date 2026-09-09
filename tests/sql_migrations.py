"""Vérifie le schéma et les migrations dans un MySQL Docker jetable.

Python standard uniquement. L'option --export teste aussi une sauvegarde locale,
sans copier son contenu dans le dépôt ni se connecter à la base de travail.
"""

import argparse
import hashlib
import pathlib
import secrets
import subprocess
import time


ROOT = pathlib.Path(__file__).resolve().parents[1]
TABLES = ("companies", "users", "prospects", "devis", "events", "prestations", "notes")


def run(*args, data=None, check=True):
    result = subprocess.run(args, input=data, capture_output=True)
    if check and result.returncode:
        raise RuntimeError(result.stderr.decode("utf-8", errors="replace"))
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--export", type=pathlib.Path)
    options = parser.parse_args()
    export_sql = options.export.read_bytes() if options.export else None
    container = "innovevents-sql-test-" + secrets.token_hex(4)
    password = secrets.token_hex(16)
    migrations = sorted((ROOT / "scripts").glob("update_*.sql"))
    schema = (ROOT / "scripts/schema.sql").read_bytes()
    fixtures = (ROOT / "scripts/initialise.sql").read_bytes()

    def sql(database, statement, check=True):
        if isinstance(statement, str):
            statement = statement.encode("utf-8")
        return run(
            "docker", "exec", "-i", container, "mysql", "-uroot", "-p" + password,
            "--default-character-set=utf8mb4", "--batch", "--skip-column-names",
            database, data=statement, check=check,
        )

    def rows(database, query):
        return sql(database, query).stdout.decode("utf-8").splitlines()

    def snapshot(database):
        return {
            table: hashlib.sha256(sql(database, f"SELECT * FROM {table} ORDER BY 1").stdout).hexdigest()
            for table in TABLES
        }

    def structure(database):
        columns = rows(database, f"""
            SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
                   COALESCE(COLUMN_DEFAULT, '<NULL>'), EXTRA, COALESCE(COLLATION_NAME, '')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{database}'
            ORDER BY TABLE_NAME, COLUMN_NAME
        """)
        foreign_keys = rows(database, f"""
            SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                   k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r
              ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
             AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.CONSTRAINT_SCHEMA = '{database}'
            ORDER BY k.TABLE_NAME, k.COLUMN_NAME
        """)
        indexes = {}
        for row in rows(database, f"""
            SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '{database}'
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
        """):
            table, name, non_unique, column = row.split("\t")
            indexes.setdefault((table, name, non_unique), []).append(column)
        # L'export phpMyAdmin et schema.sql donnent des noms différents aux clés.
        logical_indexes = sorted((table, unique, tuple(cols)) for (table, _, unique), cols in indexes.items())
        return columns, foreign_keys, logical_indexes

    created = False
    try:
        run(
            "docker", "run", "-d", "--name", container, "--network", "none",
            "--tmpfs", "/var/lib/mysql", "-e", "MYSQL_ROOT_PASSWORD=" + password,
            "mysql:8.0",
        )
        created = True
        for _ in range(60):
            if sql("mysql", "SELECT 1", check=False).returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError("Le MySQL de test n'a pas démarré en 60 secondes.")

        sql("mysql", "CREATE DATABASE fresh; CREATE DATABASE migrated; CREATE DATABASE invalid;")
        sql("fresh", schema)
        sql("fresh", fixtures)
        assert rows("fresh", "SELECT role, COUNT(*) FROM users GROUP BY role ORDER BY role") == [
            "ADMIN\t1", "CLIENT\t2", "EMPLOYEE\t1"
        ], "Le jeu d'essai doit contenir un administrateur, un employé et deux clientes"
        assert rows("fresh", """
            SELECT COUNT(*) FROM prospects p JOIN users u ON p.user_id = u.id
            JOIN companies c ON p.company_id = c.id
            WHERE u.role <> 'CLIENT' OR NOT (p.company_id <=> u.company_id)
               OR p.email <> u.email OR p.company_name <> c.name
               OR p.contact_name <> CONCAT(u.firstname, ' ', u.lastname);
            SELECT COUNT(*) FROM events e JOIN users u ON e.client_id = u.id
            WHERE u.role <> 'CLIENT' OR NOT (e.company_id <=> u.company_id);
            SELECT COUNT(*) FROM users u WHERE u.role = 'CLIENT'
              AND EXISTS (SELECT 1 FROM prospects p JOIN devis d ON d.id_prospect = p.id WHERE p.user_id = u.id)
              AND EXISTS (SELECT 1 FROM events e WHERE e.client_id = u.id);
        """) == ["0", "0", "2"], "Les deux clientes doivent posséder des dossiers cohérents et distincts"
        print("OK : quatre comptes de démonstration et dossiers clients cohérents", flush=True)
        expected = structure("fresh")
        before = snapshot("fresh")
        assert sql("fresh", schema, check=False).returncode != 0, "schema.sql doit refuser une base déjà initialisée"
        assert snapshot("fresh") == before, "Le second import de schema.sql a modifié les données"
        print("OK : installation vierge et refus de réinitialisation sans perte", flush=True)

        if export_sql is not None:
            sql("migrated", export_sql)
        else:
            # Reproduit les huit écarts de colonnes relevés dans l'export courant.
            sql("migrated", schema)
            sql("migrated", fixtures)
            sql("migrated", """
                ALTER TABLE users MODIFY role VARCHAR(50) NULL DEFAULT 'CLIENT',
                    MODIFY must_change_password TINYINT(1) NULL DEFAULT 0,
                    MODIFY is_deleted TINYINT(1) NULL DEFAULT 0;
                ALTER TABLE prospects MODIFY status VARCHAR(50) NULL DEFAULT 'à contacter';
                ALTER TABLE events MODIFY status VARCHAR(50) NULL DEFAULT 'brouillon';
                ALTER TABLE devis MODIFY status VARCHAR(50) NULL DEFAULT 'brouillon',
                    ALTER montant_ht DROP DEFAULT, ALTER tva DROP DEFAULT;
            """)

        for database in ("fresh", "migrated"):
            before = snapshot(database)
            for pass_number in (1, 2):
                for migration in migrations:
                    sql(database, migration.read_bytes())
                assert snapshot(database) == before, f"Données modifiées dans {database}, passage {pass_number}"
                assert structure(database) == expected, f"Schéma divergent dans {database}, passage {pass_number}"
            print(f"OK : {database}, deux passages des {len(migrations)} migrations, données et contraintes préservées", flush=True)

        sql("invalid", schema)
        sql("invalid", fixtures)
        # Une donnée à qualifier doit bloquer le durcissement, sans être devinée.
        sql("invalid", "ALTER TABLE users MODIFY role VARCHAR(50) NULL DEFAULT 'CLIENT'; UPDATE users SET role = NULL WHERE id = 3;")
        before = snapshot("invalid")
        result = sql("invalid", b"SET SESSION sql_mode = '';\n" + (ROOT / "scripts/update_users.sql").read_bytes(), check=False)
        assert result.returncode != 0, "Un rôle NULL ne doit pas être converti silencieusement"
        assert snapshot("invalid") == before, "La migration en échec a modifié les données"
        print("OK : refus d'un rôle NULL même avec un mode SQL initialement permissif", flush=True)

        # Les clés étrangères et l'unicité de l'email doivent rester actives.
        assert sql("migrated", "START TRANSACTION; INSERT INTO notes (event_id, user_id, content) VALUES (2147483647, 1, 'test');", check=False).returncode != 0
        assert sql("migrated", "START TRANSACTION; INSERT INTO users (email, password, firstname, lastname) SELECT email, password, firstname, lastname FROM users LIMIT 1;", check=False).returncode != 0
        assert rows("migrated", """
            START TRANSACTION;
            INSERT INTO prospects (company_name, contact_name, email, phone, event_type)
                VALUES ('Test SQL', 'Test SQL', 'sql@example.test', '0102030405', 'Autre');
            SELECT status FROM prospects WHERE id = LAST_INSERT_ID();
            INSERT INTO devis (id_prospect, reference_pdf) VALUES (LAST_INSERT_ID(), 'test.pdf');
            SELECT montant_ht, tva, status FROM devis WHERE id_devis = LAST_INSERT_ID();
            INSERT INTO notes (event_id, user_id, content) VALUES (NULL, 1, 'Note globale de test');
            ROLLBACK;
        """) == ["à contacter", "0.00\t0.00\tbrouillon"]
        print("OK : clés étrangères, email unique, valeurs par défaut et note globale", flush=True)
    finally:
        if created:
            run("docker", "rm", "-fv", container)


if __name__ == "__main__":
    main()
