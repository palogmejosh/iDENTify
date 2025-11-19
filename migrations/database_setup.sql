
-- Create the iDENTify database first
-- Name of the database is "identify_db"

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Clinician', 'Clinical Instructor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create patients table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    age INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default users
INSERT INTO users (username, full_name, email, password, role) VALUES
('admin', 'Administrator', 'admin@identify.com', 'admin123', 'Admin'),
('dr.smith', 'Dr. Emily Smith', 'emily.smith@identify.com', 'password123', 'Clinician'),
('prof.johnson', 'Prof. Robert Johnson', 'robert.j@identify.com', 'password123', 'Clinical Instructor'),
('dr.davis', 'Dr. Lisa Davis', 'lisa.davis@identify.com', 'password123', 'Clinician'),
('prof.miller', 'Prof. James Miller', 'james.m@identify.com', 'password123', 'Clinical Instructor');


-- Create appointments table for future use
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    clinician_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Scheduled', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create treatment records table for future use
CREATE TABLE treatment_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    clinician_id INT NOT NULL,
    treatment_date DATE NOT NULL,
    procedure_code VARCHAR(20),
    procedure_description TEXT,
    tooth_number VARCHAR(10),
    notes TEXT,
    cost DECIMAL(10,2),
    status ENUM('Planned', 'In Progress', 'Completed') DEFAULT 'Planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE CASCADE
);


