# 뷰티북 프로젝트 인코딩 문제 해결 가이드

## 🔍 문제 진단

전체 프로젝트에서 한글 인코딩 문제가 광범위하게 발생하고 있습니다:

### 발견된 문제들:
1. **PHP 파일 전체에 한글 깨짐 현상**
   - `?몄슂` → `하세요`
   - `?대찓??` → `이메일`
   - `鍮꾨?踰덊샇` → `비밀번호`
   - `?낅젰` → `입력`
   - 기타 수백 개의 깨진 패턴

2. **영향받은 파일들**:
   - `pages/` 디렉토리의 모든 PHP 파일
   - `includes/` 디렉토리의 모든 PHP 파일  
   - `api/` 디렉토리의 모든 PHP 파일
   - 총 50개 이상의 파일에서 문제 발견

## 🛠️ 해결 방법

### 1단계: 자동 복구 스크립트 실행

프로젝트 루트에 생성된 `fix_encoding.php` 스크립트를 실행하세요:

```bash
php fix_encoding.php
```

이 스크립트는:
- 모든 PHP 파일을 스캔
- 깨진 한글 패턴을 올바른 한글로 자동 교체
- 원본 파일을 백업 (`.backup.날짜-시간` 형식)
- UTF-8 인코딩으로 저장

### 2단계: 웹서버 설정

`.htaccess` 파일이 생성되었습니다. 다음 설정이 포함되어 있습니다:

```apache
# UTF-8 인코딩 강제 설정
AddDefaultCharset UTF-8
DefaultLanguage ko-KR

# PHP 설정
php_value default_charset "UTF-8"
php_value mbstring.internal_encoding "UTF-8"
php_value mbstring.http_input "UTF-8"
php_value mbstring.http_output "UTF-8"
```

### 3단계: 데이터베이스 설정 확인

`config/database.php`가 이미 올바르게 설정되어 있습니다:
- charset: utf8mb4
- collation: utf8mb4_unicode_ci

### 4단계: 에디터 설정

앞으로 파일 편집 시:
1. **Visual Studio Code**: 우하단에서 인코딩을 "UTF-8"로 설정
2. **PhpStorm**: File > Settings > Editor > File Encodings에서 UTF-8 설정
3. **Sublime Text**: File > Save with Encoding > UTF-8

## 📋 자주 깨지는 패턴과 해결책

| 깨진 텍스트 | 올바른 텍스트 |
|-------------|---------------|
| `?몄슂` | `하세요` |
| `?대찓??` | `이메일` |
| `鍮꾨?踰덊샇` | `비밀번호` |
| `?낅젰` | `입력` |
| `?댁＜` | `해주` |
| `?꾨떃?덈떎` | `아닙니다` |
| `?깃났` | `성공` |
| `?섏쁺` | `환영` |
| `?⑸땲??` | `합니다` |
| `濡쒓렇` | `로그` |
| `愿由ъ옄` | `관리자` |
| `?좎깮` | `선생` |
| `酉고떚遺?` | `뷰티북` |

## 🔧 수동 복구 방법

자동 스크립트를 사용할 수 없는 경우:

### 1. 개별 파일 수정
각 PHP 파일을 열어서 위 표의 패턴을 찾아 교체하세요.

### 2. 찾기/바꾸기 사용
에디터의 "찾기/바꾸기" 기능을 사용하여:
- `?몄슂` → `하세요`
- `?대찓??` → `이메일`
- 등등...

### 3. 정규식 사용 (고급)
정규식을 지원하는 에디터에서:
```regex
\?[가-힣]*\?
```
패턴을 찾아서 적절한 한글로 교체

## 📊 영향받은 주요 파일 목록

### Pages 디렉토리:
- `login.php` - 로그인 페이지
- `register.php` - 회원가입 페이지
- `customer_mypage.php` - 고객 마이페이지
- `business_list.php` - 업체 목록
- `teacher_mypage.php` - 선생님 페이지
- `review_write.php` - 후기 작성
- 기타 모든 페이지 파일

### API 디렉토리:
- `toggle_favorite.php` - 즐겨찾기 API
- `cancel_reservation.php` - 예약 취소 API
- `auth_social.php` - 소셜 로그인 API
- 기타 모든 API 파일

### Includes 디렉토리:
- `functions.php` - 공통 함수
- `header.php` - 헤더 포함
- `footer.php` - 푸터 포함

## ⚠️ 주의사항

1. **백업 필수**: 스크립트 실행 전 전체 프로젝트 백업
2. **테스트 필요**: 복구 후 모든 페이지가 정상 작동하는지 확인
3. **데이터베이스**: 데이터베이스 내 한글 데이터도 확인 필요
4. **브라우저 캐시**: 복구 후 브라우저 캐시 삭제

## 🎯 복구 후 확인사항

### 1. 페이지별 확인
- [ ] 로그인 페이지 (`pages/login.php`)
- [ ] 회원가입 페이지 (`pages/register.php`)
- [ ] 업체 목록 페이지 (`pages/business_list.php`)
- [ ] 마이페이지들 (`pages/*_mypage.php`)
- [ ] 후기 작성 페이지 (`pages/review_write.php`)

### 2. 기능별 확인
- [ ] 로그인/로그아웃
- [ ] 회원가입
- [ ] 예약 기능
- [ ] 후기 작성
- [ ] 즐겨찾기 추가/제거

### 3. 에러 메시지 확인
- [ ] API 응답 메시지
- [ ] 폼 유효성 검사 메시지
- [ ] 알림 메시지

## 📞 추가 도움

복구 과정에서 문제가 발생하면:

1. **백업 파일 확인**: `.backup.*` 파일들이 생성되었는지 확인
2. **로그 확인**: 웹서버 에러 로그 확인
3. **브라우저 개발자 도구**: 콘솔에서 JavaScript 에러 확인

## 🚀 성능 최적화 추가 사항

`.htaccess` 파일에 추가된 성능 최적화:
- GZIP 압축 활성화
- 브라우저 캐싱 설정
- 보안 헤더 추가

이제 한글이 깨지지 않는 뷰티북 프로젝트를 사용할 수 있습니다! 🎉