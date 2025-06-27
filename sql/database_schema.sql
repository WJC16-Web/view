-- ============================================
-- 뷰티 예약 시스템 데이터베이스 스키마
-- ============================================

-- 데이터베이스 생성
CREATE DATABASE IF NOT EXISTS view 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE view;

-- ============================================
-- 1. 회원 관련 테이블
-- ============================================

-- 기본 사용자 테이블
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    user_type ENUM('customer', 'business_owner', 'teacher', 'admin') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    phone_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type)
);

-- 고객 상세 정보
CREATE TABLE customer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    birth_date DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    interests JSON, -- 관심 업종 저장
    total_points INT DEFAULT 0, -- 적립금
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 업체 관리자 정보
CREATE TABLE business_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_license VARCHAR(50) NOT NULL, -- 사업자등록번호
    business_name VARCHAR(200) NOT NULL,
    representative_name VARCHAR(100) NOT NULL,
    is_approved TINYINT(1) DEFAULT 0, -- 관리자 승인 여부
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_business_license (business_license)
);

-- ============================================
-- 2. 업체 관련 테이블
-- ============================================

-- 업체 기본 정보
CREATE TABLE businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    address VARCHAR(500) NOT NULL,
    latitude DECIMAL(10, 8), -- 위도
    longitude DECIMAL(11, 8), -- 경도
    phone VARCHAR(20),
    business_hours JSON, -- 요일별 운영시간
    category VARCHAR(100) NOT NULL, -- 주 업종
    subcategories JSON, -- 세부 업종들
    is_active TINYINT(1) DEFAULT 1,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES business_owners(id) ON DELETE CASCADE,
    INDEX idx_location (latitude, longitude),
    INDEX idx_category (category),
    INDEX idx_active_approved (is_active, is_approved)
);

-- 업체 서비스 메뉴
CREATE TABLE business_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    service_name VARCHAR(200) NOT NULL,
    description TEXT,
    price INT NOT NULL, -- 가격 (원 단위)
    duration INT NOT NULL, -- 소요시간 (분 단위)
    category VARCHAR(100) NOT NULL, -- 서비스 카테고리
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_category (business_id, category)
);

-- 업체 사진
CREATE TABLE business_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    photo_type ENUM('main', 'interior', 'exterior', 'service') DEFAULT 'interior',
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_type (business_id, photo_type)
);

-- 업체 정책
CREATE TABLE business_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    cancellation_policy JSON, -- 취소 정책
    deposit_policy JSON, -- 예약금 정책
    booking_rules JSON, -- 예약 규칙
    auto_approve TINYINT(1) DEFAULT 0, -- 자동 승인 여부
    min_booking_time INT DEFAULT 2, -- 최소 예약 시간 (몇 시간 전)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- ============================================
-- 3. 선생님 관련 테이블
-- ============================================

-- 선생님 정보
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    specialty VARCHAR(200), -- 전문분야
    career VARCHAR(500), -- 경력
    introduction TEXT, -- 소개글
    is_active TINYINT(1) DEFAULT 1,
    is_approved TINYINT(1) DEFAULT 0, -- 업체 승인 여부
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_active (business_id, is_active)
);

-- 선생님 정기 스케줄
CREATE TABLE teacher_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    day_of_week TINYINT NOT NULL, -- 0=일요일, 1=월요일, ..., 6=토요일
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_start TIME,
    break_end TIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_teacher_day (teacher_id, day_of_week)
);

-- 선생님 예외 일정 (휴무, 특별 근무 등)
CREATE TABLE teacher_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    exception_date DATE NOT NULL,
    exception_type ENUM('off', 'special_hours', 'blocked') NOT NULL,
    start_time TIME,
    end_time TIME,
    reason VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_teacher_date (teacher_id, exception_date)
);

-- ============================================
-- 4. 예약 관련 테이블
-- ============================================

