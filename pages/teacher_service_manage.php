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

// 선생님의 전문분야 가져오기
$teacher_specialties = [];
if ($teacher['specialties']) {
    $specialties_data = json_decode($teacher['specialties'], true);
    if (is_array($specialties_data)) {
        $teacher_specialties = $specialties_data;
    }
}

$success_message = '';
$error_message = '';

// 서비스 추가/수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO teacher_services (teacher_id, service_name, description, category, price_type, fixed_price, min_price, max_price, duration)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $teacher['id'],
                    $_POST['service_name'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['price_type'],
                    $_POST['price_type'] === 'fixed' ? $_POST['fixed_price'] : null,
                    $_POST['price_type'] === 'range' ? $_POST['min_price'] : null,
                    $_POST['price_type'] === 'range' ? $_POST['max_price'] : null,
                    $_POST['duration']
                ]);
                $success_message = "서비스가 성공적으로 추가되었습니다.";
                
            } elseif ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("
                    UPDATE teacher_services 
                    SET service_name = ?, description = ?, category = ?, price_type = ?, 
                        fixed_price = ?, min_price = ?, max_price = ?, duration = ?
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([
                    $_POST['service_name'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['price_type'],
                    $_POST['price_type'] === 'fixed' ? $_POST['fixed_price'] : null,
                    $_POST['price_type'] === 'range' ? $_POST['min_price'] : null,
                    $_POST['price_type'] === 'range' ? $_POST['max_price'] : null,
                    $_POST['duration'],
                    $_POST['service_id'],
                    $teacher['id']
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
                    $teacher['id']
                ]);
                $success_message = "서비스 상태가 변경되었습니다.";
                
            } elseif ($_POST['action'] === 'delete') {
                // 예약이 있는지 확인
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM reservations 
                    WHERE teacher_service_id = ? AND status IN ('pending', 'confirmed')
                ");
                $stmt->execute([$_POST['service_id']]);
                $active_reservations = $stmt->fetch()['count'];
                
                if ($active_reservations > 0) {
                    $error_message = "진행 중인 예약이 있는 서비스는 삭제할 수 없습니다.";
                } else {
                    $stmt = $db->prepare("
                        DELETE FROM teacher_services 
                        WHERE id = ? AND teacher_id = ?
                    ");
                    $stmt->execute([$_POST['service_id'], $teacher['id']]);
                    $success_message = "서비스가 삭제되었습니다.";
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = "처리 중 오류가 발생했습니다.";
    }
}

// 선생님의 서비스 목록 조회
$stmt = $db->prepare("
    SELECT ts.*, 
           COUNT(r.id) as total_reservations,
           COUNT(CASE WHEN r.status IN ('pending', 'confirmed') THEN 1 END) as active_reservations,
           AVG(rv.service_rating) as avg_rating
    FROM teacher_services ts
    LEFT JOIN reservations r ON ts.id = r.teacher_service_id
    LEFT JOIN reviews rv ON r.id = rv.reservation_id
    WHERE ts.teacher_id = ?
    GROUP BY ts.id
    ORDER BY ts.created_at DESC
");
$stmt->execute([$teacher['id']]);
$teacher_services = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-cut text-primary"></i> 내 서비스 관리</h2>
                    <p class="text-muted"><?= htmlspecialchars($teacher['name']) ?>님의 서비스</p>
                </div>
                <div>
                    <a href="teacher_mypage.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 마이페이지
                    </a>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                        <i class="fas fa-plus"></i> 서비스 추가
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

    <!-- 통계 카드 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= count($teacher_services) ?></h4>
                            <p class="mb-0">전체 서비스</p>
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
                            <h4><?= count(array_filter($teacher_services, function($s) { return $s['is_active']; })) ?></h4>
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
                            <h4><?= array_sum(array_column($teacher_services, 'total_reservations')) ?></h4>
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
                            $avg_price = 0;
                            $price_count = 0;
                            foreach ($teacher_services as $service) {
                                if ($service['fixed_price']) {
                                    $avg_price += $service['fixed_price'];
                                    $price_count++;
                                } elseif ($service['min_price'] && $service['max_price']) {
                                    $avg_price += ($service['min_price'] + $service['max_price']) / 2;
                                    $price_count++;
                                }
                            }
                            $avg_price = $price_count > 0 ? $avg_price / $price_count : 0;
                            ?>
                            <h4><?= number_format($avg_price) ?>원</h4>
                            <p class="mb-0">평균 가격</p>
                        </div>
                        <div>
                            <i class="fas fa-won-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 서비스 목록 -->
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0">서비스 목록</h5>
        </div>
        <div class="card-body">
            <?php if (empty($teacher_services)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-cut fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">등록된 서비스가 없습니다</h5>
                    <p class="text-muted">첫 번째 서비스를 추가해보세요!</p>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                        <i class="fas fa-plus"></i> 서비스 추가하기
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>서비스명</th>
                                <th>전문분야</th>
                                <th>가격</th>
                                <th>소요시간</th>
                                <th>예약 수</th>
                                <th>평점</th>
                                <th>상태</th>
                                <th>관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_services as $service): ?>
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
                                        <?= htmlspecialchars($service['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($service['price_type'] === 'fixed'): ?>
                                        <strong class="text-primary"><?= number_format($service['fixed_price']) ?>원</strong>
                                    <?php else: ?>
                                        <strong class="text-primary">
                                            <?= number_format($service['min_price']) ?>원 ~ <?= number_format($service['max_price']) ?>원
                                        </strong>
                                    <?php endif; ?>
                                </td>
                                <td><?= $service['duration'] ?>분</td>
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
                                <label>전문분야 <span class="text-danger">*</span></label>
                                <select name="category" class="form-control" required>
                                    <option value="">선택하세요</option>
                                    <?php foreach ($teacher_specialties as $specialty): ?>
                                        <option value="<?= htmlspecialchars($specialty) ?>"><?= htmlspecialchars($specialty) ?></option>
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
                    
                    <div class="form-group">
                        <label>가격 유형 <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="price_type" id="fixed_price" value="fixed" checked>
                            <label class="form-check-label" for="fixed_price">
                                고정 가격
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="price_type" id="range_price" value="range">
                            <label class="form-check-label" for="range_price">
                                범위 가격
                            </label>
                        </div>
                    </div>
                    
                    <div class="price-fields">
                        <div id="fixed_price_field" class="form-group">
                            <label>가격 (원) <span class="text-danger">*</span></label>
                            <input type="number" name="fixed_price" class="form-control" min="0">
                        </div>
                        
                        <div id="range_price_fields" class="row" style="display: none;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>최소 가격 (원) <span class="text-danger">*</span></label>
                                    <input type="number" name="min_price" class="form-control" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>최대 가격 (원) <span class="text-danger">*</span></label>
                                    <input type="number" name="max_price" class="form-control" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>소요시간 (분) <span class="text-danger">*</span></label>
                        <select name="duration" class="form-control" required>
                            <option value="">선택하세요</option>
                            <option value="30">30분</option>
                            <option value="60">1시간</option>
                            <option value="90">1시간 30분</option>
                            <option value="120">2시간</option>
                            <option value="150">2시간 30분</option>
                            <option value="180">3시간</option>
                            <option value="240">4시간</option>
                        </select>
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
                                <label>전문분야 <span class="text-danger">*</span></label>
                                <select name="category" id="edit_category" class="form-control" required>
                                    <option value="">선택하세요</option>
                                    <?php foreach ($teacher_specialties as $specialty): ?>
                                        <option value="<?= htmlspecialchars($specialty) ?>"><?= htmlspecialchars($specialty) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>서비스 설명</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>가격 유형 <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="price_type" id="edit_fixed_price" value="fixed">
                            <label class="form-check-label" for="edit_fixed_price">
                                고정 가격
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="price_type" id="edit_range_price" value="range">
                            <label class="form-check-label" for="edit_range_price">
                                범위 가격
                            </label>
                        </div>
                    </div>
                    
                    <div class="edit-price-fields">
                        <div id="edit_fixed_price_field" class="form-group">
                            <label>가격 (원) <span class="text-danger">*</span></label>
                            <input type="number" name="fixed_price" id="edit_fixed_price_input" class="form-control" min="0">
                        </div>
                        
                        <div id="edit_range_price_fields" class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>최소 가격 (원) <span class="text-danger">*</span></label>
                                    <input type="number" name="min_price" id="edit_min_price" class="form-control" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>최대 가격 (원) <span class="text-danger">*</span></label>
                                    <input type="number" name="max_price" id="edit_max_price" class="form-control" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>소요시간 (분) <span class="text-danger">*</span></label>
                        <select name="duration" id="edit_duration" class="form-control" required>
                            <option value="">선택하세요</option>
                            <option value="30">30분</option>
                            <option value="60">1시간</option>
                            <option value="90">1시간 30분</option>
                            <option value="120">2시간</option>
                            <option value="150">2시간 30분</option>
                            <option value="180">3시간</option>
                            <option value="240">4시간</option>
                        </select>
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
// 가격 유형 변경 처리
document.addEventListener('DOMContentLoaded', function() {
    const priceTypeRadios = document.querySelectorAll('input[name="price_type"]');
    
    priceTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const fixedField = document.getElementById('fixed_price_field');
            const rangeFields = document.getElementById('range_price_fields');
            
            if (this.value === 'fixed') {
                fixedField.style.display = 'block';
                rangeFields.style.display = 'none';
            } else {
                fixedField.style.display = 'none';
                rangeFields.style.display = 'block';
            }
        });
    });
});

function editService(service) {
    document.getElementById('edit_service_id').value = service.id;
    document.getElementById('edit_service_name').value = service.service_name;
    document.getElementById('edit_category').value = service.category;
    document.getElementById('edit_description').value = service.description || '';
    document.getElementById('edit_duration').value = service.duration;
    
    // 가격 유형 설정
    if (service.price_type === 'fixed') {
        document.getElementById('edit_fixed_price').checked = true;
        document.getElementById('edit_fixed_price_input').value = service.fixed_price;
        document.getElementById('edit_fixed_price_field').style.display = 'block';
        document.getElementById('edit_range_price_fields').style.display = 'none';
    } else {
        document.getElementById('edit_range_price').checked = true;
        document.getElementById('edit_min_price').value = service.min_price;
        document.getElementById('edit_max_price').value = service.max_price;
        document.getElementById('edit_fixed_price_field').style.display = 'none';
        document.getElementById('edit_range_price_fields').style.display = 'block';
    }
    
    $('#editServiceModal').modal('show');
}

function toggleServiceStatus(serviceId, status) {
    if (confirm(status ? '서비스를 활성화하시겠습니까?' : '서비스를 비활성화시키겠습니까?')) {
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
    if (confirm('서비스를 삭제하시겠습니까?\n삭제된 서비스는 복구할 수 없습니다.')) {
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

<?php require_once '../includes/footer.php'; ?>