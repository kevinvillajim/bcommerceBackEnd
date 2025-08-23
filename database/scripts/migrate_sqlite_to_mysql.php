<?php

/**
 * SCRIPT DE MIGRACIÓN SQLite → MySQL
 *
 * Este script migra todos los datos de la base de datos SQLite
 * a la base de datos MySQL configurada en .env
 *
 * Uso: php database/scripts/migrate_sqlite_to_mysql.php
 */

require_once __DIR__.'/../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

class SQLiteToMySQLMigrator
{
    private $sqliteConnection;

    private $mysqlConnection;

    private $migrationLog = [];

    public function __construct()
    {
        $this->setupConnections();
    }

    private function setupConnections()
    {
        // Configurar Capsule de Eloquent
        $capsule = new Capsule;

        // Configuración SQLite
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => __DIR__.'/../database.sqlite',
        ], 'sqlite');

        // Configuración MySQL (desde .env)
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'comersia',
            'username' => 'root',
            'password' => 'test123',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ], 'mysql');

        $capsule->bootEloquent();

        $this->sqliteConnection = $capsule->getConnection('sqlite');
        $this->mysqlConnection = $capsule->getConnection('mysql');

        $this->log('✅ Conexiones establecidas correctamente');
    }

    public function migrate()
    {
        $this->log('🚀 INICIANDO MIGRACIÓN SQLite → MySQL');
        $this->log('='.str_repeat('=', 50));

        try {
            // Verificar conexiones
            $this->testConnections();

            // Obtener tablas de SQLite
            $tables = $this->getSQLiteTables();
            $this->log('📋 Encontradas '.count($tables).' tablas en SQLite');

            // Migrar cada tabla en orden de dependencias
            $migrationOrder = $this->getTableMigrationOrder($tables);

            // Deshabilitar foreign key checks temporalmente
            $this->mysqlConnection->statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($migrationOrder as $table) {
                $this->migrateTable($table);
            }

            // Rehabilitar foreign key checks
            $this->mysqlConnection->statement('SET FOREIGN_KEY_CHECKS=1');

            $this->log('✅ MIGRACIÓN COMPLETADA EXITOSAMENTE');
            $this->generateReport();

        } catch (Exception $e) {
            $this->log('❌ ERROR EN MIGRACIÓN: '.$e->getMessage());
            $this->log('Stack trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    private function testConnections()
    {
        try {
            // Test SQLite
            $sqliteResult = $this->sqliteConnection->select('SELECT 1 as test');
            $this->log('✅ Conexión SQLite verificada');

            // Test MySQL
            $mysqlResult = $this->mysqlConnection->select('SELECT 1 as test');
            $this->log('✅ Conexión MySQL verificada');

        } catch (Exception $e) {
            throw new Exception('Error de conexión: '.$e->getMessage());
        }
    }

    private function getSQLiteTables()
    {
        $query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
        $results = $this->sqliteConnection->select($query);

        return array_map(function ($result) {
            return $result->name;
        }, $results);
    }

    private function getTableMigrationOrder($tables)
    {
        // Orden específico para respetrar dependencias de foreign keys
        $priorityTables = [
            'users',
            'categories',
            'sellers',
            'products',
            'orders',
            'order_items',
            'shopping_carts',
            'cart_items',
            'payments',
            'ratings',
            'chats',
            'messages',
            'volume_discounts',
        ];

        // Primero las tablas prioritarias que existan
        $orderedTables = [];
        foreach ($priorityTables as $table) {
            if (in_array($table, $tables)) {
                $orderedTables[] = $table;
            }
        }

        // Luego el resto de tablas
        foreach ($tables as $table) {
            if (! in_array($table, $orderedTables) && $table !== 'migrations') {
                $orderedTables[] = $table;
            }
        }

        return $orderedTables;
    }

    private function migrateTable($tableName)
    {
        $this->log("🔄 Migrando tabla: {$tableName}");

        try {
            // Obtener estructura de la tabla
            $columns = $this->getTableColumns($tableName);

            // Obtener todos los datos de SQLite
            $data = $this->sqliteConnection->table($tableName)->get();
            $totalRows = count($data);

            if ($totalRows === 0) {
                $this->log("   ⚠️  Tabla {$tableName} está vacía");
                $this->migrationLog[$tableName] = [
                    'rows_migrated' => 0,
                    'status' => 'success',
                    'message' => 'Tabla vacía',
                ];

                return;
            }

            // Truncar tabla MySQL para empezar limpio
            $this->mysqlConnection->table($tableName)->truncate();

            // Migrar datos en lotes para optimizar memoria
            $batchSize = 500;
            $migratedRows = 0;

            foreach (array_chunk($data->toArray(), $batchSize) as $batch) {
                // Convertir objetos a arrays y limpiar datos
                $cleanBatch = array_map(function ($row) {
                    return (array) $row;
                }, $batch);

                // Insertar lote en MySQL
                $this->mysqlConnection->table($tableName)->insert($cleanBatch);

                $migratedRows += count($cleanBatch);
                $this->log("   📊 Migradas {$migratedRows}/{$totalRows} filas");
            }

            $this->migrationLog[$tableName] = [
                'rows_migrated' => $migratedRows,
                'status' => 'success',
                'message' => 'Migración exitosa',
            ];

            $this->log("   ✅ Tabla {$tableName} migrada exitosamente ({$migratedRows} filas)");

        } catch (Exception $e) {
            $this->migrationLog[$tableName] = [
                'rows_migrated' => 0,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];

            $this->log("   ❌ Error migrando {$tableName}: ".$e->getMessage());

            // Continuar con otras tablas en lugar de fallar completamente
            if (str_contains($e->getMessage(), 'Duplicate entry') ||
                str_contains($e->getMessage(), 'foreign key constraint')) {
                $this->log('   ⚠️  Error de integridad - continuando con siguiente tabla');
            } else {
                throw $e;
            }
        }
    }

    private function getTableColumns($tableName)
    {
        $query = "PRAGMA table_info({$tableName})";

        return $this->sqliteConnection->select($query);
    }

    private function generateReport()
    {
        $this->log("\n📊 REPORTE DE MIGRACIÓN");
        $this->log('='.str_repeat('=', 50));

        $totalTables = count($this->migrationLog);
        $successfulTables = 0;
        $totalRowsMigrated = 0;

        foreach ($this->migrationLog as $table => $info) {
            $status = $info['status'] === 'success' ? '✅' : '❌';
            $this->log("{$status} {$table}: {$info['rows_migrated']} filas - {$info['message']}");

            if ($info['status'] === 'success') {
                $successfulTables++;
                $totalRowsMigrated += $info['rows_migrated'];
            }
        }

        $this->log("\n📈 RESUMEN:");
        $this->log("   • Tablas procesadas: {$totalTables}");
        $this->log("   • Tablas exitosas: {$successfulTables}");
        $this->log("   • Filas migradas: {$totalRowsMigrated}");
        $this->log('   • Tasa de éxito: '.round(($successfulTables / $totalTables) * 100, 2).'%');

        // Guardar reporte en archivo
        $reportPath = __DIR__.'/../SQLITE_MYSQL_MIGRATION_REPORT.md';
        $this->saveReportToFile($reportPath);
        $this->log("📄 Reporte guardado en: {$reportPath}");
    }

    private function saveReportToFile($path)
    {
        $content = "# REPORTE DE MIGRACIÓN SQLite → MySQL\n\n";
        $content .= '**Fecha:** '.date('Y-m-d H:i:s')."\n";
        $content .= "**Database MySQL:** comersia\n\n";

        $content .= "## Tablas Migradas\n\n";

        foreach ($this->migrationLog as $table => $info) {
            $status = $info['status'] === 'success' ? '✅' : '❌';
            $content .= "- {$status} **{$table}**: {$info['rows_migrated']} filas - {$info['message']}\n";
        }

        $totalRowsMigrated = array_sum(array_column($this->migrationLog, 'rows_migrated'));
        $successfulTables = count(array_filter($this->migrationLog, fn ($info) => $info['status'] === 'success'));

        $content .= "\n## Resumen\n\n";
        $content .= '- **Tablas procesadas:** '.count($this->migrationLog)."\n";
        $content .= "- **Tablas exitosas:** {$successfulTables}\n";
        $content .= "- **Total filas migradas:** {$totalRowsMigrated}\n";
        $content .= '- **Tasa de éxito:** '.round(($successfulTables / count($this->migrationLog)) * 100, 2)."%\n\n";

        $content .= "## Log de Migración\n\n```\n";
        $content .= implode("\n", $this->migrationLog['full_log'] ?? []);
        $content .= "\n```\n";

        file_put_contents($path, $content);
    }

    private function log($message)
    {
        $timestamp = date('H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}";
        echo $formattedMessage.PHP_EOL;

        // Guardar para el reporte
        if (! isset($this->migrationLog['full_log'])) {
            $this->migrationLog['full_log'] = [];
        }
        $this->migrationLog['full_log'][] = $formattedMessage;
    }
}

// Ejecutar migración
try {
    $migrator = new SQLiteToMySQLMigrator;
    $migrator->migrate();

    echo "\n🎉 ¡MIGRACIÓN COMPLETADA EXITOSAMENTE!\n";
    echo "Los datos han sido transferidos de SQLite a MySQL.\n";

} catch (Exception $e) {
    echo "\n💥 ERROR CRÍTICO EN MIGRACIÓN:\n";
    echo $e->getMessage()."\n";
    echo "\nStack trace:\n".$e->getTraceAsString()."\n";
    exit(1);
}
