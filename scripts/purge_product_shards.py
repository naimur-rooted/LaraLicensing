import glob
import os
import sqlite3

def purge_shards():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    db_dir = os.path.join(base_dir, "backend", "database")
    pattern = os.path.join(db_dir, "product_*.sqlite")
    shard_files = glob.glob(pattern)

    print(f"Found {len(shard_files)} product shard database file(s):")
    tables_to_purge = [
        "ProductLicenses",
        "HardwareBindings",
        "MachineBindings",
        "SecurityAuditEvents",
        "Licenses",
        "Serials",
    ]

    for shard_path in shard_files:
        basename = os.path.basename(shard_path)
        print(f"\nProcessing shard: {basename}")
        conn = sqlite3.connect(shard_path)
        cursor = conn.cursor()

        existing_tables = [
            r[0] for r in cursor.execute(
                "SELECT name FROM sqlite_master WHERE type='table'"
            ).fetchall()
        ]

        for table in tables_to_purge:
            if table in existing_tables:
                cursor.execute(f"DELETE FROM {table}")
                print(f"  Deleted all rows from {table}")

        if "sqlite_sequence" in existing_tables:
            for table in tables_to_purge:
                cursor.execute("DELETE FROM sqlite_sequence WHERE name = ?", (table,))
            print("  Reset sqlite_sequence autoincrement counters")

        conn.commit()
        cursor.execute("VACUUM")
        conn.close()
        print(f"  VACUUM executed successfully for {basename}")

    print("\nPurge completed successfully across all product shard databases.")

if __name__ == "__main__":
    purge_shards()
