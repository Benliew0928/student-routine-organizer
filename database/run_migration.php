<?php
require __DIR__ . '/../config/database.php';

try {
    $db = getDatabaseConnection();
    $sql = file_get_contents(__DIR__ . '/journal_migration.sql');
    
    if ($db->multi_query($sql)) {
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
        echo "Migration successful!\n";
    } else {
        echo "Migration failed: " . $db->error . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
