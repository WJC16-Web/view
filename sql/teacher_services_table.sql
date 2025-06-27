-- 선생님별 서비스 테이블 생성
CREATE TABLE teacher_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    business_id INT NOT NULL,
    service_name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL, -- 업체에서 선택한 카테고리만 가능
    price_type ENUM('fixed', 'range') DEFAULT 'fixed', -- 고정가격 또는 범위가격
    price_min INT NOT NULL, -- 최소 금액
    price_max INT, -- 최대 금액 (범위가격인 경우)
    duration_min INT NOT NULL, -- 최소 소요시간 (분 단위)
    duration_max INT, -- 최대 소요시간 (분 단위)
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_teacher_active (teacher_id, is_active),
    INDEX idx_business_category (business_id, category)
);