<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$host = 'sql303.infinityfree.com';
$database = 'if0_43101523_rvsp_undangan_keamanan_digital';
$username = 'if0_43101523';
$password = 'v0TzCoiJF6vzFOn';

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS rsvp_warga (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        rt_rw VARCHAR(30) NOT NULL,
        status ENUM('Hadir', 'Ragu-Ragu', 'Tidak Hadir') NOT NULL,
        pesan TEXT NULL,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM rsvp_warga')->fetchColumn();
        $entries = $pdo->query(
            'SELECT nama, rt_rw, status, pesan FROM rsvp_warga ORDER BY dibuat_pada DESC, id DESC LIMIT 100'
        )->fetchAll();
        respond(200, ['count' => $count, 'entries' => $entries]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        respond(405, ['error' => 'Metode request tidak didukung.']);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        respond(400, ['error' => 'Data RSVP tidak valid.']);
    }

    $name = trim((string) ($input['nama'] ?? ''));
    $rt = trim((string) ($input['rt_rw'] ?? ''));
    $status = (string) ($input['status'] ?? '');
    $message = trim((string) ($input['pesan'] ?? ''));
    $allowedStatuses = ['Hadir', 'Ragu-Ragu', 'Tidak Hadir'];

    if ($name === '' || mb_strlen($name) > 100 || $rt === '' || mb_strlen($rt) > 30) {
        respond(422, ['error' => 'Nama atau RT/RW kosong atau terlalu panjang.']);
    }
    if (!in_array($status, $allowedStatuses, true)) {
        respond(422, ['error' => 'Pilihan kehadiran tidak valid.']);
    }
    if (mb_strlen($message) > 2000) {
        respond(422, ['error' => 'Pesan maksimal 2000 karakter.']);
    }

    $statement = $pdo->prepare(
        'INSERT INTO rsvp_warga (nama, rt_rw, status, pesan) VALUES (:nama, :rt_rw, :status, :pesan)'
    );
    $statement->execute([
        ':nama' => $name,
        ':rt_rw' => $rt,
        ':status' => $status,
        ':pesan' => $message === '' ? null : $message,
    ]);

    respond(201, ['message' => 'RSVP berhasil disimpan.']);
} catch (Throwable $error) {
    error_log('RSVP API error: ' . $error->getMessage());
    respond(500, ['error' => 'Server gagal memproses RSVP. Periksa konfigurasi database.']);
}