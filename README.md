# 🌟 뷰티북 (BeautyBook) - 뷰티 예약 플랫폼

뷰티 업체와 고객을 연결하는 종합 예약 플랫폼입니다. 네일, 헤어, 왁싱, 피부관리 등 다양한 뷰티 서비스를 쉽고 편리하게 예약할 수 있습니다.

## 📋 프로젝트 개요

### 주요 기능
- **실시간 예약 시스템**: 선생님별 실시간 스케줄 확인 및 즉시 예약
- **업체 상태 관리**: 영업중, 예약중, 휴게시간, 영업종료 등 실시간 상태 표시
- **지역 기반 검색**: 3단계 지역 필터 + GPS 기반 거리 검색
- **다중 업종 필터**: 네일, 헤어, 왁싱, 피부관리 등 여러 업종 동시 선택
- **날짜/시간 필터**: 특정 날짜/시간대 예약 가능 업체만 필터링
- **리뷰 및 평점**: 업체별 상세 리뷰 및 다차원 평점 시스템

### 회원 유형
- **일반고객**: 예약 및 서비스 이용
- **업체관리자**: 업체 정보 관리, 선생님 관리, 예약 승인
- **선생님**: 스케줄 관리, 예약 확정/취소
- **시스템관리자**: 플랫폼 전체 관리

## 🛠 기술 스택

### Backend
- **PHP 7.4+**: 서버사이드 로직
- **MySQL 8.0+**: 데이터베이스
- **PDO**: 데이터베이스 접근 계층

### Frontend
- **HTML5/CSS3**: 마크업 및 스타일링
- **JavaScript ES6+**: 클라이언트 로직
- **jQuery 3.6+**: DOM 조작 및 AJAX

### Development Environment
- **AutoSet**: 로컬 개발 서버 (Apache + MySQL + PHP)
- **Git**: 버전 관리

## 📁 프로젝트 구조

```
view/
├── config/
│   └── database.php              # 데이터베이스 설정
├── includes/
│   ├── header.php               # 공통 헤더
│   ├── footer.php               # 공통 푸터
│   └── functions.php            # 공통 함수들
├── pages/
│   ├── login.php                # 로그인
│   ├── register.php             # 회원가입
│   ├── logout.php               # 로그아웃
│   ├── business_list.php        # 업체 리스트 (핵심 기능)
│   ├── business_detail.php      # 업체 상세
│   ├── customer/                # 고객 페이지들
│   ├── business/                # 업체관리자 페이지들
│   ├── teacher/                 # 선생님 페이지들
│   └── admin/                   # 관리자 페이지들
├── api/
│   ├── get_regions.php          # 지역 정보 API
│   └── ...                      # 기타 API들
├── assets/
│   ├── css/
│   ├── js/
│   │   └── common.js            # 공통 JavaScript
│   └── images/
├── sql/
│   └── database_schema.sql      # 데이터베이스 스키마
├── uploads/                     # 업로드 파일들
├── index.php                    # 메인 페이지
└── README.md
```

## 🚀 설치 및 실행

### 1. 프로젝트 다운로드
```bash
git clone <repository-url>
cd view
```

### 2. 데이터베이스 설정
1. AutoSet에서 MySQL 실행
2. 데이터베이스 생성 및 스키마 적용:
```sql
source sql/database_schema.sql
```

### 3. 설정 파일 수정
`config/database.php`에서 데이터베이스 연결 정보 확인:
```php
private $host = 'localhost';
private $db_name = 'beauty_booking_system';
private $username = 'root';
private $password = '';
```

### 4. 웹서버 실행
AutoSet 실행 후 브라우저에서 접속:
```
http://localhost/view
```

## 📊 데이터베이스 스키마

### 주요 테이블
- **users**: 기본 사용자 정보
- **businesses**: 업체 정보
- **teachers**: 선생님 정보
- **teacher_schedules**: 선생님 정기 스케줄
- **reservations**: 예약 정보
- **reviews**: 리뷰 및 평점
- **regions**: 지역 정보 (시/도/구/동)

### 핵심 기능 구현
- **실시간 상태 관리**: `getBusinessStatus()` 함수로 업체별 현재 상태 계산
- **지역 필터링**: 3단계 계층 구조로 시/도 → 구/군 → 동 선택
- **GPS 거리 계산**: Haversine 공식으로 정확한 거리 측정
- **예약 가능 시간 체크**: 선생님 스케줄과 기존 예약 충돌 검사

## 💻 주요 기능 설명

### 1. 업체 리스트 페이지 (`pages/business_list.php`)
- **실시간 상태 분류**: 영업중/예약중/휴게시간/영업종료
- **다중 필터링**: 지역 + 업종 + 날짜/시간 조합 검색
- **GPS 기반 검색**: 현재 위치에서 반경 3/5/10km 내 업체 검색
- **정렬 옵션**: 추천순/평점순/리뷰순/가격순/거리순/신규순

### 2. 회원가입 시스템 (`pages/register.php`)
- **타입별 회원가입**: 고객/업체관리자 구분
- **실시간 유효성 검사**: 이메일/휴대폰/사업자번호 형식 체크
- **업체 승인 프로세스**: 업체관리자는 관리자 승인 후 서비스 이용

### 3. 공통 함수 라이브러리 (`includes/functions.php`)
- **권한 관리**: 페이지별 접근 권한 체크
- **업체 상태 계산**: 복잡한 비즈니스 로직으로 실시간 상태 판단
- **거리 계산**: Haversine 공식으로 정확한 GPS 거리 측정
- **예약 시간 검증**: 스케줄 충돌 방지

## 🔧 커스터마이징

### 새로운 업종 추가
`pages/business_list.php`의 `$service_categories` 배열에 추가:
```php
$service_categories = [
    'new_category' => ['name' => '새 업종', 'icon' => '🎯'],
    // ...
];
```

### 지역 데이터 추가
`sql/database_schema.sql`의 지역 데이터 삽입 부분 수정

### 예약 시간 슬롯 변경
`includes/functions.php`의 `generateTimeSlots()` 함수 파라미터 수정

## 📱 반응형 지원

모든 페이지는 반응형으로 설계되어 다음 환경을 지원합니다:
- **데스크톱**: 1200px 이상
- **태블릿**: 768px ~ 1199px
- **모바일**: 767px 이하

## 🔒 보안 기능

- **SQL Injection 방지**: PDO Prepared Statement 사용
- **XSS 방지**: `htmlspecialchars()` 함수로 입력값 처리
- **세션 관리**: 안전한 세션 처리 및 자동 만료
- **비밀번호 암호화**: PHP `password_hash()` 함수 사용

## 📈 향후 개발 계획

### Phase 2 기능
- [ ] 결제 시스템 연동
- [ ] 쿠폰/할인 시스템
- [ ] 푸시 알림
- [ ] 소셜 로그인
- [ ] 카카오맵 API 연동

### Phase 3 기능
- [ ] 모바일 앱
- [ ] 고급 통계 대시보드
- [ ] AI 추천 시스템
- [ ] 실시간 채팅

## 🐛 알려진 이슈

1. 헤더 파일에서 카카오맵 API 키 설정 필요
2. 이미지 업로드 폴더 권한 설정 필요
3. 소셜 로그인 API 연동 준비 중

## 📞 지원

프로젝트 관련 문의사항이나 버그 리포트는 GitHub Issues를 이용해주세요.

## 📄 라이센스

이 프로젝트는 MIT 라이센스 하에 배포됩니다.

---

**뷰티북** - 예약의 새로운 경험을 제공하는 종합 뷰티 플랫폼 🌟
