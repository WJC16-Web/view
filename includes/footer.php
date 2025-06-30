    </main>
    <!-- 메인 컨텐츠 끝 -->
    
    <!-- 하단 네비게이션 -->
    <nav class="bottom-nav">
        <?php
        $current_page = basename($_SERVER['PHP_SELF'], '.php');
        $current_user = getCurrentUser();
        ?>
        
        <a href="<?php echo BASE_URL; ?>/" class="nav-item <?php echo ($current_page === 'index') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>홈</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/pages/business_list.php" class="nav-item <?php echo ($current_page === 'business_list') ? 'active' : ''; ?>">
            <i class="fas fa-search"></i>
            <span>찾기</span>
        </a>
        
        <?php if ($current_user): ?>
            <?php if ($current_user['user_type'] === 'customer'): ?>
                <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php?tab=reservations" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'reservation') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>예약</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php?tab=favorites" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'favorite') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <span>즐겨찾기</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php" class="nav-item <?php echo ($current_page === 'customer_mypage') ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>마이</span>
                </a>
            <?php elseif ($current_user['user_type'] === 'business_owner'): ?>
                <a href="<?php echo BASE_URL; ?>/pages/reservation_manage.php" class="nav-item <?php echo ($current_page === 'reservation_manage') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>예약관리</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_edit.php" class="nav-item <?php echo ($current_page === 'business_edit') ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i>
                    <span>업체관리</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_dashboard.php" class="nav-item <?php echo ($current_page === 'business_dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>대시보드</span>
                </a>
            <?php elseif ($current_user['user_type'] === 'teacher'): ?>
                <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php?tab=schedule" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'schedule') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>스케줄</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php?tab=reservations" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'reservation') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>예약현황</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php" class="nav-item <?php echo ($current_page === 'teacher_mypage') ? 'active' : ''; ?>">
                    <i class="fas fa-user-md"></i>
                    <span>마이</span>
                </a>
            <?php elseif ($current_user['user_type'] === 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>/pages/admin_user_manage.php" class="nav-item <?php echo ($current_page === 'admin_user_manage') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>회원관리</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/admin_business_manage.php" class="nav-item <?php echo ($current_page === 'admin_business_manage') ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>업체관리</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/admin_dashboard.php" class="nav-item <?php echo ($current_page === 'admin_dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-cogs"></i>
                    <span>관리자</span>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/pages/register.php" class="nav-item <?php echo ($current_page === 'register') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>회원가입</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/pages/login.php" class="nav-item <?php echo ($current_page === 'login') ? 'active' : ''; ?>">
                <i class="fas fa-sign-in-alt"></i>
                <span>로그인</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- PWA 서비스 워커 등록 -->
    <script>
        // 서비스 워커 등록 (PWA)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo BASE_URL; ?>/sw.js')
                    .then(function(registration) {
                        console.log('SW registered: ', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }

        // 앱 설치 프롬프트
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            deferredPrompt = e;
            // 설치 버튼 표시 로직 추가 가능
        });

        // 모바일 뷰포트 높이 조정 (iOS Safari 대응)
        function setViewportHeight() {
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
        
        setViewportHeight();
        window.addEventListener('resize', setViewportHeight);
        window.addEventListener('orientationchange', setViewportHeight);

        // 터치 피드백
        document.addEventListener('touchstart', function(e) {
            if (e.target.classList.contains('btn') || 
                e.target.classList.contains('nav-item') || 
                e.target.classList.contains('list-item') ||
                e.target.closest('.btn') ||
                e.target.closest('.nav-item') ||
                e.target.closest('.list-item')) {
                e.target.style.opacity = '0.7';
            }
        });

        document.addEventListener('touchend', function(e) {
            if (e.target.classList.contains('btn') || 
                e.target.classList.contains('nav-item') || 
                e.target.classList.contains('list-item') ||
                e.target.closest('.btn') ||
                e.target.closest('.nav-item') ||
                e.target.closest('.list-item')) {
                setTimeout(() => {
                    e.target.style.opacity = '';
                }, 150);
            }
        });

        // 모바일 키보드 대응
        let initialViewportHeight = window.innerHeight;
        window.addEventListener('resize', function() {
            if (window.innerHeight < initialViewportHeight * 0.75) {
                // 키보드가 올라왔을 때
                document.body.classList.add('keyboard-open');
            } else {
                // 키보드가 내려갔을 때
                document.body.classList.remove('keyboard-open');
            }
        });

        // 스크롤 끝 감지 (무한 스크롤 등에 활용)
        let isScrolling = false;
        window.addEventListener('scroll', function() {
            isScrolling = true;
            
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 1000) {
                // 페이지 하단 근처에 도달했을 때의 로직
                // 무한 스크롤 등 구현 가능
            }
        });

        // 스크롤 최적화
        setInterval(function() {
            if (isScrolling) {
                isScrolling = false;
                // 스크롤 관련 처리
            }
        }, 100);

        // 뒤로가기 제스처 (Android)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                // ESC 키 또는 Android 뒤로가기
                if (document.querySelector('.modal-overlay')) {
                    document.querySelector('.modal-overlay').remove();
                } else if (window.history.length > 1) {
                    history.back();
                }
            }
        });
    </script>

    <style>
        /* 키보드 오픈 시 스타일 조정 */
        .keyboard-open .bottom-nav {
            display: none;
        }
        
        .keyboard-open body {
            padding-bottom: 0;
        }

        /* 뷰포트 높이 CSS 변수 사용 */
        .full-height {
            height: calc(var(--vh, 1vh) * 100);
        }

        /* 터치 피드백 개선 */
        .btn:active,
        .nav-item:active,
        .list-item:active {
            transform: scale(0.98);
            transition: transform 0.1s ease;
        }

        /* 스크롤바 숨기기 (모바일 앱 느낌) */
        ::-webkit-scrollbar {
            display: none;
        }
        
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* iOS 스타일 바운스 스크롤 */
        body {
            -webkit-overflow-scrolling: touch;
        }

        /* 텍스트 선택 방지 (앱 느낌) */
        .nav-item,
        .btn,
        .header-icon {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>

    <!-- 푸터 시작 -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>뷰티북</h3>
                    <p>예약의 새로운 경험을 제공하는<br>뷰티 전문 플랫폼입니다.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-kakao"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>고객센터</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/pages/faq.php">자주묻는질문</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/inquiry.php">1:1 문의</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/notice.php">공지사항</a></li>
                        <li><a href="tel:1588-0000">1588-0000</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>업체 서비스</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/pages/business_register.php">업체 등록</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/business_guide.php">이용 가이드</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/pricing.php">요금 안내</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/marketing.php">마케팅 지원</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>약관 및 정책</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/pages/terms.php">이용약관</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/privacy.php">개인정보처리방침</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/location.php">위치정보이용약관</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/refund.php">취소/환불 정책</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="company-info">
                    <p><strong>주식회사 뷰티북</strong></p>
                    <p>
                        대표: 홍길동 | 사업자등록번호: 123-45-67890 | 통신판매신고: 2024-서울강남-1234
                    </p>
                    <p>
                        주소: 서울특별시 강남구 테헤란로 123 뷰티빌딩 10층 | 
                        이메일: contact@beautybook.com
                    </p>
                </div>
                <div class="copyright">
                    <p>&copy; 2024 BeautyBook. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <style>
        /* 푸터 스타일 */
        .footer {
            background: #2c3e50;
            color: white;
            margin-top: 80px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            padding: 60px 0 40px;
        }
        
        .footer-section h3 {
            color: #ff4757;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .footer-section h4 {
            color: #ecf0f1;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .footer-section p {
            color: #bdc3c7;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section ul li a:hover {
            color: #ff4757;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #34495e;
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: #ff4757;
            transform: translateY(-2px);
        }
        
        .footer-bottom {
            border-top: 1px solid #34495e;
            padding: 30px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .company-info p {
            color: #95a5a6;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .copyright p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* 반응형 */
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 30px;
                padding: 40px 0 30px;
            }
            
            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }
        
        /* 메인 컨텐츠 최소 높이 설정 */
        .main-content {
            min-height: calc(100vh - 140px);
            padding: 30px 0;
        }
        
        /* 스크롤 투 탑 버튼 */
        .scroll-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .scroll-to-top:hover {
            background: #ff3742;
            transform: translateY(-2px);
        }
        
        .scroll-to-top.show {
            display: flex;
        }
    </style>
    
    <!-- 스크롤 투 탑 버튼 -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript -->
    <script src="<?php echo BASE_URL; ?>/assets/js/common.js"></script>
    
    <script>
        $(document).ready(function() {
            // 스크롤 투 탑 버튼
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    $('#scrollToTop').addClass('show');
                } else {
                    $('#scrollToTop').removeClass('show');
                }
            });
            
            $('#scrollToTop').click(function() {
                $('html, body').animate({scrollTop: 0}, 600);
                return false;
            });
            
            // 알림 자동 숨김
            $('.alert').delay(5000).fadeOut();
            
            // 모바일 메뉴 토글 (필요시 추가)
            $('.mobile-menu-btn').click(function() {
                $('.nav-content').toggleClass('show');
            });
            
            // 검색 자동완성 (기본 구현)
            $('.search-input').on('input', function() {
                var query = $(this).val();
                if (query.length > 2) {
                    // AJAX 자동완성 구현 가능
                    // autocompleteSearch(query);
                }
            });
        });
        
        // 위치 기반 검색
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    
                    // 현재 위치를 세션에 저장하거나 검색에 활용
                    $.post('<?php echo BASE_URL; ?>/api/set_location.php', {
                        latitude: lat,
                        longitude: lng
                    });
                    
                    alert('현재 위치가 설정되었습니다.');
                });
            } else {
                alert('위치 서비스를 지원하지 않는 브라우저입니다.');
            }
        }
        
        // 알림 읽음 처리
        function markNotificationAsRead(notificationId) {
            $.post('<?php echo BASE_URL; ?>/api/mark_notification_read.php', {
                notification_id: notificationId
            });
        }
    </script>
</body>
</html>

<?php
// 출력 버퍼 종료 및 출력
ob_end_flush();
?> 