-- 예약 정보
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    business_id INT NOT NULL,
    teacher_id INT NOT NULL,
    service_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_amount INT NOT NULL,
    deposit_amount INT DEFAULT 0,
    status ENUM('pending', 'confirmed', 'cancelled', 'rejected', 'completed') DEFAULT 'pending',
    customer_request TEXT, -- 고객 요청사항
    rejection_reason TEXT, -- 거절 사유
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (service_id) REFERENCES business_services(id),
    INDEX idx_customer_status (customer_id, status),
    INDEX idx_business_date (business_id, reservation_date),
    INDEX idx_teacher_datetime (teacher_id, reservation_date, start_time),
    INDEX idx_status_date (status, reservation_date)
);

-- 예약 상태 변경 이력
CREATE TABLE reservation_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL, -- 변경한 사용자 ID
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_reservation_id (reservation_id)
);

-- ============================================
-- 5. 결제 관련 테이블
-- ============================================

-- 결제 정보
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    payment_method ENUM('card', 'kakaopay', 'naverpay', 'cash') NOT NULL,
    amount INT NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100), -- 외부 결제 시스템 거래 ID
    paid_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_status (status)
);

-- 쿠폰 정보
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT, -- NULL이면 플랫폼 쿠폰
    coupon_name VARCHAR(200) NOT NULL,
    coupon_code VARCHAR(50) UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value INT NOT NULL, -- 할인 값 (% 또는 원)
    min_order_amount INT DEFAULT 0, -- 최소 주문 금액
    max_discount_amount INT, -- 최대 할인 금액
    valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP NOT NULL,
    usage_limit INT, -- 사용 제한 횟수
    used_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    INDEX idx_code_active (coupon_code, is_active),
    INDEX idx_business_active (business_id, is_active)
);

-- 고객별 쿠폰 보유
CREATE TABLE customer_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    coupon_id INT NOT NULL,
    used_at TIMESTAMP NULL,
    reservation_id INT, -- 사용된 예약
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    INDEX idx_customer_used (customer_id, is_used)
);

-- 적립금 내역
CREATE TABLE point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    transaction_type ENUM('earn', 'use', 'refund') NOT NULL,
    amount INT NOT NULL, -- 양수: 적립, 음수: 사용
    description VARCHAR(200),
    reservation_id INT, -- 관련 예약
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    INDEX idx_customer_type (customer_id, transaction_type)
);

-- ============================================
-- 6. 후기 관련 테이블
-- ============================================

-- 후기 정보
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    customer_id INT NOT NULL,
    business_id INT NOT NULL,
    teacher_id INT NOT NULL,
    overall_rating TINYINT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    service_rating TINYINT NOT NULL CHECK (service_rating >= 1 AND service_rating <= 5),
    kindness_rating TINYINT NOT NULL CHECK (kindness_rating >= 1 AND kindness_rating <= 5),
    cleanliness_rating TINYINT NOT NULL CHECK (cleanliness_rating >= 1 AND cleanliness_rating <= 5),
    content TEXT,
    is_hidden TINYINT(1) DEFAULT 0, -- 숨김 처리
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    UNIQUE KEY uk_reservation_review (reservation_id),
    INDEX idx_business_rating (business_id, overall_rating),
    INDEX idx_teacher_rating (teacher_id, overall_rating)
);

-- 후기 사진
CREATE TABLE review_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    INDEX idx_review_id (review_id)
);

-- 후기 신고
CREATE TABLE review_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    reporter_id INT NOT NULL,
    report_reason ENUM('inappropriate', 'fake', 'offensive', 'spam', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id),
    FOREIGN KEY (reporter_id) REFERENCES users(id),
    INDEX idx_review_status (review_id, status)
);

-- ============================================
-- 7. 알림 관련 테이블
-- ============================================

-- 알림 정보
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_id INT, -- 관련된 예약이나 업체 ID
    related_type VARCHAR(50), -- 관련 타입 (reservation, business, etc)
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user_created (user_id, created_at)
);

