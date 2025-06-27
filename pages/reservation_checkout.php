<?php
session_start();
$page_title = '결제하기 - 뷰티북';

// 로그인 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$reservation_id = $_GET['reservation_id'] ?? 0;

if (!$reservation_id) {
    header('Location: customer_mypage.php?tab=reservations');
    exit;
}

// 예약 정보 확인
$stmt = $db->prepare("
    SELECT r.*, b.name as business_name, b.address as business_address,
           t.name as teacher_name, bs.service_name, bs.price, bs.duration,
           u.name as customer_name, u.phone as customer_phone
    FROM reservations r
    JOIN businesses b ON r.business_id = b.id
    JOIN teachers t ON r.teacher_id = t.id
    JOIN business_services bs ON r.service_id = bs.id
    JOIN users u ON r.customer_id = u.id
    WHERE r.id = ? AND r.customer_id = ? AND r.status = 'pending'
");
$stmt->execute([$reservation_id, $_SESSION['user_id']]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: customer_mypage.php?tab=reservations');
    exit;
}

// 고객 쿠폰 조회
$stmt = $db->prepare("
    SELECT cc.*, c.*
    FROM customer_coupons cc
    JOIN coupons c ON cc.coupon_id = c.id
    WHERE cc.customer_id = ? AND cc.is_used = 0 AND c.is_active = 1
    AND (c.valid_until IS NULL OR c.valid_until >= NOW())
    AND (c.business_id IS NULL OR c.business_id = ?)
    AND c.min_order_amount <= ?
    ORDER BY c.discount_value DESC
");
$stmt->execute([$_SESSION['user_id'], $reservation['business_id'], $reservation['total_amount']]);
$available_coupons = $stmt->fetchAll();

// 고객 적립금 조회
$stmt = $db->prepare("
    SELECT COALESCE(SUM(CASE WHEN point_type = 'earn' THEN amount ELSE -amount END), 0) as total_points
    FROM points 
    WHERE customer_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$customer_points = $stmt->fetch()['total_points'];

// 업체 정책 조회
$stmt = $db->prepare("
    SELECT * FROM business_policies 
    WHERE business_id = ? AND policy_type = 'deposit' AND is_active = 1
");
$stmt->execute([$reservation['business_id']]);
$deposit_policy = $stmt->fetch();

$errors = [];
$success_message = '';

// 결제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? 'cash'; // cash, card, kakaopay, naverpay
    $payment_type = $_POST['payment_type'] ?? 'full'; // full, deposit, onsite
    $selected_coupon_id = intval($_POST['coupon_id'] ?? 0);
    $use_points = intval($_POST['use_points'] ?? 0);
    
    try {
        $db->beginTransaction();
        
        $original_amount = $reservation['total_amount'];
        $final_amount = $original_amount;
        $discount_amount = 0;
        $deposit_amount = 0;
        
        // 쿠폰 적용
        if ($selected_coupon_id > 0) {
            $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
            $stmt->execute([$selected_coupon_id]);
            $coupon = $stmt->fetch();
            
            if ($coupon && $original_amount >= $coupon['min_order_amount']) {
                if ($coupon['discount_type'] === 'percentage') {
                    $discount_amount = floor($original_amount * $coupon['discount_value'] / 100);
                    if ($coupon['max_discount_amount'] && $discount_amount > $coupon['max_discount_amount']) {
                        $discount_amount = $coupon['max_discount_amount'];
                    }
                } else {
                    $discount_amount = $coupon['discount_value'];
                }
                $final_amount -= $discount_amount;
            }
        }
        
        // 적립금 사용
        if ($use_points > 0 && $use_points <= $customer_points && $use_points >= 1000) {
            $use_points = min($use_points, $final_amount);
            $final_amount -= $use_points;
        }
        
        // 예약금 계산
        if ($payment_type === 'deposit' && $deposit_policy) {
            $policy_data = json_decode($deposit_policy['policy_data'], true);
            $deposit_rate = $policy_data['deposit_rate'] ?? 0.2; // 기본 20%
            $deposit_amount = floor($final_amount * $deposit_rate);
            $payment_amount = $deposit_amount;
        } elseif ($payment_type === 'onsite') {
            $payment_amount = 0; // 현장결제
        } else {
            $payment_amount = $final_amount; // 전액 선결제
        }
        
        // 예약 정보 업데이트
        $stmt = $db->prepare("
            UPDATE reservations 
            SET total_amount = ?, deposit_amount = ?, discount_amount = ?, 
                coupon_id = ?, points_used = ?, payment_type = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $original_amount, $deposit_amount, $discount_amount,
            $selected_coupon_id ?: null, $use_points, $payment_type, $reservation_id
        ]);
        
        // 결제 기록 생성
        if ($payment_amount > 0) {
            $transaction_id = 'TXN_' . time() . '_' . rand(1000, 9999);
            
            $stmt = $db->prepare("
                INSERT INTO payments 
                (reservation_id, payment_method, amount, status, transaction_id, paid_at)
                VALUES (?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([$reservation_id, $payment_method, $payment_amount, $transaction_id]);
        }
        
        // 쿠폰 사용 처리
        if ($selected_coupon_id > 0) {
            $stmt = $db->prepare("
                UPDATE customer_coupons 
                SET is_used = 1, used_at = NOW(), used_in_reservation = ?
                WHERE customer_id = ? AND coupon_id = ?
            ");
            $stmt->execute([$reservation_id, $_SESSION['user_id'], $selected_coupon_id]);
        }
        
        // 적립금 사용 처리
        if ($use_points > 0) {
            $stmt = $db->prepare("
                INSERT INTO points 
                (customer_id, point_type, amount, description, reservation_id, created_at)
                VALUES (?, 'use', ?, '예약 결제 시 사용', ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], -$use_points, $reservation_id]);
        }
        
        $db->commit();
        
        // 성공 페이지로 리다이렉트
        header('Location: reservation_success.php?reservation_id=' . $reservation_id);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = '결제 처리 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<style>
.checkout-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.checkout-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.reservation-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.summary-row:last-child {
    margin-bottom: 0;
    font-weight: bold;
    font-size: 18px;
    color: #ff4757;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}

.payment-options {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.payment-option {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-option.selected {
    border-color: #ff4757;
    background: #fff5f5;
}

.payment-option:hover {
    border-color: #ff6b7a;
}

.option-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.option-desc {
    font-size: 14px;
    color: #666;
}

.coupon-section, .points-section {
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.coupon-list {
    max-height: 200px;
    overflow-y: auto;
}

.coupon-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #eee;
    border-radius: 6px;
    margin-bottom: 10px;
}

.coupon-item.selected {
    border-color: #ff4757;
    background: #fff5f5;
}

.points-input {
    display: flex;
    align-items: center;
    gap: 10px;
}

.points-input input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.checkout-btn {
    background: linear-gradient(135deg, #ff4757, #ff6b7a);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 8px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    width: 100%;
    transition: all 0.3s;
}

.checkout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.payment-method {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-method.selected {
    border-color: #ff4757;
    background: #fff5f5;
}

.payment-method i {
    font-size: 24px;
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .checkout-container {
        padding: 10px;
    }
    
    .payment-methods {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="checkout-container">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 예약 정보 요약 -->
    <div class="checkout-section">
        <div class="section-title">
            <i class="fas fa-receipt"></i> 예약 정보
        </div>
        
        <div class="reservation-summary">
            <h5><?= htmlspecialchars($reservation['business_name']) ?></h5>
            <p class="text-muted"><?= htmlspecialchars($reservation['business_address']) ?></p>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <strong>선생님:</strong> <?= htmlspecialchars($reservation['teacher_name']) ?><br>
                    <strong>서비스:</strong> <?= htmlspecialchars($reservation['service_name']) ?><br>
                    <strong>소요시간:</strong> <?= $reservation['duration'] ?>분
                </div>
                <div class="col-md-6">
                    <strong>예약일:</strong> <?= date('Y년 m월 d일', strtotime($reservation['reservation_date'])) ?><br>
                    <strong>시간:</strong> <?= date('H:i', strtotime($reservation['start_time'])) ?><br>
                    <strong>금액:</strong> <?= number_format($reservation['total_amount']) ?>원
                </div>
            </div>
        </div>
    </div>

    <form method="POST" id="checkoutForm">
        <!-- 결제 방식 선택 -->
        <div class="checkout-section">
            <div class="section-title">
                <i class="fas fa-credit-card"></i> 결제 방식
            </div>
            
            <div class="payment-options">
                <div class="payment-option" onclick="selectPaymentType('onsite')">
                    <input type="radio" name="payment_type" value="onsite" checked>
                    <div class="option-title">현장결제</div>
                    <div class="option-desc">방문 시 현금 또는 카드로 결제</div>
                </div>
                
                <div class="payment-option" onclick="selectPaymentType('full')">
                    <input type="radio" name="payment_type" value="full">
                    <div class="option-title">전액 선결제 <span style="color: #ff4757;">(5% 할인)</span></div>
                    <div class="option-desc">온라인으로 전액 미리 결제</div>
                </div>
                
                <?php if ($deposit_policy): ?>
                <div class="payment-option" onclick="selectPaymentType('deposit')">
                    <input type="radio" name="payment_type" value="deposit">
                    <div class="option-title">예약금 결제</div>
                    <div class="option-desc">일부 금액만 선결제, 나머지는 현장 결제</div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 온라인 결제 수단 (선결제 선택 시만 표시) -->
            <div id="paymentMethods" style="display: none;">
                <h6 class="mt-3 mb-2">결제 수단 선택</h6>
                <div class="payment-methods">
                    <div class="payment-method" onclick="selectPaymentMethod('card')">
                        <input type="radio" name="payment_method" value="card" style="display: none;">
                        <i class="fas fa-credit-card"></i>
                        <span>신용카드</span>
                    </div>
                    <div class="payment-method" onclick="selectPaymentMethod('kakaopay')">
                        <input type="radio" name="payment_method" value="kakaopay" style="display: none;">
                        <i class="fab fa-kickstarter-k" style="color: #fee500;"></i>
                        <span>카카오페이</span>
                    </div>
                    <div class="payment-method" onclick="selectPaymentMethod('naverpay')">
                        <input type="radio" name="payment_method" value="naverpay" style="display: none;">
                        <i class="fab fa-neos" style="color: #03c75a;"></i>
                        <span>네이버페이</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 쿠폰 사용 -->
        <?php if (!empty($available_coupons)): ?>
        <div class="checkout-section">
            <div class="section-title">
                <i class="fas fa-ticket-alt"></i> 쿠폰 사용
            </div>
            
            <div class="coupon-section">
                <div class="form-check mb-2">
                    <input type="radio" name="coupon_id" value="0" id="no_coupon" checked>
                    <label for="no_coupon">쿠폰 사용 안함</label>
                </div>
                
                <div class="coupon-list">
                    <?php foreach ($available_coupons as $coupon): ?>
                        <div class="coupon-item" onclick="selectCoupon(<?= $coupon['id'] ?>)">
                            <input type="radio" name="coupon_id" value="<?= $coupon['id'] ?>" id="coupon_<?= $coupon['id'] ?>">
                            <div class="flex-grow-1">
                                <div class="font-weight-bold"><?= htmlspecialchars($coupon['coupon_name']) ?></div>
                                <div class="text-muted small">
                                    <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                        <?= $coupon['discount_value'] ?>% 할인
                                        <?php if ($coupon['max_discount_amount']): ?>
                                            (최대 <?= number_format($coupon['max_discount_amount']) ?>원)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= number_format($coupon['discount_value']) ?>원 할인
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 적립금 사용 -->
        <?php if ($customer_points >= 1000): ?>
        <div class="checkout-section">
            <div class="section-title">
                <i class="fas fa-coins"></i> 적립금 사용
            </div>
            
            <div class="points-section">
                <p>보유 적립금: <strong><?= number_format($customer_points) ?>원</strong></p>
                <div class="points-input">
                    <input type="number" name="use_points" min="0" max="<?= $customer_points ?>" step="1000" placeholder="사용할 적립금 (1,000원 단위)">
                    <button type="button" class="btn btn-outline-primary" onclick="useAllPoints()">전액 사용</button>
                </div>
                <small class="text-muted">최소 1,000원부터 1,000원 단위로 사용 가능</small>
            </div>
        </div>
        <?php endif; ?>

        <!-- 최종 결제 금액 -->
        <div class="checkout-section">
            <div class="section-title">
                <i class="fas fa-calculator"></i> 결제 금액
            </div>
            
            <div class="summary-row">
                <span>서비스 금액</span>
                <span id="originalAmount"><?= number_format($reservation['total_amount']) ?>원</span>
            </div>
            <div class="summary-row" id="discountRow" style="display: none;">
                <span>할인 금액</span>
                <span id="discountAmount" style="color: #ff4757;">-0원</span>
            </div>
            <div class="summary-row" id="pointsRow" style="display: none;">
                <span>적립금 사용</span>
                <span id="pointsAmount" style="color: #ff4757;">-0원</span>
            </div>
            <div class="summary-row">
                <span>최종 결제 금액</span>
                <span id="finalAmount"><?= number_format($reservation['total_amount']) ?>원</span>
            </div>
        </div>

        <button type="submit" class="checkout-btn">
            <i class="fas fa-check"></i> 결제하기
        </button>
    </form>
</div>

<script>
let originalAmount = <?= $reservation['total_amount'] ?>;
let customerPoints = <?= $customer_points ?>;

function selectPaymentType(type) {
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    document.querySelector(`input[name="payment_type"][value="${type}"]`).checked = true;
    
    // 온라인 결제 수단 표시/숨김
    const paymentMethods = document.getElementById('paymentMethods');
    if (type === 'full' || type === 'deposit') {
        paymentMethods.style.display = 'block';
        selectPaymentMethod('card'); // 기본 선택
    } else {
        paymentMethods.style.display = 'none';
    }
    
    calculateTotal();
}

function selectPaymentMethod(method) {
    document.querySelectorAll('.payment-method').forEach(method => {
        method.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    document.querySelector(`input[name="payment_method"][value="${method}"]`).checked = true;
}

function selectCoupon(couponId) {
    document.querySelectorAll('.coupon-item').forEach(item => {
        item.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    document.getElementById(`coupon_${couponId}`).checked = true;
    calculateTotal();
}

function useAllPoints() {
    document.querySelector('input[name="use_points"]').value = Math.floor(customerPoints / 1000) * 1000;
    calculateTotal();
}

function calculateTotal() {
    let finalAmount = originalAmount;
    let discountAmount = 0;
    let pointsUsed = 0;
    
    // 선결제 할인 적용
    const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
    if (paymentType === 'full') {
        discountAmount += Math.floor(originalAmount * 0.05); // 5% 할인
    }
    
    // 쿠폰 할인 계산 (실제로는 서버에서 정확히 계산해야 함)
    const selectedCoupon = document.querySelector('input[name="coupon_id"]:checked');
    if (selectedCoupon && selectedCoupon.value !== '0') {
        // 쿠폰 할인 로직 (예시)
        discountAmount += 5000; // 예시값
    }
    
    // 적립금 사용
    const pointsInput = document.querySelector('input[name="use_points"]');
    if (pointsInput && pointsInput.value) {
        pointsUsed = parseInt(pointsInput.value);
    }
    
    finalAmount = originalAmount - discountAmount - pointsUsed;
    if (finalAmount < 0) finalAmount = 0;
    
    // UI 업데이트
    document.getElementById('discountAmount').textContent = '-' + discountAmount.toLocaleString() + '원';
    document.getElementById('pointsAmount').textContent = '-' + pointsUsed.toLocaleString() + '원';
    document.getElementById('finalAmount').textContent = finalAmount.toLocaleString() + '원';
    
    document.getElementById('discountRow').style.display = discountAmount > 0 ? 'flex' : 'none';
    document.getElementById('pointsRow').style.display = pointsUsed > 0 ? 'flex' : 'none';
}

// 적립금 입력 시 실시간 계산
document.querySelector('input[name="use_points"]')?.addEventListener('input', calculateTotal);

// 쿠폰 선택 시 계산
document.querySelectorAll('input[name="coupon_id"]').forEach(radio => {
    radio.addEventListener('change', calculateTotal);
});

// 초기 계산
calculateTotal();
</script>

<?php include '../includes/footer.php'; ?>
</rewritten_file>