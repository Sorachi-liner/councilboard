<?php
/**
 * 設定
 */
$station_id = "14163"; // 札幌
$forecast_url = "https://www.jma.go.jp/bosai/forecast/data/forecast/016000.json";
$amedas_latest_url = "https://www.jma.go.jp/bosai/amedas/data/latest_time.json";

// エラー抑制（本番用）
error_reporting(0);

/**
 * データを取得する関数
 */
function fetch_json($url) {
    $options = [
        "http" => ["method" => "GET", "header" => "User-Agent: MyDashboardApp/1.0\r\n"]
    ];
    $context = stream_context_create($options);
    $json = file_get_contents($url, false, $context);
    return json_decode($json, true);
}

// 1. 天気概況の取得
$forecast_data = fetch_json($forecast_url);
$report_time = $forecast_data[0]['reportDatetime'];
$office = $forecast_data[0]['publishingOffice'];
$weather_text = $forecast_data[0]['timeSeries'][0]['areas'][0]['weathers'][0] ?? "取得失敗";

// 2. アメダス最新時刻の取得
$latest_time_str = fetch_json($amedas_latest_url); // e.g. 20231027151000

// 3. アメダス地点データの取得 (年月日部分を抽出)
$date_part = substr($latest_time_str, 0, 8);
$amedas_url = "https://www.jma.go.jp/bosai/amedas/data/point/{$station_id}/{$date_part}.json";
$amedas_data = fetch_json($amedas_url);

// 4. 統計計算とリスト作成
$display_data = [];
$max_temp = -99;
$min_temp = 99;

if ($amedas_data) {
    // 降順にソートして直近データを取得
    krsort($amedas_data);
    
    foreach ($amedas_data as $time => $val) {
        $temp = $val['temp'][0];
        // 最高・最低の更新（当日全体）
        if ($temp !== null) {
            if ($temp > $max_temp) $max_temp = $temp;
            if ($temp < $min_temp) $min_temp = $temp;
        }
    }

    // 表示用に直近60分（7件）を抽出
    $count = 0;
    foreach ($amedas_data as $time => $val) {
        if ($count >= 7) break;
        $display_data[] = [
            'time' => substr($time, 8, 2) . ":" . substr($time, 10, 2),
            'temp' => $val['temp'][0] ?? '--',
            'prec' => $val['precipitation10m'][0] ?? '0.0',
            'wind' => $val['wind'][0] ?? '--'
        ];
        $count++;
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>気象ダッシュボード (PHP版)</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f9; color: #333; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { border-left: 5px solid #0044cc; padding-left: 10px; font-size: 1.2rem; }
        .stats { display: flex; gap: 20px; }
        .stat-box { flex: 1; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .temp-max { color: #e74c3c; font-weight: bold; font-size: 1.5rem; }
        .temp-min { color: #3498db; font-weight: bold; font-size: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #f8f9fa; }
        .error { color: red; background: #fee; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>気象情報ダッシュボード</h1>
    <p>最終更新時刻: <?php echo date("H:i", strtotime($latest_time_str)); ?></p>

    <?php if (!$amedas_data): ?>
        <div class="error">データの取得に失敗しました。URLまたはネットワーク設定を確認してください。</div>
    <?php endif; ?>

    <div class="card">
        <h2>天気概況</h2>
        <p style="font-size: 0.9rem; color: #666;"><?php echo $office; ?>発表 (<?php echo date("Y/m/d H:i", strtotime($report_time)); ?>)</p>
        <p style="line-height: 1.6;"><?php echo nl2br($weather_text); ?></p>
    </div>

    <div class="card">
        <h2>本日の気温（アメダス札幌）</h2>
        <div class="stats">
            <div class="stat-box">
                <div>最高気温</div>
                <div class="temp-max"><?php echo $max_temp != -99 ? number_format($max_temp, 1) . "℃" : "--"; ?></div>
            </div>
            <div class="stat-box">
                <div>最低気温</div>
                <div class="temp-min"><?php echo $min_temp != 99 ? number_format($min_temp, 1) . "℃" : "--"; ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>直近60分の推移</h2>
        <table>
            <thead>
                <tr>
                    <th>時刻</th>
                    <th>気温(℃)</th>
                    <th>降水(mm)</th>
                    <th>風速(m/s)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($display_data as $row): ?>
                <tr>
                    <td><?php echo $row['time']; ?></td>
                    <td><?php echo $row['temp']; ?></td>
                    <td><?php echo $row['prec']; ?></td>
                    <td><?php echo $row['wind']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
