<?php

declare(strict_types=1);

use App\Core\Env;
use App\Services\SchemaService;
use App\Services\UserRepository;

require dirname(__DIR__) . '/config/bootstrap.php';

$schema = new SchemaService();
$schema->migrate();

echo "Schema migration complete.\n";

$adminName = trim((string) Env::get('ADMIN_NAME', 'admin'));
$adminPassword = (string) Env::get('ADMIN_PASSWORD', '');

if ($adminPassword === '') {
    echo "ADMIN_PASSWORD is empty. Skipping admin seed.\n";
    exit(0);
}

$users = new UserRepository();
$existing = $users->findByName($adminName);
$hash = password_hash($adminPassword, PASSWORD_BCRYPT);

if ($existing !== null) {
    $users->updatePassword((int) $existing['id'], $hash);
    echo sprintf("Updated password for existing '%s' user.\n", $adminName);
    exit(0);
}

$users->create($adminName, $hash);
echo sprintf("Created admin user '%s'.\n", $adminName);
