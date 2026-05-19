-- ============================================================
--  DATABASE: admission_system — DiemChuan.vn v2
-- ============================================================
CREATE DATABASE IF NOT EXISTS admission_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE admission_system;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') DEFAULT 'admin',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mật khẩu: admin123
INSERT INTO users (username,password,role) VALUES
('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin');

CREATE TABLE universities (
  university_id INT AUTO_INCREMENT PRIMARY KEY,
  university_name VARCHAR(255) NOT NULL,
  university_code VARCHAR(20) UNIQUE,
  logo VARCHAR(255) DEFAULT NULL,
  description TEXT,
  province VARCHAR(100) DEFAULT 'TP. Hồ Chí Minh',
  address VARCHAR(255),
  website VARCHAR(255),
  school_type ENUM('Công lập','Dân lập','Tư thục','Quốc tế') DEFAULT 'Công lập',
  is_featured TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO universities (university_name,university_code,description,province,address,website,school_type,is_featured) VALUES
('Đại học Bách Khoa TP.HCM',     'BKU',  'Trường kỹ thuật hàng đầu miền Nam',          'TP. Hồ Chí Minh','268 Lý Thường Kiệt, Q.10','https://hcmut.edu.vn', 'Công lập',1),
('Đại học Kinh tế TP.HCM',       'UEH',  'Trường kinh tế uy tín hàng đầu VN',          'TP. Hồ Chí Minh','59C Nguyễn Đình Chiểu, Q.3','https://ueh.edu.vn','Công lập',1),
('Đại học Công nghiệp TP.HCM',   'IUH',  'Trường đa ngành lớn nhất TP.HCM',           'TP. Hồ Chí Minh','12 Nguyễn Văn Bảo, Gò Vấp','https://iuh.edu.vn','Công lập',1),
('Đại học Bách Khoa Hà Nội',     'HUST', 'Trường kỹ thuật hàng đầu miền Bắc',          'Hà Nội',         '1 Đại Cồ Việt, Hai Bà Trưng','https://hust.edu.vn','Công lập',1),
('Đại học Kinh tế Quốc dân',     'NEU',  'Trường kinh tế hàng đầu phía Bắc',           'Hà Nội',         '207 Giải Phóng, Hai Bà Trưng','https://neu.edu.vn','Công lập',1),
('Đại học Ngoại thương',         'FTU',  'Trường ngoại thương uy tín nhất VN',          'Hà Nội',         '91 Chùa Láng, Đống Đa','https://ftu.edu.vn','Công lập',1),
('Đại học Y Hà Nội',             'HMU',  'Trường y khoa danh tiếng hàng đầu',           'Hà Nội',         '1 Tôn Thất Tùng, Đống Đa','https://hmu.edu.vn','Công lập',1),
('Đại học Khoa học Tự nhiên HCM','HCMUS','Trường khoa học cơ bản phía Nam',            'TP. Hồ Chí Minh','227 Nguyễn Văn Cừ, Q.5','https://hcmus.edu.vn','Công lập',0),
('Đại học Sư phạm TP.HCM',       'HCMUE','Đào tạo giáo viên chất lượng cao',           'TP. Hồ Chí Minh','280 An Dương Vương, Q.5','https://hcmue.edu.vn','Công lập',0),
('Đại học Đà Nẵng',              'UD',   'Trung tâm đào tạo lớn nhất miền Trung',       'Đà Nẵng',        '41 Lê Duẩn, Hải Châu','https://udn.vn','Công lập',0),
('Đại học FPT',                  'FPTU', 'Trường tư thục công nghệ hàng đầu',           'Hà Nội',         'Khu CNC Hòa Lạc, Hà Nội','https://fpt.edu.vn','Tư thục',1),
('Đại học RMIT Việt Nam',        'RMIT', 'Trường quốc tế uy tín',                      'TP. Hồ Chí Minh','702 Nguyễn Văn Linh, Q.7','https://rmit.edu.vn','Quốc tế',0);

CREATE TABLE majors (
  major_id INT AUTO_INCREMENT PRIMARY KEY,
  major_name VARCHAR(255) NOT NULL,
  major_code VARCHAR(20),
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO majors (major_name,major_code) VALUES
('Công nghệ thông tin','7480201'),('Kỹ thuật phần mềm','7480103'),
('Hệ thống thông tin','7480104'),('Khoa học máy tính','7480101'),
('Kỹ thuật điện','7520201'),('Kỹ thuật cơ khí','7520103'),
('Kỹ thuật điện tử viễn thông','7520207'),('Tài chính – Ngân hàng','7340201'),
('Kế toán','7340301'),('Quản trị kinh doanh','7340101'),
('Kinh doanh quốc tế','7340120'),('Marketing','7340115'),
('Y đa khoa','7720101'),('Dược học','7720201'),
('Giáo dục tiểu học','7140202'),('Ngôn ngữ Anh','7220201'),
('Luật','7380101'),('Kiến trúc','7580101');

CREATE TABLE admission_scores (
  score_id INT AUTO_INCREMENT PRIMARY KEY,
  university_id INT NOT NULL,
  major_id INT NOT NULL,
  year YEAR NOT NULL,
  combination VARCHAR(10) NOT NULL,
  score DECIMAL(4,2) NOT NULL,
  quota INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (university_id) REFERENCES universities(university_id) ON DELETE CASCADE,
  FOREIGN KEY (major_id) REFERENCES majors(major_id) ON DELETE CASCADE,
  UNIQUE KEY uq_score (university_id,major_id,year,combination)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admission_scores (university_id,major_id,year,combination,score,quota) VALUES
(1,1,2024,'A00',27.20,500),(1,1,2023,'A00',26.80,480),(1,1,2022,'A00',26.50,460),(1,1,2021,'A00',26.00,450),(1,1,2020,'A00',25.50,430),
(1,2,2024,'A01',26.40,280),(1,2,2023,'A01',25.90,260),(1,2,2022,'A01',25.50,250),
(1,4,2024,'A00',25.50,400),(1,4,2023,'A00',25.00,380),(1,4,2022,'A00',24.80,360),
(1,5,2024,'A00',24.80,350),(1,5,2023,'A00',24.30,330),
(1,3,2024,'A01',26.00,200),(1,3,2023,'A01',25.60,190),
(2,8,2024,'A01',26.75,450),(2,8,2023,'A01',26.40,430),(2,8,2022,'A01',26.00,400),(2,8,2021,'A01',25.50,380),(2,8,2020,'A01',24.80,360),
(2,9,2024,'D01',25.80,380),(2,9,2023,'D01',25.20,360),(2,9,2022,'D01',24.80,340),
(2,10,2024,'A01',25.20,300),(2,10,2023,'A01',24.80,280),
(2,11,2024,'D01',26.00,250),(2,11,2023,'D01',25.60,230),
(3,1,2024,'A00',22.50,600),(3,1,2023,'A00',21.80,580),(3,1,2022,'A00',21.00,550),(3,1,2021,'A00',20.50,520),
(3,2,2024,'A00',22.00,400),(3,2,2023,'A00',21.50,380),
(3,10,2024,'D01',20.50,500),(3,10,2023,'D01',20.00,480),
(4,1,2024,'A00',27.50,350),(4,1,2023,'A00',27.00,330),(4,1,2022,'A00',26.80,310),(4,1,2021,'A00',26.50,300),
(4,5,2024,'A00',26.80,300),(4,5,2023,'A00',26.20,280),
(4,7,2024,'A00',26.00,250),(4,7,2023,'A00',25.50,230),
(5,10,2024,'A01',26.90,300),(5,10,2023,'A01',26.50,280),(5,10,2022,'A01',26.00,260),
(5,8,2024,'A01',26.40,350),(5,8,2023,'A01',26.00,330),
(6,11,2024,'D01',27.80,200),(6,11,2023,'D01',27.40,190),(6,11,2022,'D01',27.00,180),(6,11,2021,'D01',26.50,170),
(6,16,2024,'D01',27.50,150),(6,16,2023,'D01',27.00,140),
(7,13,2024,'B00',28.72,150),(7,13,2023,'B00',28.50,140),(7,13,2022,'B00',28.20,130),(7,13,2021,'B00',27.80,120),
(7,14,2024,'B00',26.50,100),(7,14,2023,'B00',26.00,95),
(8,1,2024,'A00',26.20,200),(8,1,2023,'A00',25.80,190),(8,1,2022,'A00',25.50,180),
(9,15,2024,'C00',22.00,250),(9,15,2023,'C00',21.50,230),
(9,16,2024,'D01',24.50,200),(9,16,2023,'D01',24.00,190),
(10,1,2024,'A00',23.50,300),(10,1,2023,'A00',23.00,280),
(10,10,2024,'A01',22.80,250),(10,10,2023,'A01',22.20,230),
(11,1,2024,'A00',24.00,400),(11,1,2023,'A00',23.50,380),
(11,2,2024,'A01',23.50,350),(11,2,2023,'A01',23.00,330),
(11,11,2024,'D01',22.00,300),(11,11,2023,'D01',21.50,280);

CREATE TABLE ai_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_score DECIMAL(4,2),
  combination VARCHAR(10),
  province VARCHAR(100),
  suggested_result TEXT,
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
