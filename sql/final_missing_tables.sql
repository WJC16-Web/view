-- 최종 누락된 테이블들 생성 SQL

-- 선생님 실시간 상태 로그 테이블
CREATE TABLE IF NOT EXISTS teacher_status_logs (
    teacher_id INT PRIMARY KEY,
    status ENUM('available', 'busy', 'break', 'offline') NOT NULL DEFAULT 'offline',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_changed_at (changed_at)
);

-- 예약 임시 잠금 테이블 (동시 예약 방지)
CREATE TABLE IF NOT EXISTS reservation_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    locked_by INT NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lock (teacher_id, reservation_date, start_time),
    INDEX idx_expires (expires_at)
);

-- 업체 평가 통계 테이블 (성능 최적화용)
CREATE TABLE IF NOT EXISTS business_ratings (
    business_id INT PRIMARY KEY,
    total_reviews INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    rating_1_count INT DEFAULT 0,
    rating_2_count INT DEFAULT 0,
    rating_3_count INT DEFAULT 0,
    rating_4_count INT DEFAULT 0,
    rating_5_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_rating (average_rating DESC),
    INDEX idx_reviews (total_reviews DESC)
);

-- 사용자 활동 로그 테이블
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout', 'reservation_create', 'reservation_cancel', 'review_write', 'profile_update') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    activity_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activity (user_id, activity_type),
    INDEX idx_created_at (created_at)
);

-- 시스템 알림 템플릿 테이블
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    template_name VARCHAR(200) NOT NULL,
    sms_template TEXT,
    email_template TEXT,
    push_template TEXT,
    variables JSON, -- 사용 가능한 변수들
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (template_key),
    INDEX idx_active (is_active)
);

-- 업체 운영 통계 테이블
CREATE TABLE IF NOT EXISTS business_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    stat_date DATE NOT NULL,
    total_reservations INT DEFAULT 0,
    completed_reservations INT DEFAULT 0,
    cancelled_reservations INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    new_customers INT DEFAULT 0,
    repeat_customers INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_date (business_id, stat_date),
    INDEX idx_business_date (business_id, stat_date),
    INDEX idx_revenue (total_revenue DESC)
);

-- 기본 알림 템플릿 데이터 삽입
INSERT IGNORE INTO notification_templates (template_key, template_name, sms_template, email_template, push_template, variables) VALUES
('reservation_confirmed', '예약 확정 알림', 
 '[{business_name}] {date} {time} 예약이 확정되었습니다. 감사합니다!', 
 '예약이 확정되었습니다.\n\n업체: {business_name}\n날짜: {date}\n시간: {time}\n서비스: {service_name}\n\n즐거운 시간 되세요!',
 '예약이 확정되었습니다!',
 '["business_name", "date", "time", "service_name", "customer_name"]'),

('reservation_cancelled', '예약 취소 알림',
 '[{business_name}] {date} {time} 예약이 취소되었습니다.',
 '예약이 취소되었습니다.\n\n업체: {business_name}\n날짜: {date}\n시간: {time}\n취소사유: {reason}',
 '예약이 취소되었습니다.',
 '["business_name", "date", "time", "reason"]'),

('waitlist_available', '대기열 알림',
 '[{business_name}] {date} {time}에 자리가 났습니다! 지금 예약하세요.',
 '대기하신 시간에 자리가 났습니다!\n\n업체: {business_name}\n날짜: {date}\n시간: {time}\n\n서둘러 예약해주세요!',
 '자리가 났습니다! 지금 예약하세요.',
 '["business_name", "date", "time"]'),

('review_reminder', '후기 작성 알림',
 '[{business_name}] 서비스는 어떠셨나요? 후기를 남겨주세요!',
 '안녕하세요!\n\n{business_name}에서의 서비스는 어떠셨나요?\n소중한 후기를 남겨주시면 1000포인트를 적립해드립니다!',
 '후기를 작성하고 포인트를 받으세요!',
 '["business_name", "customer_name"]');

-- 시스템 설정 기본값 삽입
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('min_booking_hours', '2', 'number', '최소 예약 가능 시간 (몇 시간 전)', 1),
('max_booking_days', '30', 'number', '최대 예약 가능 일수', 1),
('point_earn_rate', '1', 'number', '예약 완료 시 적립율 (%)', 1),
('review_point_reward', '1000', 'number', '후기 작성 시 적립금', 1),
('reservation_lock_minutes', '5', 'number', '예약 진행 중 임시 잠금 시간 (분)', 0),
('sms_enabled', 'true', 'boolean', 'SMS 알림 활성화', 0),
('email_enabled', 'true', 'boolean', '이메일 알림 활성화', 0),
('push_enabled', 'true', 'boolean', '푸시 알림 활성화', 0),
('maintenance_mode', 'false', 'boolean', '유지보수 모드', 0),
('app_name', '뷰티 예약 시스템', 'string', '앱 이름', 1);

-- 임시 만료된 데이터 정리 이벤트 (MySQL Event Scheduler 사용)
-- SET GLOBAL event_scheduler = ON;

DELIMITER ;;
CREATE EVENT IF NOT EXISTS cleanup_expired_data
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    -- 만료된 예약 잠금 삭제
    DELETE FROM reservation_locks WHERE expires_at < NOW();
    
    -- 만료된 대기열 정리
    UPDATE reservation_waitlist 
    SET status = 'expired' 
    WHERE status = 'waiting' AND expires_at < NOW();
    
    -- 만료된 쿠폰 정리
    UPDATE coupons 
    SET is_active = 0 
    WHERE is_active = 1 AND valid_until < NOW();
    
    -- 만료된 적립금 정리
    UPDATE points 
    SET point_type = 'expire', amount = -amount 
    WHERE point_type = 'earn' AND expires_at < NOW() AND expires_at IS NOT NULL;
END;;
DELIMITER ; 