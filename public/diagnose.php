<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo '<div style="font-family:sans-serif; max-width:800px; margin:40px auto; padding:24px; border:1px solid #ddd; border-radius:12px; background:#fff;">';
echo '<h2 style="margin-top:0; color:#1a73e8;">Malabar Club Server Diagnostics</h2>';

echo '<b>PHP Version:</b> ' . PHP_VERSION . '<br>';

$envPath = __DIR__ . '/../.env';
$envFound = file_exists($envPath);
echo '<b>.env File:</b> ' . ($envFound ? '<span style="color:green">Found at ' . realpath($envPath) . '</span>' : '<span style="color:red">MISSING at ' . realpath(__DIR__ . '/..') . '</span>') . '<br>';

$autoload = __DIR__ . '/../vendor/autoload.php';
$autoFound = file_exists($autoload);
echo '<b>vendor/autoload.php:</b> ' . ($autoFound ? '<span style="color:green">Found</span>' : '<span style="color:red">MISSING</span>') . '<br>';

$storageWritable = is_writable(__DIR__ . '/../storage');
echo '<b>storage/ Writable:</b> ' . ($storageWritable ? '<span style="color:green">YES</span>' : '<span style="color:red">NO (Permissions issue)</span>') . '<br>';

if ($autoFound) {
    try {
        require $autoload;
        echo '<b>Composer Autoload:</b> <span style="color:green">OK</span><br>';

        $app = require_once __DIR__ . '/../bootstrap/app.php';
        echo '<b>Bootstrap App:</b> <span style="color:green">OK</span><br>';

        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->bootstrap();

        $appKey = config('app.key');
        echo '<b>APP_KEY:</b> ' . (!empty($appKey) ? '<span style="color:green">Set (' . htmlspecialchars(substr($appKey, 0, 15)) . '...)</span>' : '<span style="color:red">MISSING / EMPTY!</span>') . '<br>';

        $dbConn = config('database.default');
        $dbName = config("database.connections.{$dbConn}.database");
        $dbUser = config("database.connections.{$dbConn}.username");
        $dbHost = config("database.connections.{$dbConn}.host");
        echo "<b>Database Config:</b> {$dbConn} &rarr; {$dbUser}@{$dbHost}/{$dbName}<br>";

        try {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            echo '<div style="margin-top:12px; padding:12px; background:#e6f4ea; border-radius:8px; color:#137333;"><b>✓ Database Connection Successful!</b></div>';
            
            // Check tables
            $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $tableCount = count($tables);
            echo "<b>Total Tables in DB:</b> {$tableCount}<br>";
            
            if ($tableCount > 0) {
                $hasUsers = \Illuminate\Support\Facades\Schema::hasTable('users');
                $hasMembers = \Illuminate\Support\Facades\Schema::hasTable('members');
                
                $userCount = $hasUsers ? \Illuminate\Support\Facades\DB::table('users')->count() : 0;
                $memberCount = $hasMembers ? \Illuminate\Support\Facades\DB::table('members')->count() : 0;
                
                echo "<b>Users Count:</b> {$userCount} " . ($userCount > 0 ? '(Accounts exist)' : '<span style="color:red">(0 accounts - needs seeding!)</span>') . "<br>";
                echo "<b>Members Count:</b> {$memberCount} " . ($memberCount > 0 ? '(Members exist)' : '<span style="color:red">(0 members - needs seeding!)</span>') . "<br>";
                
                if ($userCount > 0) {
                    $sampleUsers = \Illuminate\Support\Facades\DB::table('users')->select('name', 'email', 'role')->get();
                    echo '<ul style="margin:4px 0; font-size:13px;">';
                    foreach ($sampleUsers as $u) {
                        echo "<li><b>{$u->email}</b> ({$u->name}) &mdash; Role: {$u->role}</li>";
                    }
                    echo '</ul>';
                }
            } else {
                echo '<div style="margin-top:8px; color:#c5221f;"><b>No tables found!</b> Database migrations have not been run yet.</div>';
            }
        } catch (\Throwable $dbe) {
            echo '<div style="margin-top:12px; padding:12px; background:#fce8e6; border-radius:8px; color:#c5221f;"><b>✗ Database Connection Failed:</b> ' . htmlspecialchars($dbe->getMessage()) . '</div>';
        }

        echo '<div style="margin-top:16px;"><a href="/setup-club?token=malabar-club-setup-2026" style="display:inline-block; padding:10px 18px; background:#1a73e8; color:#fff; border-radius:8px; text-decoration:none; font-weight:bold;">Proceed to Setup Wizard &rarr;</a></div>';

    } catch (\Throwable $e) {
        echo '<div style="margin-top:16px; padding:16px; background:#fce8e6; border:1px solid #fad2cf; border-radius:8px; color:#c5221f;">';
        echo '<h3 style="margin-top:0;">Boot Error: ' . htmlspecialchars(get_class($e)) . '</h3>';
        echo '<p><b>Message:</b> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><b>File:</b> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<pre style="background:#fff; padding:10px; border-radius:4px; font-size:12px; overflow-x:auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    }
}

echo '</div>';