-- 알림 설정
CREATE TABLE notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sms_enabled TINYINT(1) DEFAULT 1,
    push_enabled TINYINT(1) DEFAULT 1,
    email_enabled TINYINT(1) DEFAULT 1,
    marketing_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY uk_user_settings (user_id)
);

-- ============================================
-- 8. 지역 관련 테이블
-- ============================================

-- 지역 정보 (시/도 - 구/군 - 동)
CREATE TABLE regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_type ENUM('sido', 'sigungu', 'dong') NOT NULL,
    region_name VARCHAR(100) NOT NULL,
    parent_id INT NULL, -- 상위 지역 ID
    region_code VARCHAR(20), -- 행정구역 코드
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES regions(id),
    INDEX idx_type_parent (region_type, parent_id),
    INDEX idx_name (region_name)
);

-- 지역 데이터 입력 (서울특별시 예시)
INSERT INTO regions (region_type, region_name, parent_id, region_code) VALUES
-- 시/도
('sido', '서울특별시', NULL, '11'),
('sido', '부산광역시', NULL, '26'),
('sido', '대구광역시', NULL, '27'),
('sido', '인천광역시', NULL, '28'),
('sido', '광주광역시', NULL, '29'),
('sido', '대전광역시', NULL, '30'),
('sido', '울산광역시', NULL, '31'),
('sido', '세종특별자치시', NULL, '36'),
('sido', '경기도', NULL, '41'),
('sido', '강원도', NULL, '42'),
('sido', '충청북도', NULL, '43'),
('sido', '충청남도', NULL, '44'),
('sido', '전라북도', NULL, '45'),
('sido', '전라남도', NULL, '46'),
('sido', '경상북도', NULL, '47'),
('sido', '경상남도', NULL, '48'),
('sido', '제주특별자치도', NULL, '50');

-- 서울특별시 구/군
INSERT INTO regions (region_type, region_name, parent_id, region_code) VALUES
('sigungu', '종로구', 1, '11110'),
('sigungu', '중구', 1, '11140'),
('sigungu', '용산구', 1, '11170'),
('sigungu', '성동구', 1, '11200'),
('sigungu', '광진구', 1, '11215'),
('sigungu', '동대문구', 1, '11230'),
('sigungu', '중랑구', 1, '11260'),
('sigungu', '성북구', 1, '11290'),
('sigungu', '강북구', 1, '11305'),
('sigungu', '도봉구', 1, '11320'),
('sigungu', '노원구', 1, '11350'),
('sigungu', '은평구', 1, '11380'),
('sigungu', '서대문구', 1, '11410'),
('sigungu', '마포구', 1, '11440'),
('sigungu', '양천구', 1, '11470'),
('sigungu', '강서구', 1, '11500'),
('sigungu', '구로구', 1, '11530'),
('sigungu', '금천구', 1, '11545'),
('sigungu', '영등포구', 1, '11560'),
('sigungu', '동작구', 1, '11590'),
('sigungu', '관악구', 1, '11620'),
('sigungu', '서초구', 1, '11650'),
('sigungu', '강남구', 1, '11680'),
('sigungu', '송파구', 1, '11710'),
('sigungu', '강동구', 1, '11740');

-- 성동구 동 (예시)
INSERT INTO regions (region_type, region_name, parent_id, region_code) VALUES
('dong', '왕십리동', 21, '1120010100'),
('dong', '왕십리도선동', 21, '1120010200'),
('dong', '마장동', 21, '1120010300'),
('dong', '사근동', 21, '1120010400'),
('dong', '행당동', 21, '1120010500'),
('dong', '응봉동', 21, '1120010600'),
('dong', '금호동1가', 21, '1120010700'),
('dong', '금호동2가', 21, '1120010800'),
('dong', '금호동3가', 21, '1120010900'),
('dong', '금호동4가', 21, '1120011000'),
('dong', '옥수동', 21, '1120011100'),
('dong', '성수동1가', 21, '1120011200'),
('dong', '성수동2가', 21, '1120011300'),
('dong', '송정동', 21, '1120011400'),
('dong', '용답동', 21, '1120011500');

