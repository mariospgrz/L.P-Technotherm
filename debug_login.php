<?php
// DIAGNOSTIC TOOL — DELETE THIS FILE AFTER TESTING
// Browse to: http://localhost/L.P-Technotherm/debug_login.php
// Then DELETE it immediately — it reveals sensitive info!

require_once __DIR__ . '/Backend/Database/Database.php';

echo "<h2>Login Diagnostic</h2>";

// 1. DB connection
echo "<p>✅ DB connected</p>";

// 2. Show all users (username + first chars of password)
$result = $conn->query("SELECT id, username, role, LEFT(password, 7) as pw_prefix FROM users");
if (!$result) {
    echo "<p>❌ Query failed: " . $conn->error . "</p>";
    exit;
}

echo "<table border='1' cellpadding='6'>";
echo "<tr><th>id</th><th>username</th><th>role</th><th>password starts with</th><th>is bcrypt?</th></tr>";
while ($row = $result->fetch_assoc()) {
    $isBcrypt = str_starts_with($row['pw_prefix'], '$2y$') || str_starts_with($row['pw_prefix'], '$2a$');
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
    echo "<td>" . htmlspecialchars($row['role']) . "</td>";
    echo "<td>" . htmlspecialchars($row['pw_prefix']) . "...</td>";
    echo "<td>" . ($isBcrypt ? "✅ Yes" : "❌ No — plain text or other hash!") . "</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Test password_verify for a specific user
$testUser = $_GET['user'] ?? '';
$testPass = $_GET['pass'] ?? '';

if ($testUser && $testPass) {
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $testUser);
    $stmt->execute();
    $stmt->bind_result($hash);
    $stmt->fetch();
    $stmt->close();

    if ($hash) {
        $ok = password_verify($testPass, $hash);
        echo "<p>password_verify result for '<b>" . htmlspecialchars($testUser) . "</b>': " . ($ok ? "✅ MATCH" : "❌ NO MATCH") . "</p>";
    } else {
        echo "<p>❌ User not found.</p>";
    }
}

echo "<hr><p>Test a specific user: <a href='?user=YOUR_USERNAME&pass=YOUR_PASSWORD'>?user=...&pass=...</a></p>";
echo "<p style='color:red'><b>DELETE this file after testing!</b></p>";
