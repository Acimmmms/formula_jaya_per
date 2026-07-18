<?php
// Load .env file if present to populate getenv() values for local setups.
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === '') {
            continue;
        }
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

function whatsappEnabled(): bool {
    $enabled = getenv('WHATSAPP_ENABLED');
    if ($enabled === false || $enabled === '') {
        return false;
    }

    return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
}

function whatsappApiUrl(): string {
    $url = getenv('WHATSAPP_API_URL');
    $url = is_string($url) ? trim($url) : '';

    return $url !== '' ? $url : 'https://api.fonnte.com/send';
}

function whatsappApiKey(): string {
    $key = getenv('WHATSAPP_API_KEY');
    return is_string($key) ? trim($key) : '';
}

function whatsappCountryCode(): string {
    $code = getenv('WHATSAPP_COUNTRY_CODE');
    $code = is_string($code) ? preg_replace('/\D+/', '', $code) : '';

    return $code !== '' ? $code : '62';
}

function whatsappNormalizeNumber(?string $phone): string {
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }

    $countryCode = whatsappCountryCode();
    if (str_starts_with($digits, '0')) {
        return $countryCode . substr($digits, 1);
    }

    if (str_starts_with($digits, $countryCode)) {
        return $digits;
    }

    if (str_starts_with($digits, '8')) {
        return $countryCode . $digits;
    }

    return $digits;
}

function whatsappFormatRupiah($value): string {
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function whatsappSendMessage(?string $phone, string $message): bool {
    if (!whatsappEnabled() || whatsappApiKey() === '') {
        return false;
    }

    $target = whatsappNormalizeNumber($phone);
    $message = trim($message);

    if ($target === '' || $message === '') {
        return false;
    }

    $payload = http_build_query([
        'target' => $target,
        'message' => $message,
        'countryCode' => whatsappCountryCode(),
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init(whatsappApiUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . whatsappApiKey(),
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error !== '') {
            error_log('WhatsApp notification failed: ' . $error);
            return false;
        }

        return $status >= 200 && $status < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: " . whatsappApiKey() . "\r\n" .
                "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 15,
        ],
    ]);

    $response = @file_get_contents(whatsappApiUrl(), false, $context);
    if ($response === false) {
        return false;
    }

    $statusLine = $http_response_header[0] ?? '';
    return (bool) preg_match('/\s2\d\d\s/', $statusLine);
}

function whatsappComposeServisMessage(array $servis, string $event = 'created'): string {
    // Attempt to render template first
    $vars = [
        'no_servis' => (string) ($servis['no_servis'] ?? '-'),
        'nama' => trim((string) ($servis['nama_pelanggan'] ?? $servis['nama'] ?? 'Pelanggan')),
        'kendaraan' => trim((string) ($servis['no_polisi'] ?? '') . ' - ' . trim((string) ($servis['merk'] ?? '') . ' ' . (string) ($servis['model'] ?? ''))),
        'tanggal_masuk' => !empty($servis['tanggal_masuk']) ? date('d/m/Y', strtotime((string) $servis['tanggal_masuk'])) : '-',
        'kilometer' => trim((string) ($servis['odometer'] ?? $servis['kilometer'] ?? '')),
        'keluhan' => trim((string) ($servis['keluhan'] ?? '')),
        'diagnosis' => trim((string) ($servis['diagnosis'] ?? '')),
        'status' => (string) ($servis['status'] ?? 'masuk'),
    ];

    $templateName = 'servis_' . $event;
    $rendered = renderWhatsappTemplate($templateName, $vars);
    if ($rendered !== null) {
        return $rendered;
    }
    $statusLabel = [
        'masuk' => 'Masuk',
        'proses' => 'Proses',
        'selesai' => 'Selesai',
        'diambil' => 'Diambil',
    ];

    $nama = trim((string) ($servis['nama_pelanggan'] ?? $servis['nama'] ?? 'Pelanggan'));
    $kendaraan = trim((string) ($servis['no_polisi'] ?? '') . ' - ' . trim((string) ($servis['merk'] ?? '') . ' ' . (string) ($servis['model'] ?? '')));
    $status = (string) ($servis['status'] ?? 'masuk');
    $statusText = $statusLabel[$status] ?? ucfirst($status);
    $diagnosis = trim((string) ($servis['diagnosis'] ?? ''));
    $keluhan = trim((string) ($servis['keluhan'] ?? ''));
    $kilometer = trim((string) ($servis['odometer'] ?? $servis['kilometer'] ?? ''));
    $tanggalMasuk = !empty($servis['tanggal_masuk']) ? date('d/m/Y', strtotime((string) $servis['tanggal_masuk'])) : '-';

    $lines = [];
    if ($event === 'created') {
        $lines[] = "Halo $nama, order servis Anda telah diterima.";
    } elseif ($event === 'processing') {
        $lines[] = "Halo $nama, kendaraan Anda sedang kami proses servis.";
    } elseif ($event === 'completed') {
        $lines[] = "Halo $nama, mobil Anda sudah selesai diservis dan siap diambil.";
    } else {
        $lines[] = "Halo $nama, ada pembaruan status servis untuk kendaraan Anda.";
    }

    $lines[] = '';
    $lines[] = 'No. Servis: ' . (string) ($servis['no_servis'] ?? '-');
    $lines[] = 'Kendaraan: ' . $kendaraan;
    $lines[] = 'Tanggal Masuk: ' . $tanggalMasuk;
    if ($kilometer !== '') {
        $lines[] = 'Kilometer: ' . number_format((int) preg_replace('/\D+/', '', $kilometer), 0, ',', '.') . ' km';
    }
    $lines[] = 'Keluhan: ' . ($keluhan !== '' ? $keluhan : '-');
    if ($diagnosis !== '') {
        $lines[] = 'Diagnosis: ' . $diagnosis;
    }
    $lines[] = 'Status Saat Ini: ' . $statusText;

    if ($event === 'processing' || $status === 'proses') {
        $lines[] = 'Kami sedang mengerjakan servis kendaraan Anda. Jika ada kendala atau tambahan estimasi, kami akan informasikan segera.';
    } elseif ($event === 'completed' || $status === 'selesai') {
        $lines[] = 'Silakan datang ke bengkel untuk mengambil kendaraan Anda.';
    } elseif ($status === 'diambil') {
        $lines[] = 'Kendaraan sudah diambil. Terima kasih atas kepercayaannya.';
    } else {
        $lines[] = 'Kami akan mengirim pembaruan berikutnya melalui WhatsApp.';
    }

    return implode("\n", $lines);
}

