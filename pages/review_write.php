<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 고객 권한 ?인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();
$reservation_id = $_GET['reservation_id'] ?? 0;

// ?약 ?보 ?인
$stmt = $db->prepare("
    SELECT r.*, b.name as business_name, t.name as teacher_name, 
           bs.service_name, bs.price, u.name as customer_name
    FROM reservations r
    JOIN businesses b ON r.business_id = b.id
    JOIN teachers t ON r.teacher_id = t.id
    JOIN business_services bs ON r.service_id = bs.id
    JOIN users u ON r.customer_id = u.id
    WHERE r.id = ? AND r.customer_id = ? AND r.status = 'completed'
");
$stmt->execute([$reservation_id, $user_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: customer_mypage.php?tab=reservations');
    exit;
}

// ?기 ?성 ?인
$stmt = $db->prepare("SELECT id FROM reviews WHERE reservation_id = ?");
$stmt->execute([$reservation_id]);
$existing_review = $stmt->fetch();

if ($existing_review) {
    header('Location: review_edit.php?id=' . $existing_review['id']);
    exit;
}

// ?기 ?성 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $overall_rating = (int)$_POST['overall_rating'];
        $service_rating = (int)$_POST['service_rating'];
        $kindness_rating = (int)$_POST['kindness_rating'];
        $cleanliness_rating = (int)$_POST['cleanliness_rating'];
        $content = trim($_POST['content']);
        
        // ?기 ?록
        $stmt = $db->prepare("
            INSERT INTO reviews (
                reservation_id, customer_id, business_id, teacher_id,
                overall_rating, service_rating, kindness_rating, cleanliness_rating,
                content, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $reservation_id, $user_id, $reservation['business_id'], $reservation['teacher_id'],
            $overall_rating, $service_rating, $kindness_rating, $cleanliness_rating, $content
        ]);
        
        $review_id = $db->lastInsertId();
        
        // ?진 ?로처리
        if (!empty($_FILES['photos']['name'][0])) {
            $upload_dir = '../uploads/reviews/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['photos']['name'] as $key => $filename) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $new_filename = 'review_' . $review_id . '_' . $key . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $upload_path)) {
                        $stmt = $db->prepare("
                            INSERT INTO review_photos (review_id, photo_url, order_index)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$review_id, 'uploads/reviews/' . $new_filename, $key]);
                    }
                }
            }
        }
        
        // ?�립�?지�?(?�기 ?�성 보상)
        $point_amount = 1000; // ?�기 ?�성 ??1000???�립
        $stmt = $db->prepare("
            INSERT INTO points (customer_id, point_type, amount, description, created_at)
            VALUES (?, 'earned', ?, '?�기 ?�성 ?�립�?, NOW())
        ");
        $stmt->execute([$user_id, $point_amount]);
        
        $db->commit();
        
        $success_message = "?�기가 ?�공?�으�??�록?�었?�니?? {$point_amount}?�이 ?�립?�었?�니??";
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error_message = "?�기 ?�록 �??�류가 발생?�습?�다.";
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-star"></i> ?�기 ?�성</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <?= $success_message ?>
                            <div class="mt-3">
                                <a href="customer_mypage.php?tab=reviews" class="btn btn-primary">???�기 보기</a>
                                <a href="customer_mypage.php?tab=reservations" class="btn btn-outline-primary">?�약 목록</a>
                            </div>
                        </div>
                    <?php elseif (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <!-- ?�약 ?�보 -->
                    <div class="bg-light p-3 rounded mb-4">
                        <h5 class="mb-2"><?= htmlspecialchars($reservation['business_name']) ?></h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>?�생??</strong> <?= htmlspecialchars($reservation['teacher_name']) ?></p>
                                <p class="mb-1"><strong>?�비??</strong> <?= htmlspecialchars($reservation['service_name']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>?�용??</strong> <?= date('Y??m??d??, strtotime($reservation['reservation_date'])) ?></p>
                                <p class="mb-1"><strong>?�용?�간:</strong> <?= date('H:i', strtotime($reservation['start_time'])) ?> ~ <?= date('H:i', strtotime($reservation['end_time'])) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!isset($success_message)): ?>
                    <form method="POST" enctype="multipart/form-data" id="reviewForm">
                        <!-- ?�체 만족??-->
                        <div class="form-group">
                            <label class="font-weight-bold">?�체 만족??<span class="text-danger">*</span></label>
                            <div class="star-rating" data-rating="overall_rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" data-value="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="overall_rating" id="overall_rating" required>
                            <small class="text-muted">별점???�릭??주세??/small>
                        </div>
                        
                        <!-- ?��? ?��? -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">?�비???�질</label>
                                    <div class="star-rating star-rating-sm" data-rating="service_rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" data-value="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="service_rating" id="service_rating" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">친절??/label>
                                    <div class="star-rating star-rating-sm" data-rating="kindness_rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" data-value="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="kindness_rating" id="kindness_rating" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">�?��??/label>
                                    <div class="star-rating star-rating-sm" data-rating="cleanliness_rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" data-value="<?= $i ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="cleanliness_rating" id="cleanliness_rating" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ?�기 ?�용 -->
                        <div class="form-group">
                            <label class="font-weight-bold">?�기 ?�용 <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control" rows="5" 
                                      placeholder="?�비?��? ?�용?�신 ?�감???�세???�어주세?? ?�른 고객?�에�??��????�는 ?�직???�기�??�겨주시�?감사?�겠?�니??" 
                                      required minlength="10"></textarea>
                            <small class="text-muted">최소 10???�상 ?�성??주세??/small>
                        </div>
                        
                        <!-- ?�진 ?�로??-->
                        <div class="form-group">
                            <label class="font-weight-bold">?�진 (?�택)</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="photos[]" id="photos" 
                                       multiple accept="image/*" onchange="previewImages(this)">
                                <label class="custom-file-label" for="photos">?�진 ?�택...</label>
                            </div>
                            <small class="text-muted">최�? 5?�까지 ?�로??가??(jpg, png, gif)</small>
                            
                            <!-- ?��?지 미리보기 -->
                            <div id="imagePreview" class="row mt-3" style="display: none;"></div>
                        </div>
                        
                        <!-- ?�용 ?��? -->
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    <small>?�기 ?�성 ??<a href="#" data-toggle="modal" data-target="#termsModal">?�용?��?</a>???�의?�니??</small>
                                </label>
                            </div>
                        </div>
                        
                        <!-- ?�출 버튼 -->
                        <div class="text-center">
                            <button type="button" class="btn btn-secondary" onclick="history.back()">
                                <i class="fas fa-arrow-left"></i> 취소
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-star"></i> ?�기 ?�록?�기
                            </button>
                        </div>
                        
                        <!-- ?�립�??�내 -->
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-gift"></i> 
                            <strong>?�기 ?�성 ?�택:</strong> ?�기�??�성?�시�?1,000?�의 ?�립금을 ?�립?�다!
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ?�용?��? 모달 -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">?�기 ?�성 ?�용?��?</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>1. ?�기 ?�성 ?�칙</h6>
                <ul>
                    <li>?�제 ?�용 경험??기반???�직???�기�??�성??주세??</li>
                    <li>?�설, 비방, 개인?�보 ?��? ?�함?��? 말아 주세??</li>
                    <li>?�체?� 무�????�용?� ??��?????�습?�다.</li>
                </ul>
                
                <h6>2. ?�진 ?�로??/h6>
                <ul>
                    <li>?�?�권 문제가 ?�는 본인??촬영???�진�??�로?�해 주세??</li>
                    <li>?�?�의 ?�굴???�별?�는 ?�진?� ?�로?�하지 말아 주세??</li>
                    <li>부?�절???�진?� 관리자???�해 ??��?????�습?�다.</li>
                </ul>
                
                <h6>3. ?�기 관�?/h6>
                <ul>
                    <li>?�성???�기???�정 �???��가 가?�합?�다.</li>
                    <li>?�위 ?�기???�의?�인 ?�기????��?�며, 계정 ?�재�?받을 ???�습?�다.</li>
                    <li>?�기 ?�성?�로 지급된 ?�립금�? ?�기 ??�� ???�수?�니??</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">?�인</button>
            </div>
        </div>
    </div>
</div>

<style>
.star-rating {
    font-size: 2rem;
    color: #ddd;
    margin: 10px 0;
}

.star-rating-sm {
    font-size: 1.2rem;
}

.star-rating i {
    cursor: pointer;
    transition: color 0.2s;
}

.star-rating i:hover,
.star-rating i.active {
    color: #ffc107;
}

.image-preview {
    position: relative;
    display: inline-block;
    margin: 5px;
}

.image-preview img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
}

.image-preview .remove-image {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    cursor: pointer;
}
</style>

<script>
// 별점 기능
document.querySelectorAll('.star-rating').forEach(function(rating) {
    const stars = rating.querySelectorAll('i');
    const ratingName = rating.dataset.rating;
    const hiddenInput = document.getElementById(ratingName);
    
    stars.forEach(function(star, index) {
        star.addEventListener('click', function() {
            const value = parseInt(star.dataset.value);
            hiddenInput.value = value;
            
            // 별점 ?�시 ?�데?�트
            stars.forEach(function(s, i) {
                if (i < value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
        
        star.addEventListener('mouseover', function() {
            const value = parseInt(star.dataset.value);
            stars.forEach(function(s, i) {
                if (i < value) {
                    s.style.color = '#ffc107';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });
    });
    
    rating.addEventListener('mouseleave', function() {
        const currentValue = parseInt(hiddenInput.value) || 0;
        stars.forEach(function(s, i) {
            if (i < currentValue) {
                s.style.color = '#ffc107';
            } else {
                s.style.color = '#ddd';
            }
        });
    });
});

// ?��?지 미리보기
function previewImages(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (input.files.length > 0) {
        preview.style.display = 'block';
        
        // 최�? 5???�한
        const files = Array.from(input.files).slice(0, 5);
        
        files.forEach(function(file, index) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'col-auto';
                    div.innerHTML = `
                        <div class="image-preview">
                            <img src="${e.target.result}" alt="미리보기">
                            <button type="button" class="remove-image" onclick="removeImage(${index})">×</button>
                        </div>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        });
        
        // ?�일�??�데?�트
        const label = document.querySelector('.custom-file-label');
        if (files.length === 1) {
            label.textContent = files[0].name;
        } else {
            label.textContent = `${files.length}�??�일 ?�택??;
        }
    } else {
        preview.style.display = 'none';
        document.querySelector('.custom-file-label').textContent = '?�진 ?�택...';
    }
}

function removeImage(index) {
    const input = document.getElementById('photos');
    const files = Array.from(input.files);
    files.splice(index, 1);
    
    // ?�로??FileList ?�성 (?�제로는 불�??�하므�?미리보기�??�거)
    previewImages(input);
}

// ???�출 검�?
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    const overallRating = document.getElementById('overall_rating').value;
    const serviceRating = document.getElementById('service_rating').value;
    const kindnessRating = document.getElementById('kindness_rating').value;
    const cleanlinessRating = document.getElementById('cleanliness_rating').value;
    
    if (!overallRating || !serviceRating || !kindnessRating || !cleanlinessRating) {
        e.preventDefault();
        alert('모든 별점???�력??주세??');
        return false;
    }
    
    const content = document.querySelector('textarea[name="content"]').value.trim();
    if (content.length < 10) {
        e.preventDefault();
        alert('?�기 ?�용??최소 10???�상 ?�성??주세??');
        return false;
    }
    
    if (!document.getElementById('agree_terms').checked) {
        e.preventDefault();
        alert('?�용?��????�의??주세??');
        return false;
    }
    
    // ?�출 버튼 비활?�화
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ?�록 �?..';
});
</script>

<?php include '../includes/footer.php'; ?> 