-- 강남구 동 (예시)
INSERT INTO regions (region_type, region_name, parent_id, region_code) VALUES
('dong', '신사동', 40, '1168010100'),
('dong', '논현동', 40, '1168010200'),
('dong', '압구정동', 40, '1168010300'),
('dong', '청담동', 40, '1168010400'),
('dong', '삼성동', 40, '1168010500'),
('dong', '대치동', 40, '1168010600'),
('dong', '역삼동', 40, '1168010700'),
('dong', '도곡동', 40, '1168010800'),
('dong', '개포동', 40, '1168010900'),
('dong', '세곡동', 40, '1168011000'),
('dong', '일원동', 40, '1168011100'),
('dong', '수서동', 40, '1168011200');

-- ============================================
-- 9. 대기열 시스템
-- ============================================

-- 예약 대기열
CREATE TABLE reservation_waitlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    business_id INT NOT NULL,
    teacher_id INT,
    service_id INT NOT NULL,
    desired_date DATE NOT NULL,
    desired_time TIME NOT NULL,
    priority INT DEFAULT 0, -- 우선순위 (VIP 고객 등)
    status ENUM('waiting', 'notified', 'expired', 'converted') DEFAULT 'waiting',
    notified_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (service_id) REFERENCES business_services(id),
    INDEX idx_business_date_time (business_id, desired_date, desired_time),
    INDEX idx_customer_status (customer_id, status)
);

-- ============================================
-- 10. 예약 변경 시스템  
-- ============================================

-- 예약 변경 요청
CREATE TABLE reservation_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    change_type ENUM('datetime', 'service', 'teacher') NOT NULL,
    original_value JSON NOT NULL, -- 기존 값들
    new_value JSON NOT NULL, -- 변경 요청 값들
    change_reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_by INT NOT NULL, -- 요청자 (고객 또는 업체)
    processed_by INT, -- 처리자
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    INDEX idx_reservation_status (reservation_id, status)
);

-- ============================================
-- 11. SMS/알림 로그 테이블
-- ============================================

-- SMS 발송 로그 (가상)
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sms_type VARCHAR(50) NOT NULL, -- 예약확정, 취소알림 등
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_type (user_id, sms_type),
    INDEX idx_status_created (status, created_at)
);

-- 푸시 알림 로그 (가상)
CREATE TABLE push_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    push_type VARCHAR(50) NOT NULL,
    device_token VARCHAR(255), -- FCM 토큰 등
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_type (user_id, push_type),
    INDEX idx_status_created (status, created_at)
);

-- ============================================
-- 12. 시스템 설정 및 관리
-- ============================================

-- 시스템 설정
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(200),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 관리자 로그
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50), -- user, business, reservation 등
    target_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id),
    INDEX idx_admin_created (admin_id, created_at),
    INDEX idx_target (target_type, target_id)
);

-- ============================================
-- 기본 데이터 삽입
-- ============================================

-- 기본 시스템 설정
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('platform_name', '뷰티북', '플랫폼 이름'),
('commission_rate', '5', '수수료 비율 (%)'),
('point_earn_rate', '1', '적립 비율 (%)'),
('review_point_reward', '1000', '후기 작성 적립금'),
('min_booking_hours', '2', '최소 예약 시간 (시간)'),
('max_booking_days', '30', '최대 예약 가능 일수');

-- 기본 지역 데이터 (서울특별시 일부)
INSERT INTO regions (name, level, code) VALUES 
('서울특별시', 1, '11'),
('경기도', 1, '41'),
('인천광역시', 1, '28');

