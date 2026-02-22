-- Smart Parking - Seed Data
-- Pre-seeded users and parking spots

-- Users (password: password123)
-- bcrypt hash for 'password123'
INSERT INTO users (email, password_hash) VALUES
    ('driver1@parking.com', '$2y$10$nrJMb5RSBN1Sj5/T7xEUX.JHRQB/C2uPdMELcBDgnqHzSZd1/EKYa'),
    ('driver2@parking.com', '$2y$10$nrJMb5RSBN1Sj5/T7xEUX.JHRQB/C2uPdMELcBDgnqHzSZd1/EKYa')
ON DUPLICATE KEY UPDATE email = email;

-- 5 Parking Spots (as per assignment: 5 spots, 3 time slots per day)
INSERT INTO parking_spots (spot_number, floor_number, type) VALUES
    (1, 1, 'Regular'),
    (2, 1, 'Regular'),
    (3, 1, 'Regular'),
    (4, 1, 'Regular'),
    (5, 1, 'Regular')
ON DUPLICATE KEY UPDATE spot_number = spot_number;
