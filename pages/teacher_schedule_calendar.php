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
    SELECT t.*, u.name, b.name as business_name
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

$teacher_id = $teacher['id'];

// 현재 년월 가져오기
$current_year = $_GET['year'] ?? date('Y');
$current_month = $_GET['month'] ?? date('m');
$calendar_date = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT);

// 달력 데이터 생성
$first_day = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '-01';
$last_day = date('Y-m-t', strtotime($first_day));
$start_calendar = date('Y-m-d', strtotime('last sunday', strtotime($first_day)));
$end_calendar = date('Y-m-d', strtotime('next saturday', strtotime($last_day)));

// 해당 월의 예약 데이터 조회
$stmt = $db->prepare("
    SELECT r.*, u.name as customer_name, ts.service_name
    FROM reservations r
    JOIN users u ON r.customer_id = u.id
    LEFT JOIN teacher_services ts ON r.teacher_id = ts.teacher_id
    WHERE r.teacher_id = ? 
    AND r.reservation_date BETWEEN ? AND ?
    ORDER BY r.reservation_date, r.start_time
");
$stmt->execute([$teacher_id, $start_calendar, $end_calendar]);
$reservations = $stmt->fetchAll();

// 날짜별 예약 그룹화
$reservation_by_date = [];
foreach ($reservations as $reservation) {
    $date = $reservation['reservation_date'];
    if (!isset($reservation_by_date[$date])) {
        $reservation_by_date[$date] = [];
    }
    $reservation_by_date[$date][] = $reservation;
}

// 선생님 정기 스케줄 조회
$stmt = $db->prepare("
    SELECT * FROM teacher_schedules 
    WHERE teacher_id = ? AND is_active = 1
    ORDER BY day_of_week ASC
");
$stmt->execute([$teacher_id]);
$regular_schedules = $stmt->fetchAll();

// 요일별 스케줄 매핑
$schedule_by_day = [];
foreach ($regular_schedules as $schedule) {
    $schedule_by_day[$schedule['day_of_week']] = $schedule;
}

// 예외 일정 조회
$stmt = $db->prepare("
    SELECT * FROM teacher_exceptions 
    WHERE teacher_id = ? 
    AND exception_date BETWEEN ? AND ?
");
$stmt->execute([$teacher_id, $start_calendar, $end_calendar]);
$exceptions = $stmt->fetchAll();

// 날짜별 예외 일정 매핑
$exceptions_by_date = [];
foreach ($exceptions as $exception) {
    $exceptions_by_date[$exception['exception_date']] = $exception;
}

include '../includes/header.php';
?>

<style>
.calendar-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.calendar-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
}

.calendar-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.calendar-nav button {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.calendar-nav button:hover {
    background: rgba(255, 255, 255, 0.3);
}

.calendar-title {
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    margin: 0;
}

.calendar-table {
    width: 100%;
    border-collapse: collapse;
}

.calendar-table th {
    background: #f8f9fa;
    padding: 15px 5px;
    text-align: center;
    font-weight: bold;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.calendar-table td {
    height: 120px;
    vertical-align: top;
    padding: 8px;
    border: 1px solid #dee2e6;
    position: relative;
    background: white;
}

.calendar-table td.other-month {
    background: #f8f9fa;
    color: #6c757d;
}

.calendar-table td.today {
    background: #fff3cd;
    border-color: #ffc107;
}

.calendar-table td.working-day {
    background: #e8f5e8;
}

.calendar-table td.holiday {
    background: #ffe8e8;
}

.date-number {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 5px;
}

.reservation-item {
    background: #007bff;
    color: white;
    padding: 2px 5px;
    margin: 1px 0;
    border-radius: 3px;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
}

.reservation-item.pending {
    background: #ffc107;
    color: #212529;
}

.reservation-item.confirmed {
    background: #28a745;
}

.reservation-item.completed {
    background: #6c757d;
}

.reservation-item.cancelled {
    background: #dc3545;
}

.working-hours {
    font-size: 10px;
    color: #28a745;
    font-weight: bold;
}

.exception-notice {
    font-size: 10px;
    color: #dc3545;
    font-weight: bold;
}

.legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.quick-actions {
    margin-bottom: 20px;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-alt text-primary"></i> 스케줄 달력</h2>
                    <p class="text-muted"><?= htmlspecialchars($teacher['name']) ?> 선생님 - <?= htmlspecialchars($teacher['business_name']) ?></p>
                </div>
                <div class="quick-actions">
                    <a href="teacher_mypage.php?tab=schedule" class="btn btn-outline-secondary">
                        <i class="fas fa-cog"></i> 스케줄 설정
                    </a>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addExceptionModal">
                        <i class="fas fa-plus"></i> 예외 일정 추가
                    </button>
                    <a href="teacher_mypage.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> 마이페이지
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="calendar-container">
        <div class="calendar-header">
            <div class="calendar-nav">
                <button onclick="changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i> 이전 달
                </button>
                <button onclick="goToday()">
                    <i class="fas fa-home"></i> 오늘
                </button>
                <button onclick="changeMonth(1)">
                    다음 달 <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <h3 class="calendar-title"><?= date('Y년 m월', strtotime($calendar_date)) ?></h3>
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
                $current_date = $start_calendar;
                while ($current_date <= $end_calendar):
                    echo '<tr>';
                    for ($i = 0; $i < 7; $i++):
                        $day_of_week = date('w', strtotime($current_date));
                        $is_current_month = date('m', strtotime($current_date)) == $current_month;
                        $is_today = $current_date == date('Y-m-d');
                        
                        // 근무일인지 확인
                        $working_schedule = $schedule_by_day[$day_of_week] ?? null;
                        $exception = $exceptions_by_date[$current_date] ?? null;
                        
                        $css_classes = [];
                        if (!$is_current_month) $css_classes[] = 'other-month';
                        if ($is_today) $css_classes[] = 'today';
                        if ($working_schedule && !$exception) $css_classes[] = 'working-day';
                        if ($exception && $exception['exception_type'] === 'off') $css_classes[] = 'holiday';
                        
                        echo '<td class="' . implode(' ', $css_classes) . '">';
                        echo '<div class="date-number">' . date('j', strtotime($current_date)) . '</div>';
                        
                        // 근무 시간 표시
                        if ($working_schedule && !$exception) {
                            echo '<div class="working-hours">';
                            echo date('H:i', strtotime($working_schedule['start_time'])) . '-';
                            echo date('H:i', strtotime($working_schedule['end_time']));
                            echo '</div>';
                        }
                        
                        // 예외 일정 표시
                        if ($exception) {
                            echo '<div class="exception-notice">';
                            if ($exception['exception_type'] === 'off') {
                                echo '휴무';
                            } elseif ($exception['exception_type'] === 'special_hours') {
                                echo '특별근무';
                                if ($exception['start_time']) {
                                    echo '<br>' . date('H:i', strtotime($exception['start_time'])) . '-';
                                    echo date('H:i', strtotime($exception['end_time']));
                                }
                            }
                            echo '</div>';
                        }
                        
                        // 예약 표시
                        if (isset($reservation_by_date[$current_date])) {
                            foreach ($reservation_by_date[$current_date] as $reservation) {
                                $status_class = $reservation['status'];
                                echo '<div class="reservation-item ' . $status_class . '" ';
                                echo 'title="' . htmlspecialchars($reservation['customer_name']) . ' - ';
                                echo date('H:i', strtotime($reservation['start_time'])) . '">';
                                echo date('H:i', strtotime($reservation['start_time'])) . ' ';
                                echo htmlspecialchars(mb_substr($reservation['customer_name'], 0, 3)) . '..';
                                echo '</div>';
                            }
                        }
                        
                        echo '</td>';
                        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                    endfor;
                    echo '</tr>';
                endwhile;
                ?>
            </tbody>
        </table>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: #e8f5e8;"></div>
                <span>근무일</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #ffe8e8;"></div>
                <span>휴무일</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #ffc107;"></div>
                <span>승인 대기</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #28a745;"></div>
                <span>확정 예약</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #6c757d;"></div>
                <span>완료 예약</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #dc3545;"></div>
                <span>취소 예약</span>
            </div>
        </div>
    </div>
</div>

<!-- 예외 일정 추가 모달 -->
<div class="modal fade" id="addExceptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">예외 일정 추가</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" action="teacher_mypage.php?tab=schedule">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_exception">
                    
                    <div class="form-group">
                        <label>날짜 <span class="text-danger">*</span></label>
                        <input type="date" name="exception_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>유형 <span class="text-danger">*</span></label>
                        <select name="exception_type" class="form-control" onchange="toggleTimeFields(this)" required>
                            <option value="">선택하세요</option>
                            <option value="off">휴무</option>
                            <option value="special_hours">특별 근무시간</option>
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
                        <textarea name="reason" class="form-control" rows="3" placeholder="예외 일정 사유를 입력하세요"></textarea>
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

<script>
function changeMonth(delta) {
    const currentYear = <?= $current_year ?>;
    const currentMonth = <?= $current_month ?>;
    
    let newMonth = currentMonth + delta;
    let newYear = currentYear;
    
    if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    } else if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    }
    
    window.location.href = `?year=${newYear}&month=${newMonth}`;
}

function goToday() {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    window.location.href = `?year=${year}&month=${month}`;
}

function toggleTimeFields(select) {
    const timeFields = document.getElementById('timeFields');
    if (select.value === 'special_hours') {
        timeFields.style.display = 'block';
    } else {
        timeFields.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>