INSERT INTO regions (parent_id, name, level, code) VALUES 
(1, '강남구', 2, '11680'),
(1, '성동구', 2, '11200'),
(1, '마포구', 2, '11440'),
(1, '홍대구', 2, '11170');

INSERT INTO regions (parent_id, name, level, code) VALUES 
(4, '성수동1가', 3, '1120051'),
(4, '성수동2가', 3, '1120052'),
(4, '왕십리동', 3, '1120060');

-- 기본 관리자 계정 생성 (비밀번호: admin123!)
INSERT INTO users (email, password, name, phone, user_type) VALUES 
('admin@beautybook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '시스템관리자', '010-0000-0000', 'admin');

-- ============================================
-- 인덱스 최적화
-- ============================================

-- 복합 인덱스 추가
ALTER TABLE reservations ADD INDEX idx_datetime_status (reservation_date, start_time, status);
ALTER TABLE businesses ADD INDEX idx_category_active (category, is_active, is_approved);
ALTER TABLE teachers ADD INDEX idx_business_approved (business_id, is_approved, is_active);

-- 전문 검색을 위한 풀텍스트 인덱스
ALTER TABLE businesses ADD FULLTEXT(name, description);
ALTER TABLE business_services ADD FULLTEXT(service_name, description);

-- ============================================
-- 스키마 생성 완료
-- ============================================ 

-- ============================================
-- 추가 테이블들
-- ============================================

-- SMS 로그 테이블
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sms_type VARCHAR(50) DEFAULT 'general',
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- 푸시 알림 로그 테이블
CREATE TABLE push_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    push_type VARCHAR(50) DEFAULT 'general',
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- 예약 변경 요청 테이블
CREATE TABLE reservation_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    change_type ENUM('datetime', 'service', 'teacher') NOT NULL,
    original_value JSON NOT NULL,
    new_value JSON NOT NULL,
    change_reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_by INT NOT NULL,
    processed_by INT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_status (status),
    INDEX idx_requested_by (requested_by)
);

-- 예약 대기열 테이블
CREATE TABLE reservation_waitlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    business_id INT NOT NULL,
    teacher_id INT NULL,
    service_id INT NOT NULL,
    desired_date DATE NOT NULL,
    desired_time TIME NOT NULL,
    priority INT DEFAULT 0, -- 0: 일반, 1: VIP
    status ENUM('waiting', 'notified', 'expired', 'converted') DEFAULT 'waiting',
    notified_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (service_id) REFERENCES business_services(id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_business_id (business_id),
    INDEX idx_status (status),
    INDEX idx_desired_datetime (desired_date, desired_time),
    INDEX idx_expires_at (expires_at)
);

-- 선생님 예외 일정 테이블 (이미 있는지 확인 후 추가)
CREATE TABLE IF NOT EXISTS teacher_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    exception_date DATE NOT NULL,
    exception_type ENUM('off', 'special_hours', 'blocked') NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    UNIQUE KEY unique_teacher_date (teacher_id, exception_date),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_exception_date (exception_date)
);

-- 업체에 latitude, longitude 컬럼 추가 (없는 경우)
ALTER TABLE businesses 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL,
ADD COLUMN IF NOT EXISTS is_rejected TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS rejected_by INT NULL,
ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL,
ADD INDEX IF NOT EXISTS idx_coordinates (latitude, longitude);

-- 지역 데이터 샘플 (서울특별시 예시)
INSERT IGNORE INTO regions (region_type, region_name, parent_id, region_code) VALUES
-- 시/도
('sido', '서울특별시', NULL, '11'),
('sido', '부산광역시', NULL, '26'),
('sido', '대구광역시', NULL, '27'),
('sido', '인천광역시', NULL, '28'),
('sido', '광주광역시', NULL, '29'),
('sido', '대전광역시', NULL, '30'),
('sido', '울산광역시', NULL, '31'),
('sido', '세종특별자치시', NULL, '36'),
('sido', '경기도', NULL, '41'),
('sido', '강원도', NULL, '42'),

