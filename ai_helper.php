<?php
session_start();
require_once 'includes/functions.php';
require_login();

header('Content-Type: application/json');

$apiKey = 'YOUR_GEMINI_API_KEY'; // <--- ใส่ Key ของคุณเหมือนเดิม

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!isset($data['criteria_title'], $data['criteria_desc'], $data['contest_title'], $data['contest_desc'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: Missing required data.']);
    exit();
}

$prompt = "คุณคือผู้ช่วยให้คะแนนในการประกวดนวัตกรรม
    
    เกณฑ์การให้คะแนนคือ: '{$data['criteria_title']}' ({$data['criteria_desc']})
    
    ข้อมูลของผลงานที่กำลังพิจารณาคือ: '{$data['contest_title']}'
    คำอธิบายผลงาน: '{$data['contest_desc']}'
    
    จากการวิเคราะห์ข้อมูลผลงานเทียบกับเกณฑ์ โปรดแนะนำคะแนนระหว่าง 1-10 พร้อมให้เหตุผลประกอบสั้นๆ ไม่เกิน 2 บรรทัด โดยให้คำตอบเป็นภาษาไทย";

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

// ****** นี่คือส่วนที่แก้ไข ******
// ตรวจสอบว่ามี cURL error หรือไม่
if (curl_errno($ch)) {
    $curl_error_message = curl_error($ch);
    curl_close($ch);
    http_response_code(500); // Internal Server Error
    // ส่งข้อความ cURL error กลับไปให้หน้าเว็บ
    echo json_encode(['error' => 'cURL Error: ' . $curl_error_message]);
    exit();
}
// ****** สิ้นสุดส่วนที่แก้ไข ******

curl_close($ch);

if ($httpcode == 200) {
    $result = json_decode($response, true);
    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'ขออภัย, ไม่สามารถสร้างคำแนะนำได้ในขณะนี้';
    echo json_encode(['suggestion' => nl2br(htmlspecialchars($aiText))]);
} else {
    http_response_code($httpcode);
    echo json_encode(['error' => 'Failed to get suggestion from AI.', 'details' => json_decode($response)]);
}
?>