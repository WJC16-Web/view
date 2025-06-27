<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getBusinessSpecialties($business_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT specialties FROM businesses WHERE id = :business_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':business_id', $business_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['specialties']) {
        return explode(',', $result['specialties']);
    }
    return [];
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserBusinessId() {
    return $_SESSION['business_id'] ?? null;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>