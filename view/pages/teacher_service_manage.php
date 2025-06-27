<?php
$pageTitle = "서비스 관리";
require_once '../includes/functions.php';

// 로그인 체크 및 선생님 권한 확인
if (!isLoggedIn() || getUserRole() !== 'teacher') {
    redirect('../pages/login.php');
}

$teacher_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// 선생님 정보 및 전문분야 가져오기
$teacher_query = "SELECT t.specialty, u.name FROM teachers t JOIN users u ON t.id = u.id WHERE t.id = :teacher_id";
$teacher_stmt = $db->prepare($teacher_query);
$teacher_stmt->bindParam(':teacher_id', $teacher_id);
$teacher_stmt->execute();
$teacher_info = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// 서비스 추가/수정 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $service_name = sanitizeInput($_POST['service_name']);
            $description = sanitizeInput($_POST['description']);
            $price_type = sanitizeInput($_POST['price_type']);
            $min_price = floatval($_POST['min_price']);
            $max_price = floatval($_POST['max_price']);
            $fixed_price = floatval($_POST['fixed_price']);
            $duration = intval($_POST['duration']);
            
            if (empty($service_name) || empty($price_type) || $duration <= 0) {
                $error = "모든 필수 필드를 입력해주세요.";
            } else {
                try {
                    $insert_query = "INSERT INTO teacher_services (teacher_id, service_name, description, price_type, min_price, max_price, fixed_price, duration_minutes, status) 
                                   VALUES (:teacher_id, :service_name, :description, :price_type, :min_price, :max_price, :fixed_price, :duration, 'active')";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':teacher_id', $teacher_id);
                    $insert_stmt->bindParam(':service_name', $service_name);
                    $insert_stmt->bindParam(':description', $description);
                    $insert_stmt->bindParam(':price_type', $price_type);
                    $insert_stmt->bindParam(':min_price', $min_price);
                    $insert_stmt->bindParam(':max_price', $max_price);
                    $insert_stmt->bindParam(':fixed_price', $fixed_price);
                    $insert_stmt->bindParam(':duration', $duration);
                    $insert_stmt->execute();
                    
                    $message = "서비스가 성공적으로 등록되었습니다.";
                } catch (Exception $e) {
                    $error = "서비스 등록 중 오류가 발생했습니다: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $service_id = intval($_POST['service_id']);
            try {
                $delete_query = "DELETE FROM teacher_services WHERE id = :service_id AND teacher_id = :teacher_id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->bindParam(':service_id', $service_id);
                $delete_stmt->bindParam(':teacher_id', $teacher_id);
                $delete_stmt->execute();
                
                $message = "서비스가 삭제되었습니다.";
            } catch (Exception $e) {
                $error = "서비스 삭제 중 오류가 발생했습니다: " . $e->getMessage();
            }
        }
    }
}

// 서비스 목록 가져오기
$services_query = "SELECT * FROM teacher_services WHERE teacher_id = :teacher_id ORDER BY created_at DESC";
$services_stmt = $db->prepare($services_query);
$services_stmt->bindParam(':teacher_id', $teacher_id);
$services_stmt->execute();
$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>서비스 관리</h2>
            <span class="badge bg-info">전문분야: <?php echo htmlspecialchars($teacher_info['specialty']); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- 서비스 추가 폼 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">새 서비스 등록</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_name" class="form-label">서비스명 *</label>
                                <input type="text" class="form-control" id="service_name" name="service_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="duration" class="form-label">시술 시간 (분) *</label>
                                <input type="number" class="form-control" id="duration" name="duration" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">서비스 설명</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="price_type" class="form-label">가격 유형 *</label>
                        <select class="form-control" id="price_type" name="price_type" required onchange="togglePriceFields()">
                            <option value="">선택하세요</option>
                            <option value="fixed">고정 금액</option>
                            <option value="range">금액 범위</option>
                        </select>
                    </div>

                    <div class="row" id="fixed_price_field" style="display: none;">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fixed_price" class="form-label">고정 금액 (원)</label>
                                <input type="number" class="form-control" id="fixed_price" name="fixed_price" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="row" id="range_price_fields" style="display: none;">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="min_price" class="form-label">최소 금액 (원)</label>
                                <input type="number" class="form-control" id="min_price" name="min_price" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_price" class="form-label">최대 금액 (원)</label>
                                <input type="number" class="form-control" id="max_price" name="max_price" min="0">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">서비스 등록</button>
                </form>
            </div>
        </div>

        <!-- 서비스 목록 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">등록된 서비스</h5>
            </div>
            <div class="card-body">
                <?php if (empty($services)): ?>
                    <p class="text-muted">등록된 서비스가 없습니다.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>서비스명</th>
                                    <th>설명</th>
                                    <th>가격</th>
                                    <th>시술시간</th>
                                    <th>등록일</th>
                                    <th>관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                        <td><?php echo htmlspecialchars($service['description']); ?></td>
                                        <td>
                                            <?php if ($service['price_type'] == 'fixed'): ?>
                                                <?php echo number_format($service['fixed_price']); ?>원
                                            <?php else: ?>
                                                <?php echo number_format($service['min_price']); ?>원 ~ <?php echo number_format($service['max_price']); ?>원
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $service['duration_minutes']; ?>분</td>
                                        <td><?php echo date('Y-m-d', strtotime($service['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                            </form>
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
</div>

<script>
function togglePriceFields() {
    const priceType = document.getElementById('price_type').value;
    const fixedField = document.getElementById('fixed_price_field');
    const rangeFields = document.getElementById('range_price_fields');
    
    if (priceType === 'fixed') {
        fixedField.style.display = 'block';
        rangeFields.style.display = 'none';
        document.getElementById('min_price').required = false;
        document.getElementById('max_price').required = false;
        document.getElementById('fixed_price').required = true;
    } else if (priceType === 'range') {
        fixedField.style.display = 'none';
        rangeFields.style.display = 'block';
        document.getElementById('min_price').required = true;
        document.getElementById('max_price').required = true;
        document.getElementById('fixed_price').required = false;
    } else {
        fixedField.style.display = 'none';
        rangeFields.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>