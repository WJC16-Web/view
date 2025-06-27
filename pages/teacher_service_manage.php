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
    SELECT t.*, u.name, b.name as business_name, b.category, b.subcategories
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
$business_id = $teacher['business_id'];

// 업체 카테고리 정의
$business_categories = [
    'nail' => '네일',
    'hair' => '헤어',
    'waxing' => '왁싱',
    'skincare' => '피부관리',
    'eyebrow' => '속눈썹/눈썹',
    'massage' => '마사지',
    'makeup' => '메이크업',
    'total' => '토탈뷰티'
];

// 선생님의 전문분야 파싱
$teacher_specialties = [];
if ($teacher['specialty']) {
    $specialties = json_decode($teacher['specialty'], true);
    if ($specialties) {
        foreach ($specialties as $specialty) {
            if (isset($business_categories[$specialty])) {
                $teacher_specialties[$specialty] = $business_categories[$specialty];
            }
        }
    }
}

// 서비스 추가/수정/삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO teacher_services (teacher_id, business_id, service_name, description, category, price_type, price_min, price_max, duration_min, duration_max, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $teacher_id,
                    $business_id,
                    $_POST['service_name'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['price_type'],
                    $_POST['price_min'],
                    $_POST['price_max'] ?: null,
                    $_POST['duration_min'],
                    $_POST['duration_max'] ?: null
                ]);
                $success_message = "서비스가 성공적으로 추가되었습니다.";
                
            } elseif ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("
                    UPDATE teacher_services 
                    SET service_name = ?, description = ?, category = ?, price_type = ?, price_min = ?, price_max = ?, duration_min = ?, duration_max = ?
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([
                    $_POST['service_name'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['price_type'],
                    $_POST['price_min'],
                    $_POST['price_max'] ?: null,
                    $_POST['duration_min'],
                    $_POST['duration_max'] ?: null,
                    $_POST['service_id'],
                    $teacher_id
                ]);
                $success_message = "서비스가 성공적으로 수정되었습니다.";
                
            } elseif ($_POST['action'] === 'toggle_status') {
                $stmt = $db->prepare("
                    UPDATE teacher_services 
                    SET is_active = ? 
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([
                    $_POST['status'],
                    $_POST['service_id'],
                    $teacher_id
                ]);
                $success_message = "서비스 상태가 변경되었습니다.";
                
            } elseif ($_POST['action'] === 'delete') {
                // 예약이 있는지 확인
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM reservations 
                    WHERE teacher_id = ? AND status IN ('pending', 'confirmed')
                ");
                $stmt->execute([$teacher_id]);
                $active_reservations = $stmt->fetch()['count'];
                
                if ($active_reservations > 0) {
                    $error_message = "진행 중인 예약이 있는 서비스는 삭제할 수 없습니다.";
                } else {
                    $stmt = $db->prepare("
                        DELETE FROM teacher_services 
                        WHERE id = ? AND teacher_id = ?
                    ");
                    $stmt->execute([$_POST['service_id'], $teacher_id]);
                    $success_message = "서비스가 삭제되었습니다.";
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = "처리 중 오류가 발생했습니다.";
    }
}

// 서비스 목록 조회
$stmt = $db->prepare("
    SELECT ts.*, 
           COUNT(r.id) as total_reservations,
           COUNT(CASE WHEN r.status IN ('pending', 'confirmed') THEN 1 END) as active_reservations,
           AVG(rv.service_rating) as avg_rating
    FROM teacher_services ts
    LEFT JOIN reservations r ON ts.teacher_id = r.teacher_id
    LEFT JOIN reviews rv ON r.id = rv.reservation_id
    WHERE ts.teacher_id = ?
    GROUP BY ts.id
    ORDER BY ts.created_at DESC
");
$stmt->execute([$teacher_id]);
$services = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-cut text-primary"></i> 내 서비스 관리</h2>
                    <p class="text-muted"><?= htmlspecialchars($teacher['name']) ?> 선생님 - <?= htmlspecialchars($teacher['business_name']) ?></p>
                </div>
                <div>
                    <a href="teacher_mypage.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 마이페이지
                    </a>
                    <?php if (!empty($teacher_specialties)): ?>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                            <i class="fas fa-plus"></i> 서비스 추가
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 전문분야 정보 -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-star text-warning"></i> 내 전문분야</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($teacher_specialties)): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($teacher_specialties as $key => $name): ?>
                        <span class="badge badge-primary badge-lg"><?= $name ?></span>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted mt-2 d-block">이 전문분야에 해당하는 서비스만 등록할 수 있습니다.</small>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    전문분야가 설정되지 않았습니다. 업체 관리자에게 문의하세요.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 통계 카드 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= count($services) ?></h4>
                            <p class="mb-0">등록된 서비스</p>
                        </div>
                        <div>
                            <i class="fas fa-cut fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= count(array_filter($services, function($s) { return $s['is_active']; })) ?></h4>
                            <p class="mb-0">활성 서비스</p>
                        </div>
                        <div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= array_sum(array_column($services, 'total_reservations')) ?></h4>
                            <p class="mb-0">총 예약 수</p>
                        </div>
                        <div>
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php 
                            $avg_rating = 0;
                            $rating_count = 0;
                            foreach ($services as $service) {
                                if ($service['avg_rating']) {
                                    $avg_rating += $service['avg_rating'];
                                    $rating_count++;
                                }
                            }
                            $overall_rating = $rating_count > 0 ? $avg_rating / $rating_count : 0;
                            ?>
                            <h4><?= number_format($overall_rating, 1) ?></h4>
                            <p class="mb-0">평균 평점</p>
                        </div>
                        <div>
                            <i class="fas fa-star fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 서비스 목록 -->
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0">내 서비스 목록</h5>
        </div>
        <div class="card-body">
            <?php if (empty($services)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-cut fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">등록된 서비스가 없습니다</h5>
                    <p class="text-muted">첫 번째 서비스를 추가해보세요!</p>
                    <?php if (!empty($teacher_specialties)): ?>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                            <i class="fas fa-plus"></i> 서비스 추가하기
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>서비스명</th>
                                <th>카테고리</th>
                                <th>가격</th>
                                <th>소요시간</th>
                                <th>예약 수</th>
                                <th>평점</th>
                                <th>상태</th>
                                <th>관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                                        <?php if ($service['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($service['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?= $teacher_specialties[$service['category']] ?? $service['category'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($service['price_type'] === 'fixed'): ?>
                                        <strong class="text-primary"><?= number_format($service['price_min']) ?>원</strong>
                                    <?php else: ?>
                                        <strong class="text-primary"><?= number_format($service['price_min']) ?>원 ~ <?= number_format($service['price_max']) ?>원</strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($service['duration_max'] && $service['duration_max'] != $service['duration_min']): ?>
                                        <?= $service['duration_min'] ?>분 ~ <?= $service['duration_max'] ?>분
                                    <?php else: ?>
                                        <?= $service['duration_min'] ?>분
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= $service['total_reservations'] ?>건</span>
                                    <?php if ($service['active_reservations'] > 0): ?>
                                        <br><small class="text-warning">진행 중 <?= $service['active_reservations'] ?>건</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($service['avg_rating']): ?>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= round($service['avg_rating']) ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small><?= number_format($service['avg_rating'], 1) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">평점 없음</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($service['is_active']): ?>
                                        <span class="badge badge-success">활성</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">비활성</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editService(<?= htmlspecialchars(json_encode($service)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-<?= $service['is_active'] ? 'warning' : 'success' ?>" 
                                                onclick="toggleServiceStatus(<?= $service['id'] ?>, <?= $service['is_active'] ? 0 : 1 ?>)">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <?php if ($service['active_reservations'] == 0): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteService(<?= $service['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 서비스 추가 모달 -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">서비스 추가</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>서비스명 <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>카테고리 <span class="text-danger">*</span></label>
                                <select name="category" class="form-control" required>
                                    <option value="">선택하세요</option>
                                    <?php foreach ($teacher_specialties as $key => $name): ?>
                                        <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>서비스 설명</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="서비스에 대한 자세한 설명을 입력하세요"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>가격 타입 <span class="text-danger">*</span></label>
                                <select name="price_type" class="form-control" onchange="togglePriceType(this)" required>
                                    <option value="fixed">고정 가격</option>
                                    <option value="range">가격 범위</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최소 가격 (원) <span class="text-danger">*</span></label>
                                <input type="number" name="price_min" class="form-control" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최대 가격 (원)</label>
                                <input type="number" name="price_max" class="form-control" min="0" disabled>
                                <small class="form-text text-muted">가격 범위 선택 시에만 입력</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최소 소요시간 (분) <span class="text-danger">*</span></label>
                                <input type="number" name="duration_min" class="form-control" min="15" step="15" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최대 소요시간 (분)</label>
                                <input type="number" name="duration_max" class="form-control" min="15" step="15">
                                <small class="form-text text-muted">시간 범위가 있는 경우 입력</small>
                            </div>
                        </div>
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

<!-- 서비스 수정 모달 -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">서비스 수정</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" id="editServiceForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="service_id" id="edit_service_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>서비스명 <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>카테고리 <span class="text-danger">*</span></label>
                                <select name="category" id="edit_category" class="form-control" required>
                                    <option value="">선택하세요</option>
                                    <?php foreach ($teacher_specialties as $key => $name): ?>
                                        <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>서비스 설명</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>가격 타입 <span class="text-danger">*</span></label>
                                <select name="price_type" id="edit_price_type" class="form-control" onchange="toggleEditPriceType(this)" required>
                                    <option value="fixed">고정 가격</option>
                                    <option value="range">가격 범위</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최소 가격 (원) <span class="text-danger">*</span></label>
                                <input type="number" name="price_min" id="edit_price_min" class="form-control" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최대 가격 (원)</label>
                                <input type="number" name="price_max" id="edit_price_max" class="form-control" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최소 소요시간 (분) <span class="text-danger">*</span></label>
                                <input type="number" name="duration_min" id="edit_duration_min" class="form-control" min="15" step="15" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>최대 소요시간 (분)</label>
                                <input type="number" name="duration_max" id="edit_duration_max" class="form-control" min="15" step="15">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">수정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePriceType(select) {
    const priceMaxInput = document.querySelector('input[name="price_max"]');
    if (select.value === 'range') {
        priceMaxInput.disabled = false;
        priceMaxInput.required = true;
    } else {
        priceMaxInput.disabled = true;
        priceMaxInput.required = false;
        priceMaxInput.value = '';
    }
}

function toggleEditPriceType(select) {
    const priceMaxInput = document.getElementById('edit_price_max');
    if (select.value === 'range') {
        priceMaxInput.disabled = false;
        priceMaxInput.required = true;
    } else {
        priceMaxInput.disabled = true;
        priceMaxInput.required = false;
        priceMaxInput.value = '';
    }
}

function editService(service) {
    document.getElementById('edit_service_id').value = service.id;
    document.getElementById('edit_service_name').value = service.service_name;
    document.getElementById('edit_category').value = service.category;
    document.getElementById('edit_description').value = service.description || '';
    document.getElementById('edit_price_type').value = service.price_type;
    document.getElementById('edit_price_min').value = service.price_min;
    document.getElementById('edit_price_max').value = service.price_max || '';
    document.getElementById('edit_duration_min').value = service.duration_min;
    document.getElementById('edit_duration_max').value = service.duration_max || '';
    
    toggleEditPriceType(document.getElementById('edit_price_type'));
    
    $('#editServiceModal').modal('show');
}

function toggleServiceStatus(serviceId, status) {
    if (confirm(status ? '서비스를 활성화하시겠습니까?' : '서비스를 비활성화하시겠습니까?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="service_id" value="${serviceId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteService(serviceId) {
    if (confirm('정말로 이 서비스를 삭제하시겠습니까?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="service_id" value="${serviceId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>