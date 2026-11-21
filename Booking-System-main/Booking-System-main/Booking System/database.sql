CREATE DATABASE IF NOT EXISTS casa_adelaida
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE casa_adelaida;

-- Customer records used by booking requests
CREATE TABLE IF NOT EXISTS customers (
    CustomerID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (CustomerID),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB;

-- Booking table schema
CREATE TABLE IF NOT EXISTS bookings (
    BookingID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    CustomerID BIGINT UNSIGNED NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    pool_size ENUM('Big Pool', 'Small Pool') NOT NULL DEFAULT 'Big Pool',
    tour_type ENUM('Day Tour', 'Overnight Tour') NOT NULL DEFAULT 'Day Tour',
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    check_in_time TIME NOT NULL DEFAULT '08:00:00',
    check_out_time TIME NOT NULL DEFAULT '17:00:00',
    status ENUM('Pending', 'Confirmed', 'Declined', 'Checked Out') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (BookingID),
    KEY idx_bookings_customer_id (CustomerID),
    KEY idx_bookings_status (status),
    KEY idx_bookings_check_in (check_in),
    KEY idx_bookings_email (email),
    CONSTRAINT fk_bookings_customer FOREIGN KEY (CustomerID) REFERENCES customers(CustomerID)
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
    AdminID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (AdminID),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS booking_status_history (
    StatusHistoryID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    BookingID BIGINT UNSIGNED NOT NULL,
    AdminID BIGINT UNSIGNED NULL,
    previous_status ENUM('Pending', 'Confirmed', 'Declined', 'Checked Out') NOT NULL,
    new_status ENUM('Pending', 'Confirmed', 'Declined', 'Checked Out') NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (StatusHistoryID),
    KEY idx_booking_status_history_booking (BookingID, changed_at),
    KEY idx_booking_status_history_admin (AdminID),
    CONSTRAINT fk_booking_status_history_booking FOREIGN KEY (BookingID) REFERENCES bookings(BookingID)
        ON DELETE CASCADE,
    CONSTRAINT fk_booking_status_history_admin FOREIGN KEY (AdminID) REFERENCES admin_users(AdminID)
        ON DELETE SET NULL
) ENGINE=InnoDB;