-- Create the table with only the columns you need
CREATE TABLE patient_pir (
    patient_id INT NOT NULL PRIMARY KEY,
    mi VARCHAR(10),
    nickname VARCHAR(100),
    age INT,
    gender VARCHAR(10),
    date_of_birth DATE,
    civil_status VARCHAR(50),
    home_address TEXT,
    home_phone VARCHAR(20),
    mobile_no VARCHAR(20),
    email VARCHAR(100),
    occupation VARCHAR(100),
    work_address TEXT,
    work_phone VARCHAR(20),
    ethnicity VARCHAR(50),
    guardian_name VARCHAR(100),
    guardian_contact VARCHAR(20),
    emergency_contact_name VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    date_today DATE,
    clinician VARCHAR(100),
    clinic VARCHAR(100),
    chief_complaint TEXT,
    present_illness TEXT,
    medical_history TEXT,
    dental_history TEXT,
    family_history TEXT,
    personal_history TEXT,
    skin TEXT,
    extremities TEXT,
    eyes TEXT,
    ent TEXT,
    respiratory TEXT,
    cardiovascular TEXT,
    gastrointestinal TEXT,
    genitourinary TEXT,
    endocrine TEXT,
    hematopoietic TEXT,
    neurological TEXT,
    psychiatric TEXT,
    growth_or_tumor TEXT,
    summary TEXT,
    asa VARCHAR(10),
    asa_notes TEXT,
    patient_signature VARCHAR(100),
    photo VARCHAR(255),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

/* =========================================================
   Patient Health Questionnaire & Clinical Examination
   (Step 2 in the multi-page PIR flow)
   ========================================================= */
CREATE TABLE IF NOT EXISTS patient_health (
    /* ---- Primary key / relation to master table ---- */
    patient_id INT NOT NULL PRIMARY KEY,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,

    /* -----------------------------------------------------
       Part A – Health Questionnaire (Yes/No + Notes)
       ----------------------------------------------------- */
    last_medical_physical         VARCHAR(255),
    physician_name_addr           VARCHAR(255),

    under_physician_care          BOOLEAN DEFAULT FALSE,
    under_physician_care_note     VARCHAR(255),

    serious_illness_operation     BOOLEAN DEFAULT FALSE,
    serious_illness_operation_note VARCHAR(255),

    hospitalized                  BOOLEAN DEFAULT FALSE,
    hospitalized_note             VARCHAR(255),

    /* Diseases / conditions */
    rheumatic_fever               BOOLEAN DEFAULT FALSE,
    cardiovascular_disease        BOOLEAN DEFAULT FALSE,
    asthma_hayfever               BOOLEAN DEFAULT FALSE,
    fainting_seizures             BOOLEAN DEFAULT FALSE,
    urinate_more                  BOOLEAN DEFAULT FALSE,
    mouth_dry                     BOOLEAN DEFAULT FALSE,
    arthritis                     BOOLEAN DEFAULT FALSE,
    kidney_trouble                BOOLEAN DEFAULT FALSE,
    venereal_disease              BOOLEAN DEFAULT FALSE,
    hepatitis                     BOOLEAN DEFAULT FALSE,
    diabetes                      BOOLEAN DEFAULT FALSE,
    tuberculosis                  BOOLEAN DEFAULT FALSE,
    stomach_ulcers                BOOLEAN DEFAULT FALSE,
    heart_abnormalities           BOOLEAN DEFAULT FALSE,
    childhood_diseases            BOOLEAN DEFAULT FALSE,
    childhood_diseases_note       VARCHAR(255),
    hives_skin_rash               BOOLEAN DEFAULT FALSE,
    abnormal_bleeding             BOOLEAN DEFAULT FALSE,
    bruise_easily                 BOOLEAN DEFAULT FALSE,
    blood_transfusion             BOOLEAN DEFAULT FALSE,
    blood_transfusion_note        VARCHAR(255),
    blood_disorder                BOOLEAN DEFAULT FALSE,
    head_neck_radiation           BOOLEAN DEFAULT FALSE,
    other_conditions              BOOLEAN DEFAULT FALSE,
    other_conditions_note         VARCHAR(255),

    /* Allergies */
    anesthetic_allergy            BOOLEAN DEFAULT FALSE,
    penicillin_allergy            BOOLEAN DEFAULT FALSE,
    aspirin_allergy               BOOLEAN DEFAULT FALSE,
    latex_allergy                 BOOLEAN DEFAULT FALSE,
    other_allergy                 BOOLEAN DEFAULT FALSE,

    /* Free-text questions */
    taking_drugs                  VARCHAR(255),
    previous_dental_trouble       VARCHAR(255),
    other_problem                 VARCHAR(255),
    xray_exposure                 VARCHAR(255),
    eyeglasses                    VARCHAR(255),

    /* Women-specific */
    pregnant                      BOOLEAN DEFAULT FALSE,
    breast_feeding                BOOLEAN DEFAULT FALSE,

    /* -----------------------------------------------------
       Part B – Clinical Examination / Extra- & Intra-oral
       ----------------------------------------------------- */
    general_health_notes          VARCHAR(255),
    physical                      VARCHAR(255),
    mental                        VARCHAR(255),
    vital_signs                   VARCHAR(255),

    /* Extra-oral */
    extra_head_face               VARCHAR(255),
    extra_eyes                    VARCHAR(255),
    extra_ears                    VARCHAR(255),
    extra_nose                    VARCHAR(255),
    extra_hair                    VARCHAR(255),
    extra_neck                    VARCHAR(255),
    extra_paranasal               VARCHAR(255),
    extra_lymph                   VARCHAR(255),
    extra_salivary                VARCHAR(255),
    extra_tmj                     VARCHAR(255),
    extra_muscles                 VARCHAR(255),
    extra_other                   VARCHAR(255),

    /* Intra-oral soft tissues */
    intra_lips                    VARCHAR(255),
    intra_buccal                  VARCHAR(255),
    intra_alveolar                VARCHAR(255),
    intra_floor                   VARCHAR(255),
    intra_tongue                  VARCHAR(255),
    intra_saliva                  VARCHAR(255),
    intra_pillars                 VARCHAR(255),
    intra_tonsils                 VARCHAR(255),
    intra_uvula                   VARCHAR(255),
    intra_oropharynx              VARCHAR(255),
    intra_other                   VARCHAR(255),

    /* Periodontal screening */
    perio_gingiva                 VARCHAR(255),
    perio_healthy               VARCHAR(255),
    perio_inflamed              VARCHAR(255),
    perio_degree_inflame        VARCHAR(255),
    perio_mild                  VARCHAR(255),
    perio_moderate              VARCHAR(255),
    perio_severe                VARCHAR(255),
    perio_deposits              VARCHAR(255),
    perio_light                 VARCHAR(255),
    perio_mod_deposits          VARCHAR(255),
    perio_heavy                 VARCHAR(255),
    perio_other                 VARCHAR(255),

    /* Occlusion / orthodontics */
    occl_molar_l                  VARCHAR(255),
    occl_molar_r                  VARCHAR(255),
    occl_canine                   VARCHAR(255),
    occl_incisal                  VARCHAR(255),
    occl_overbite                 VARCHAR(255),
    occl_overjet                  VARCHAR(255),
    occl_midline                  VARCHAR(255),
    occl_crossbite                VARCHAR(255),
    occl_appliances               VARCHAR(255),

    /* Signatures */
    signature                     VARCHAR(255),
    patient_signature             VARCHAR(255)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

  -- =========================================================
-- DENTAL_EXAMINATION  (Step-3 data)
-- =========================================================
CREATE TABLE IF NOT EXISTS `dental_examination` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,

    -- Clinical header
    `date_examined` date DEFAULT NULL,
    `clinician` varchar(100) DEFAULT NULL,
    `checked_by` varchar(100) DEFAULT NULL,

    -- Free-text areas
    `diagnostic_tests` text,
    `other_notes` text,

    -- Canvas drawing stored as base-64 PNG
    `tooth_chart_drawing` longtext,

    -- Assessment / Plan grid (JSON)
    `assessment_plan_json` json DEFAULT NULL,

    -- Signatures
    `patient_signature` varchar(100) DEFAULT NULL,
    `history_performed_by` varchar(100) DEFAULT NULL,
    `history_performed_date` date DEFAULT NULL,
    `history_checked_by` varchar(100) DEFAULT NULL,
    `history_checked_date` date DEFAULT NULL,

    -- Keys & relations
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_patient` (`patient_id`),          -- one row per patient
    KEY `idx_exam_patient` (`patient_id`),             -- speeds look-ups
    CONSTRAINT `fk_exam_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- INFORMED_CONSENT  (Step-4 data)
-- =========================================================
CREATE TABLE IF NOT EXISTS `informed_consent` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,

    /* ---- Consent initials ---- */
    `consent_treatment`          VARCHAR(4),
    `consent_drugs`              VARCHAR(4),
    `consent_changes`            VARCHAR(4),
    `consent_radiographs`        VARCHAR(4),
    `consent_removal_teeth`      VARCHAR(4),
    `consent_crowns`             VARCHAR(4),
    `consent_dentures`           VARCHAR(4),
    `consent_fillings`           VARCHAR(4),
    `consent_guarantee`          VARCHAR(4),

    /* ---- Signatures / dates ---- */
    `patient_signature`          VARCHAR(100),
    `witness_signature`          VARCHAR(100),
    `consent_date`               DATE,

    `clinician_signature`        VARCHAR(100),
    `clinician_date`             DATE,

    /* ---- Data-privacy section ---- */
    `data_privacy_signature`     VARCHAR(100),   -- signature over printed name / date
    `data_privacy_patient_sign`  VARCHAR(100),   -- patient name & signature

    /* ---- Keys & relations ---- */
    UNIQUE KEY `uniq_patient` (`patient_id`),
    KEY `idx_consent_patient` (`patient_id`),
    CONSTRAINT `fk_consent_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PROGRESS_NOTES  (scalable)
-- =========================================================
CREATE TABLE IF NOT EXISTS `progress_notes` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id`  INT NOT NULL,
  `date`        DATE        NULL,
  `tooth`       VARCHAR(20) NULL,
  `progress`    VARCHAR(255) NULL,
  `clinician`   VARCHAR(100) NULL,
  `ci`          VARCHAR(100) NULL,
  `remarks`     VARCHAR(255) NULL,
  `patient_signature` VARCHAR(100) NULL,

  KEY `idx_patient` (`patient_id`),
  CONSTRAINT `fk_progress_patient`
    FOREIGN KEY (`patient_id`)
    REFERENCES `patients`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
  
-- Create indexes for better performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_patients_email ON patients(email);
CREATE INDEX idx_patients_name ON patients(last_name, first_name);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_treatment_records_patient ON treatment_records(patient_id);
CREATE INDEX idx_treatment_records_date ON treatment_records(treatment_date);
