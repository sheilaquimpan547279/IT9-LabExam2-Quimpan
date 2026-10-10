CREATE DATABASE IF NOT EXISTS student_enrollment
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_enrollment;

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    gender ENUM('Female', 'Male', 'Prefer not to say') NOT NULL,
    date_of_birth DATE NOT NULL,
    course VARCHAR(120) NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('Enrolled', 'Pending', 'Graduated') NOT NULL DEFAULT 'Pending',
    address VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_name (last_name, first_name),
    KEY idx_students_status (status)
) ENGINE=InnoDB;

INSERT IGNORE INTO students
    (first_name, last_name, email, phone, gender, date_of_birth, course, year_level, enrollment_date, status, address)
VALUES
    ('Ava', 'Santos', 'ava.santos@example.com', '0917 555 0101', 'Female', '2005-04-12', 'BS Information Technology', 2, '2025-06-10', 'Enrolled', 'Quezon City'),
    ('Noah', 'Reyes', 'noah.reyes@example.com', '0917 555 0102', 'Male', '2004-11-03', 'BS Computer Science', 3, '2025-06-12', 'Enrolled', 'Makati City'),
    ('Mia', 'Cruz', 'mia.cruz@example.com', '0917 555 0103', 'Female', '2006-02-21', 'BS Information Systems', 1, '2025-06-15', 'Pending', 'Pasig City'),
    ('Ethan', 'Garcia', 'ethan.garcia@example.com', '0917 555 0104', 'Male', '2003-08-17', 'BS Computer Science', 4, '2025-06-18', 'Graduated', 'Manila'),
    ('Isla', 'Mendoza', 'isla.mendoza@example.com', '0917 555 0105', 'Prefer not to say', '2005-06-29', 'BS Information Technology', 2, '2025-06-20', 'Pending', 'Taguig City'),
    ('Leo', 'Bautista', 'leo.bautista@example.com', '0917 555 0106', 'Male', '2004-12-05', 'BS Information Systems', 3, '2025-06-22', 'Enrolled', 'Caloocan City'),
    ('Sophia', 'Torres', 'sophia.torres@example.com', '0917 555 0107', 'Female', '2005-09-15', 'BS Information Technology', 2, '2025-06-25', 'Enrolled', 'Valenzuela City');