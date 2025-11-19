USE identify_db;

-- Create procedure_assignments table if not exists (safety)
CREATE TABLE IF NOT EXISTS `procedure_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `procedure_log_id` int(11) NOT NULL COMMENT 'Reference to procedure_logs table',
  `cod_user_id` int(11) DEFAULT NULL COMMENT 'COD user who made the assignment',
  `clinical_instructor_id` int(11) DEFAULT NULL COMMENT 'Clinical Instructor assigned to review the procedure',
  `assignment_status` enum('pending','accepted','rejected','completed') NOT NULL DEFAULT 'pending' COMMENT 'Status of the assignment',
  `notes` text DEFAULT NULL COMMENT 'Assignment notes from COD or CI',
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the procedure was assigned',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `procedure_log_id` (`procedure_log_id`),
  KEY `clinical_instructor_id` (`clinical_instructor_id`),
  KEY `cod_user_id` (`cod_user_id`),
  KEY `assignment_status` (`assignment_status`),
  CONSTRAINT `fk_procedure_assignments_procedure_log` FOREIGN KEY (`procedure_log_id`) REFERENCES `procedure_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_procedure_assignments_ci` FOREIGN KEY (`clinical_instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_procedure_assignments_cod` FOREIGN KEY (`cod_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks procedure assignments to Clinical Instructors';

-- Stored procedure to auto-assign a procedure log to an online CI
DROP PROCEDURE IF EXISTS AutoAssignProcedureLogToCI;
DELIMITER //
CREATE PROCEDURE AutoAssignProcedureLogToCI(IN p_procedure_log_id INT)
BEGIN
    DECLARE v_ci_id INT;
    DECLARE v_ci_name VARCHAR(255);
    DECLARE v_notes TEXT;
    DECLARE v_cod_user_id INT;
    DECLARE v_details VARCHAR(255);

    -- Pick any active COD for attribution (or NULL)
    SELECT MIN(id) INTO v_cod_user_id FROM users WHERE role='COD' AND account_status='active';

    -- Read procedure_details from the log for specialty matching
    SELECT COALESCE(procedure_details, '') INTO v_details
    FROM procedure_logs WHERE id = p_procedure_log_id;

    -- Try to match specialty first among online CIs with least workload
    SELECT u.id, u.full_name
      INTO v_ci_id, v_ci_name
    FROM users u
    LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
    WHERE u.role='Clinical Instructor'
      AND u.account_status='active'
      AND u.connection_status='online'
      AND (u.last_activity IS NULL OR u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))
      AND (
            (u.specialty_hint IS NOT NULL AND u.specialty_hint <> '' AND v_details <> '' AND (
                LOWER(u.specialty_hint) LIKE LOWER(CONCAT('%', v_details, '%')) OR
                LOWER(v_details) LIKE LOWER(CONCAT('%', u.specialty_hint, '%'))
            ))
          )
    GROUP BY u.id, u.full_name
    ORDER BY COUNT(CASE WHEN pa.assignment_status IN ('accepted','pending') THEN 1 END) ASC, RAND()
    LIMIT 1;

    -- If no matching specialty, pick any online CI with least workload
    IF v_ci_id IS NULL THEN
        SELECT u.id, u.full_name
          INTO v_ci_id, v_ci_name
        FROM users u
        LEFT JOIN patient_assignments pa ON u.id = pa.clinical_instructor_id
        WHERE u.role='Clinical Instructor'
          AND u.account_status='active'
          AND u.connection_status='online'
          AND (u.last_activity IS NULL OR u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))
        GROUP BY u.id, u.full_name
        ORDER BY COUNT(CASE WHEN pa.assignment_status IN ('accepted','pending') THEN 1 END) ASC, RAND()
        LIMIT 1;
    END IF;

    -- If still none, exit (no online CI)
    IF v_ci_id IS NULL THEN
        LEAVE BEGIN;
    END IF;

    -- Upsert into procedure_assignments with pending status (CI must accept)
    IF EXISTS (SELECT 1 FROM procedure_assignments WHERE procedure_log_id = p_procedure_log_id) THEN
        UPDATE procedure_assignments
           SET clinical_instructor_id = v_ci_id,
               assignment_status = 'pending',
               notes = CONCAT(IFNULL(notes,''), '\nAuto-assigned by trigger at ', NOW()),
               assigned_at = NOW(),
               updated_at = NOW()
         WHERE procedure_log_id = p_procedure_log_id;
    ELSE
        INSERT INTO procedure_assignments
            (procedure_log_id, cod_user_id, clinical_instructor_id, assignment_status, notes, assigned_at, created_at, updated_at)
        VALUES
            (p_procedure_log_id, v_cod_user_id, v_ci_id, 'pending', 'Auto-assigned on submit', NOW(), NOW(), NOW());
    END IF;
END //
DELIMITER ;

-- Trigger to call procedure after a procedure log is inserted
DROP TRIGGER IF EXISTS trg_auto_assign_procedure_log_after_insert;
DELIMITER //
CREATE TRIGGER trg_auto_assign_procedure_log_after_insert
AFTER INSERT ON procedure_logs
FOR EACH ROW
BEGIN
    CALL AutoAssignProcedureLogToCI(NEW.id);
END //
DELIMITER ;
