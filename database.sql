-- Create Database
CREATE DATABASE IF NOT EXISTS food_donation_db;
USE food_donation_db;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('donor', 'recipient', 'ngo', 'volunteer', 'admin') NOT NULL DEFAULT 'recipient',
    organization VARCHAR(255),
    address VARCHAR(500),
    city VARCHAR(100),
    profile_photo VARCHAR(255),
    verification_status ENUM('unverified', 'verified', 'rejected') DEFAULT 'unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
);

-- Donations Table
CREATE TABLE donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    food_type VARCHAR(100) NOT NULL,
    quantity VARCHAR(100) NOT NULL,
    description TEXT,
    pickup_address VARCHAR(500) NOT NULL,
    pickup_time DATETIME,
    expires_at DATETIME NOT NULL,
    status ENUM('available', 'requested', 'accepted', 'picked_up', 'completed', 'cancelled', 'expired') DEFAULT 'available',
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_donor (donor_id),
    INDEX idx_created (created_at)
);

-- Requests Table
CREATE TABLE requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    recipient_id INT NOT NULL,
    delivery_address VARCHAR(500) NOT NULL,
    notes TEXT,
    status ENUM('pending', 'accepted', 'rejected', 'assigned_volunteer', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_recipient (recipient_id),
    INDEX idx_donation (donation_id)
);

-- Deliveries Table
CREATE TABLE deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    status ENUM('assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled') DEFAULT 'assigned',
    picked_up_at DATETIME,
    delivered_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_volunteer (volunteer_id)
);

-- Reviews Table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_delivery (delivery_id),
    INDEX idx_reviewer (reviewer_id),
    UNIQUE KEY unique_review (delivery_id, reviewer_id)
);

-- Notifications Table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50),
    title VARCHAR(255),
    message TEXT,
    related_donation_id INT,
    related_request_id INT,
    related_delivery_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_donation_id) REFERENCES donations(id) ON DELETE SET NULL,
    FOREIGN KEY (related_request_id) REFERENCES requests(id) ON DELETE SET NULL,
    FOREIGN KEY (related_delivery_id) REFERENCES deliveries(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
);

-- Activity Log Table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100),
    entity_type VARCHAR(50),
    entity_id INT,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- Insert Sample Admin User
-- Password: 123456
INSERT INTO users (name, email, phone, password, role, organization, verification_status) VALUES
('Admin', 'admin@fooddonation.com', '01700000000', '$2y$10$YY2fhYK2qZxVDmYuCj0T0e5TjU4VuYrUmKdYnI9L.kNzVBwC6pPGa', 'admin', 'Food Donation System', 'verified');
