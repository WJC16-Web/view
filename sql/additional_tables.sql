-- 추가 테이블들 생성 SQL

-- 휴대폰 인증 테이블
CREATE TABLE IF NOT EXISTS phone_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(15) NOT NULL,
    verification_code VARCHAR(6) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_code (verification_code),
    INDEX idx_expires (expires_at)
);

-- 이메일 인증 테이블
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    is_verified TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- 이메일 발송 로그 테이블
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT,
    email_type ENUM('VERIFICATION', 'NOTIFICATION', 'MARKETING', 'SYSTEM') DEFAULT 'NOTIFICATION',
    status ENUM('SUCCESS', 'FAILED', 'PENDING') DEFAULT 'PENDING',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_type (email_type),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- 1:1 문의 테이블
CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category ENUM('reservation', 'payment', 'business', 'technical', 'other') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_private TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'answered', 'closed') DEFAULT 'pending',
    answer_content TEXT NULL,
    answered_by INT NULL,
    answered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (answered_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 쿠폰 시스템 테이블
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NULL, -- NULL이면 플랫폼 쿠폰
    coupon_code VARCHAR(50) NOT NULL UNIQUE,
    coupon_name VARCHAR(100) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0,
    maximum_discount DECIMAL(10,2) NULL,
    usage_limit INT NULL, -- 전체 사용 한도
    usage_per_user INT DEFAULT 1, -- 사용자당 사용 가능 횟수
    is_active TINYINT(1) DEFAULT 1,
    valid_from TIMESTAMP NOT NULL,
    valid_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_code (coupon_code),
    INDEX idx_business (business_id),
    INDEX idx_valid_period (valid_from, valid_until),
    INDEX idx_active (is_active)
);

-- 고객 쿠폰 보유 테이블
CREATE TABLE IF NOT EXISTS customer_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    coupon_id INT NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    used_at TIMESTAMP NULL,
    used_in_reservation INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (used_in_reservation) REFERENCES reservations(id) ON DELETE SET NULL,
    UNIQUE KEY unique_customer_coupon (customer_id, coupon_id),
    INDEX idx_customer (customer_id),
    INDEX idx_coupon (coupon_id),
    INDEX idx_used (is_used)
);

-- 적립금 시스템 테이블
CREATE TABLE IF NOT EXISTS points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    point_type ENUM('earn', 'use', 'expire', 'admin_adjust') NOT NULL,
    amount INT NOT NULL, -- 양수: 적립, 음수: 사용/차감
    balance INT NOT NULL, -- 거래 후 잔액
    description VARCHAR(255) NOT NULL,
    reservation_id INT NULL,
    expires_at TIMESTAMP NULL, -- 적립금 만료일
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_type (point_type),
    INDEX idx_expires (expires_at)
);

-- 이벤트/프로모션 테이블
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NULL, -- NULL이면 플랫폼 이벤트
    event_type ENUM('discount', 'point_bonus', 'free_service', 'flash_sale') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    banner_image VARCHAR(500) NULL,
    discount_rate DECIMAL(5,2) NULL, -- 할인율 (%)
    discount_amount DECIMAL(10,2) NULL, -- 할인 금액
    point_bonus_rate DECIMAL(5,2) NULL, -- 포인트 추가 적립율
    target_services JSON NULL, -- 대상 서비스 ID 배열
    target_user_types JSON NULL, -- 대상 회원 유형
    usage_limit INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business (business_id),
    INDEX idx_type (event_type),
    INDEX idx_period (start_date, end_date),
    INDEX idx_active (is_active)
);

-- 업체별 정책 설정 테이블
CREATE TABLE IF NOT EXISTS business_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    policy_type ENUM('cancellation', 'deposit', 'booking_time', 'auto_approval') NOT NULL,
    policy_data JSON NOT NULL, -- 정책 상세 설정 (JSON 형태)
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_policy (business_id, policy_type),
    INDEX idx_business (business_id),
    INDEX idx_type (policy_type)
);

-- 리뷰 신고 테이블
CREATE TABLE IF NOT EXISTS review_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    reporter_id INT NOT NULL,
    report_reason ENUM('inappropriate', 'fake', 'spam', 'offensive', 'other') NOT NULL,
    report_detail TEXT NULL,
    status ENUM('pending', 'reviewed', 'action_taken', 'dismissed') DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_review (review_id),
    INDEX idx_reporter (reporter_id),
    INDEX idx_status (status)
);

