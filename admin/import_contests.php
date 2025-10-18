<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_admin();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv_file'])) {

    // ตรวจสอบ Error ของการอัปโหลด
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['admin_message_error'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์!";
        header('Location: manage_contests.php');
        exit();
    }

    $file_path = $_FILES['csv_file']['tmp_name'];
    $file_mime_type = mime_content_type($file_path);

    // ตรวจสอบว่าเป็นไฟล์ CSV จริงหรือไม่
    if ($file_mime_type !== 'text/plain' && $file_mime_type !== 'text/csv') {
        $_SESSION['admin_message_error'] = "รูปแบบไฟล์ไม่ถูกต้อง! กรุณาอัปโหลดไฟล์ .csv เท่านั้น";
        header('Location: manage_contests.php');
        exit();
    }

    $inserted_count = 0;
    $is_header = true;

    // เปิดไฟล์ CSV เพื่ออ่าน
    if (($handle = fopen($file_path, "r")) !== FALSE) {

        // เตรียมคำสั่ง SQL สำหรับ INSERT
        $sql = "INSERT INTO contests (title, full_name, department, division, phone, email, contest_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        $stmt = $pdo->prepare($sql);

        // วนลูปอ่านข้อมูลทีละแถว
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            
            // ข้ามแถวแรก (Header)
            if ($is_header) {
                $is_header = false;
                continue;
            }

            // ตรวจสอบว่ามีข้อมูลครบหรือไม่ (อย่างน้อยต้องมี title)
            if (empty($data[0])) {
                continue;
            }

            // กำหนดค่าให้ตัวแปรจากคอลัมน์ใน CSV
            $title        = $data[0] ?? '';
            $full_name    = $data[1] ?? '';
            $department   = $data[2] ?? '';
            $division     = $data[3] ?? '';
            $phone        = $data[4] ?? '';
            $email        = $data[5] ?? '';

            // แปลงรูปแบบวันที่ให้ถูกต้อง
            $contest_date = null;
            if (!empty($data[6])) {
                // สร้าง object วันที่จากรูปแบบ 'วัน/เดือน/ปี'
                $date_obj = DateTime::createFromFormat('d/m/Y', $data[6]);
                if ($date_obj) {
                    // จัดรูปแบบใหม่เป็น 'ปี-เดือน-วัน' เพื่อบันทึกลงฐานข้อมูล
                    $contest_date = $date_obj->format('Y-m-d');
                }
            }


            // Execute คำสั่ง SQL
            try {
                if ($stmt->execute([$title, $full_name, $department, $division, $phone, $email, $contest_date])) {
                    $inserted_count++;
                }
            } catch (PDOException $e) {
                // สามารถเพิ่มการจัดการ Error ที่นี่ได้ เช่น เก็บ log
                continue;
            }
        }
        fclose($handle);
    }

    $_SESSION['admin_message'] = "นำเข้าข้อมูลสำเร็จ " . $inserted_count . " รายการ!";
    header('Location: manage_contests.php');
    exit();

} else {
    // กรณีเข้าถึงหน้านี้โดยตรง
    header('Location: manage_contests.php');
    exit();
}
?>