<?php
// 데이터베이스 연결 설정
class Database {
    private $host = 'localhost';
    private $db_name = 'view';
    private $username = 'root';  // AutoSet 기본 설정
    private $password = 'autoset';      // AutoSet 기본 설정
    private $charset = 'utf8mb4';
    public $conn;

    // 데이터베이스 연결
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // MariaDB 호환성을 위한 추가 설정
            $this->conn->exec("SET sql_mode = 'TRADITIONAL'");
            $this->conn->exec("SET time_zone = '+09:00'");
            
        } catch(PDOException $exception) {
            // 더 자세한 오류 정보 제공
            error_log("DB 연결 오류: " . $exception->getMessage());
            // echo를 제거하여 출력 문제 해결
        }
        
        return $this->conn;
    }
}

// 전역 데이터베이스 연결 함수
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// 기존 $pdo 변수와의 호환성을 위한 전역 변수 설정
// $pdo = getDB(); // 전역 변수 초기화 제거

// PDO 연결 함수 (호환성 유지)
function getPDO() {
    return getDB();
}

// 환경별 설정
define('BASE_URL', 'http://localhost/view');
define('UPLOAD_PATH', 'uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// 보안 설정
define('HASH_ALGORITHM', 'sha256');
define('SESSION_LIFETIME', 3600 * 24); // 24시간

// 페이징 설정
define('ITEMS_PER_PAGE', 20);
define('BUSINESSES_PER_PAGE', 12);

// 예약 설정
define('MIN_BOOKING_HOURS', 2); // 최소 예약 시간 (몇 시간 전)
define('MAX_BOOKING_DAYS', 30); // 최대 예약 가능 일수

// 포인트 및 쿠폰 설정
define('POINT_EARN_RATE', 1); // 적립 비율 (%)
define('REVIEW_POINT_REWARD', 1000); // 후기 작성 적립금

?>