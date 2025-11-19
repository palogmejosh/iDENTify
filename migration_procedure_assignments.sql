-- Migration script to create procedure_assignments table
-- This table will track procedure assignments from COD to Clinical Instructors

CREATE TABLE IF NOT EXISTS `procedure_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `procedure_log_id` int(11) NOT NULL COMMENT 'Reference to procedure_logs table',
  `cod_user_id` int(11) DEFAULT NULL COMMENT 'COD user who made the assignment',
  `clinical_instructor_id` int(11) NOT NULL COMMENT 'Clinical Instructor assigned to review the procedure',
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
  CONSTRAINT `fk_procedure_assignments_ci` FOREIGN KEY (`clinical_instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_procedure_assignments_cod` FOREIGN KEY (`cod_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks procedure assignments to Clinical Instructors';

-- Add index for faster queries
CREATE INDEX idx_procedure_assignments_status_ci ON procedure_assignments(clinical_instructor_id, assignment_status);
