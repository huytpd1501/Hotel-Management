
-- Bảng Account
CREATE TABLE account (
  account_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE,
  password_hash VARCHAR(255),
  role ENUM('Admin','Staff') NOT NULL
);

-- Bảng Staff
CREATE TABLE staff (
  staff_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100),
  role VARCHAR(50),
  phone_number VARCHAR(20),
  email VARCHAR(100),
  account_id INT,
  FOREIGN KEY (account_id) REFERENCES account(account_id)
);

-- Bảng Customer
CREATE TABLE customer (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100),
  gender VARCHAR(10),
  phone_number VARCHAR(20),
  email VARCHAR(100),
  id_card VARCHAR(50),
  address VARCHAR(200)
);

-- Bảng Room
CREATE TABLE room (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  room_number VARCHAR(10) UNIQUE,
  room_type VARCHAR(50),
  status ENUM('AVAILABLE','BOOKED','IN_USE') DEFAULT 'AVAILABLE',
  price_per_night INT,
  description VARCHAR(200)
);

-- Bảng Booking
CREATE TABLE booking (
  booking_id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT,
  room_id INT,
  checkin_date DATE,
  checkout_date DATE,
  booking_date DATE,
  status ENUM('PENDING','CONFIRMED','CANCELLED') DEFAULT 'PENDING',
  FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
  FOREIGN KEY (room_id) REFERENCES room(room_id)
);

-- Bảng Service
CREATE TABLE service (
  service_id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(100),
  unit_price INT,
  description VARCHAR(200)
);

-- Bảng Service Usage
CREATE TABLE service_usage (
  usage_id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT,
  service_id INT,
  quantity INT,
  usage_date DATE,
  FOREIGN KEY (booking_id) REFERENCES booking(booking_id),
  FOREIGN KEY (service_id) REFERENCES service(service_id)
);

-- Bảng Invoice
CREATE TABLE invoice (
  invoice_id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT,
  booking_id INT,
  total_amount INT,
  created_date DATE,
  payment_status ENUM('PAID','UNPAID') DEFAULT 'UNPAID',
  FOREIGN KEY (staff_id) REFERENCES staff(staff_id),
  FOREIGN KEY (booking_id) REFERENCES booking(booking_id)
);

-- Thêm dữ liệu mẫu
INSERT INTO account (username, password_hash, role) VALUES
('admin', MD5('123456'), 'Admin'),
('staff1', MD5('123456'), 'Staff');

INSERT INTO staff (full_name, role, phone_number, email, account_id) VALUES
('Nguyễn Văn A', 'Lễ tân', '0901234567', 'a@example.com', 2);

INSERT INTO customer (full_name, gender, phone_number, email, id_card, address) VALUES
('Trần Thảo My', 'Nữ', '0909123456', 'my@example.com', '0790xxxx', 'Q.1, TP.HCM');

INSERT INTO room (room_number, room_type, status, price_per_night, description) VALUES
('101', 'Deluxe', 'AVAILABLE', 1200000, 'Giường Queen, view phố'),
('102', 'Standard', 'BOOKED', 800000, 'Giường đôi, gần thang máy'),
('103', 'VIP', 'AVAILABLE', 2500000, 'Suite rộng, view sông'),
('201', 'Deluxe', 'IN_USE', 1300000, 'Giường King, ban công');

INSERT INTO service (service_name, unit_price, description) VALUES
('Ăn sáng buffet', 120000, 'Ăn sáng tự chọn'),
('Spa', 450000, '60 phút massage'),
('Đưa đón sân bay', 300000, '1 chiều');

INSERT INTO booking (customer_id, room_id, checkin_date, checkout_date, booking_date, status) VALUES
(1, 2, CURDATE(), CURDATE(), CURDATE(), 'CONFIRMED');

INSERT INTO service_usage (booking_id, service_id, quantity, usage_date) VALUES
(1, 1, 2, CURDATE());

INSERT INTO invoice (staff_id, booking_id, total_amount, created_date, payment_status) VALUES
(1, 1, 900000, CURDATE(), 'PAID');