function whatsappComposeMonthlyServisReminderMessage(array $servis): string {
    $nama = trim((string) ($servis['nama_pelanggan'] ?? $servis['nama'] ?? 'Pelanggan'));
    $kendaraan = trim((string) ($servis['no_polisi'] ?? '') . ' - ' . trim((string) ($servis['merk'] ?? '') . ' ' . (string) ($servis['model'] ?? '')));
    $noServis = (string) ($servis['no_servis'] ?? '-');
    $tanggalServisTerakhir = !empty($servis['tanggal_servis_terakhir']) ? strtotime((string) $servis['tanggal_servis_terakhir']) : false;
    $tanggalServisText = $tanggalServisTerakhir ? date('d/m/Y', $tanggalServisTerakhir) : '-';
    $tanggalRekomendasiText = $tanggalServisTerakhir ? date('d/m/Y', strtotime('+1 month', $tanggalServisTerakhir)) : '-';

    $vars = [
        'nama' => $nama,
        'no_servis' => $noServis,
        'kendaraan' => $kendaraan,
        'no_polisi' => (string) ($servis['no_polisi'] ?? '-'),
        'tanggal_servis_terakhir' => $tanggalServisText,
        'tanggal_rekomendasi' => $tanggalRekomendasiText,
    ];

    $rendered = renderWhatsappTemplate('servis_bulanan', $vars);
    if ($rendered !== null) {
        return $rendered;
    }

    $lines = [];
    $lines[] = "Halo $nama, ini pengingat servis berkala bulanan untuk kendaraan Anda.";
    $lines[] = '';
    $lines[] = 'No. Servis Terakhir: ' . $noServis;
    $lines[] = 'Kendaraan: ' . $kendaraan;
    $lines[] = 'Servis Terakhir: ' . $tanggalServisText;
    $lines[] = 'Jadwal Rekomendasi: ' . $tanggalRekomendasiText;
    $lines[] = '';
    $lines[] = 'Kami menyarankan servis rutin agar kondisi kendaraan tetap optimal.';
    $lines[] = 'Silakan hubungi bengkel untuk booking jadwal servis.';

    return implode("\n", $lines);
}

function whatsappFetchMonthlyServiceReminders(PDO $pdo): array {
    $cutoffDate = date('Y-m-d', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT s.id,s.no_servis,s.tanggal_masuk,s.tanggal_selesai,COALESCE(s.tanggal_selesai,s.tanggal_masuk) AS tanggal_servis_terakhir,p.nama AS nama_pelanggan,p.no_telepon,k.no_polisi,k.merk,k.model FROM servis s INNER JOIN (SELECT kendaraan_id, MAX(id) AS latest_id FROM servis WHERE status IN ('selesai','diambil') GROUP BY kendaraan_id) latest ON latest.latest_id = s.id INNER JOIN pelanggan p ON s.pelanggan_id=p.id INNER JOIN kendaraan k ON s.kendaraan_id=k.id WHERE COALESCE(s.tanggal_selesai,s.tanggal_masuk) <= ? ORDER BY tanggal_servis_terakhir ASC, s.id ASC");
    $stmt->execute([$cutoffDate]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Template storage and rendering helpers
function getWhatsappTemplatesPath(): string {
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }

    return $dataDir . '/whatsapp_templates.json';
}

function loadWhatsappTemplates(): array {
    $path = getWhatsappTemplatesPath();
    if (!file_exists($path)) {
        return [];
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveWhatsappTemplates(array $templates): bool {
    $path = getWhatsappTemplatesPath();
    $json = json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return (bool) @file_put_contents($path, $json);
}

function renderWhatsappTemplate(string $name, array $vars = []): ?string {
    $templates = loadWhatsappTemplates();
    if (!isset($templates[$name]) || trim((string) $templates[$name]) === '') {
        return null;
    }

    $tpl = (string) $templates[$name];
    // Simple placeholder substitution {{key}}
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', (string) $v, $tpl);
    }

    // Remove unreplaced placeholders to avoid leaking template keys
    $tpl = preg_replace('/\{\{[^}]+\}\}/', '', $tpl);

    return trim($tpl);
}