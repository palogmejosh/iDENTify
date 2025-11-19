<?php
// Simple test for progress notes auto-generation
echo "<h2>Testing Progress Notes Auto-Generation</h2>";

// Check if the auto_generated column exists in progress_notes table
try {
    $pdo = new PDO('mysql:host=localhost;dbname=identify_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if the column exists
    $stmt = $pdo->prepare("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_schema = 'identify_db'
        AND table_name = 'progress_notes'
        AND column_name IN ('auto_generated', 'procedure_log_id')
    ");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "<h3>Database Columns</h3>";
    if (!empty($columns)) {
        echo "<table border='1'><tr><th>Column</th><th>Type</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>Required columns not found! Please run the migration script.</p>";
    }
    
    // Check if there are any procedure logs with progress notes
    echo "<h3>Procedure Logs and Progress Notes</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            pl.id as proc_id,
            pl.patient_id,
            pl.procedure_selected,
            pl.logged_at,
            pn.id as progress_id,
            pn.progress,
            pn.auto_generated,
            pn.procedure_log_id
        FROM procedure_logs pl
        LEFT JOIN progress_notes pn ON pl.id = pn.procedure_log_id
        ORDER BY pl.logged_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    if (!empty($results)) {
        echo "<table border='1'><tr><th>Proc ID</th><th>Procedure</th><th>Progress Note ID</th><th>Progress</th><th>Auto</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>{$row['proc_id']}</td>";
            echo "<td>" . htmlspecialchars($row['procedure_selected']) . "</td>";
            echo "<td>{$row['progress_id']}</td>";
            echo "<td>" . htmlspecialchars($row['progress']) . "</td>";
            echo "<td>" . ($row['auto_generated'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No procedure logs found.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>