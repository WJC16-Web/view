<?php
session_start();
$page_title = '업체 정책 관리 - 뷰티북';

// 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();

// 업체 정보 확인
$business_stmt = $db->prepare("
    SELECT b.* 
    FROM businesses b 
    JOIN business_owners bo ON b.owner_id = bo.id 
    WHERE bo.user_id = ?
");
$business_stmt->execute([$_SESSION['user_id']]);
$business = $business_stmt->fetch();

if (!$business) {
    header('Location: business_register.php');
    exit;
}

$success_message = '';
$error_message = '';

// 정책 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $policy_type = $_POST['policy_type'] ?? '';
    
    try {
        $db->beginTransaction();
        
        if ($policy_type === 'cancellation') {
            $policy_data = [
                'customer_cancel_hours' => intval($_POST['customer_cancel_hours'] ?? 2),
                'fee_rates' => [
                    ['hours_before' => 24, 'fee_rate' => intval($_POST['fee_24h'] ?? 0)],
                    ['hours_before' => 12, 'fee_rate' => intval($_POST['fee_12h'] ?? 10)],
                    ['hours_before' => 6, 'fee_rate' => intval($_POST['fee_6h'] ?? 30)],
                    ['hours_before' => 2, 'fee_rate' => intval($_POST['fee_2h'] ?? 50)],
                    ['hours_before' => 0, 'fee_rate' => intval($_POST['fee_0h'] ?? 100)]
                ]
            ];
        } elseif ($policy_type === 'deposit') {
            $policy_data = [
                'deposit_required' => isset($_POST['deposit_required']),
                'deposit_rate' => floatval($_POST['deposit_rate'] ?? 20) / 100,
                'min_deposit' => intval($_POST['min_deposit'] ?? 10000),
                'max_deposit' => intval($_POST['max_deposit'] ?? 100000)
            ];
        } elseif ($policy_type === 'booking_time') {
            $policy_data = [
                'min_advance_hours' => intval($_POST['min_advance_hours'] ?? 2),
                'max_advance_days' => intval($_POST['max_advance_days'] ?? 30),
                'same_day_booking' => isset($_POST['same_day_booking']),
                'auto_approval' => isset($_POST['auto_approval'])
            ];
        }
        
        // 기존 정책 확인
        $stmt = $db->prepare("
            SELECT id FROM business_policies 
            WHERE business_id = ? AND policy_type = ?
        ");
        $stmt->execute([$business['id'], $policy_type]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // 업데이트
            $stmt = $db->prepare("
                UPDATE business_policies 
                SET policy_data = ?, updated_at = NOW()
                WHERE business_id = ? AND policy_type = ?
            ");
            $stmt->execute([
                json_encode($policy_data, JSON_UNESCAPED_UNICODE),
                $business['id'],
                $policy_type
            ]);
        } else {
            // 새로 삽입
            $stmt = $db->prepare("
                INSERT INTO business_policies 
                (business_id, policy_type, policy_data) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $business['id'],
                $policy_type,
                json_encode($policy_data, JSON_UNESCAPED_UNICODE)
            ]);
        }
        
        $db->commit();
        $success_message = '정책이 성공적으로 저장되었습니다.';
        
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = '정책 저장 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

// 기존 정책 조회
$policies = [];
$stmt = $db->prepare("
    SELECT policy_type, policy_data 
    FROM business_policies 
    WHERE business_id = ? AND is_active = 1
");
$stmt->execute([$business['id']]);
$policy_rows = $stmt->fetchAll();

foreach ($policy_rows as $row) {
    $policies[$row['policy_type']] = json_decode($row['policy_data'], true);
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-cogs text-primary"></i> 업체 정책 관리</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="business_dashboard.php">대시보드</a></li>
                        <li class="breadcrumb-item active">정책 관리</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- 정책 탭 메뉴 -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="policyTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="cancellation-tab" data-toggle="tab" href="#cancellation" role="tab">
                                <i class="fas fa-ban"></i> 취소/환불 정책
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="deposit-tab" data-toggle="tab" href="#deposit" role="tab">
                                <i class="fas fa-money-bill-wave"></i> 예약금 정책
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="booking-tab" data-toggle="tab" href="#booking" role="tab">
                                <i class="fas fa-clock"></i> 예약 시간 정책
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content mt-4" id="policyTabContent">
                        <!-- 취소/환불 정책 -->
                        <div class="tab-pane fade show active" id="cancellation" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="policy_type" value="cancellation">
                                
                                <h5 class="mb-3"><i class="fas fa-ban text-danger"></i> 취소/환불 정책 설정</h5>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">고객 취소 가능 시간</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select name="customer_cancel_hours" class="form-control">
                                                <option value="1" <?= ($policies['cancellation']['customer_cancel_hours'] ?? 2) == 1 ? 'selected' : '' ?>>1시간 전까지</option>
                                                <option value="2" <?= ($policies['cancellation']['customer_cancel_hours'] ?? 2) == 2 ? 'selected' : '' ?>>2시간 전까지</option>
                                                <option value="6" <?= ($policies['cancellation']['customer_cancel_hours'] ?? 2) == 6 ? 'selected' : '' ?>>6시간 전까지</option>
                                                <option value="12" <?= ($policies['cancellation']['customer_cancel_hours'] ?? 2) == 12 ? 'selected' : '' ?>>12시간 전까지</option>
                                                <option value="24" <?= ($policies['cancellation']['customer_cancel_hours'] ?? 2) == 24 ? 'selected' : '' ?>>24시간 전까지</option>
                                            </select>
                                        </div>
                                    </div>
                                    <small class="text-muted">고객이 직접 취소할 수 있는 시간 제한</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">취소 시점별 수수료 (환불 차감율)</label>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>취소 시점</th>
                                                    <th>수수료율 (%)</th>
                                                    <th>고객 환불율</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>24시간 전</td>
                                                    <td>
                                                        <input type="number" name="fee_24h" class="form-control" 
                                                               value="<?= $policies['cancellation']['fee_rates'][0]['fee_rate'] ?? 0 ?>" 
                                                               min="0" max="100">
                                                    </td>
                                                    <td>
                                                        <span class="refund-rate text-success font-weight-bold"><?= 100 - ($policies['cancellation']['fee_rates'][0]['fee_rate'] ?? 0) ?>%</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>12시간 전</td>
                                                    <td>
                                                        <input type="number" name="fee_12h" class="form-control" 
                                                               value="<?= $policies['cancellation']['fee_rates'][1]['fee_rate'] ?? 10 ?>" 
                                                               min="0" max="100">
                                                    </td>
                                                    <td>
                                                        <span class="refund-rate text-success font-weight-bold"><?= 100 - ($policies['cancellation']['fee_rates'][1]['fee_rate'] ?? 10) ?>%</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>6시간 전</td>
                                                    <td>
                                                        <input type="number" name="fee_6h" class="form-control" 
                                                               value="<?= $policies['cancellation']['fee_rates'][2]['fee_rate'] ?? 30 ?>" 
                                                               min="0" max="100">
                                                    </td>
                                                    <td>
                                                        <span class="refund-rate text-warning font-weight-bold"><?= 100 - ($policies['cancellation']['fee_rates'][2]['fee_rate'] ?? 30) ?>%</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>2시간 전</td>
                                                    <td>
                                                        <input type="number" name="fee_2h" class="form-control" 
                                                               value="<?= $policies['cancellation']['fee_rates'][3]['fee_rate'] ?? 50 ?>" 
                                                               min="0" max="100">
                                                    </td>
                                                    <td>
                                                        <span class="refund-rate text-warning font-weight-bold"><?= 100 - ($policies['cancellation']['fee_rates'][3]['fee_rate'] ?? 50) ?>%</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>2시간 미만</td>
                                                    <td>
                                                        <input type="number" name="fee_0h" class="form-control" 
                                                               value="<?= $policies['cancellation']['fee_rates'][4]['fee_rate'] ?? 100 ?>" 
                                                               min="0" max="100">
                                                    </td>
                                                    <td>
                                                        <span class="refund-rate text-danger font-weight-bold"><?= 100 - ($policies['cancellation']['fee_rates'][4]['fee_rate'] ?? 100) ?>%</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted">업체 사정으로 인한 취소는 100% 환불됩니다.</small>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 취소 정책 저장
                                </button>
                            </form>
                        </div>

                        <!-- 예약금 정책 -->
                        <div class="tab-pane fade" id="deposit" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="policy_type" value="deposit">
                                
                                <h5 class="mb-3"><i class="fas fa-money-bill-wave text-success"></i> 예약금 정책 설정</h5>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="deposit_required" name="deposit_required" 
                                               <?= ($policies['deposit']['deposit_required'] ?? false) ? 'checked' : '' ?>>
                                        <label class="custom-control-label font-weight-bold" for="deposit_required">
                                            예약금 결제 필수
                                        </label>
                                    </div>
                                    <small class="text-muted">체크하면 모든 예약에서 예약금 결제가 필수가 됩니다.</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">예약금 비율</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="number" name="deposit_rate" class="form-control" 
                                                       value="<?= ($policies['deposit']['deposit_rate'] ?? 0.2) * 100 ?>" 
                                                       min="10" max="50" step="5">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted">서비스 금액의 몇 %를 예약금으로 받을지 설정 (10%~50%)</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">예약금 한도</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="small">최소 예약금</label>
                                            <div class="input-group">
                                                <input type="number" name="min_deposit" class="form-control" 
                                                       value="<?= $policies['deposit']['min_deposit'] ?? 10000 ?>" 
                                                       min="5000" max="50000" step="1000">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">원</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small">최대 예약금</label>
                                            <div class="input-group">
                                                <input type="number" name="max_deposit" class="form-control" 
                                                       value="<?= $policies['deposit']['max_deposit'] ?? 100000 ?>" 
                                                       min="50000" max="500000" step="10000">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">원</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> 예약금 정책 저장
                                </button>
                            </form>
                        </div>

                        <!-- 예약 시간 정책 -->
                        <div class="tab-pane fade" id="booking" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="policy_type" value="booking_time">
                                
                                <h5 class="mb-3"><i class="fas fa-clock text-info"></i> 예약 시간 정책 설정</h5>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">최소 예약 시간</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select name="min_advance_hours" class="form-control">
                                                <option value="0" <?= ($policies['booking_time']['min_advance_hours'] ?? 2) == 0 ? 'selected' : '' ?>>즉시 예약 가능</option>
                                                <option value="1" <?= ($policies['booking_time']['min_advance_hours'] ?? 2) == 1 ? 'selected' : '' ?>>1시간 전까지</option>
                                                <option value="2" <?= ($policies['booking_time']['min_advance_hours'] ?? 2) == 2 ? 'selected' : '' ?>>2시간 전까지</option>
                                                <option value="6" <?= ($policies['booking_time']['min_advance_hours'] ?? 2) == 6 ? 'selected' : '' ?>>6시간 전까지</option>
                                                <option value="12" <?= ($policies['booking_time']['min_advance_hours'] ?? 2) == 12 ? 'selected' : '' ?>>12시간 전까지</option>
                                                <option value="24" <?= ($policies['booking_time']['min_advance_hours'] ?? 2) == 24 ? 'selected' : '' ?>>24시간 전까지</option>
                                            </select>
                                        </div>
                                    </div>
                                    <small class="text-muted">예약 가능한 최소 시간 (현재 시간 기준)</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">최대 예약 기간</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select name="max_advance_days" class="form-control">
                                                <option value="7" <?= ($policies['booking_time']['max_advance_days'] ?? 30) == 7 ? 'selected' : '' ?>>1주일 후까지</option>
                                                <option value="14" <?= ($policies['booking_time']['max_advance_days'] ?? 30) == 14 ? 'selected' : '' ?>>2주일 후까지</option>
                                                <option value="30" <?= ($policies['booking_time']['max_advance_days'] ?? 30) == 30 ? 'selected' : '' ?>>1개월 후까지</option>
                                                <option value="60" <?= ($policies['booking_time']['max_advance_days'] ?? 30) == 60 ? 'selected' : '' ?>>2개월 후까지</option>
                                                <option value="90" <?= ($policies['booking_time']['max_advance_days'] ?? 30) == 90 ? 'selected' : '' ?>>3개월 후까지</option>
                                            </select>
                                        </div>
                                    </div>
                                    <small class="text-muted">예약 가능한 최대 기간</small>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="same_day_booking" name="same_day_booking" 
                                               <?= ($policies['booking_time']['same_day_booking'] ?? true) ? 'checked' : '' ?>>
                                        <label class="custom-control-label font-weight-bold" for="same_day_booking">
                                            당일 예약 허용
                                        </label>
                                    </div>
                                    <small class="text-muted">당일 예약을 허용할지 설정</small>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="auto_approval" name="auto_approval" 
                                               <?= ($policies['booking_time']['auto_approval'] ?? false) ? 'checked' : '' ?>>
                                        <label class="custom-control-label font-weight-bold" for="auto_approval">
                                            예약 자동 승인
                                        </label>
                                    </div>
                                    <small class="text-muted">체크하면 예약 신청 시 자동으로 승인됩니다.</small>
                                </div>

                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> 예약 시간 정책 저장
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 정책 미리보기 -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-eye text-primary"></i> 고객에게 표시되는 정책</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-danger"><i class="fas fa-ban"></i> 취소/환불 정책</h6>
                            <ul class="list-unstyled small">
                                <li>• 고객 취소: <?= $policies['cancellation']['customer_cancel_hours'] ?? 2 ?>시간 전까지 가능</li>
                                <li>• 24시간 전: <?= 100 - ($policies['cancellation']['fee_rates'][0]['fee_rate'] ?? 0) ?>% 환불</li>
                                <li>• 12시간 전: <?= 100 - ($policies['cancellation']['fee_rates'][1]['fee_rate'] ?? 10) ?>% 환불</li>
                                <li>• 6시간 전: <?= 100 - ($policies['cancellation']['fee_rates'][2]['fee_rate'] ?? 30) ?>% 환불</li>
                                <li>• 2시간 전: <?= 100 - ($policies['cancellation']['fee_rates'][3]['fee_rate'] ?? 50) ?>% 환불</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-success"><i class="fas fa-money-bill-wave"></i> 예약금 정책</h6>
                            <ul class="list-unstyled small">
                                <li>• 예약금 필수: <?= ($policies['deposit']['deposit_required'] ?? false) ? '예' : '아니오' ?></li>
                                <li>• 예약금 비율: <?= ($policies['deposit']['deposit_rate'] ?? 0.2) * 100 ?>%</li>
                                <li>• 최소 금액: <?= number_format($policies['deposit']['min_deposit'] ?? 10000) ?>원</li>
                                <li>• 최대 금액: <?= number_format($policies['deposit']['max_deposit'] ?? 100000) ?>원</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-info"><i class="fas fa-clock"></i> 예약 시간 정책</h6>
                            <ul class="list-unstyled small">
                                <li>• 최소 예약 시간: <?= $policies['booking_time']['min_advance_hours'] ?? 2 ?>시간 전</li>
                                <li>• 최대 예약 기간: <?= $policies['booking_time']['max_advance_days'] ?? 30 ?>일 후</li>
                                <li>• 당일 예약: <?= ($policies['booking_time']['same_day_booking'] ?? true) ? '가능' : '불가능' ?></li>
                                <li>• 자동 승인: <?= ($policies['booking_time']['auto_approval'] ?? false) ? '활성화' : '비활성화' ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 수수료율 입력 시 실시간 환불율 계산
document.querySelectorAll('input[name^="fee_"]').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        const refundRate = row.querySelector('.refund-rate');
        const feeRate = parseInt(this.value) || 0;
        refundRate.textContent = (100 - feeRate) + '%';
        
        // 색상 변경
        if (feeRate >= 50) {
            refundRate.className = 'refund-rate text-danger font-weight-bold';
        } else if (feeRate >= 20) {
            refundRate.className = 'refund-rate text-warning font-weight-bold';
        } else {
            refundRate.className = 'refund-rate text-success font-weight-bold';
        }
    });
});

// 예약금 비율 입력 시 유효성 검사
document.querySelector('input[name="deposit_rate"]')?.addEventListener('input', function() {
    const value = parseInt(this.value);
    if (value < 10) this.value = 10;
    if (value > 50) this.value = 50;
});
</script>

<?php include '../includes/footer.php'; ?> 