# PostgreSQL Database Migration Guide

**Status**: Ready for Implementation  
**Timeline**: 2-4 hours  
**Impact**: 10,000+ concurrent users, enterprise-grade database

---

## Overview

Migrate from SQLite to PostgreSQL for production-grade database support. This guide covers setup, migration, and verification.

---

## Prerequisites

### Option 1: Docker (Recommended)
```bash
# Install Docker if not already installed
# Then run:
docker run --name shuttle-postgres \
  -e POSTGRES_DB=shuttle_db \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=your_secure_password \
  -p 5432:5432 \
  -d postgres:15-alpine
```

### Option 2: Local Installation
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install postgresql postgresql-contrib

# macOS
brew install postgresql

# Windows
# Download from https://www.postgresql.org/download/windows/
```

---

## Step 1: Backup SQLite Database

```bash
cd c:\Program1\Projects\Shuttle\Laravel
cp storage/database.sqlite storage/database.sqlite.backup
```

---

## Step 2: Configure Laravel for PostgreSQL

### Edit `.env`
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=shuttle_db
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

# Optional: Connection pooling
DB_POOL_ENABLED=true
DB_POOL_SIZE=10
```

### Install PostgreSQL driver for PHP (if needed)
```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql

# macOS
# Already included with Laravel sail or Homebrew PHP
```

---

## Step 3: Verify PostgreSQL Connection

```bash
cd Laravel

# Test connection
php artisan db
# Should show: pgsql@127.0.0.1

# Or use psql directly
psql -h localhost -U postgres -d postgres
# If prompted for password, use the one from .env
```

---

## Step 4: Run Migrations

```bash
# Fresh migration (recommended)
php artisan migrate:fresh

# Or with seeding (if you have seeders)
php artisan migrate:fresh --seed
```

This creates all tables in PostgreSQL with proper structure.

---

## Step 5: Migrate Data from SQLite (if needed)

### Create Data Migration Script

Create file: `Laravel/database/seeders/MigrateSQLiteData.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PDO;

class MigrateSQLiteData extends Seeder
{
    public function run()
    {
        $this->command->info('Starting SQLite to PostgreSQL migration...');

        try {
            // Connect to SQLite
            $sqlitePdo = new PDO('sqlite:' . storage_path('database.sqlite.backup'));
            $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Tables to migrate (in order of dependencies)
            $tables = ['users', 'vehicles', 'schedules', 'trips', 'seats', 'locations', 'bookings'];

            foreach ($tables as $table) {
                $this->command->info("Migrating table: $table");
                $this->migrateTable($sqlitePdo, $table);
            }

            $this->command->info('✅ Migration completed successfully!');
        } catch (\Exception $e) {
            $this->command->error('❌ Migration failed: ' . $e->getMessage());
        }
    }

    private function migrateTable($sqlitePdo, $table)
    {
        try {
            // Get data from SQLite
            $rows = $sqlitePdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->command->line("  → No data to migrate");
                return;
            }

            // Insert into PostgreSQL
            foreach ($rows as $row) {
                DB::table($table)->insert($row);
            }

            $this->command->line("  → Migrated " . count($rows) . " rows");
        } catch (\Exception $e) {
            $this->command->error("  → Error: " . $e->getMessage());
        }
    }
}
```

### Run Migration Seeder
```bash
php artisan db:seed --class=MigrateSQLiteData
```

---

## Step 6: Verify Migration

```bash
# Check record counts in each table
php artisan tinker

>>> DB::table('users')->count();
>>> DB::table('bookings')->count();
>>> DB::table('schedules')->count();
```

Or use PostgreSQL directly:
```bash
psql -h localhost -U postgres -d shuttle_db

shuttle_db=# SELECT count(*) FROM users;
shuttle_db=# SELECT count(*) FROM bookings;
shuttle_db=# SELECT count(*) FROM schedules;
```

---

## Step 7: Run Tests

```bash
# Run Laravel tests
php artisan test

# Test APIs
curl http://localhost:8000/api/admin/dashboard/stats

# Check response time (should be <200ms)
```

