-- =====================================================
-- SkillSync – database.sql
-- Complete database schema with seed data
-- Database: MySQL 5.7+
-- =====================================================

CREATE DATABASE IF NOT EXISTS skillsync_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skillsync_db;

-- -------------------------------------------------------
-- Table: users
-- -------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  roll_no       VARCHAR(30)   DEFAULT NULL,
  branch        VARCHAR(80)   DEFAULT 'CSE',
  semester      TINYINT       DEFAULT 3,
  target_role   VARCHAR(100)  DEFAULT NULL,
  profile_pic   VARCHAR(255)  DEFAULT 'default.png',
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  is_active     TINYINT(1)    DEFAULT 1
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: skills  (master list)
-- -------------------------------------------------------
CREATE TABLE skills (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  skill_name VARCHAR(80)  NOT NULL,
  category   VARCHAR(60)  NOT NULL,  -- 'Technical','Soft','Tool','Framework'
  difficulty ENUM('Beginner','Intermediate','Advanced') DEFAULT 'Beginner'
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: user_skills  (student ↔ skill mapping)
-- -------------------------------------------------------
CREATE TABLE user_skills (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  skill_id     INT NOT NULL,
  proficiency  TINYINT DEFAULT 1,  -- 1=Beginner 2=Intermediate 3=Advanced
  added_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_skill (user_id, skill_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: resumes
-- -------------------------------------------------------
CREATE TABLE resumes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT  NOT NULL,
  file_name      VARCHAR(255)  NOT NULL,
  file_path      VARCHAR(500)  NOT NULL,
  file_size      INT           DEFAULT 0,
  strength_score TINYINT       DEFAULT 0,
  passed_checks  TEXT          DEFAULT NULL,  -- JSON array
  failed_checks  TEXT          DEFAULT NULL,  -- JSON array
  resume_text    MEDIUMTEXT    DEFAULT NULL,
  uploaded_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: aptitude_questions
-- -------------------------------------------------------
CREATE TABLE aptitude_questions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  question_text TEXT NOT NULL,
  option_a      VARCHAR(300) NOT NULL,
  option_b      VARCHAR(300) NOT NULL,
  option_c      VARCHAR(300) NOT NULL,
  option_d      VARCHAR(300) NOT NULL,
  correct_ans   CHAR(1) NOT NULL,  -- 'A','B','C','D'
  category      VARCHAR(50) DEFAULT 'Quantitative',  -- Quant/Logical/Verbal/Technical
  difficulty    ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
  marks         TINYINT DEFAULT 1
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: aptitude_attempts
-- -------------------------------------------------------
CREATE TABLE aptitude_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT  NOT NULL,
  attempt_no   INT  DEFAULT 1,
  total_q      INT  DEFAULT 0,
  correct      INT  DEFAULT 0,
  wrong        INT  DEFAULT 0,
  skipped      INT  DEFAULT 0,
  score        DECIMAL(5,2) DEFAULT 0,
  percentage   DECIMAL(5,2) DEFAULT 0,
  time_taken   INT  DEFAULT 0,  -- seconds
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: readiness_scores
-- -------------------------------------------------------
CREATE TABLE readiness_scores (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL UNIQUE,
  overall_score   TINYINT DEFAULT 0,
  skill_score     TINYINT DEFAULT 0,
  aptitude_score  TINYINT DEFAULT 0,
  resume_score    TINYINT DEFAULT 0,
  roadmap_score   TINYINT DEFAULT 0,
  last_updated    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: career_roadmap_progress
-- -------------------------------------------------------
CREATE TABLE career_roadmap_progress (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  role        VARCHAR(100) NOT NULL,
  phase_index TINYINT NOT NULL,
  is_complete TINYINT(1) DEFAULT 0,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_roadmap (user_id, role, phase_index)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: job_roles  (for role matching)
-- -------------------------------------------------------
CREATE TABLE job_roles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  role_name    VARCHAR(100) NOT NULL,
  company_type VARCHAR(80)  DEFAULT 'Product',
  min_cgpa     DECIMAL(3,1) DEFAULT 6.0,
  req_skills   TEXT         NOT NULL,  -- comma-separated skill names
  description  TEXT         DEFAULT NULL,
  avg_package  VARCHAR(30)  DEFAULT '4-6 LPA'
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: admin_users
-- -------------------------------------------------------
CREATE TABLE admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) DEFAULT 'Admin',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Table: announcements
-- -------------------------------------------------------
CREATE TABLE announcements (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(200) NOT NULL,
  content    TEXT NOT NULL,
  admin_id   INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB;

-- =======================================================
-- SEED DATA
-- =======================================================

-- Skills master list
INSERT INTO skills (skill_name, category, difficulty) VALUES
('HTML',             'Technical',  'Beginner'),
('CSS',              'Technical',  'Beginner'),
('JavaScript',       'Technical',  'Intermediate'),
('React.js',         'Framework',  'Intermediate'),
('PHP',              'Technical',  'Intermediate'),
('Node.js',          'Framework',  'Intermediate'),
('Python',           'Technical',  'Intermediate'),
('Java',             'Technical',  'Intermediate'),
('C++',              'Technical',  'Intermediate'),
('SQL',              'Technical',  'Beginner'),
('MySQL',            'Tool',       'Beginner'),
('MongoDB',          'Tool',       'Intermediate'),
('Git',              'Tool',       'Beginner'),
('Linux',            'Tool',       'Intermediate'),
('Data Structures',  'Technical',  'Advanced'),
('Algorithms',       'Technical',  'Advanced'),
('DBMS',             'Technical',  'Intermediate'),
('Computer Networks','Technical',  'Intermediate'),
('OOP',              'Technical',  'Intermediate'),
('REST API',         'Technical',  'Intermediate'),
('AWS',              'Tool',       'Advanced'),
('Docker',           'Tool',       'Advanced'),
('Figma',            'Tool',       'Beginner'),
('Power BI',         'Tool',       'Intermediate'),
('Excel',            'Tool',       'Beginner'),
('Communication',    'Soft',       'Beginner'),
('Problem Solving',  'Soft',       'Beginner'),
('Teamwork',         'Soft',       'Beginner'),
('Pandas',           'Framework',  'Intermediate'),
('Statistics',       'Technical',  'Intermediate');

-- Aptitude Questions
INSERT INTO aptitude_questions (question_text, option_a, option_b, option_c, option_d, correct_ans, category, difficulty) VALUES
('If a train travels 60 km in 45 minutes, what is its speed in km/h?',
 '75', '80', '85', '90', 'B', 'Quantitative', 'Easy'),
('A class has 40 students. 25% are girls. How many boys are there?',
 '10', '25', '30', '35', 'C', 'Quantitative', 'Easy'),
('Which of the following is NOT a prime number?',
 '2', '7', '9', '11', 'C', 'Quantitative', 'Easy'),
('If 5x + 3 = 28, find x.',
 '4', '5', '6', '7', 'B', 'Quantitative', 'Easy'),
('A box has 4 red and 6 blue balls. What is the probability of picking a red ball?',
 '0.3', '0.4', '0.5', '0.6', 'B', 'Quantitative', 'Medium'),
('The next number in the series 2, 6, 12, 20, 30 is:',
 '40', '42', '44', '46', 'B', 'Logical', 'Medium'),
('All cats are animals. Some animals are pets. Therefore:',
 'All cats are pets', 'Some cats may be pets', 'No cats are pets', 'All pets are cats',
 'B', 'Logical', 'Medium'),
('Which figure completes the pattern: △▲△ / ▲△▲ / △▲?',
 '▲', '△', '■', '●', 'A', 'Logical', 'Medium'),
('Choose the odd one: Apple, Mango, Potato, Grapes',
 'Apple', 'Mango', 'Potato', 'Grapes', 'C', 'Logical', 'Easy'),
('A is taller than B. B is taller than C. D is shorter than B. Who is the tallest?',
 'A', 'B', 'C', 'D', 'A', 'Logical', 'Easy'),
('Select the correct passive voice: "She writes a letter."',
 'A letter is written by her.',
 'A letter was written by her.',
 'A letter will be written by her.',
 'A letter has been written.',
 'A', 'Verbal', 'Easy'),
('What is the antonym of "Benevolent"?',
 'Kind', 'Generous', 'Malevolent', 'Friendly', 'C', 'Verbal', 'Easy'),
('Find the error: "She don\'t knows how to swim."',
 'She', 'don\'t knows', 'how to', 'swim', 'B', 'Verbal', 'Easy'),
('Which output does printf("%d", 5/2) give in C?',
 '2.5', '2', '3', 'Error', 'B', 'Technical', 'Easy'),
('What is the time complexity of binary search?',
 'O(n)', 'O(n log n)', 'O(log n)', 'O(1)', 'C', 'Technical', 'Medium'),
('Which data structure uses LIFO order?',
 'Queue', 'Array', 'Stack', 'Linked List', 'C', 'Technical', 'Easy'),
('What is the output: x=5; y=++x; in C?',
 'x=5,y=5', 'x=6,y=6', 'x=5,y=6', 'x=6,y=5', 'B', 'Technical', 'Medium'),
('Which SQL clause is used to filter groups?',
 'WHERE', 'HAVING', 'GROUP BY', 'ORDER BY', 'B', 'Technical', 'Medium'),
('What does HTML stand for?',
 'HyperText Makeup Language', 'HyperText Markup Language',
 'HighText Markup Language', 'HyperText Machine Language', 'B', 'Technical', 'Easy'),
('Which of these is NOT an OOP principle?',
 'Encapsulation', 'Inheritance', 'Compilation', 'Polymorphism', 'C', 'Technical', 'Easy');

-- Job Roles
INSERT INTO job_roles (role_name, company_type, min_cgpa, req_skills, description, avg_package) VALUES
('Web Developer', 'Service', 6.0, 'HTML,CSS,JavaScript,PHP,MySQL,Git', 'Build and maintain web applications for clients.', '3-5 LPA'),
('Software Engineer', 'Product', 6.5, 'Data Structures,Algorithms,C++,Java,OOP,DBMS', 'Design and develop scalable software solutions.', '6-12 LPA'),
('Data Analyst', 'Analytics', 6.0, 'Python,SQL,Excel,Statistics,Power BI', 'Analyze data to support business decisions.', '4-7 LPA'),
('Full Stack Developer', 'Startup', 6.0, 'React.js,Node.js,MySQL,MongoDB,Git,REST API', 'Work on both frontend and backend systems.', '5-10 LPA'),
('Cloud Engineer', 'IT', 7.0, 'AWS,Linux,Docker,Computer Networks,Git', 'Manage and deploy cloud infrastructure.', '7-14 LPA'),
('UI/UX Designer', 'Design', 5.5, 'Figma,CSS,HTML,Communication', 'Create intuitive user experiences.', '4-8 LPA');

-- Default admin account (password: admin@123  → bcrypt hash placeholder)
INSERT INTO admin_users (username, password_hash, full_name) VALUES
('admin', '$2y$10$URhiU3e7K.5m/HNTb6XW0.O3/4J.gkMHcdoaJTkB4lK2T2y/rqMzO', 'System Administrator');