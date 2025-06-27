// 뷰티북 공통 JavaScript 함수들

$(document).ready(function() {
    // 전역 설정
    $.ajaxSetup({
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            if (xhr.status === 401) {
                alert('로그인이 필요합니다.');
                window.location.href = '/view/pages/login.php';
            } else if (xhr.status === 403) {
                alert('권한이 없습니다.');
            } else {
                alert('오류가 발생했습니다. 다시 시도해주세요.');
            }
        }
    });
    
    // 모든 AJAX 요청에 CSRF 토큰 추가 (추후 구현)
    // $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
    //     if (options.type.toLowerCase() === 'post') {
    //         options.data = options.data || {};
    //         options.data.csrf_token = $('meta[name="csrf-token"]').attr('content');
    //     }
    // });
});

// 유틸리티 함수들
const BeautyBook = {
    // 이메일 유효성 검사
    validateEmail: function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    // 휴대폰 번호 유효성 검사
    validatePhone: function(phone) {
        const phoneNumber = phone.replace(/[^0-9]/g, '');
        return /^01[0-9]{8,9}$/.test(phoneNumber);
    },
    
    // 휴대폰 번호 포맷팅
    formatPhone: function(phone) {
        const numbers = phone.replace(/[^0-9]/g, '');
        if (numbers.length >= 3 && numbers.length <= 7) {
            return numbers.replace(/(\d{3})(\d+)/, '$1-$2');
        } else if (numbers.length >= 8) {
            return numbers.replace(/(\d{3})(\d{4})(\d+)/, '$1-$2-$3');
        }
        return numbers;
    },
    
    // 사업자등록번호 포맷팅
    formatBusinessNumber: function(number) {
        const numbers = number.replace(/[^0-9]/g, '');
        if (numbers.length >= 3 && numbers.length <= 5) {
            return numbers.replace(/(\d{3})(\d+)/, '$1-$2');
        } else if (numbers.length >= 6) {
            return numbers.replace(/(\d{3})(\d{2})(\d+)/, '$1-$2-$3');
        }
        return numbers;
    },
    
    // 금액 포맷팅
    formatPrice: function(price) {
        return new Intl.NumberFormat('ko-KR').format(price) + '원';
    },
    
    // 날짜 포맷팅
    formatDate: function(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        switch (format) {
            case 'YYYY-MM-DD':
                return `${year}-${month}-${day}`;
            case 'YYYY.MM.DD':
                return `${year}.${month}.${day}`;
            case 'MM/DD':
                return `${month}/${day}`;
            default:
                return `${year}-${month}-${day}`;
        }
    },
    
    // 시간 포맷팅
    formatTime: function(time) {
        return time.substring(0, 5); // HH:MM 형태로 변환
    },
    
    // 로딩 표시
    showLoading: function(element) {
        if (typeof element === 'string') {
            element = $(element);
        }
        element.html('<i class="fas fa-spinner fa-spin"></i> 로딩 중...');
        element.prop('disabled', true);
    },
    
    // 로딩 숨김
    hideLoading: function(element, originalText) {
        if (typeof element === 'string') {
            element = $(element);
        }
        element.html(originalText || '완료');
        element.prop('disabled', false);
    },
    
    // 모달 창 표시
    showModal: function(title, content, options = {}) {
        const modal = $(`
            <div class="modal-overlay" id="beautyBookModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    ${options.showFooter !== false ? `
                        <div class="modal-footer">
                            <button class="btn btn-outline modal-cancel">취소</button>
                            <button class="btn btn-primary modal-confirm">확인</button>
                        </div>
                    ` : ''}
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.fadeIn(200);
        
        // 이벤트 바인딩
        modal.find('.modal-close, .modal-cancel').click(function() {
            BeautyBook.hideModal();
            if (options.onCancel) options.onCancel();
        });
        
        modal.find('.modal-confirm').click(function() {
            BeautyBook.hideModal();
            if (options.onConfirm) options.onConfirm();
        });
        
        modal.click(function(e) {
            if (e.target === this) {
                BeautyBook.hideModal();
                if (options.onCancel) options.onCancel();
            }
        });
        
        return modal;
    },
    
    // 모달 창 숨김
    hideModal: function() {
        $('#beautyBookModal').fadeOut(200, function() {
            $(this).remove();
        });
    },
    
    // 알림 메시지
    showAlert: function(message, type = 'info', duration = 3000) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-error',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const alert = $(`
            <div class="floating-alert ${alertClass}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button class="alert-close">&times;</button>
            </div>
        `);
        
        $('body').append(alert);
        alert.addClass('show');
        
        alert.find('.alert-close').click(function() {
            alert.removeClass('show');
            setTimeout(() => alert.remove(), 300);
        });
        
        if (duration > 0) {
            setTimeout(() => {
                alert.removeClass('show');
                setTimeout(() => alert.remove(), 300);
            }, duration);
        }
        
        return alert;
    },
    
    // 확인 다이얼로그
    confirm: function(message, onConfirm, onCancel) {
        return this.showModal('확인', `<p>${message}</p>`, {
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    },
    
    // AJAX 요청 래퍼
    request: function(url, options = {}) {
        const defaults = {
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                if (options.loadingElement) {
                    BeautyBook.showLoading(options.loadingElement);
                }
            },
            complete: function() {
                if (options.loadingElement) {
                    BeautyBook.hideLoading(options.loadingElement);
                }
            },
            success: function(data) {
                if (data.success) {
                    if (options.successMessage) {
                        BeautyBook.showAlert(options.successMessage, 'success');
                    }
                    if (options.onSuccess) {
                        options.onSuccess(data);
                    }
                } else {
                    BeautyBook.showAlert(data.message || '오류가 발생했습니다.', 'error');
                    if (options.onError) {
                        options.onError(data);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (options.errorMessage) {
                    BeautyBook.showAlert(options.errorMessage, 'error');
                }
                if (options.onError) {
                    options.onError({success: false, message: error});
                }
            }
        };
        
        return $.ajax(url, Object.assign(defaults, options));
    },
    
    // 위치 정보 가져오기
    getCurrentLocation: function() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('위치 서비스를 지원하지 않는 브라우저입니다.'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    });
                },
                (error) => {
                    let message = '위치 정보를 가져올 수 없습니다.';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = '위치 정보 접근이 거부되었습니다.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = '위치 정보를 사용할 수 없습니다.';
                            break;
                        case error.TIMEOUT:
                            message = '위치 정보 요청이 시간 초과되었습니다.';
                            break;
                    }
                    reject(new Error(message));
                }
            );
        });
    },
    
    // 거리 계산 (Haversine 공식)
    calculateDistance: function(lat1, lon1, lat2, lon2) {
        const R = 6371; // 지구 반지름 (km)
        const dLat = this.deg2rad(lat2 - lat1);
        const dLon = this.deg2rad(lon2 - lon1);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    },
    
    deg2rad: function(deg) {
        return deg * (Math.PI/180);
    },
    
    // 쿠키 관리
    setCookie: function(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
    },
    
    getCookie: function(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    },
    
    deleteCookie: function(name) {
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/`;
    },
    
    // 즐겨찾기 토글
    toggleFavorite: function(businessId, element) {
        this.request('/view/api/toggle_favorite.php', {
            type: 'POST',
            data: { business_id: businessId },
            loadingElement: element,
            onSuccess: function(data) {
                const icon = $(element).find('i');
                if (data.is_favorite) {
                    icon.removeClass('far').addClass('fas');
                    BeautyBook.showAlert('즐겨찾기에 추가되었습니다.', 'success');
                } else {
                    icon.removeClass('fas').addClass('far');
                    BeautyBook.showAlert('즐겨찾기에서 제거되었습니다.', 'info');
                }
            }
        });
    }
};

// 전역 함수로 등록
window.BeautyBook = BeautyBook;

// 페이지 로드 완료 후 실행
$(document).ready(function() {
    // 전화번호 자동 포맷팅
    $(document).on('input', 'input[type="tel"], input[name*="phone"]', function() {
        $(this).val(BeautyBook.formatPhone($(this).val()));
    });
    
    // 사업자등록번호 자동 포맷팅
    $(document).on('input', 'input[name*="business_license"]', function() {
        $(this).val(BeautyBook.formatBusinessNumber($(this).val()));
    });
    
    // 이미지 로드 실패 시 기본 이미지로 대체
    $(document).on('error', 'img', function() {
        if (!$(this).data('fallback-applied')) {
            $(this).data('fallback-applied', true);
            $(this).attr('src', '/view/assets/images/no-image.jpg');
        }
    });
    
    // 외부 링크는 새 창에서 열기
    $(document).on('click', 'a[href^="http"]:not([href*="' + window.location.hostname + '"])', function(e) {
        e.preventDefault();
        window.open($(this).attr('href'), '_blank');
    });
    
    // 폼 제출 시 중복 방지
    $(document).on('submit', 'form', function() {
        const submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
        submitBtn.prop('disabled', true);
        
        setTimeout(() => {
            submitBtn.prop('disabled', false);
        }, 2000);
    });
});

// CSS for modals and alerts (will be moved to CSS file later)
const styles = `
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-content {
    background: white;
    border-radius: 10px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2c3e50;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.floating-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    z-index: 9998;
    min-width: 300px;
}

.floating-alert.show {
    transform: translateX(0);
}

.floating-alert.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.floating-alert.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.floating-alert.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.floating-alert.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    margin-left: auto;
    opacity: 0.6;
}

.alert-close:hover {
    opacity: 1;
}
</style>
`;

$('head').append(styles); 