<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/functions.php';

startSession();

try {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 이메일 인증 발송
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $user_id = $input['user_id'] ?? '';
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => '올바른 이메일 주소를 입력해주세요.']);
            exit;
        }
        
        // 이메일 중복 확인
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id ?: 0]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            echo json_encode(['success' => false, 'message' => '이미 사용 중인 이메일입니다.']);
            exit;
        }
        
        // 인증 토큰 생성
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // 기존 인증 토큰 삭제
        $stmt = $db->prepare("DELETE FROM email_verifications WHERE email = ?");
        $stmt->execute([$email]);
        
        // 새 인증 토큰 저장
        $stmt = $db->prepare("
            INSERT INTO email_verifications (email, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $token, $expires_at]);
        
        // 인증 이메일 발송
        $verification_url = BASE_URL . "/api/verify_email.php?token=" . $token;
        $email_sent = sendVerificationEmail($email, $verification_url);
        
        if ($email_sent) {
            echo json_encode([
                'success' => true,
                'message' => '인증 이메일이 발송되었습니다. 이메일을 확인해주세요.',
                'email' => $email
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '이메일 발송에 실패했습니다. 잠시 후 다시 시도해주세요.'
            ]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 이메일 인증 처리
        $token = $_GET['token'] ?? '';
        
        if (!$token) {
            showVerificationResult(false, '잘못된 인증 링크입니다.');
            exit;
        }
        
        // 토큰 확인
        $stmt = $db->prepare("
            SELECT * FROM email_verifications 
            WHERE token = ? AND expires_at > NOW() AND is_verified = 0
        ");
        $stmt->execute([$token]);
        $verification = $stmt->fetch();
        
        if (!$verification) {
            showVerificationResult(false, '인증 링크가 만료되었거나 유효하지 않습니다.');
            exit;
        }
        
        // 인증 완료 처리
        $stmt = $db->prepare("
            UPDATE email_verifications 
            SET is_verified = 1, verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$verification['id']]);
        
        // 사용자 이메일 인증 상태 업데이트
        $stmt = $db->prepare("
            UPDATE users 
            SET email_verified_at = NOW(), updated_at = NOW()
            WHERE email = ?
        ");
        $stmt->execute([$verification['email']]);
        
        showVerificationResult(true, '이메일 인증이 완료되었습니다.');
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '허용되지 않는 메소드입니다.']);
    }
    
} catch (Exception $e) {
    error_log("Email verification error: " . $e->getMessage());
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        showVerificationResult(false, '인증 처리 중 오류가 발생했습니다.');
    } else {
        echo json_encode(['success' => false, 'message' => '인증 처리 중 오류가 발생했습니다.']);
    }
}

/**
 * 이메일 인증 결과 페이지 표시
 */
