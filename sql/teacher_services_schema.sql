-- 선생님별 서비스 관리를 위한 스키마 수정

-- 1. teachers 테이블 컬럼 수정
ALTER TABLE teachers 
    CHANGE COLUMN specialty specialties JSON,
    ADD COLUMN experience_years INT DEFAULT 0 AFTER specialties;

-- 2. 선생님별 서비스 테이블 생성
CREATE TABLE teacher_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    service_name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL, -- 전문분야와 매칭
    price_type ENUM('fixed', 'range') DEFAULT 'fixed',
    fixed_price INT, -- 고정 가격
    min_price INT, -- 최소 가격 (범위 가격 시)
    max_price INT, -- 최대 가격 (범위 가격 시)
    duration INT NOT NULL, -- 소요시간 (분 단위)
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_teacher_category (teacher_id, category),
    INDEX idx_teacher_active (teacher_id, is_active)
);

-- 3. 예약 테이블에 선생님 서비스 ID 컬럼 추가
ALTER TABLE reservations 
    ADD COLUMN teacher_service_id INT AFTER service_id,
    ADD FOREIGN KEY (teacher_service_id) REFERENCES teacher_services(id);

-- 4. 기존 business_services 데이터를 teacher_services로 마이그레이션하는 예시
-- (실제 데이터가 있을 경우 실행)
/*
INSERT INTO teacher_services (teacher_id, service_name, description, category, price_type, fixed_price, duration)
SELECT 
    t.id as teacher_id,
    bs.service_name,
    bs.description,
    bs.category,
    'fixed' as price_type,
    bs.price as fixed_price,
    bs.duration
FROM business_services bs
JOIN businesses b ON bs.business_id = b.id
JOIN teachers t ON t.business_id = b.id
WHERE bs.is_active = 1 AND t.is_active = 1;
*/