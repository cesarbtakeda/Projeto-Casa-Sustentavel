<?php
$txtFile = 'energia.txt';
$jsonFile = 'energia_data.json';
$histFile = 'energia_hist.json';

function loadEnergyData($txtFile, $jsonFile, $histFile) {
    $currentDay = date('Y-m-d');

    if (file_exists($jsonFile)) {
        $json = json_decode(file_get_contents($jsonFile), true);
    } else {
        $json = [
            'wind' => 0, 'hydro' => 0, 'solar' => 0,
            'count' => 0,
            'last_reset' => $currentDay
        ];
    }

    if ($json['last_reset'] !== $currentDay) {
        $hist = file_exists($histFile) ? json_decode(file_get_contents($histFile), true) : [];
        $hist[$json['last_reset']] = [
            'wind'  => $json['wind'],
            'hydro' => $json['hydro'],
            'solar' => $json['solar']
        ];
        file_put_contents($histFile, json_encode($hist));

        $json = [
            'wind' => 0, 'hydro' => 0, 'solar' => 0,
            'count' => 0,
            'last_reset' => $currentDay
        ];
    }

    $lines = file($txtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lineIndex = 0;
    $temp = ['wind' => 0, 'hydro' => 0, 'solar' => 0];
    $count = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if (!is_numeric($line)) continue;
        $value = (int)$line;
        $pos = $lineIndex % 3;
        if ($pos === 0) {
            $temp['wind'] += $value;
        } elseif ($pos === 1) {
            $temp['hydro'] += $value;
        } else {
            $temp['solar'] += $value;
            $count++;
        }
        $lineIndex++;
    }

    $json['wind']  += $temp['wind'];
    $json['hydro'] += $temp['hydro'];
    $json['solar'] += $temp['solar'];
    $json['count'] += $count;

    file_put_contents($jsonFile, json_encode($json));

    if ($json['count'] > 0) {
        $avg = [
            'wind_avg'  => round($json['wind'] / $json['count'], 2),
            'hydro_avg' => round($json['hydro'] / $json['count'], 2),
            'solar_avg' => round($json['solar'] / $json['count'], 2)
        ];
    } else {
        $avg = ['wind_avg' => 0, 'hydro_avg' => 0, 'solar_avg' => 0];
    }

    $hist = file_exists($histFile) ? json_decode(file_get_contents($histFile), true) : [];

    return [
        'wind_total'  => $json['wind'],
        'hydro_total' => $json['hydro'],
        'solar_total' => $json['solar'],
        'wind_avg'    => $avg['wind_avg'],
        'hydro_avg'   => $avg['hydro_avg'],
        'solar_avg'   => $avg['solar_avg'],
        'day'         => $currentDay,
        'history'     => $hist
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'getData') {
    header('Content-Type: application/json');
    echo json_encode(loadEnergyData($txtFile, $jsonFile, $histFile));
    exit;
}
?>

<!-- HTML abaixo -->

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard Energia</title>
  <style>
    /* Aqui vai o CSS moderno com cores, gradientes e sombra */
    body {
      background: linear-gradient(135deg, #1f1f2e, #141414);
      color: #e0e0e0;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
    }
    .header {
      text-align: center;
      padding: 30px 20px;
      font-size: 2.5rem;
      color: #fff;
    }
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 25px;
      padding: 0 20px 40px;
      max-width: 1200px;
      margin: 0 auto;
    }
    .card {
      background: #2a2a3d;
      border-radius: 16px;
      padding: 25px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
      transition: transform 0.3s ease;
    }
    .card:hover { transform: translateY(-5px); }
    .label { font-size: 1rem; color: #ccc; margin-bottom: 10px; }
    .value { font-size: 2rem; font-weight: bold; }
    .wind .value { color: #00ffd5; }
    .hydro .value { color: #4ac6ff; }
    .solar .value { color: #ffdd57; }
    .chart-container {
      background: #2a2a3d;
      border-radius: 16px;
      padding: 30px;
      margin: 0 auto 60px;
      max-width: 1200px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    }
    canvas {
      width: 100% !important;
      max-height: 400px;
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="header">Dashboard de Energia</div>

  <div class="cards">
    <div class="card wind"><div class="label">Total Eólica</div><div class="value" id="tot-wind">0</div></div>
    <div class="card hydro"><div class="label">Total Hidro</div><div class="value" id="tot-hydro">0</div></div>
    <div class="card solar"><div class="label">Total Solar</div><div class="value" id="tot-solar">0</div></div>
    <div class="card wind"><div class="label">Média Eólica</div><div class="value" id="avg-wind">0</div></div>
    <div class="card hydro"><div class="label">Média Hidro</div><div class="value" id="avg-hydro">0</div></div>
    <div class="card solar"><div class="label">Média Solar</div><div class="value" id="avg-solar">0</div></div>
  </div>

  <div class="chart-container">
    <canvas id="energyChart"></canvas>
  </div>

  <script>
    let chart;
    async function fetchAndRender() {
      const res = await fetch('?action=getData');
      const data = await res.json();
      document.getElementById('tot-wind').textContent = data.wind_total;
      document.getElementById('tot-hydro').textContent = data.hydro_total;
      document.getElementById('tot-solar').textContent = data.solar_total;
      document.getElementById('avg-wind').textContent = data.wind_avg;
      document.getElementById('avg-hydro').textContent = data.hydro_avg;
      document.getElementById('avg-solar').textContent = data.solar_avg;

      const labels = Object.keys(data.history);
      const windArr = labels.map(d => data.history[d].wind);
      const hydroArr = labels.map(d => data.history[d].hydro);
      const solarArr = labels.map(d => data.history[d].solar);

      if (!chart) {
        const ctx = document.getElementById('energyChart').getContext('2d');
        chart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [
              { label: 'Eólica', data: windArr, borderColor: '#00ffd5', fill: true },
              { label: 'Hidro', data: hydroArr, borderColor: '#4ac6ff', fill: true },
              { label: 'Solar', data: solarArr, borderColor: '#ffdd57', fill: true },
            ]
          },
          options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#fff' } } },
            scales: {
              x: { ticks: { color: '#aaa' } },
              y: { ticks: { color: '#aaa' } }
            }
          }
        });
      } else {
        chart.data.labels = labels;
        chart.data.datasets[0].data = windArr;
        chart.data.datasets[1].data = hydroArr;
        chart.data.datasets[2].data = solarArr;
        chart.update();
      }
    }

    fetchAndRender();
    setInterval(fetchAndRender, 30000);
  </script>
</body>
</html>
