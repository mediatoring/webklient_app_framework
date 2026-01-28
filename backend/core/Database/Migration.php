<?php

declare(strict_types=1);

namespace WebklientApp\Core\Database;

abstract class Migration
{
    abstract public function up(Connection $db): void;

    abstract public function down(Connection $db): void;
}
