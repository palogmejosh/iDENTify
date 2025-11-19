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