function showVerificationResult($success, $message) {
    $title = $success ? '이메일 인증 완료' : '이메일 인증 실패';
    $icon = $success ? 'check-circle' : 'times-circle';
    $color = $success ? '#28a745' : '#dc3545';
    
    echo "<!DOCTYPE html>
<html lang='ko'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .icon {
            font-size: 64px;
            color: {$color};
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 24px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .btn {
            background: #ff4757;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #ff3838;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='icon'>
            <i class='fas fa-{$icon}'></i>
        </div>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <a href='" . BASE_URL . "' class='btn'>
            메인으로 돌아가기
        </a>
    </div>
</body>
</html>";
}

/**
 * 인증 이메일 발송
 */
function sendVerificationEmail($email, $verification_url) {
    try {
        $subject = "[뷰티예약] 이메일 인증을 완료해주세요";
        
        $html_body = "
<!DOCTYPE html>
<html lang='ko'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>이메일 인증</title>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
    <div style='max-width: 600px; margin: 0 auto; background-color: white;'>
        <!-- 헤더 -->
        <div style='background: linear-gradient(135deg, #ff4757, #ff6b7a); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0; font-size: 28px;'>뷰티예약</h1>
            <p style='color: white; margin: 10px 0 0 0; opacity: 0.9;'>이메일 인증을 완료해주세요</p>
        </div>
        
        <!-- 본문 -->
        <div style='padding: 40px 30px;'>
            <h2 style='color: #333; margin-bottom: 20px;'>안녕하세요!</h2>
            <p style='color: #666; line-height: 1.6; margin-bottom: 25px;'>
                뷰티예약 서비스 가입을 환영합니다.<br>
                아래 버튼을 클릭하여 이메일 인증을 완료해주세요.
            </p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verification_url}' 
                   style='background: #ff4757; color: white; padding: 15px 30px; 
                          text-decoration: none; border-radius: 6px; display: inline-block;
                          font-weight: bold; font-size: 16px;'>
                    이메일 인증하기
                </a>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 30px;'>
                <p style='color: #666; font-size: 14px; margin: 0; line-height: 1.5;'>
                    <strong>주의사항:</strong><br>
                    • 이 링크는 24시간 동안만 유효합니다.<br>
                    • 버튼을 클릭할 수 없는 경우, 아래 링크를 복사하여 브라우저에 붙여넣으세요:<br>
                    <span style='word-break: break-all; color: #007bff;'>{$verification_url}</span>
                </p>
            </div>
        </div>
        
        <!-- 푸터 -->
        <div style='background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee;'>
            <p style='color: #999; font-size: 12px; margin: 0;'>
                본 메일은 발신전용입니다. 문의사항은 고객센터를 이용해주세요.<br>
                © 2024 뷰티예약. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>";
        
        $text_body = "
뷰티예약 이메일 인증

안녕하세요!
뷰티예약 서비스 가입을 환영합니다.

아래 링크를 클릭하여 이메일 인증을 완료해주세요:
{$verification_url}

주의: 이 링크는 24시간 동안만 유효합니다.

문의사항이 있으시면 고객센터로 연락해주세요.
감사합니다.

뷰티예약 팀
";
        
        // 실제 이메일 발송 (여러 방법 중 선택)
        $success = false;
        
        // 방법 1: PHPMailer 사용 (권장)
        // $success = sendMailWithPHPMailer($email, $subject, $html_body, $text_body);
        
        // 방법 2: mail() 함수 사용 (간단하지만 제한적)
        // $success = sendMailWithPHP($email, $subject, $html_body);
        
        // 방법 3: 외부 메일 서비스 API (SendGrid, Mailgun 등)
        // $success = sendMailWithAPI($email, $subject, $html_body);
        
        // 가상 발송 성공 (개발용)
        $success = true;
        
        // 이메일 발송 로그 저장
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO email_logs (
                email, subject, content, email_type, status, 
                sent_at, created_at
            ) VALUES (?, ?, ?, 'VERIFICATION', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $email,
            $subject,
            $html_body,
            $success ? 'SUCCESS' : 'FAILED'
        ]);
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return false;
    }
}

/**
 * PHPMailer를 사용한 이메일 발송
 */
function sendMailWithPHPMailer($to, $subject, $html_body, $text_body) {
    /*
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    
    require 'vendor/autoload.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // 서버 설정
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // 또는 다른 SMTP 서버
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';
        $mail->Password   = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        // 발신자
        $mail->setFrom('noreply@beauty-booking.com', '뷰티예약');
        
        // 수신자
        $mail->addAddress($to);
        
        // 내용
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = $text_body;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
    */
    
    return true; // 가상 성공
}

/**
 * PHP mail() 함수를 사용한 이메일 발송
 */
function sendMailWithPHP($to, $subject, $html_body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: 뷰티예약 <noreply@beauty-booking.com>" . "\r\n";
    
    return mail($to, $subject, $html_body, $headers);
}

/**
 * 외부 API를 사용한 이메일 발송 (SendGrid 예시)
 */
function sendMailWithAPI($to, $subject, $html_body) {
    /*
    // SendGrid API 예시
    $api_key = 'YOUR_SENDGRID_API_KEY';
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to]]
            ]
        ],
        'from' => [
            'email' => 'noreply@beauty-booking.com',
            'name' => '뷰티예약'
        ],
        'subject' => $subject,
        'content' => [
            [
                'type' => 'text/html',
                'value' => $html_body
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 202;
    */
    
    return true; // 가상 성공
}
?> 