-- 예약 테이블에 결제 관련 컬럼 추가
ALTER TABLE reservations 
ADD COLUMN payment_type ENUM('onsite', 'full', 'deposit') DEFAULT 'onsite' AFTER total_amount,
ADD COLUMN discount_amount INT DEFAULT 0 AFTER payment_type,
ADD COLUMN coupon_id INT NULL AFTER discount_amount,
ADD COLUMN points_used INT DEFAULT 0 AFTER coupon_id,
ADD FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL;

-- 적립금 테이블 점검 및 수정
ALTER TABLE points 
CHANGE COLUMN point_type point_type ENUM('earn', 'use', 'expire', 'admin_adjust') NOT NULL,
ADD COLUMN balance INT NOT NULL DEFAULT 0 AFTER amount,
ADD COLUMN expires_at TIMESTAMP NULL AFTER balance;

-- 결제 테이블에 현장결제 옵션 추가
ALTER TABLE payments 
CHANGE COLUMN payment_method payment_method ENUM('card', 'kakaopay', 'naverpay', 'cash', 'onsite') NOT NULL;

-- 업체 정책 테이블이 없다면 생성
CREATE TABLE IF NOT EXISTS business_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    policy_type ENUM('deposit', 'cancellation', 'auto_approval') NOT NULL,
    policy_data JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_type (business_id, policy_type)
);

-- 기본 정책 데이터 삽입
INSERT IGNORE INTO business_policies (business_id, policy_type, policy_data) 
SELECT id, 'deposit', '{"deposit_rate": 0.2, "min_deposit": 10000}' 
FROM businesses WHERE is_active = 1;

-- 적립금 데이터 정리 (잔액 계산)
UPDATE points p1 
SET balance = (
    SELECT COALESCE(SUM(
        CASE 
            WHEN p2.point_type = 'earn' THEN p2.amount 
            ELSE -p2.amount 
        END
    ), 0)
    FROM points p2 
    WHERE p2.customer_id = p1.customer_id 
    AND p2.created_at <= p1.created_at
)
WHERE p1.balance = 0; 