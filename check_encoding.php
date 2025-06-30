<?php
/**
 * 뷰티북 프로젝트 인코딩 검사 스크립트
 * 
 * 이 스크립트는 프로젝트의 파일들과 설정을 검사하여
 * 인코딩 관련 문제를 진단합니다.
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>인코딩 검사 - 뷰티북</title>
    <style>
        body {
            font-family: 'Malgun Gothic', sans-serif;
            margin: 40px;
            background: #f8f9fa;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .section h3 {
            color: #007bff;
            margin-bottom: 15px;
        }
        .test-item {
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.ok {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .korean-test {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .fix-button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .fix-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 뷰티북 프로젝트 인코딩 검사</h1>
            <p>프로젝트의 인코딩 설정과 한글 표시 상태를 검사합니다</p>
        </div>

        <!-- 한글 표시 테스트 -->
        <div class="section">
            <h3>📝 한글 표시 테스트</h3>
            <div class="korean-test">
                안녕하세요! 뷰티북 프로젝트입니다. 🏥💅💇‍♀️
            </div>
            <div class="korean-test">
                예약, 관리, 서비스, 고객, 업체, 선생님, 후기, 적립금
            </div>
            <p>위 텍스트가 정상적으로 표시되면 브라우저 인코딩이 올바릅니다.</p>
        </div>

        <!-- PHP 설정 검사 -->
        <div class="section">
            <h3>⚙️ PHP 인코딩 설정</h3>
            <?php
            $php_tests = [
                'default_charset' => ini_get('default_charset'),
                'internal_encoding' => ini_get('mbstring.internal_encoding'),
                'input_encoding' => ini_get('mbstring.http_input'),
                'output_encoding' => ini_get('mbstring.http_output'),
                'encoding_translation' => ini_get('mbstring.encoding_translation'),
                'detect_order' => ini_get('mbstring.detect_order')
            ];

            foreach ($php_tests as $setting => $value) {
                $status = 'ok';
                $status_text = 'OK';
                
                if (empty($value)) {
                    $status = 'warning';
                    $status_text = '설정 없음';
                } elseif ($setting === 'default_charset' && strtolower($value) !== 'utf-8') {
                    $status = 'error';
                    $status_text = 'UTF-8이 아님';
                } elseif (in_array($setting, ['internal_encoding', 'input_encoding', 'output_encoding']) && strtolower($value) !== 'utf-8') {
                    $status = 'warning';
                    $status_text = 'UTF-8 권장';
                }
                
                echo "<div class='test-item'>";
                echo "<span><strong>$setting:</strong> " . ($value ?: '(설정되지 않음)') . "</span>";
                echo "<span class='status $status'>$status_text</span>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- 데이터베이스 연결 및 인코딩 검사 -->
        <div class="section">
            <h3>🗄️ 데이터베이스 인코딩</h3>
            <?php
            try {
                require_once 'config/database.php';
                $db = getDB();
                
                if ($db) {
                    echo "<div class='test-item'>";
                    echo "<span>데이터베이스 연결</span>";
                    echo "<span class='status ok'>성공</span>";
                    echo "</div>";
                    
                    // 현재 charset 확인
                    $stmt = $db->query("SHOW VARIABLES LIKE 'character_set_%'");
                    $charsets = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    $important_charsets = [
                        'character_set_client' => '클라이언트 charset',
                        'character_set_connection' => '연결 charset', 
                        'character_set_database' => '데이터베이스 charset',
                        'character_set_results' => '결과 charset',
                        'character_set_server' => '서버 charset'
                    ];
                    
                    foreach ($important_charsets as $var => $name) {
                        $value = $charsets[$var] ?? '';
                        $status = (strpos(strtolower($value), 'utf8') !== false) ? 'ok' : 'warning';
                        $status_text = (strpos(strtolower($value), 'utf8') !== false) ? 'UTF-8' : '확인 필요';
                        
                        echo "<div class='test-item'>";
                        echo "<span><strong>$name:</strong> $value</span>";
                        echo "<span class='status $status'>$status_text</span>";
                        echo "</div>";
                    }
                    
                } else {
                    echo "<div class='test-item'>";
                    echo "<span>데이터베이스 연결</span>";
                    echo "<span class='status error'>실패</span>";
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='test-item'>";
                echo "<span>데이터베이스 연결 오류: " . htmlspecialchars($e->getMessage()) . "</span>";
                echo "<span class='status error'>오류</span>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- 파일 인코딩 검사 -->
        <div class="section">
            <h3>📁 파일 인코딩 검사</h3>
            <?php
            $directories = ['pages', 'includes', 'api', 'config'];
            $corrupted_patterns = ['?몄슂', '?대찓??', '鍮꾨?踰덊샇', '?낅젰', '?댁＜', '?꾨떃?덈떎'];
            
            $total_files = 0;
            $corrupted_files = 0;
            $file_issues = [];
            
            foreach ($directories as $dir) {
                if (is_dir($dir)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
                    );
                    
                    foreach ($iterator as $file) {
                        if ($file->getExtension() === 'php') {
                            $total_files++;
                            $content = file_get_contents($file->getPathname());
                            
                            foreach ($corrupted_patterns as $pattern) {
                                if (strpos($content, $pattern) !== false) {
                                    $corrupted_files++;
                                    $file_issues[] = $file->getPathname();
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            
            echo "<div class='test-item'>";
            echo "<span>총 PHP 파일 수: $total_files개</span>";
            echo "<span class='status ok'>검사 완료</span>";
            echo "</div>";
            
            echo "<div class='test-item'>";
            echo "<span>인코딩 문제 파일: $corrupted_files개</span>";
            if ($corrupted_files > 0) {
                echo "<span class='status error'>문제 발견</span>";
            } else {
                echo "<span class='status ok'>문제 없음</span>";
            }
            echo "</div>";
            
            if (!empty($file_issues)) {
                echo "<div class='code-block'>";
                echo "<strong>문제가 발견된 파일들:</strong><br>";
                foreach (array_slice($file_issues, 0, 10) as $file) {
                    echo "• " . htmlspecialchars($file) . "<br>";
                }
                if (count($file_issues) > 10) {
                    echo "... 외 " . (count($file_issues) - 10) . "개 파일<br>";
                }
                echo "</div>";
            }
            ?>
        </div>

        <!-- 웹서버 헤더 검사 -->
        <div class="section">
            <h3>🌐 웹서버 헤더</h3>
            <?php
            $headers = getallheaders();
            $content_type = '';
            
            // 현재 페이지의 Content-Type 확인
            foreach (headers_list() as $header) {
                if (stripos($header, 'content-type') === 0) {
                    $content_type = $header;
                    break;
                }
            }
            
            echo "<div class='test-item'>";
            echo "<span>Content-Type 헤더: " . ($content_type ?: '설정되지 않음') . "</span>";
            $status = (stripos($content_type, 'utf-8') !== false) ? 'ok' : 'warning';
            $status_text = (stripos($content_type, 'utf-8') !== false) ? 'UTF-8' : '확인 필요';
            echo "<span class='status $status'>$status_text</span>";
            echo "</div>";
            
            // Accept-Charset 헤더 확인
            $accept_charset = $_SERVER['HTTP_ACCEPT_CHARSET'] ?? '';
            echo "<div class='test-item'>";
            echo "<span>브라우저 Accept-Charset: " . ($accept_charset ?: '설정되지 않음') . "</span>";
            echo "<span class='status ok'>정보</span>";
            echo "</div>";
            ?>
        </div>

        <!-- 해결 방법 -->
        <div class="section">
            <h3>🔧 해결 방법</h3>
            
            <?php if ($corrupted_files > 0): ?>
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <strong>⚠️ 인코딩 문제가 발견되었습니다!</strong><br>
                자동 복구 스크립트를 실행하여 문제를 해결할 수 있습니다.
            </div>
            
            <a href="fix_encoding.php" class="fix-button" target="_blank">
                🔨 자동 인코딩 복구 실행
            </a>
            <?php endif; ?>
            
            <a href="javascript:location.reload()" class="fix-button">
                🔄 다시 검사
            </a>
            
            <div class="code-block">
                <strong>수동 해결 방법:</strong><br>
                1. 에디터에서 파일을 UTF-8 인코딩으로 저장<br>
                2. 웹서버 설정에서 charset을 UTF-8로 설정<br>
                3. 데이터베이스 charset을 utf8mb4로 설정<br>
                4. PHP ini 설정에서 default_charset을 UTF-8로 설정
            </div>
        </div>

        <!-- 권장 설정 -->
        <div class="section">
            <h3>📋 권장 설정</h3>
            
            <h4>PHP (php.ini)</h4>
            <div class="code-block">
default_charset = "UTF-8"<br>
mbstring.internal_encoding = "UTF-8"<br>
mbstring.http_input = "UTF-8"<br>
mbstring.http_output = "UTF-8"<br>
mbstring.encoding_translation = On<br>
mbstring.detect_order = "UTF-8,EUC-KR,CP949"
            </div>
            
            <h4>MySQL/MariaDB</h4>
            <div class="code-block">
CREATE DATABASE view DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;<br>
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
            </div>
            
            <h4>Apache (.htaccess)</h4>
            <div class="code-block">
AddDefaultCharset UTF-8<br>
DefaultLanguage ko-KR
            </div>
        </div>
    </div>

    <script>
        // 페이지 로드 시 인코딩 테스트
        document.addEventListener('DOMContentLoaded', function() {
            const testString = '한글 테스트: 안녕하세요!';
            const encodedString = encodeURIComponent(testString);
            
            console.log('인코딩 테스트:');
            console.log('원본:', testString);
            console.log('인코딩:', encodedString);
            console.log('디코딩:', decodeURIComponent(encodedString));
        });
    </script>
</body>
</html>