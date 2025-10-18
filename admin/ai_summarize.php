<?php
session_start();
require_once '../includes/functions.php';
require_admin();

header('Content-Type: application/json');

$apiKey = 'AIzaSyAg8s6ySsAQ23ty8_OLb7i7KKO0zMRkAFA'; // <--- วาง Key ของคุณตรงนี้!

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!isset($data['contest_data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: Missing required data.']);
    exit();
}

// สร้าง Prompt สำหรับ Gemini
$prompt = "คุณคือพิธีกรในงานประกาศรางวัลนวัตกรรม
    ข้อมูลของผู้ชนะ 3 อันดับแรกมีดังนี้ (ในรูปแบบ JSON): " . json_encode($data['contest_data']) . "
    
    จงเขียนบทพูดสั้นๆ ที่น่าตื่นเต้นและเป็นทางการสำหรับสไลด์ 3 สไลด์ เพื่อประกาศรางวัลแต่ละอันดับ (อันดับ 3, 2, และ 1) โดยให้พูดถึงชื่อผลงาน, คะแนนเฉลี่ย, และเงินรางวัล
    
    โปรดตอบกลับมาในรูปแบบ JSON เท่านั้น โดยมี key เป็น 'winner_3', 'winner_2', และ 'winner_1' โดยแต่ละ key ให้มีค่าเป็น String ของบทพูด";

// --- ติดต่อ Gemini API ---
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

$postData = [ 'contents' => [ [ 'parts' => [ ['text' => $prompt] ] ] ] ];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode == 200) {
    $result = json_decode($response, true);
    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    // ทำความสะอาดผลลัพธ์ให้อยู่ในรูปแบบ JSON ที่ถูกต้อง
    $cleanJson = preg_replace('/```json\s*|\s*```/', '', $aiText);
    echo $cleanJson;
} else {
    http_response_code($httpcode);
    echo json_encode(['error' => 'Failed to get summary from AI.', 'details' => json_decode($response)]);
}
?>