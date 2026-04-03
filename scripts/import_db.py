"""
Importar todas las migraciones SQL a Railway MySQL.
Uso: python scripts/import_db.py
"""
import pymysql
import os
import sys

HOST = "junction.proxy.rlwy.net"
PORT = 17355
USER = "root"
PASSWORD = "rWIWQKzdtaDaaMIbJfZhPVVsZAOGDlco"
DB = "railway"

SQL_DIR = os.path.join(os.path.dirname(__file__), '..', 'sql')

# Orden de migraciones
MIGRATIONS = [
    'soporte_tables.sql',
    'migration_advanced.sql',
    'migration_ux.sql',
    'migration_modular.sql',
    'migration_versionado.sql',
    'migration_funnel.sql',
    'datos_demo.sql',
]

def run():
    print(f"Conectando a {HOST}:{PORT}...")
    conn = pymysql.connect(
        host=HOST, port=PORT, user=USER, password=PASSWORD, database=DB,
        charset='utf8mb4', autocommit=True
    )
    cursor = conn.cursor()

    # Verificar conexión
    cursor.execute("SELECT VERSION()")
    version = cursor.fetchone()[0]
    print(f"MySQL {version} — Conectado OK\n")

    for filename in MIGRATIONS:
        filepath = os.path.join(SQL_DIR, filename)
        if not os.path.exists(filepath):
            print(f"  [SKIP] {filename} — no encontrado")
            continue

        print(f"  Importando {filename}...")
        with open(filepath, 'r', encoding='utf-8') as f:
            sql = f.read()

        # Reemplazar USE dbsolventas17 por USE railway
        sql = sql.replace('USE dbsolventas17;', f'USE {DB};')
        sql = sql.replace('dbsolventas17', DB)

        # Ejecutar statement por statement
        statements = [s.strip() for s in sql.split(';') if s.strip() and not s.strip().startswith('--')]
        errors = 0
        for stmt in statements:
            if not stmt or stmt.startswith('--'):
                continue
            try:
                cursor.execute(stmt)
            except pymysql.err.OperationalError as e:
                if 'Duplicate column' in str(e) or 'Duplicate entry' in str(e) or 'already exists' in str(e):
                    pass  # Ignorar duplicados (re-run safe)
                else:
                    print(f"    [WARN] {str(e)[:100]}")
                    errors += 1
            except pymysql.err.IntegrityError:
                pass  # Duplicate key — OK para INSERT IGNORE
            except Exception as e:
                print(f"    [ERR] {str(e)[:100]}")
                errors += 1

        status = "OK" if errors == 0 else f"{errors} warnings"
        print(f"    -> {status}")

    # Verificar tablas creadas
    cursor.execute("SHOW TABLES")
    tables = [r[0] for r in cursor.fetchall()]
    print(f"\nTablas en BD: {len(tables)}")
    for t in sorted(tables):
        print(f"  - {t}")

    cursor.close()
    conn.close()
    print("\nImportación completada.")

if __name__ == '__main__':
    run()
