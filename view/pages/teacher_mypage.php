<?php
$pageTitle = "선생님 마이페이지";
require_once '../includes/functions.php';

// 로그인 체크 및 선생님 권한 확인
if (!isLoggedIn() || getUserRole() !== 'teacher') {
    redirect('../pages/login.php');
}

$teacher_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// 선생님 정보 가져오기
$teacher_query = "SELECT u.name, u.email, u.phone, t.specialty, t.introduction, t.career 
                  FROM users u JOIN teachers t ON u.id = t.id WHERE u.id = :teacher_id";
$teacher_stmt = $db->prepare($teacher_query);
$teacher_stmt->bindParam(':teacher_id', $teacher_id);
$teacher_stmt->execute();
$teacher_info = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// 수동 스케줄 등록 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_schedule') {
    $title = sanitizeInput($_POST['title']);
    $start_date = sanitizeInput($_POST['start_date']);
    $start_time = sanitizeInput($_POST['start_time']);
    $end_time = sanitizeInput($_POST['end_time']);
    $description = sanitizeInput($_POST['description']);
    
    if (empty($title) || empty($start_date) || empty($start_time) || empty($end_time)) {
        $error = "모든 필수 필드를 입력해주세요.";
    } else {
        $start_datetime = $start_date . ' ' . $start_time;
        $end_datetime = $start_date . ' ' . $end_time;
        
        try {
            $insert_query = "INSERT INTO teacher_schedules (teacher_id, title, description, start_datetime, end_datetime, type, status) 
                           VALUES (:teacher_id, :title, :description, :start_datetime, :end_datetime, 'manual', 'active')";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':teacher_id', $teacher_id);
            $insert_stmt->bindParam(':title', $title);
            $insert_stmt->bindParam(':description', $description);
            $insert_stmt->bindParam(':start_datetime', $start_datetime);
            $insert_stmt->bindParam(':end_datetime', $end_datetime);
            $insert_stmt->execute();
            
            $message = "스케줄이 성공적으로 등록되었습니다.";
        } catch (Exception $e) {
            $error = "스케줄 등록 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <!-- 선생님 정보 카드 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">내 정보</h5>
            </div>
            <div class="card-body">
                <p><strong>이름:</strong> <?php echo htmlspecialchars($teacher_info['name']); ?></p>
                <p><strong>이메일:</strong> <?php echo htmlspecialchars($teacher_info['email']); ?></p>
                <p><strong>연락처:</strong> <?php echo htmlspecialchars($teacher_info['phone']); ?></p>
                <p><strong>전문분야:</strong> 
                   <span class="badge bg-primary"><?php echo htmlspecialchars($teacher_info['specialty']); ?></span>
                </p>
                <?php if ($teacher_info['career']): ?>
                    <p><strong>경력:</strong> <?php echo nl2br(htmlspecialchars($teacher_info['career'])); ?></p>
                <?php endif; ?>
                <?php if ($teacher_info['introduction']): ?>
                    <p><strong>소개:</strong> <?php echo nl2br(htmlspecialchars($teacher_info['introduction'])); ?></p>
                <?php endif; ?>
                <a href="teacher_edit.php" class="btn btn-outline-primary btn-sm">정보 수정</a>
            </div>
        </div>

        <!-- 수동 스케줄 등록 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">스케줄 등록</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="add_schedule">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">제목 *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="start_date" class="form-label">날짜 *</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">시작 시간 *</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="end_time" class="form-label">종료 시간 *</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">설명</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">등록</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- 스케줄 달력 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">내 스케줄</h5>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- 이벤트 상세 모달 -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle">스케줄 상세</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- 동적으로 내용이 채워집니다 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" id="deleteEventBtn" style="display: none;">삭제</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'ko',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        height: 'auto',
        editable: false,
        eventClick: function(info) {
            showEventDetails(info.event);
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            // AJAX로 스케줄 데이터 가져오기
            fetch('../api/get_teacher_schedule.php?teacher_id=<?php echo $teacher_id; ?>')
                .then(response => response.json())
                .then(data => {
                    successCallback(data);
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    failureCallback(error);
                });
        }
    });
    
    calendar.render();
});

function showEventDetails(event) {
    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
    
    document.getElementById('eventModalTitle').textContent = event.title;
    
    let content = `
        <p><strong>시간:</strong> ${event.start.toLocaleString('ko-KR')}`;
    
    if (event.end) {
        content += ` ~ ${event.end.toLocaleString('ko-KR')}`;
    }
    content += `</p>`;
    
    if (event.extendedProps.description) {
        content += `<p><strong>설명:</strong> ${event.extendedProps.description}</p>`;
    }
    
    content += `<p><strong>유형:</strong> ${event.extendedProps.type === 'reservation' ? '예약' : '수동 등록'}</p>`;
    
    if (event.extendedProps.customer_name) {
        content += `<p><strong>고객:</strong> ${event.extendedProps.customer_name}</p>`;
    }
    
    if (event.extendedProps.service_name) {
        content += `<p><strong>서비스:</strong> ${event.extendedProps.service_name}</p>`;
    }
    
    document.getElementById('eventModalBody').innerHTML = content;
    
    // 수동 등록된 스케줄만 삭제 가능
    const deleteBtn = document.getElementById('deleteEventBtn');
    if (event.extendedProps.type === 'manual') {
        deleteBtn.style.display = 'block';
        deleteBtn.onclick = function() {
            if (confirm('정말 삭제하시겠습니까?')) {
                deleteSchedule(event.id);
            }
        };
    } else {
        deleteBtn.style.display = 'none';
    }
    
    modal.show();
}

function deleteSchedule(scheduleId) {
    fetch('../api/delete_teacher_schedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({schedule_id: scheduleId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('삭제 중 오류가 발생했습니다.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('삭제 중 오류가 발생했습니다.');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>