-- Disaster Response Coordination System
DROP DATABASE IF EXISTS disaster_response;
CREATE DATABASE disaster_response;
USE disaster_response;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','volunteer','citizen','team') NOT NULL DEFAULT 'citizen',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE volunteers (
  volunteer_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  skills VARCHAR(200),
  location VARCHAR(150),
  availability ENUM('available','busy','offline') DEFAULT 'available',
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE disaster (
  disaster_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(50) NOT NULL,
  location VARCHAR(150) NOT NULL,
  severity ENUM('low','medium','high','critical') DEFAULT 'medium',
  status ENUM('active','contained','closed') DEFAULT 'active',
  start_date DATE NOT NULL
);

CREATE TABLE shelter (
  shelter_id INT AUTO_INCREMENT PRIMARY KEY,
  disaster_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(150) NOT NULL,
  capacity INT NOT NULL CHECK (capacity >= 0),
  occupied INT NOT NULL DEFAULT 0,
  FOREIGN KEY (disaster_id) REFERENCES disaster(disaster_id) ON DELETE CASCADE
);

CREATE TABLE relief_supplies (
  supply_id INT AUTO_INCREMENT PRIMARY KEY,
  shelter_id INT NOT NULL,
  item_name VARCHAR(100) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  unit VARCHAR(20) DEFAULT 'units',
  FOREIGN KEY (shelter_id) REFERENCES shelter(shelter_id) ON DELETE CASCADE
);

CREATE TABLE emergency_request (
  request_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  disaster_id INT NULL,
  request_type ENUM('rescue','food','water','medicine','other') NOT NULL,
  description TEXT,
  location VARCHAR(200) NOT NULL,
  priority ENUM('low','medium','high') DEFAULT 'medium',
  status ENUM('pending','assigned','in_progress','completed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (disaster_id) REFERENCES disaster(disaster_id) ON DELETE SET NULL
);

CREATE TABLE rescue_team (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,          -- login account of the team lead
  team_name VARCHAR(100) NOT NULL,
  members INT DEFAULT 1,
  status ENUM('available','deployed') DEFAULT 'available',
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE assignment (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  team_id INT NULL,
  volunteer_id INT NULL,
  status ENUM('assigned','accepted','in_progress','completed') DEFAULT 'assigned',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (request_id) REFERENCES emergency_request(request_id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES rescue_team(team_id) ON DELETE SET NULL,
  FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE SET NULL
);

CREATE TABLE donation (
  donation_id INT AUTO_INCREMENT PRIMARY KEY,
  donor_name VARCHAR(100) NOT NULL,
  supply_id INT NOT NULL,
  quantity INT NOT NULL CHECK (quantity > 0),
  donated_on DATE DEFAULT (CURRENT_DATE),
  FOREIGN KEY (supply_id) REFERENCES relief_supplies(supply_id) ON DELETE CASCADE
);

CREATE TABLE status_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  old_status VARCHAR(20),
  new_status VARCHAR(20),
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES emergency_request(request_id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX idx_req_status ON emergency_request(status);
CREATE INDEX idx_req_user ON emergency_request(user_id);
CREATE INDEX idx_assign_status ON assignment(status);

-- Triggers
DELIMITER //
CREATE TRIGGER trg_request_status_log
AFTER UPDATE ON emergency_request
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO status_log(request_id, old_status, new_status)
    VALUES (NEW.request_id, OLD.status, NEW.status);
  END IF;
END//

CREATE TRIGGER trg_donation_stock
AFTER INSERT ON donation
FOR EACH ROW
BEGIN
  UPDATE relief_supplies SET quantity = quantity + NEW.quantity
  WHERE supply_id = NEW.supply_id;
END//

CREATE TRIGGER trg_shelter_check
BEFORE UPDATE ON shelter
FOR EACH ROW
BEGIN
  IF NEW.occupied > NEW.capacity THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shelter occupancy exceeds capacity';
  END IF;
END//

-- Stored procedure: assign request (used inside a transaction by PHP too)
CREATE PROCEDURE assign_request(IN p_req INT, IN p_team INT, IN p_vol INT)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
    INSERT INTO assignment(request_id, team_id, volunteer_id) VALUES (p_req, p_team, p_vol);
    UPDATE emergency_request SET status='assigned' WHERE request_id = p_req;
    IF p_team IS NOT NULL THEN UPDATE rescue_team SET status='deployed' WHERE team_id = p_team; END IF;
    IF p_vol  IS NOT NULL THEN UPDATE volunteers SET availability='busy' WHERE volunteer_id = p_vol; END IF;
  COMMIT;
END//
DELIMITER ;

-- Views
CREATE VIEW v_request_summary AS
SELECT r.request_id, u.name AS citizen, r.request_type, r.priority, r.location,
       r.status, r.created_at, t.team_name, vu.name AS volunteer
FROM emergency_request r
JOIN users u ON u.user_id = r.user_id
LEFT JOIN assignment a ON a.request_id = r.request_id
LEFT JOIN rescue_team t ON t.team_id = a.team_id
LEFT JOIN volunteers v ON v.volunteer_id = a.volunteer_id
LEFT JOIN users vu ON vu.user_id = v.user_id;

CREATE VIEW v_shelter_availability AS
SELECT s.shelter_id, s.name, d.name AS disaster, s.location, s.capacity, s.occupied,
       (s.capacity - s.occupied) AS free_beds
FROM shelter s JOIN disaster d ON d.disaster_id = s.disaster_id;

-- Seed data (admin login: admin@dr.com / admin123)
INSERT INTO users(name,email,phone,password,role) VALUES
('Administrator','admin@dr.com','0000000000','$2b$12$jhhzmRDUTuwA9UkU8TqTc.qQ550Fh2qrTYwK1UPLBO/c/siPk3L92','admin');
INSERT INTO disaster(name,type,location,severity,start_date) VALUES
('Kerala Floods','Flood','Kerala','high',CURDATE());
INSERT INTO shelter(disaster_id,name,location,capacity,occupied) VALUES
(1,'Government School Camp','Thiruvananthapuram',300,120);
INSERT INTO relief_supplies(shelter_id,item_name,quantity,unit) VALUES
(1,'Drinking Water',500,'litres'),(1,'Rice',200,'kg'),(1,'First Aid Kits',50,'boxes');
