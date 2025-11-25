USE identify_db;

-- Add columns to support auto-generated progress notes from procedure logs
-- Check if column exists before adding to avoid errors
SET @dbname = DATABASE();
SET @tablename = 'progress_notes';
SET @columnname1 = 'auto_generated';
SET @columnname2 = 'procedure_log_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname1)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname1, ' TINYINT(1) NULL DEFAULT 0 COMMENT ''1 if this progress note was auto-generated from a procedure log''')
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add procedure_log_id column
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname2)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname2, ' INT(11) NULL DEFAULT NULL COMMENT ''Reference to the procedure log that generated this progress note''')
));

PREPARE alterIfNotExists2 FROM @preparedStatement;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- Add index for procedure_log_id
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (index_name = 'idx_procedure_log_id')
  ) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX idx_procedure_log_id ON ', @tablename, ' (procedure_log_id)')
));

PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- 1.  Link progress_notes ↔ procedure_logs on procedure_log_id
--     (already exists, just add index for speed)
ALTER TABLE progress_notes
ADD INDEX idx_proc_log_id (procedure_log_id);
-- 2.  View that returns the latest remark per procedure_log
DROP VIEW IF EXISTS v_proc_latest_remark;
CREATE VIEW v_proc_latest_remark AS
SELECT  procedure_log_id,
remarks  -- the remark text written by CI
FROM    (
SELECT  procedure_log_id,
remarks,
ROW_NUMBER() OVER (PARTITION BY procedure_log_id
ORDER BY id DESC) AS rn
FROM    progress_notes
WHERE   procedure_log_id IS NOT NULL
AND   remarks IS NOT NULL
AND   remarks != ''
) AS t
WHERE   rn = 1;