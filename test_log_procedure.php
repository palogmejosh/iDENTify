<!DOCTYPE html>
<html>
<head>
    <title>Test Procedure Logging</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        input, select, button { padding: 5px; margin: 5px 0; }
    </style>
</head>
<body>
    <h2>Test Procedure Logging with Auto Progress Notes</h2>
    
    <form method="post" action="save_procedure_log.php">
        <div>
            <label for="patient_id">Patient ID:</label>
            <select id="patient_id" name="patient_id" required>
                <?php
                require_once 'config.php';
                requireAuth();
                
                $stmt = $pdo->query("SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name LIMIT 10");
                while ($row = $stmt->fetch()) {
                    echo "<option value=\"{$row['id']}\">" . htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) . "</option>";
                }
                ?>
            </select>
        </div>
        
        <div>
            <label for="procedure_selected">Procedure:</label>
            <input type="text" id="procedure_selected" name="procedure_selected" placeholder="Test procedure" required>
        </div>
        
        <div>
            <label for="procedure_details">Procedure Details:</label>
            <input type="text" id="procedure_details" name="procedure_details" placeholder="Test procedure details">
        </div>
        
        <div>
            <label for="clinician_name">Clinician Name:</label>
            <input type="text" id="clinician_name" name="clinician_name" value="Test Clinician" required>
        </div>
        
        <div>
            <label for="remarks">Remarks:</label>
            <input type="text" id="remarks" name="remarks" placeholder="Test remarks">
        </div>
        
        <button type="submit">Submit</button>
    </form>
    
    <h3>Latest Procedure Logs</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Procedure</th>
            <th>Clinician</th>
            <th>Logged At</th>
        </tr>
        <?php
        $stmt = $pdo->query("SELECT pl.id, p.first_name, p.last_name, pl.procedure_selected, pl.clinician_name, pl.logged_at 
                              FROM procedure_logs pl 
                              JOIN patients p ON pl.patient_id = p.id 
                              ORDER BY pl.id DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['procedure_selected']) . "</td>";
            echo "<td>" . htmlspecialchars($row['clinician_name']) . "</td>";
            echo "<td>" . $row['logged_at'] . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    
    <h3>Latest Progress Notes</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Date</th>
            <th>Progress</th>
            <th>Clinician</th>
            <th>CI</th>
            <th>Remarks</th>
            <th>Auto</th>
            <th>Log Ref</th>
        </tr>
        <?php
        $stmt = $pdo->query("SELECT pn.id, p.first_name, p.last_name, pn.date, pn.progress, pn.clinician, pn.ci, pn.remarks, pn.auto_generated, pn.procedure_log_id 
                              FROM progress_notes pn 
                              JOIN patients p ON pn.patient_id = p.id 
                              ORDER BY pn.id DESC LIMIT 10");
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
            echo "<td>" . $row['date'] . "</td>";
            echo "<td>" . htmlspecialchars($row['progress']) . "</td>";
            echo "<td>" . htmlspecialchars($row['clinician']) . "</td>";
            echo "<td>" . htmlspecialchars($row['ci']) . "</td>";
            echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
            echo "<td>" . ((int)$row['auto_generated'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($row['procedure_log_id'] ?: 'None') . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</body>
</html>