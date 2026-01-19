<?php
require_once 'config.php';

try {
    $db = dbBaglanti();
    echo "DB Connection Successful.<br>";

    // Test 1: Native PostgreSQL placeholders ($1)
    echo "Testing Native Placeholders ($1)... ";
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM cti_kayitlari WHERE 1=$1");
        $stmt->execute([1]);
        echo "OK (Result: " . $stmt->fetchColumn() . ")<br>";
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "<br>";
    }

    // Test 2: Standard PDO placeholders (?)
    echo "Testing Standard Placeholders (?)... ";
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM cti_kayitlari WHERE 1=?");
        $stmt->execute([1]);
        echo "OK (Result: " . $stmt->fetchColumn() . ")<br>";
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "<br>";
    }

} catch (Exception $e) {
    echo "General Error: " . $e->getMessage();
}
?>
