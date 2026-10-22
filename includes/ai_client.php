<?php
/**
 * ตัวเรียก Gemini ที่ใช้ร่วมกัน (ใช้โดยผู้ช่วยร้านค้า)
 * - ส่ง API key ทาง header (x-goog-api-key) ไม่ใส่ใน URL เพื่อไม่ให้ key หลุดลง access log
 * - คืนค่า null เมื่อไม่มี key / เรียกไม่สำเร็จ เพื่อให้ผู้เรียกสลับไปโหมด fallback ได้
 */
function stepup_ai_chat(array $config, array $messages, float $temperature = 0.2): ?string {
    if (empty($config['api_key']) || empty($config['model']) || !function_exists('curl_init')) return null;

    $systemParts = [];
    $contents = [];
    foreach ($messages as $m) {
        $role = (string)($m['role'] ?? 'user');
        $text = $m['content'] ?? '';
        if (!is_string($text) || trim($text) === '') continue;
        if ($role === 'system') {
            $systemParts[] = ['text' => $text];
        } else {
            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $text]]];
        }
    }
    if (!$contents) return null;

    $payload = ['contents' => $contents, 'generationConfig' => ['temperature' => $temperature]];
    if ($systemParts) $payload['systemInstruction'] = ['parts' => $systemParts];

    $base = rtrim((string)($config['api_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
    $url = $base . '/' . rawurlencode((string)$config['model']) . ':generateContent';
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'x-goog-api-key: ' . $config['api_key']],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => (int)($config['timeout'] ?? 45),
    ]);
    $res = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($res) || $status < 200 || $status >= 300) {
        if (is_string($res)) error_log('StepUP seller assistant: Gemini HTTP ' . $status . ': ' . mb_substr($res, 0, 300));
        return null;
    }
    $parts = json_decode($res, true)['candidates'][0]['content']['parts'] ?? null;
    if (!is_array($parts)) return null;
    $text = '';
    foreach ($parts as $p) if (isset($p['text']) && is_string($p['text'])) $text .= $p['text'];
    $text = trim($text);
    return $text !== '' ? $text : null;
}
