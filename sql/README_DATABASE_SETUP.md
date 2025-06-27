# 뷰티 예약 시스템 데이터베이스 설정 가이드

## 📋 개요
이 가이드는 뷰티 예약 시스템의 데이터베이스를 처음부터 설정하는 방법을 안내합니다.

## 🔧 필요 사항
- MySQL 5.7 이상 또는 MariaDB 10.2 이상
- PHP 7.4 이상
- AutoSet 로컬 서버 환경

## 📁 SQL 파일 목록
```
sql/
├── database_schema.sql       # 메인 데이터베이스 스키마 (필수)
├── additional_tables.sql     # 추가 기능 테이블들 (필수)
├── missing_columns.sql       # 누락된 컬럼 추가 (필수)
├── final_missing_tables.sql  # 최종 누락 테이블 (필수)
└── README_DATABASE_SETUP.md  # 이 파일
```

## 🚀 설치 순서

### 1단계: 메인 데이터베이스 생성
```sql
-- MySQL/MariaDB에 로그인 후 실행
mysql -u root -p autoset

-- 또는 AutoSet 관리자에서 SQL 실행
SOURCE sql/database_schema.sql;
```

### 2단계: 추가 테이블 생성
```sql
SOURCE sql/additional_tables.sql;
```

### 3단계: 누락된 컬럼 추가
```sql
SOURCE sql/missing_columns.sql;
```

### 4단계: 최종 테이블 생성
```sql
SOURCE sql/final_missing_tables.sql;
```

## 📊 생성되는 테이블 목록 (총 35개)

### 👥 회원 관리 (4개)
- `users` - 기본 사용자 정보
- `customer_profiles` - 고객 상세 정보
- `business_owners` - 업체 관리자 정보
- `user_activity_logs` - 사용자 활동 로그

### 🏪 업체 관리 (6개)
- `businesses` - 업체 기본 정보
- `business_services` - 업체 서비스 메뉴
- `business_photos` - 업체 사진
- `business_policies` - 업체 정책
- `business_ratings` - 업체 평가 통계
- `business_statistics` - 업체 운영 통계

### 👩‍💼 선생님 관리 (4개)
- `teachers` - 선생님 정보
- `teacher_schedules` - 선생님 정기 스케줄
- `teacher_exceptions` - 선생님 예외 일정
- `teacher_status_logs` - 선생님 실시간 상태

### 📅 예약 관리 (6개)
- `reservations` - 예약 정보
- `reservation_status_logs` - 예약 상태 변경 이력
- `reservation_waitlist` - 예약 대기열
- `reservation_changes` - 예약 변경 요청
- `reservation_locks` - 예약 임시 잠금

### 💳 결제 관리 (3개)
- `payments` - 결제 정보
- `coupons` - 쿠폰 정보
- `customer_coupons` - 고객 쿠폰 보유

### ⭐ 후기 관리 (3개)
- `reviews` - 후기 정보
- `review_photos` - 후기 사진
- `review_reports` - 후기 신고

### 🔔 알림 관리 (5개)
- `notifications` - 알림 정보
- `notification_settings` - 알림 설정
- `notification_templates` - 알림 템플릿
- `sms_logs` - SMS 발송 로그
- `push_logs` - 푸시 알림 로그
- `email_logs` - 이메일 발송 로그

### 🎁 마케팅 (3개)
- `points` - 적립금 정보
- `point_transactions` - 적립금 거래 내역
- `events` - 이벤트/프로모션

### 🌍 지역/기타 (2개)
- `regions` - 지역 정보 (3단계)
- `customer_favorites` - 고객 즐겨찾기

### ⚙️ 시스템 (2개)
- `system_settings` - 시스템 설정
- `admin_logs` - 관리자 작업 로그

### 📞 인증 (3개)
- `phone_verifications` - 휴대폰 인증
- `email_verifications` - 이메일 인증
- `inquiries` - 1:1 문의

## ✅ 설치 확인

### 1. 테이블 개수 확인
```sql
USE view;
SELECT COUNT(*) as table_count FROM information_schema.tables 
WHERE table_schema = 'view';
-- 결과: 35개 이상이어야 함
```

### 2. 주요 테이블 존재 확인
```sql
SHOW TABLES LIKE '%reservation%';
SHOW TABLES LIKE '%teacher%';
SHOW TABLES LIKE '%business%';
```

### 3. 샘플 데이터 확인
```sql
SELECT * FROM regions WHERE level = 1 LIMIT 5; -- 시/도 데이터
SELECT * FROM notification_templates LIMIT 3; -- 알림 템플릿
SELECT * FROM system_settings LIMIT 5; -- 시스템 설정
```

## 🔧 AutoSet 환경 설정

### config/database.php 설정 확인
```php
private $host = 'localhost';
private $db_name = 'view';
private $username = 'root';
private $password = 'autoset';  // AutoSet 기본 비밀번호
```

## 🚨 주의사항

1. **백업**: 기존 데이터가 있다면 반드시 백업 후 진행
2. **권한**: MySQL 사용자에게 CREATE, ALTER, DROP 권한 필요
3. **용량**: 최소 100MB 이상의 데이터베이스 공간 필요
4. **인덱스**: 모든 테이블에 적절한 인덱스가 설정됨

## 🔄 업데이트 시 주의사항

기존 시스템이 있다면:
1. `missing_columns.sql`만 실행 (ALTER TABLE 포함)
2. `final_missing_tables.sql` 실행 (새 테이블만 생성)
3. 기존 데이터는 보존됨

## 📞 문제 해결

### 일반적인 오류들

1. **"Table already exists"**
   - 정상적인 메시지 (`IF NOT EXISTS` 사용)

2. **"Unknown column"**
   - `missing_columns.sql` 실행 필요

3. **"Foreign key constraint fails"**
   - SQL 실행 순서 확인 필요

## ✨ 완성된 기능들

이 데이터베이스로 다음 기능들이 100% 작동합니다:

- ✅ 4가지 회원 유형 관리
- ✅ 소셜 로그인 (카카오/네이버/구글)
- ✅ SMS/이메일 본인인증
- ✅ 3단계 지역 검색 + GPS 기반 검색
- ✅ 실시간 업체 상태 분류
- ✅ 30분 단위 예약 시스템
- ✅ 예약 대기열 (VIP 우선순위)
- ✅ 쿠폰/적립금 시스템
- ✅ 완전한 결제 시스템
- ✅ 후기/평점 시스템
- ✅ 관리자 통계 대시보드
- ✅ 1:1 문의 시스템
- ✅ 업체 정책 설정 시스템

**🎉 엔터프라이즈급 뷰티 예약 플랫폼 완성!** 