-- 서울특별시 구/군
(SELECT r.id FROM regions r WHERE r.region_name = '서울특별시' AND r.region_type = 'sido'),

-- 서울 구별 데이터 입력을 위한 변수 설정
SET @seoul_id = (SELECT id FROM regions WHERE region_name = '서울특별시' AND region_type = 'sido');

INSERT IGNORE INTO regions (region_type, region_name, parent_id, region_code) VALUES
('sigungu', '종로구', @seoul_id, '11110'),
('sigungu', '중구', @seoul_id, '11140'),
('sigungu', '용산구', @seoul_id, '11170'),
('sigungu', '성동구', @seoul_id, '11200'),
('sigungu', '광진구', @seoul_id, '11215'),
('sigungu', '동대문구', @seoul_id, '11230'),
('sigungu', '중랑구', @seoul_id, '11260'),
('sigungu', '성북구', @seoul_id, '11290'),
('sigungu', '강북구', @seoul_id, '11305'),
('sigungu', '도봉구', @seoul_id, '11320'),
('sigungu', '노원구', @seoul_id, '11350'),
('sigungu', '은평구', @seoul_id, '11380'),
('sigungu', '서대문구', @seoul_id, '11410'),
('sigungu', '마포구', @seoul_id, '11440'),
('sigungu', '양천구', @seoul_id, '11470'),
('sigungu', '강서구', @seoul_id, '11500'),
('sigungu', '구로구', @seoul_id, '11530'),
('sigungu', '금천구', @seoul_id, '11545'),
('sigungu', '영등포구', @seoul_id, '11560'),
('sigungu', '동작구', @seoul_id, '11590'),
('sigungu', '관악구', @seoul_id, '11620'),
('sigungu', '서초구', @seoul_id, '11650'),
('sigungu', '강남구', @seoul_id, '11680'),
('sigungu', '송파구', @seoul_id, '11710'),
('sigungu', '강동구', @seoul_id, '11740');

-- 성동구 동 데이터
SET @seongdong_id = (SELECT id FROM regions WHERE region_name = '성동구' AND region_type = 'sigungu');

INSERT IGNORE INTO regions (region_type, region_name, parent_id, region_code) VALUES
('dong', '왕십리동', @seongdong_id, '1120051'),
('dong', '성수동1가', @seongdong_id, '1120052'),
('dong', '성수동2가', @seongdong_id, '1120053'),
('dong', '뚝섬동', @seongdong_id, '1120054'),
('dong', '용답동', @seongdong_id, '1120055'),
('dong', '사근동', @seongdong_id, '1120056'),
('dong', '행당동', @seongdong_id, '1120057'),
('dong', '응봉동', @seongdong_id, '1120058'),
('dong', '금정동', @seongdong_id, '1120059'),
('dong', '옥수동', @seongdong_id, '1120060'),
('dong', '성수동', @seongdong_id, '1120061');

-- 강남구 동 데이터
SET @gangnam_id = (SELECT id FROM regions WHERE region_name = '강남구' AND region_type = 'sigungu');

INSERT IGNORE INTO regions (region_type, region_name, parent_id, region_code) VALUES
('dong', '신사동', @gangnam_id, '1168051'),
('dong', '논현동', @gangnam_id, '1168052'),
('dong', '압구정동', @gangnam_id, '1168053'),
('dong', '청담동', @gangnam_id, '1168054'),
('dong', '삼성동', @gangnam_id, '1168055'),
('dong', '대치동', @gangnam_id, '1168056'),
('dong', '역삼동', @gangnam_id, '1168057'),
('dong', '도곡동', @gangnam_id, '1168058'),
('dong', '개포동', @gangnam_id, '1168059'),
('dong', '일원동', @gangnam_id, '1168060'),
('dong', '수서동', @gangnam_id, '1168061'); 