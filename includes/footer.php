    </main>
    <!-- 메인 컨텐츠 끝 -->
    
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