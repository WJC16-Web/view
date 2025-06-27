<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// ?체관리자 권한 ?인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();

// ?체 ?보 조회
$stmt = $db->prepare("SELECT * FROM businesses WHERE owner_id = ?");
$stmt->execute([$user_id]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: business_register.php');
    exit;
}

$business_id = $business['id'];

// ?비??추?/?정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO business_services (business_id, service_name, description, price, duration, category, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $business_id,
                    $_POST['service_name'],
                    $_POST['description'],
                    $_POST['price'],
                    $_POST['duration'],
                    $_POST['category']
                ]);
                $success_message = "?비? ?공?으?추??었?니?";
                
            } elseif ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("
                    UPDATE business_services 
                    SET service_name = ?, description = ?, price = ?, duration = ?, category = ?
                    WHERE id = ? AND business_id = ?
                ");
                $stmt->execute([
                    $_POST['service_name'],
                    $_POST['description'],
                    $_POST['price'],
                    $_POST['duration'],
                    $_POST['category'],
                    $_POST['service_id'],
                    $business_id
                ]);
                $success_message = "?비? ?공?으??정?었?니?";
                
            } elseif ($_POST['action'] === 'toggle_status') {
                $stmt = $db->prepare("
                    UPDATE business_services 
                    SET is_active = ? 
                    WHERE id = ? AND business_id = ?
                ");
                $stmt->execute([
                    $_POST['status'],
                    $_POST['service_id'],
                    $business_id
                ]);
                $success_message = "?비???태가 변경되습?다.";
                
            } elseif ($_POST['action'] === 'delete') {
                // ?약???는지 ?인
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM reservations 
                    WHERE service_id = ? AND status IN ('pending', 'confirmed')
                ");
                $stmt->execute([$_POST['service_id']]);
                $active_reservations = $stmt->fetch()['count'];
                
                if ($active_reservations > 0) {
                    $error_message = "진행 중인 ?약???는 ?비?는 ??습?다.";
                } else {
                    $stmt = $db->prepare("
                        DELETE FROM business_services 
                        WHERE id = ? AND business_id = ?
                    ");
                    $stmt->execute([$_POST['service_id'], $business_id]);
                    $success_message = "?비? ??었?니?";
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = "처리 ?류가 발생습?다.";
    }
}

// ?비??목록 조회
$stmt = $db->prepare("
    SELECT bs.*, 
           COUNT(r.id) as total_reservations,
           COUNT(CASE WHEN r.status IN ('pending', 'confirmed') THEN 1 END) as active_reservations,
           AVG(rv.service_rating) as avg_rating
    FROM business_services bs
    LEFT JOIN reservations r ON bs.id = r.service_id
    LEFT JOIN reviews rv ON r.id = rv.reservation_id
    WHERE bs.business_id = ?
    GROUP BY bs.id
    ORDER BY bs.created_at DESC
");
$stmt->execute([$business_id]);
$services = $stmt->fetchAll();

// 카테고리 목록
$categories = [
    'nail' => '?일',
    'hair' => '?어',
    'waxing' => '?싱',
    'skincare' => '?관,
    'massage' => '마사지',
    'makeup' => '메이?업',
    'tanning' => '?닝',
    'pedicure' => '?디?어'
];

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-cut text-primary"></i> ?비??관?/h2>
                    <p class="text-muted"><?= htmlspecialchars($business['name']) ?></p>
                </div>
                <div>
                    <a href="business_dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> ??보??
                    </a>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                        <i class="fas fa-plus"></i> ?비??추?
                    </button>
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

    <!-- ?계 카드 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= count($services) ?></h4>
                            <p class="mb-0">?체 ?비??/p>
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
                            <p class="mb-0">?성 ?비??/p>
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
                            <p class="mb-0">�??약 ??/p>
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
                            $avg_price = count($services) > 0 ? array_sum(array_column($services, 'price')) / count($services) : 0;
                            ?>
                            <h4><?= number_format($avg_price) ?>??/h4>
                            <p class="mb-0">?균 가?/p>
                        </div>
                        <div>
                            <i class="fas fa-won-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ?비??목록 -->
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0">?비??목록</h5>
        </div>
        <div class="card-body">
            <?php if (empty($services)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-cut fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">?록???비? ?습?다</h5>
                    <p class="text-muted?번째 ?비? 추?보?요!</p>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                        <i class="fas fa-plus"></i> ?비??추??기
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>?비?명</th>
                                <th>카테고리</th>
                                <th>가?</th>
                                <th>?요?간</th>
                                <th>?약 ??</th>
                                <th>?점</th>
                                <th>?태</th>
                                <th>?관?</th>
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
                                        <?= $categories[$service['category']] ?? $service['category'] ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-primary"><?= number_format($service['price']) ?>??/strong>
                                </td>
                                <td><?= $service['duration'] ?>�?</td>
                                <td>
                                    <span class="badge badge-info"><?= $service['total_reservations'] ?>?</span>
                                    <?php if ($service['active_reservations'] > 0): ?>
                                        <br><small class="text-warning">진행 ? <?= $service['active_reservations'] ?>?</small>
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
                                        <span class="text-muted">?점 ?음</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($service['is_active']): ?>
                                        <span class="badge badge-success">?성</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">비활??/span>
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

<!-- ?비??추? 모달 -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">?비??추?</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>?비?명 <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>카테고리 <span class="text-danger">*</span></label>
                                <select name="category" class="form-control" required>
                                    <option value="">?택?세??/option>
                                    <?php foreach ($categories as $key => $name): ?>
                                        <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>?�비???�명</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="?�비?�에 ?�???�세???�명???�력?�세??></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>가�?(?? <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>?�요?�간 (�? <span class="text-danger">*</span></label>
                                <select name="duration" class="form-control" required>
                                    <option value="">?�택?�세??/option>
                                    <option value="30">30�?/option>
                                    <option value="60">1?�간</option>
                                    <option value="90">1?�간 30�?/option>
                                    <option value="120">2?�간</option>
                                    <option value="150">2?�간 30�?/option>
                                    <option value="180">3?�간</option>
                                    <option value="240">4?�간</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">추�?</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ?�비???�정 모달 -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">?�비???�정</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" id="editServiceForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="service_id" id="edit_service_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>?�비?�명 <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>카테고리 <span class="text-danger">*</span></label>
                                <select name="category" id="edit_category" class="form-control" required>
                                    <option value="">?�택?�세??/option>
                                    <?php foreach ($categories as $key => $name): ?>
                                        <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>?�비???�명</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>가�?(?? <span class="text-danger">*</span></label>
                                <input type="number" name="price" id="edit_price" class="form-control" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>?�요?�간 (�? <span class="text-danger">*</span></label>
                                <select name="duration" id="edit_duration" class="form-control" required>
                                    <option value="">?�택?�세??/option>
                                    <option value="30">30�?/option>
                                    <option value="60">1?�간</option>
                                    <option value="90">1?�간 30�?/option>
                                    <option value="120">2?�간</option>
                                    <option value="150">2?�간 30�?/option>
                                    <option value="180">3?�간</option>
                                    <option value="240">4?�간</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">?�정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editService(service) {
    document.getElementById('edit_service_id').value = service.id;
    document.getElementById('edit_service_name').value = service.service_name;
    document.getElementById('edit_category').value = service.category;
    document.getElementById('edit_description').value = service.description || '';
    document.getElementById('edit_price').value = service.price;
    document.getElementById('edit_duration').value = service.duration;
    
    $('#editServiceModal').modal('show');
}

function toggleServiceStatus(serviceId, status) {
    if (confirm(status ? '?�비?��? ?�성?�하?�겠?�니�?' : '?�비?��? 비활?�화?�시겠습?�까?')) {
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
    if (confirm('?�말�????�비?��? ??��?�시겠습?�까?\n??��???�비?�는 복구?????�습?�다.')) {
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

// ?�자 ?�력 ??천단??구분???�시
document.addEventListener('DOMContentLoaded', function() {
    const priceInputs = document.querySelectorAll('input[name="price"]');
    priceInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            // ?�자�??�기�?
            let value = this.value.replace(/[^0-9]/g, '');
            // 천단??구분??추�? (?�시??
            this.setAttribute('data-formatted', value.replace(/\B(?=(\d{3})+(?!\d))/g, ','));
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?> 
