# Roostar V2

Roostar V2 is a modular rebuild of the Roostar application without Composer.

The application uses a small internal autoloader, module boundaries, policies,
middleware, migrations, and security-first defaults.

## Local commands

Run database migrations:

```bash
php bin/db-create.php
php bin/migrate.php
```

Check migration status:

```bash
php bin/migrate-status.php
```

Generate an encryption key:

```bash
php bin/key-generate.php
```

Create a first user after migrations:

```bash
php bin/user-create.php --name="Admin" --email="admin@example.com" --password="change-me" --role="school_admin" --school-id="school-id"
```

Seed local development data:

```bash
php bin/dev-seed.php
```