-- 고객 즐겨찾기 테이블
CREATE TABLE IF NOT EXISTS customer_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    business_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (customer_id, business_id),
    INDEX idx_customer (customer_id),
    INDEX idx_business (business_id)
);

-- 알림 설정 테이블
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sms_enabled TINYINT(1) DEFAULT 1,
    email_enabled TINYINT(1) DEFAULT 1,
    push_enabled TINYINT(1) DEFAULT 1,
    marketing_enabled TINYINT(1) DEFAULT 0,
    reservation_reminders TINYINT(1) DEFAULT 1,
    promotion_alerts TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
);

-- 푸시 알림 로그 테이블 확장
CREATE TABLE IF NOT EXISTS push_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    data JSON NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    device_token VARCHAR(255) NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (notification_type),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- 시스템 설정 테이블
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public TINYINT(1) DEFAULT 0, -- 공개 설정인지 여부
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
);

-- 업체 평가/평점 요약 테이블 (성능 최적화용)
CREATE TABLE IF NOT EXISTS business_ratings (
    business_id INT PRIMARY KEY,
    total_reviews INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    rating_1_count INT DEFAULT 0,
    rating_2_count INT DEFAULT 0,
    rating_3_count INT DEFAULT 0,
    rating_4_count INT DEFAULT 0,
    rating_5_count INT DEFAULT 0,
    average_service_rating DECIMAL(3,2) DEFAULT 0.00,
    average_kindness_rating DECIMAL(3,2) DEFAULT 0.00,
    average_cleanliness_rating DECIMAL(3,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- 기본 시스템 설정값 삽입
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', '뷰티예약', 'string', '사이트 이름', 1),
('site_description', '간편한 뷰티 예약 플랫폼', 'string', '사이트 설명', 1),
('admin_email', 'admin@beauty-booking.com', 'string', '관리자 이메일', 0),
('sms_api_provider', 'virtual', 'string', 'SMS API 제공업체', 0),
('email_api_provider', 'virtual', 'string', '이메일 API 제공업체', 0),
('point_earn_rate', '0.01', 'number', '포인트 적립율 (1%)', 1),
('review_point_bonus', '1000', 'number', '후기 작성 적립금', 1),
('vip_threshold', '10', 'number', 'VIP 등급 기준 (이용 횟수)', 1),
('waitlist_duration_days', '7', 'number', '대기열 유지 기간 (일)', 1),
('priority_reservation_minutes', '10', 'number', '우선 예약권 유효 시간 (분)', 1),
('auto_complete_hours', '2', 'number', '예약 자동 완료 시간 (시)', 1),
('maintenance_mode', 'false', 'boolean', '점검 모드', 0);

-- 기본 플랫폼 쿠폰 생성
INSERT INTO coupons (coupon_code, coupon_name, description, discount_type, discount_value, minimum_amount, usage_per_user, valid_from, valid_until) VALUES
('WELCOME20', '신규가입 20% 할인', '첫 예약 시 20% 할인 (최대 10,000원)', 'percentage', 20.00, 10000, 1, '2024-01-01 00:00:00', '2024-12-31 23:59:59'),
('REVIEW5000', '후기작성 5천원 할인', '후기 작성 후 다음 예약 시 5,000원 할인', 'fixed_amount', 5000.00, 20000, 1, '2024-01-01 00:00:00', '2024-12-31 23:59:59'),
('VIP10', 'VIP 고객 10% 할인', 'VIP 고객 전용 10% 할인 쿠폰', 'percentage', 10.00, 30000, 5, '2024-01-01 00:00:00', '2024-12-31 23:59:59');

-- 기본 업체 정책 템플릿
INSERT INTO business_policies (business_id, policy_type, policy_data) 
SELECT id, 'cancellation', JSON_OBJECT(
    'customer_cancel_hours', 2,
    'fee_rates', JSON_ARRAY(
        JSON_OBJECT('hours_before', 24, 'fee_rate', 0),
        JSON_OBJECT('hours_before', 2, 'fee_rate', 50),
        JSON_OBJECT('hours_before', 0, 'fee_rate', 100)
    )
) FROM businesses WHERE id <= 10;

INSERT INTO business_policies (business_id, policy_type, policy_data)
SELECT id, 'booking_time', JSON_OBJECT(
    'min_advance_hours', 2,
    'max_advance_days', 30,
    'same_day_booking', true
) FROM businesses WHERE id <= 10;

-- 알림 설정 기본값
INSERT INTO notification_settings (user_id, sms_enabled, email_enabled, push_enabled, marketing_enabled)
SELECT id, 1, 1, 1, 0 FROM users WHERE user_type = 'customer'; 