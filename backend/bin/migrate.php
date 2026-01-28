<?php

declare(strict_types=1);

/**
 * CLI migration runner.
 *
 * Usage:
 *   php bin/migrate.php              Run pending migrations
 *   php bin/migrate.php rollback     Rollback last batch
 *   php bin/migrate.php status       Show migration status
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Database\Migrator;

$config = ConfigLoader::getInstance(__DIR__ . '/../.env');
$config->loadConfigDirectory(__DIR__ . '/../config');

$db = new Connection($config->get('database'));
$migrator = new Migrator($db, __DIR__ . '/../database/migrations');

$command = $argv[1] ?? 'migrate';

switch ($command) {
    case 'migrate':
        echo "Running migrations...\n";
        $executed = $migrator->migrate();
        if (empty($executed)) {
            echo "Nothing to migrate.\n";
        } else {
            foreach ($executed as $name) {
                echo "  Migrated: {$name}\n";
            }
            echo "Done. " . count($executed) . " migration(s) executed.\n";
        }
        break;

    case 'rollback':
        echo "Rolling back last batch...\n";
        $rolled = $migrator->rollback();
        if (empty($rolled)) {
            echo "Nothing to rollback.\n";
        } else {
            foreach ($rolled as $name) {
                echo "  Rolled back: {$name}\n";
            }
            echo "Done. " . count($rolled) . " migration(s) rolled back.\n";
        }
        break;

    case 'status':
        $status = $migrator->status();
        echo str_pad("Migration", 60) . "Status\n";
        echo str_repeat('-', 70) . "\n";
        foreach ($status as $row) {
            echo str_pad($row['migration'], 60) . $row['status'] . "\n";
        }
        break;

    default:
        echo "Unknown command: {$command}\n";
        echo "Usage: php bin/migrate.php [migrate|rollback|status]\n";
        exit(1);
}
