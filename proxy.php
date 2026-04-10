<?php
/**
 * QNAP bildproxy — löser CORS-problemet
 * Lägg denna fil i \Qnap\Web\ bredvid display.html
 */

// Tillåt anrop från display.html på port 80
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$ssid = '72831bf16f4f41e786e38a908fd6880f';
$act  = isset($_GET['act']) ? $_GET['act'] : 'list';

if ($act === 'list') {
    // Hämta bildlista från share.cgi på port 8080 (internt på QNAP)
    $dir    = isset($_GET['dir'])    ? $_GET['dir']    : '/Yodeck bildspel - utvalda';
    $limit  = isset($_GET['limit'])  ? intval($_GET['limit'])  : 200;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $url = 'http://127.0.0.1:8080/share.cgi'
         . '?ssid='   . urlencode($ssid)
         . '&act=list'
         . '&limit='  . $limit
         . '&offset=' . $offset
         . '&dir='    . urlencode($dir);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        http_response_code(502);
        echo json_encode(['error' => $err]);
    } else {
        echo $resp;
    }

} elseif ($act === 'image') {
    // Hämta en specifik bild och skicka vidare
    $filename = isset($_GET['filename']) ? $_GET['filename'] : '';
    $dir      = isset($_GET['dir'])      ? $_GET['dir']      : '/Yodeck bildspel - utvalda';

    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Inget filnamn']);
        exit;
    }

    $url = 'http://127.0.0.1:8080/share.cgi'
         . '?ssid='     . urlencode($ssid)
         . '&act=download'
         . '&filename=' . urlencode($filename)
         . '&path='     . urlencode($dir);

    // Bestäm Content-Type
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
    ];
    $ct = isset($types[$ext]) ? $types[$ext] : 'image/jpeg';

    header('Content-Type: ' . $ct);
    header('Cache-Control: max-age=86400');
    header('Access-Control-Allow-Origin: *');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $data = curl_exec($ch);
    curl_close($ch);

    echo $data;
    exit;

} elseif ($act === 'ical') {
    // Hämta iCal-flöde och returnera som text (löser CORS)
    $url = isset($_GET['url']) ? $_GET['url'] : '';
    if (!$url || strpos($url, 'https://') !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ogiltig URL']);
        exit;
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Cache-Control: max-age=900');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code >= 400) {
        http_response_code(502);
        echo json_encode(['error' => $err ?: 'HTTP ' . $code]);
    } else {
        echo $data;
    }
    exit;

} elseif ($act === 'rss') {
    // Hämta och parsa RSS-flöde, returnera titlar som JSON
    $url = isset($_GET['url']) ? $_GET['url'] : 'https://www.svd.se/feed/articles.rss';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: max-age=900');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KoksDisplay/1.0');
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$data) {
        http_response_code(502);
        echo json_encode(['error' => $err ?: 'Tomt svar']);
        exit;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($data);
    if (!$xml) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunde inte parsa RSS']);
        exit;
    }

    $titles = [];
    $items = $xml->channel->item ?? $xml->entry ?? [];
    foreach ($items as $item) {
        $title = trim((string)($item->title ?? ''));
        if ($title) $titles[] = $title;
        if (count($titles) >= $limit) break;
    }

    echo json_encode($titles, JSON_UNESCAPED_UNICODE);
    exit;

} elseif ($act === 'tibber') {
    // Proxya Tibber GraphQL API
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    $query = '{"query":"{viewer{homes{currentSubscription{priceInfo{current{total startsAt level}today{total startsAt level}tomorrow{total startsAt level}}}}}}"}';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: max-age=900');

    $ch = curl_init('https://api.tibber.com/v1-beta/gql');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$data) {
        http_response_code(502);
        echo json_encode(['error' => $err ?: 'Tomt svar']);
    } else {
        echo $data;
    }
    exit;

} elseif ($act === 'weather') {
    // Proxya Open-Meteo väder-API (löser eventuella CORS/nätverksproblem)
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 59.52;
    $lon = isset($_GET['lon']) ? floatval($_GET['lon']) : 17.91;

    $url = 'https://api.open-meteo.com/v1/forecast'
         . '?latitude=' . $lat
         . '&longitude=' . $lon
         . '&current=temperature_2m,weather_code'
         . '&daily=weather_code,temperature_2m_max,temperature_2m_min'
         . '&past_days=1&forecast_days=2&timezone=auto';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: max-age=1800');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code >= 400) {
        http_response_code(502);
        echo json_encode(['error' => $err ?: 'HTTP ' . $code]);
    } else {
        echo $data;
    }
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Okänd åtgärd']);
}
