<?php
$page_title = '업체 이용 가이드 - 뷰티북';
require_once '../includes/header.php';
?>

<style>
.guide-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.guide-header {
    background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
    color: white;
    border-radius: 15px;
    padding: 50px 30px;
    text-align: center;
    margin-bottom: 40px;
}

.guide-header h1 {
    font-size: 36px;
    margin-bottom: 15px;
}

.guide-header p {
    font-size: 18px;
    opacity: 0.9;
}

.guide-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.step-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s;
}

.step-card:hover {
    transform: translateY(-5px);
}

.step-number {
    width: 60px;
    height: 60px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin: 0 auto 20px;
}

.step-title {
    font-size: 20px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
}

.step-description {
    color: #666;
    line-height: 1.6;
}

.features-section {
    background: white;
    border-radius: 15px;
    padding: 40px;
    margin-bottom: 40px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    text-align: center;
    margin-bottom: 30px;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
}

.feature-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.feature-icon {
    width: 50px;
    height: 50px;
    background: #f8f9fa;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.feature-content h4 {
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 16px;
}

.feature-content p {
    color: #666;
    margin: 0;
    font-size: 14px;
}

.cta-section {
    background: white;
    border-radius: 15px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.cta-section h3 {
    font-size: 24px;
    color: #2c3e50;
    margin-bottom: 15px;
}

.cta-section p {
    color: #666;
    margin-bottom: 30px;
    font-size: 16px;
}

.btn {
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin: 0 10px;
    transition: all 0.3s;
}

.btn-primary {
    background: #ff4757;
    color: white;
}

.btn-primary:hover {
    background: #ff3742;
    transform: translateY(-2px);
}

.btn-outline {
    background: white;
    color: #ff4757;
    border: 2px solid #ff4757;
}

.btn-outline:hover {
    background: #ff4757;
    color: white;
}

@media (max-width: 768px) {
    .guide-container {
        padding: 10px;
    }
    
    .guide-header {
        padding: 30px 20px;
    }
    
    .guide-header h1 {
        font-size: 28px;
    }
    
    .guide-steps {
        grid-template-columns: 1fr;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="guide-container">
    <div class="guide-header">
        <h1>업체 등록 및 이용 가이드</h1>
        <p>뷰티북에서 업체를 등록하고 효과적으로 운영하는 방법을 안내합니다</p>
    </div>

    <div class="guide-steps">
        <div class="step-card">
            <div class="step-number">1</div>
            <h3 class="step-title">회원가입</h3>
            <p class="step-description">
                업체관리자 계정으로 회원가입을 진행합니다. 
                정확한 정보를 입력하여 신뢰도를 높이세요.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">2</div>
            <h3 class="step-title">업체 등록</h3>
            <p class="step-description">
                업체의 기본 정보, 위치, 서비스 내용을 등록합니다. 
                자세한 정보일수록 고객 유치에 유리합니다.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">3</div>
            <h3 class="step-title">승인 대기</h3>
            <p class="step-description">
                관리자가 업체 정보를 검토하고 승인 처리합니다. 
                보통 1-2일 정도 소요됩니다.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">4</div>
            <h3 class="step-title">선생님 등록</h3>
            <p class="step-description">
                서비스를 제공할 선생님들을 등록하고 
                각자의 전문 분야와 스케줄을 설정합니다.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">5</div>
            <h3 class="step-title">서비스 관리</h3>
            <p class="step-description">
                제공하는 서비스의 종류, 가격, 소요시간을 
                상세히 등록하여 고객에게 정확한 정보를 제공합니다.
            </p>
        </div>

        <div class="step-card">
            <div class="step-number">6</div>
            <h3 class="step-title">예약 관리</h3>
            <p class="step-description">
                고객의 예약 요청을 확인하고 승인/거절을 
                처리하며, 스케줄을 체계적으로 관리합니다.
            </p>
        </div>
    </div>

    <div class="features-section">
        <h2 class="section-title">뷰티북의 주요 기능</h2>
        
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">📅</div>
                <div class="feature-content">
                    <h4>실시간 예약 관리</h4>
                    <p>고객의 예약 요청을 실시간으로 확인하고 효율적으로 관리할 수 있습니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">👩‍💼</div>
                <div class="feature-content">
                    <h4>선생님 스케줄 관리</h4>
                    <p>각 선생님의 근무 시간과 휴무일을 설정하여 정확한 예약 가능 시간을 제공합니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <div class="feature-content">
                    <h4>요금 및 서비스 관리</h4>
                    <p>다양한 서비스의 요금과 옵션을 세부적으로 설정할 수 있습니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">🎉</div>
                <div class="feature-content">
                    <h4>이벤트 및 프로모션</h4>
                    <p>할인 이벤트와 특별 프로모션을 등록하여 고객 유치를 늘릴 수 있습니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">📊</div>
                <div class="feature-content">
                    <h4>매출 및 통계</h4>
                    <p>업체의 매출 현황과 고객 통계를 한눈에 파악할 수 있습니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">💬</div>
                <div class="feature-content">
                    <h4>고객 소통</h4>
                    <p>고객과의 소통 창구를 제공하여 서비스 품질을 향상시킬 수 있습니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">📱</div>
                <div class="feature-content">
                    <h4>모바일 최적화</h4>
                    <p>스마트폰에서도 쉽게 업체를 관리할 수 있는 반응형 인터페이스를 제공합니다.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">🔔</div>
                <div class="feature-content">
                    <h4>알림 서비스</h4>
                    <p>새로운 예약이나 취소 시 즉시 알림을 받아 빠른 대응이 가능합니다.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="cta-section">
        <h3>지금 바로 시작하세요!</h3>
        <p>뷰티북과 함께 더 많은 고객을 만나고 효율적으로 업체를 운영해보세요</p>
        
        <a href="business_register.php" class="btn btn-primary">업체 등록하기</a>
        <a href="business_list.php" class="btn btn-outline">업체 둘러보기</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 