---

## Step 8: Performance Optimization (Post-Migration)

### Re-run Indexes
```bash
php artisan migrate --path=database/migrations/2026_05_22_000000_add_performance_indexes.php
```

### Verify Indexes
```bash
# List all indexes in PostgreSQL
psql -h localhost -U postgres -d shuttle_db

shuttle_db=# \d+ bookings
shuttle_db=# \di

# Check index usage statistics
shuttle_db=# SELECT schemaname, tablename, indexname 
             FROM pg_indexes 
             WHERE schemaname != 'pg_catalog';
```

---

## Step 9: Update Cache (if using)

```bash
php artisan cache:clear
redis-cli FLUSHALL  # If using Redis
```

---

## Rollback Plan (if needed)

```bash
# If migration fails, restore SQLite
cp storage/database.sqlite.backup storage/database.sqlite

# Revert .env to use SQLite
# DB_CONNECTION=sqlite

# Verify
php artisan db
```

---

## Performance Comparison

| Metric | SQLite | PostgreSQL | Improvement |
|--------|--------|------------|-------------|
| Concurrent Users | 50 | 10,000+ | 200× |
| Max Connections | 1 | 1000+ | 1000× |
| Query Speed | 100-500ms | 5-50ms | 10-100× |
| Data Size | 50MB | 100MB+ | Scales |
| Full-Text Search | ❌ | ✅ | New feature |
| JSONB Support | ❌ | ✅ | New feature |
| Replication | ❌ | ✅ | New feature |
| Transactions | Limited | Full ACID | Better |

---

## Maintenance

### Backup PostgreSQL
```bash
# Using pg_dump
pg_dump -h localhost -U postgres shuttle_db > backup.sql

# Restore from backup
psql -h localhost -U postgres shuttle_db < backup.sql
```

### Monitor Performance
```bash
# Check slow queries
psql -h localhost -U postgres -d shuttle_db

shuttle_db=# SELECT query, calls, mean_time 
             FROM pg_stat_statements 
             ORDER BY mean_time DESC LIMIT 10;
```

---

## PostgreSQL Configuration (Production)

For production, optimize PostgreSQL:

```bash
# Edit PostgreSQL config
sudo nano /etc/postgresql/15/main/postgresql.conf

# Key settings:
shared_buffers = 256MB          # 25% of RAM
effective_cache_size = 1GB      # 50% of RAM
work_mem = 16MB                 # shared_buffers / (max_connections * 2)
maintenance_work_mem = 64MB
max_connections = 200
synchronous_commit = off        # Faster writes
```

Restart PostgreSQL:
```bash
sudo systemctl restart postgresql
```

---

## Troubleshooting

### Connection refused
```bash
# Check if PostgreSQL is running
sudo systemctl status postgresql

# Or with Docker
docker ps | grep postgres
```

### Wrong credentials
```bash
# Reset PostgreSQL password
sudo -u postgres psql
ALTER USER postgres PASSWORD 'new_password';
```

### Migration hangs
```bash
# Kill long-running queries
psql -h localhost -U postgres
SELECT pg_terminate_backend(pid) FROM pg_stat_activity 
WHERE state = 'active';
```

---

## Completion Checklist

- [ ] PostgreSQL installed and running
- [ ] `.env` updated with PostgreSQL config
- [ ] `php artisan migrate:fresh` executed
- [ ] Data migrated from SQLite (if needed)
- [ ] Row counts verified
- [ ] Tests passing
- [ ] APIs responding in <200ms
- [ ] Indexes verified
- [ ] Cache cleared
- [ ] Backup created
- [ ] Performance metrics recorded

---

## Documentation

- [PostgreSQL Official Docs](https://www.postgresql.org/docs/)
- [Laravel Database Docs](https://laravel.com/docs/database)
- [PostgreSQL Performance Tuning](https://wiki.postgresql.org/wiki/Performance_Optimization)

---

**Status**: Ready for Production  
**Recommended**: Execute in staging first, then production
