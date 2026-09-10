#!/usr/bin/env php
<?php
/**
 * Prepare install/database.sql with a table prefix for one-time Railway (or any) import.
 *
 * Usage:
 *   php scripts/prepare_schema.php
 *   php scripts/prepare_schema.php rise_ > install/database.prefixed.sql
 *   php scripts/prepare_schema.php rise_ admin@example.com 'YourPassword' 'First' 'Last' 'PURCHASE-CODE'
 */

$prefix = $argv[1] ?? 'rise_';
$email = $argv[2] ?? 'admin@example.com';
$password = $argv[3] ?? 'ChangeMe123!';
$firstName = $argv[4] ?? 'Admin';
$lastName = $argv[5] ?? 'User';
$purchaseCode = $argv[6] ?? 'ITEM-PURCHASE-CODE';

$source = dirname(__DIR__) . '/install/database.sql';
if (!is_file($source)) {
    fwrite(STDERR, "Could not find install/database.sql\n");
    exit(1);
}

$sql = file_get_contents($source);
$now = date('Y-m-d H:i:s');

$sql = str_replace('admin_first_name', $firstName, $sql);
$sql = str_replace('admin_last_name', $lastName, $sql);
$sql = str_replace('admin_email', $email, $sql);
$sql = str_replace('admin_password', password_hash($password, PASSWORD_DEFAULT), $sql);
$sql = str_replace('admin_created_at', $now, $sql);
$sql = str_replace('ITEM-PURCHASE-CODE', $purchaseCode, $sql);

$sql = str_replace('CREATE TABLE IF NOT EXISTS `', 'CREATE TABLE IF NOT EXISTS `' . $prefix, $sql);
$sql = str_replace('INSERT INTO `', 'INSERT INTO `' . $prefix, $sql);

fwrite(STDOUT, $sql);
fwrite(STDERR, "Prepared schema with prefix '{$prefix}' and admin email '{$email}'.\n");
fwrite(STDERR, "Import with: mysql -h HOST -u USER -p DATABASE < install/database.prefixed.sql\n");
