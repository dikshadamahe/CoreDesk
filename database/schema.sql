-- =====================================================================
-- CoreDesk Database Schema
-- Multi-Role Support & Helpdesk Ticketing System
-- Pure Native SQL with Relational Integrity & Performance Indexes
-- =====================================================================

CREATE DATABASE IF NOT EXISTS coredesk_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coredesk_db;

-- 1. Users Table (Role-based access: Admin, Support Executive/Agent, Customer)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'agent', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Ticket Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 3. Tickets Table (Core entity with multi-column relations and SLA tracking)
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    status ENUM('Open', 'In-Progress', 'Resolved', 'Closed') NOT NULL DEFAULT 'Open',
    assigned_agent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_priority (status, priority),
    INDEX idx_user (user_id),
    INDEX idx_agent (assigned_agent_id)
) ENGINE=InnoDB;

-- 4. Ticket Replies / Comment Thread
CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_internal_note TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_replies (ticket_id, created_at)
) ENGINE=InnoDB;

-- 5. Audit & Activity Logs (Audit trail for status changes and triage)
CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    old_value VARCHAR(100) NULL,
    new_value VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA: Realistic Enterprise Data
-- Passwords hashed with bcrypt: 'password123'
-- =====================================================================

INSERT INTO categories (name, description) VALUES
('Technical Support', 'Software bugs, exception crashes, and runtime errors'),
('Database & Storage', 'Query performance bottlenecks, schema sync, and backups'),
('API & Integrations', 'REST payload failures, webhooks, and auth issues'),
('Billing & Accounts', 'Invoices, subscriptions, and access permissions');

-- Users: Admin, Agents, and Customers
INSERT INTO users (name, email, password_hash, role) VALUES
('Diksha Support Lead', 'admin@coredesk.local', '$2y$10$wEkgzJjU5bC9sV1rV9gC6eYfCcm9v0rU8lS7r7y3n7i7Wb9W7.QeS', 'admin'),
('Support Executive Alex', 'alex@coredesk.local', '$2y$10$wEkgzJjU5bC9sV1rV9gC6eYfCcm9v0rU8lS7r7y3n7i7Wb9W7.QeS', 'agent'),
('Support Executive Priya', 'priya@coredesk.local', '$2y$10$wEkgzJjU5bC9sV1rV9gC6eYfCcm9v0rU8lS7r7y3n7i7Wb9W7.QeS', 'agent'),
('Rahul Verma', 'rahul@client.com', '$2y$10$wEkgzJjU5bC9sV1rV9gC6eYfCcm9v0rU8lS7r7y3n7i7Wb9W7.QeS', 'customer'),
('Ananya Sen', 'ananya@fintech.io', '$2y$10$wEkgzJjU5bC9sV1rV9gC6eYfCcm9v0rU8lS7r7y3n7i7Wb9W7.QeS', 'customer'),
('Marcus Brody', 'marcus@corp.com', '$2y$10$wEkgzJjU5bC9sV1rV9gC6eYfCcm9v0rU8lS7r7y3n7i7Wb9W7.QeS', 'customer');

-- Tickets
INSERT INTO tickets (ticket_code, user_id, category_id, subject, description, priority, status, assigned_agent_id, created_at) VALUES
('CD-1001', 4, 1, '500 Internal Server Error on Payment Webhook Callback', 'Our webhook receiver triggers an uncaught PDOException during peak transactional bursts. The payment payload fails to commit to the ledger table.', 'Critical', 'In-Progress', 2, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('CD-1002', 5, 2, 'Slow MySQL Query Latency on Order History Dashboard', 'Selecting customer order histories takes over 3,200ms when filtered by date range. Execution plan indicates a missing composite index on orders(user_id, created_at).', 'High', 'Open', 1, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('CD-1003', 6, 3, 'Bearer Token Expiration Issue in Customer REST API', 'JWT tokens appear to expire 15 minutes ahead of the documented TTL header, resulting in 401 Unauthorized responses during automated synchronizations.', 'Medium', 'Resolved', 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('CD-1004', 4, 1, 'SSL Handshake Timeout on Staging Subdomain', 'Staging server fails with SSL certificate verify error: unable to get local issuer certificate when initiating external curl requests.', 'Medium', 'Open', NULL, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('CD-1005', 5, 4, 'Invoice PDF Generation Rendering Blank Page in Firefox', 'When clicking download monthly invoice in Firefox on macOS, the PDF generates with headers but empty tabular line items.', 'Low', 'Closed', 2, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Replies
INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal_note, created_at) VALUES
(1, 2, 'Investigated Apache and PHP error logs. The issue is caused by unhandled database deadlocks during simultaneous write operations. Preparing a prepared statement with retry logic.', 0, DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(1, 4, 'Thank you Alex! Please let us know once the patch is applied on the staging environment for verification.', 0, DATE_SUB(NOW(), INTERVAL 60 MINUTE)),
(3, 3, 'Identified server clock drift on API gateway node. Synchronized NTP daemon and verified token validation. Issue resolved.', 0, DATE_SUB(NOW(), INTERVAL 18 HOUR));

-- Audit Logs
INSERT INTO ticket_logs (ticket_id, user_id, action, old_value, new_value, created_at) VALUES
(1, 1, 'Assignment Changed', 'Unassigned', 'Support Executive Alex', DATE_SUB(NOW(), INTERVAL 110 MINUTE)),
(1, 2, 'Status Changed', 'Open', 'In-Progress', DATE_SUB(NOW(), INTERVAL 95 MINUTE)),
(3, 3, 'Status Changed', 'In-Progress', 'Resolved', DATE_SUB(NOW(), INTERVAL 18 HOUR));
