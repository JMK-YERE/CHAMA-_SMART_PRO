-- database/chama_smart_pro.sql
CREATE DATABASE IF NOT EXISTS chama_smart_pro;
USE chama_smart_pro;

-- ============================================
-- Table: wanakikundi
-- ============================================
CREATE TABLE wanakikundi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    email VARCHAR(100),
    nida VARCHAR(20) UNIQUE,
    role ENUM('mwenyekiti','katibu','mhazina','mkaguzi','mwanakikundi') DEFAULT 'mwanakikundi',
    password VARCHAR(255) NOT NULL,
    savings DECIMAL(12,2) DEFAULT 0,
    monthly_income DECIMAL(12,2) DEFAULT 0,
    language ENUM('sw','en') DEFAULT 'sw',
    mpesa_phone VARCHAR(15),
    status ENUM('pending','active','inactive','suspended') DEFAULT 'pending',
    confirmed_by INT,
    confirmed_at TIMESTAMP,
    join_date DATE DEFAULT (CURRENT_DATE),
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (confirmed_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: mikopo
-- ============================================
CREATE TABLE mikopo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    purpose TEXT,
    repaid DECIMAL(12,2) DEFAULT 0,
    term_months INT NOT NULL,
    interest_rate DECIMAL(5,2) DEFAULT 5.00,
    status ENUM('pending','approved','active','paid','rejected','overdue') DEFAULT 'pending',
    due_date DATE,
    approved_by INT,
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: chango
-- ============================================
CREATE TABLE chango (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    date DATE DEFAULT (CURRENT_DATE),
    description TEXT,
    payment_method ENUM('cash','bank','mobile','other') DEFAULT 'cash',
    transaction_id VARCHAR(100),
    confirmed BOOLEAN DEFAULT FALSE,
    confirmed_by INT,
    confirmed_at TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: malipo_ya_mikopo
-- ============================================
CREATE TABLE malipo_ya_mikopo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE DEFAULT (CURRENT_DATE),
    payment_method ENUM('cash','bank','mobile','other') DEFAULT 'cash',
    transaction_id VARCHAR(100),
    confirmed BOOLEAN DEFAULT FALSE,
    confirmed_by INT,
    confirmed_at TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES mikopo(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: mpesa_transactions
-- ============================================
CREATE TABLE mpesa_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    transaction_type ENUM('contribution','loan_repayment','disbursement') NOT NULL,
    reference_id INT,
    amount DECIMAL(12,2) NOT NULL,
    mpesa_receipt VARCHAR(50),
    phone VARCHAR(15) NOT NULL,
    status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
    request_id VARCHAR(50),
    response_code VARCHAR(10),
    response_description TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_by INT,
    confirmed_at TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id),
    FOREIGN KEY (confirmed_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: meetings
-- ============================================
CREATE TABLE meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    meeting_date DATETIME NOT NULL,
    duration INT DEFAULT 60,
    meeting_link VARCHAR(500),
    meeting_type ENUM('video','audio','hybrid') DEFAULT 'video',
    platform ENUM('zoom','google_meet','jitsi','custom') DEFAULT 'zoom',
    created_by INT,
    status ENUM('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: meeting_attendees
-- ============================================
CREATE TABLE meeting_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    member_id INT NOT NULL,
    status ENUM('invited','confirmed','attended','absent') DEFAULT 'invited',
    joined_at TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id) ON DELETE CASCADE
);

-- ============================================
-- Table: chat_rooms
-- ============================================
CREATE TABLE chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(255) NOT NULL,
    room_type ENUM('general','group','private') DEFAULT 'general',
    group_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','archived','deleted') DEFAULT 'active',
    FOREIGN KEY (created_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: chat_messages
-- ============================================
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','image','file','audio','video') DEFAULT 'text',
    file_url VARCHAR(500),
    read_at TIMESTAMP,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: chat_participants
-- ============================================
CREATE TABLE chat_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    member_id INT NOT NULL,
    role ENUM('admin','member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id),
    UNIQUE KEY unique_participant (room_id, member_id)
);

-- ============================================
-- Table: api_tokens
-- ============================================
CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    platform ENUM('mobile','web','desktop') DEFAULT 'mobile',
    device_name VARCHAR(100),
    ip_address VARCHAR(45),
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','expired','revoked') DEFAULT 'active',
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id) ON DELETE CASCADE
);

-- ============================================
-- Table: api_logs
-- ============================================
CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    request_data JSON,
    response_data JSON,
    status_code INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Table: whatsapp_messages
-- ============================================
CREATE TABLE whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    recipient_phone VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    template_name VARCHAR(100),
    status ENUM('pending','sent','delivered','read','failed') DEFAULT 'pending',
    message_id VARCHAR(100),
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: notifications
-- ============================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('sms','whatsapp','email') DEFAULT 'sms',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: matangazo (Announcements)
-- ============================================
CREATE TABLE matangazo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    posted_by INT,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATE,
    status ENUM('active','expired','draft') DEFAULT 'active',
    FOREIGN KEY (posted_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: maelekezo (Instructions)
-- ============================================
CREATE TABLE maelekezo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: logs
-- ============================================
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: viwango_vya_mkopo (Loan Settings)
-- ============================================
CREATE TABLE viwango_vya_mkopo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    max_loan_percentage DECIMAL(5,2) DEFAULT 70.00,
    max_loan_income_percentage DECIMAL(5,2) DEFAULT 50.00,
    default_interest_rate DECIMAL(5,2) DEFAULT 5.00,
    min_contributions_required INT DEFAULT 3,
    contribution_period_months INT DEFAULT 6,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES wanakikundi(id)
);

-- ============================================
-- Table: translations (Multi-language)
-- ============================================
CREATE TABLE translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_string VARCHAR(255) NOT NULL UNIQUE,
    swahili TEXT,
    english TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Default translations
INSERT INTO translations (key_string, swahili, english) VALUES
('welcome', 'Karibu', 'Welcome'),
('dashboard', 'Dashibodi', 'Dashboard'),
('members', 'Wanakikundi', 'Members'),
('loans', 'Mikopo', 'Loans'),
('contributions', 'Chango', 'Contributions'),
('repayments', 'Malipo', 'Repayments'),
('reports', 'Ripoti', 'Reports'),
('settings', 'Mipangilio', 'Settings'),
('logout', 'Toka', 'Logout'),
('profile', 'Wasifu', 'Profile'),
('total_funds', 'Jumla ya Hazina', 'Total Funds'),
('total_loans', 'Mikopo Iliyotolewa', 'Total Loans'),
('overdue_loans', 'Mikopo Imechelewa', 'Overdue Loans'),
('total_members', 'Wanakikundi', 'Total Members');

-- Default settings
INSERT INTO viwango_vya_mkopo (max_loan_percentage, max_loan_income_percentage, default_interest_rate, min_contributions_required, contribution_period_months) 
VALUES (70.00, 50.00, 5.00, 3, 6);

-- Default instructions
INSERT INTO maelekezo (title, content) VALUES 
('Karibu kwenye CHAMA SMART PRO', 'Maelekezo ya msingi:\n\n1. Kila mwanakikundi anatakiwa kulipa chango yake kila mwezi.\n2. Mkopo unaweza kuombwa baada ya kuwa na angalau chango 3 katika miezi 6 iliyopita.\n3. Hawezi kukopa zaidi ya 70% ya akiba yake.\n4. Riba ya mkopo ni 5% kwa mwezi.\n5. Malipo yote yanarekodiwa kwenye mfumo.\n\nKwa maelezo zaidi, wasiliana na Katibu au Mhazina.');

-- Default chat room
INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES ('Jumuiya ya Wanakikundi', 'general', 1);

-- Default admin user (password: Admin@2025)
INSERT INTO wanakikundi (first_name, last_name, phone, nida, role, password, savings, status, confirmed_by, confirmed_at) VALUES
('Admin', 'Mwenyekiti', '0712345678', 'NIDA0000', 'mwenyekiti', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active', 1, NOW());
