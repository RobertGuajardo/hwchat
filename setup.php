<?php
/**
 * RobChat Setup Helper
 *
 * Run this from the command line to generate config values
 * and verify your setup:
 *
 *   cd C:\Projects\robchat\backend
 *   php setup.php
 */

echo "\n========================================\n";
echo "  ROBCHAT SETUP HELPER\n";
echo "========================================\n\n";

// 1. Generate encryption key
echo "1. ENCRYPTION KEY (for config.php)\n";
echo "   " . bin2hex(random_bytes(32)) . "\n\n";

// 2. Generate a password hash
echo "2. PASSWORD HASH GENERATOR\n";
$defaultPass = 'demo123';
echo "   Hash for '$defaultPass': " . password_hash($defaultPass, PASSWORD_BCRYPT) . "\n";
echo "   (Use this in the migration SQL or to reset a tenant password)\n\n";

// 3. Check if config.php exists
echo "3. CONFIG CHECK\n";
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    echo "   ✓ config.php found\n";
    $config = require $configPath;

    // 4. Test database connection
    echo "\n4. DATABASE CONNECTION\n";
    try {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
            $config['db_host'] ?? 'localhost',
            $config['db_port'] ?? 5432,
            $config['db_name'] ?? 'robchat'
        );
        $pdo = new PDO($dsn, $config['db_user'] ?? '', $config['db_password'] ?? '');
        echo "   ✓ Connected to PostgreSQL\n";

        // Check tables
        $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            echo "   ✗ No tables found — run the migration:\n";
            echo "     psql -U robchat -d robchat -f migrations/001_initial_schema.sql\n";
        } else {
            echo "   ✓ Tables: " . implode(', ', $tables) . "\n";
        }

        // Check tenants
        $count = $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
        echo "   ✓ Tenants: $count\n";

        if ($count > 0) {
            $tenants = $pdo->query("SELECT id, display_name, email FROM tenants")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tenants as $t) {
                echo "     - {$t['id']}: {$t['display_name']} ({$t['email']})\n";
            }
        }

    } catch (PDOException $e) {
        echo "   ✗ Connection failed: " . $e->getMessage() . "\n";
        echo "   Check your db_host, db_port, db_name, db_user, db_password in config.php\n";
    }

    // 5. Check API keys
    echo "\n5. API KEYS\n";
    $openai = $config['default_openai_key'] ?? '';
    $anthropic = $config['default_anthropic_key'] ?? '';
    echo "   OpenAI:    " . ($openai && !str_contains($openai, 'YOUR') ? '✓ Set (' . substr($openai, 0, 12) . '...)' : '✗ Not set') . "\n";
    echo "   Anthropic: " . ($anthropic && !str_contains($anthropic, 'YOUR') ? '✓ Set (' . substr($anthropic, 0, 12) . '...)' : '✗ Not set') . "\n";

} else {
    echo "   ✗ config.php not found\n";
    echo "   Run: cp config.example.php config.php\n";
    echo "   Then edit it with your database credentials and API keys.\n";
}

echo "\n========================================\n";
echo "  Setup complete. See SETUP.md for full guide.\n";
echo "========================================\n\n";
