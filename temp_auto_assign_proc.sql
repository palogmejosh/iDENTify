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
    LEFT JOIN procedure_assignments pa ON u.id = pa.clinical_instructor_id
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
        LEFT JOIN procedure_assignments pa ON u.id = pa.clinical_instructor_id
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