<?php
require_once __DIR__ . '/Includes/db.php'; // adjust if path differs
echo '<pre>';
try {
  echo "Connected DB: " . ($pdo->query("SELECT DATABASE()")->fetchColumn() ?: 'NULL') . "\n";
  echo "MySQL USER(): " . ($pdo->query("SELECT USER()")->fetchColumn() ?: 'N/A') . "\n";
  echo "CURRENT_USER(): " . ($pdo->query("SELECT CURRENT_USER()")->fetchColumn() ?: 'N/A') . "\n\n";
  echo "Server variables:\n";
  $vars = $pdo->query("SHOW VARIABLES WHERE Variable_name IN ('port','socket','basedir','datadir')")->fetchAll(PDO::FETCH_KEY_PAIR);
  print_r($vars);
  echo "\nSchemas with auction_db: ";
  $exists = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='auction_db'")->fetchColumn();
  echo ($exists ? "YES\n" : "NO\n");
  echo "\nCategories rows:\n";
  $cats = $pdo->query("SELECT category_id,name FROM categories ORDER BY category_id")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cats as $c) echo " - {$c['category_id']}: {$c['name']}\n";
} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
echo '</pre>';