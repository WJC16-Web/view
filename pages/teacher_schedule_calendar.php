<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 선생님 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();

// 선생님 정보 조회
$stmt = $db->prepare("
    SELECT t.*, u.name, u.email, u.phone, b.name as business_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    JOIN businesses b ON t.business_id = b.id
    WHERE t.user_id = ?
");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    header('Location: login.php');
    exit;
}

// 현재 년월 설정
$current_year = $_GET['year'] ?? date('Y');
$current_month = $_GET['month'] ?? date('m');

// 달력 데이터 생성
$first_day = date('Y-m-01', strtotime("$current_year-$current_month-01"));
$last_day = date('Y-m-t', strtotime("$current_year-$current_month-01"));
$first_day_of_week = date('w', strtotime($first_day)); // 0=일요일
$days_in_month = date('t', strtotime($first_day));

// 해당 월의 예약 조회
$stmt = $db->prepare("
    SELECT r.*, u.name as customer_name, ts.service_name, ts.duration
    FROM reservations r
    JOIN users u ON r.customer_id = u.id
    LEFT JOIN teacher_services ts ON r.teacher_service_id = ts.id
    WHERE r.teacher_id = ? 
    AND r.reservation_date BETWEEN ? AND ?
    AND r.status IN ('confirmed', 'pending', 'completed')
    ORDER BY r.reservation_date, r.start_time
");
$stmt->execute([$teacher['id'], $first_day, $last_day]);
$reservations = $stmt->fetchAll();

// 날짜별 예약 그룹화
$reservations_by_date = [];
foreach ($reservations as $reservation) {
    $date = $reservation['reservation_date'];
    if (!isset($reservations_by_date[$date])) {
        $reservations_by_date[$date] = [];
    }
    $reservations_by_date[$date][] = $reservation;
}

// 스케줄 예외 조회
$stmt = $db->prepare("
    SELECT * FROM teacher_exceptions 
    WHERE teacher_id = ? 
    AND exception_date BETWEEN ? AND ?
");
$stmt->execute([$teacher['id'], $first_day, $last_day]);
$exceptions = $stmt->fetchAll();

// 날짜별 예외 그룹화
$exceptions_by_date = [];
foreach ($exceptions as $exception) {
    $exceptions_by_date[$exception['exception_date']] = $exception;
}

// 정기 스케줄 조회
$stmt = $db->prepare("
    SELECT * FROM teacher_schedules 
    WHERE teacher_id = ? AND is_active = 1
");
$stmt->execute([$teacher['id']]);
$regular_schedules = $stmt->fetchAll();

// 요일별 스케줄 매핑
$schedules_by_day = [];
foreach ($regular_schedules as $schedule) {
    $schedules_by_day[$schedule['day_of_week']] = $schedule;
}

$success_message = '';
$error_message = '';

// 수동 예약 추가 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_manual_reservation') {
            // 시간 중복 체크
            $reservation_date = $_POST['reservation_date'];
            $start_time = $_POST['start_time'];
            $duration = (int)$_POST['duration'];
            $end_time = date('H:i:s', strtotime($start_time) + ($duration * 60));
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM reservations 
                WHERE teacher_id = ? 
                AND reservation_date = ? 
                AND status IN ('confirmed', 'pending')
                AND (
                    (start_time <= ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ");
            $stmt->execute([
                $teacher['id'], $reservation_date, 
                $start_time, $start_time, 
                $end_time, $end_time,
                $start_time, $end_time
            ]);
            
            if ($stmt->fetch()['count'] > 0) {
                $error_message = "선택한 시간에 이미 예약이 있습니다.";
            } else {
                // 수동 예약 추가 (블록 예약)
                $stmt = $db->prepare("
                    INSERT INTO reservations (
                        customer_id, teacher_id, business_id, teacher_service_id,
                        reservation_date, start_time, end_time, total_amount,
                        status, customer_request, created_at
                    ) VALUES (?, ?, ?, NULL, ?, ?, ?, 0, 'confirmed', ?, NOW())
                ");
                $stmt->execute([
                    $teacher['user_id'], // 임시로 선생님을 고객으로 설정
                    $teacher['id'],
                    $teacher['business_id'],
                    $reservation_date,
                    $start_time,
                    $end_time,
                    '수동 등록: ' . ($_POST['note'] ?? '개인 일정')
                ]);
                
                $success_message = "일정이 성공적으로 등록되었습니다.";
            }
            
        } elseif ($_POST['action'] === 'add_exception') {
            $stmt = $db->prepare("
                INSERT INTO teacher_exceptions (teacher_id, exception_date, exception_type, start_time, end_time, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $teacher['id'],
                $_POST['exception_date'],
                $_POST['exception_type'],
                $_POST['start_time'] ?: null,
                $_POST['end_time'] ?: null,
                $_POST['reason']
            ]);
            
            $success_message = "예외 일정이 등록되었습니다.";
        }
    } catch (Exception $e) {
        $error_message = "처리 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<style>
.calendar-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.calendar-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.calendar-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.calendar-nav button {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
}

.calendar-nav button:hover {
    background: rgba(255,255,255,0.3);
}

.calendar-table {
    width: 100%;
    table-layout: fixed;
}

.calendar-table th {
    background: #f8f9fa;
    text-align: center;
    padding: 15px 5px;
    font-weight: bold;
    border-bottom: 2px solid #dee2e6;
}

.calendar-table td {
    height: 120px;
    vertical-align: top;
    padding: 5px;
    border: 1px solid #dee2e6;
    position: relative;
}

.calendar-date {
    font-weight: bold;
    margin-bottom: 5px;
    cursor: pointer;
}

.calendar-date:hover {
    color: #007bff;
}

.today {
    background: #e3f2fd;
}

.other-month {
    background: #f8f9fa;
    color: #6c757d;
}

.reservation-item {
    background: #007bff;
    color: white;
    padding: 2px 4px;
    margin: 1px 0;
    border-radius: 3px;
    font-size: 10px;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.reservation-item.pending {
    background: #ffc107;
    color: #212529;
}

.reservation-item.completed {
    background: #28a745;
}

.exception-item {
    background: #dc3545;
    color: white;
    padding: 2px 4px;
    margin: 1px 0;
    border-radius: 3px;
    font-size: 10px;
}

.schedule-info {
    position: absolute;
    bottom: 2px;
    right: 2px;
    font-size: 8px;
    background: rgba(0,0,0,0.1);
    padding: 1px 3px;
    border-radius: 2px;
}

.quick-actions {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.btn-floating {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    margin: 10px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-alt text-primary"></i> 스케줄 달력</h2>
                    <p class="text-muted"><?= htmlspecialchars($teacher['name']) ?>님의 일정 관리</p>
                </div>
                <div>
                    <a href="teacher_mypage.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 마이페이지
                    </a>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addManualReservationModal">
                        <i class="fas fa-plus"></i> 일정 추가
                    </button>
                    <button class="btn btn-warning" data-toggle="modal" data-target="#addExceptionModal">
                        <i class="fas fa-ban"></i> 휴무 등록
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="calendar-container">
        <div class="calendar-header">
            <div class="calendar-nav">
                <button onclick="changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h3><?= $current_year ?>년 <?= $current_month ?>월</h3>
                <button onclick="changeMonth(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div>
                <small>클릭하여 날짜별 상세 일정을 확인하세요</small>
            </div>
        </div>

        <table class="calendar-table">
            <thead>
                <tr>
                    <th style="color: #dc3545;">일</th>
                    <th>월</th>
                    <th>화</th>
                    <th>수</th>
                    <th>목</th>
                    <th>금</th>
                    <th style="color: #007bff;">토</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 달력 그리기
                $current_date = 1;
                $today = date('Y-m-d');
                
                for ($week = 0; $week < 6; $week++):
                    if ($current_date > $days_in_month) break;
                ?>
                <tr>
                    <?php for ($day = 0; $day < 7; $day++): ?>
                        <td class="<?= 
                            ($week == 0 && $day < $first_day_of_week) || $current_date > $days_in_month ? 'other-month' : 
                            (sprintf('%04d-%02d-%02d', $current_year, $current_month, $current_date) == $today ? 'today' : '')
                        ?>">
                            <?php if ($week == 0 && $day < $first_day_of_week): ?>
                                <!-- 이전 달 날짜 -->
                            <?php elseif ($current_date <= $days_in_month): ?>
                                <?php 
                                $current_date_str = sprintf('%04d-%02d-%02d', $current_year, $current_month, $current_date);
                                $day_of_week = $day + 1; // 0=일요일을 1로 변경
                                if ($day_of_week == 7) $day_of_week = 0; // 토요일을 0으로
                                ?>
                                
                                <div class="calendar-date" onclick="showDayDetail('<?= $current_date_str ?>')">
                                    <?= $current_date ?>
                                </div>

                                <!-- 예외 일정 표시 -->
                                <?php if (isset($exceptions_by_date[$current_date_str])): ?>
                                    <div class="exception-item">
                                        <?= $exceptions_by_date[$current_date_str]['exception_type'] === 'off' ? '휴무' : '시간변경' ?>
                                    </div>
                                <?php endif; ?>

                                <!-- 예약 표시 -->
                                <?php if (isset($reservations_by_date[$current_date_str])): ?>
                                    <?php foreach (array_slice($reservations_by_date[$current_date_str], 0, 3) as $reservation): ?>
                                        <div class="reservation-item <?= $reservation['status'] ?>" 
                                             title="<?= htmlspecialchars($reservation['customer_name']) ?> - <?= date('H:i', strtotime($reservation['start_time'])) ?>">
                                            <?= date('H:i', strtotime($reservation['start_time'])) ?> 
                                            <?= htmlspecialchars(substr($reservation['customer_name'], 0, 3)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($reservations_by_date[$current_date_str]) > 3): ?>
                                        <div class="reservation-item" style="background: #6c757d;">
                                            +<?= count($reservations_by_date[$current_date_str]) - 3 ?>개 더
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- 정기 스케줄 정보 -->
                                <?php if (isset($schedules_by_day[$day_of_week]) && !isset($exceptions_by_date[$current_date_str])): ?>
                                    <div class="schedule-info">
                                        <?= date('H:i', strtotime($schedules_by_day[$day_of_week]['start_time'])) ?>-
                                        <?= date('H:i', strtotime($schedules_by_day[$day_of_week]['end_time'])) ?>
                                    </div>
                                <?php endif; ?>

                                <?php $current_date++; ?>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- 범례 -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">범례</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="reservation-item me-2" style="width: 20px; height: 15px; margin-right: 10px;"></div>
                                확정된 예약
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="reservation-item pending me-2" style="width: 20px; height: 15px; margin-right: 10px;"></div>
                                대기 중인 예약
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="reservation-item completed me-2" style="width: 20px; height: 15px; margin-right: 10px;"></div>
                                완료된 예약
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="exception-item me-2" style="width: 20px; height: 15px; margin-right: 10px;"></div>
                                휴무/예외일정
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 수동 예약 추가 모달 -->
<div class="modal fade" id="addManualReservationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">개인 일정 추가</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_manual_reservation">
                    
                    <div class="form-group">
                        <label>날짜 <span class="text-danger">*</span></label>
                        <input type="date" name="reservation_date" class="form-control" 
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>시작 시간 <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>소요 시간 (분) <span class="text-danger">*</span></label>
                                <select name="duration" class="form-control" required>
                                    <option value="30">30분</option>
                                    <option value="60">1시간</option>
                                    <option value="90">1시간 30분</option>
                                    <option value="120">2시간</option>
                                    <option value="180">3시간</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>메모</label>
                        <textarea name="note" class="form-control" rows="3" 
                                  placeholder="개인 일정, 휴식 시간 등"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">추가</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 예외 일정 추가 모달 -->
<div class="modal fade" id="addExceptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">휴무/예외 일정 등록</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_exception">
                    
                    <div class="form-group">
                        <label>날짜 <span class="text-danger">*</span></label>
                        <input type="date" name="exception_date" class="form-control" 
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>유형 <span class="text-danger">*</span></label>
                        <select name="exception_type" class="form-control" required id="exceptionType">
                            <option value="off">하루 종일 휴무</option>
                            <option value="special_hours">시간 변경</option>
                        </select>
                    </div>
                    
                    <div id="timeFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>시작 시간</label>
                                    <input type="time" name="start_time" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>종료 시간</label>
                                    <input type="time" name="end_time" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>사유</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="휴가, 개인 사정, 특별 근무 등"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-warning">등록</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeMonth(direction) {
    let year = <?= $current_year ?>;
    let month = <?= $current_month ?>;
    
    month += direction;
    
    if (month > 12) {
        month = 1;
        year++;
    } else if (month < 1) {
        month = 12;
        year--;
    }
    
    window.location.href = `?year=${year}&month=${month}`;
}

function showDayDetail(date) {
    // 날짜별 상세 정보 표시 (추후 구현)
    alert(`${date} 상세 일정 - 개발 예정`);
}

// 예외 유형 변경 시 시간 필드 표시/숨김
document.getElementById('exceptionType').addEventListener('change', function() {
    const timeFields = document.getElementById('timeFields');
    if (this.value === 'special_hours') {
        timeFields.style.display = 'block';
    } else {
        timeFields.style.display = 'none';
    }
});

// 오늘 날짜로 이동
function goToToday() {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    window.location.href = `?year=${year}&month=${month}`;
}
</script>

<!-- 플로팅 액션 버튼 -->
<div class="quick-actions">
    <button class="btn btn-info btn-floating" onclick="goToToday()" title="오늘로 이동">
        <i class="fas fa-calendar-day"></i>
    </button>
</div>

<?php require_once '../includes/footer.php'; ?>