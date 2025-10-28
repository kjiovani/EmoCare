<?php
// /public/quiz_history.php
require_once __DIR__ . '/backend/config.php';
require_login();

$uid = (int) ($_SESSION['user']['pengguna_id'] ?? 0);

/* ====== Deteksi kolom yang tersedia di quiz_attempts ====== */
$cols = [];
if (
    $res = $mysqli->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = 'quiz_attempts'")
) {
    while ($r = $res->fetch_assoc())
        $cols[strtolower($r['COLUMN_NAME'])] = true;
    $res->close();
}
$hasPercent = isset($cols['percent']);
$hasCorrect = isset($cols['correct_count']);
$hasTotal = isset($cols['total_count']);
$hasCreated = isset($cols['created_at']);

// NEW: dukung skema alternatif
$hasScoreRaw = isset($cols['score_raw']) || isset($cols['score']);
$hasScoreMax = isset($cols['score_max']) || isset($cols['max_score']);

if (!$hasCreated) {
    die('Kolom created_at pada quiz_attempts tidak ditemukan.');
}


// Dukungan skema alternatif skor (beberapa database memakai nama berbeda)
$colScoreRaw = isset($cols['score_raw']) ? 'score_raw' : (isset($cols['score']) ? 'score' : null);
$colScoreMax = isset($cols['score_max']) ? 'score_max' : (isset($cols['max_score']) ? 'max_score' : null);
$hasScoreRaw = !is_null($colScoreRaw);
$hasScoreMax = !is_null($colScoreMax);

/* ====== Helper ====== */
function fmtTime($s)
{
    return $s ? date('Y-m-d H:i:s', strtotime($s)) : '—';
}

/* ====== Route: detail per kategori (?cat=slug) ====== */
$cat = trim($_GET['cat'] ?? '');
if ($cat !== '') {
    // Pastikan kategori masih aktif — kalau sudah dihapus/nonaktif, kembali ke daftar
    $check = $mysqli->prepare("SELECT 1 FROM quiz_list WHERE slug=? AND is_active=1 LIMIT 1");
    $check->bind_param('s', $cat);
    $check->execute();
    $isActive = (bool) $check->get_result()->fetch_column();
    $check->close();
    if (!$isActive) {
        header('Location: quiz_history.php');
        exit;
    }

    // Select kolom tambahan sesuai ketersediaan
    $selectExtra = [];
    if ($hasPercent)
        $selectExtra[] = "percent";
    if ($hasCorrect)
        $selectExtra[] = "correct_count";
    if ($hasTotal)
        $selectExtra[] = "total_count";
    // NEW: skema alternatif
    if ($hasScoreRaw)
        $selectExtra[] = isset($cols['score_raw']) ? "score_raw" : "score";
    if ($hasScoreMax)
        $selectExtra[] = isset($cols['score_max']) ? "score_max" : "max_score";
    $extra = $selectExtra ? ', ' . implode(', ', $selectExtra) : '';

    $sql = "SELECT id, created_at AS ts $extra
          FROM quiz_attempts
          WHERE pengguna_id=? AND category=?
          ORDER BY created_at DESC, id DESC";
    $st = $mysqli->prepare($sql);
    $st->bind_param('is', $uid, $cat);
    $st->execute();
    $rs = $st->get_result();
    $rows = $rs->fetch_all(MYSQLI_ASSOC);
    $st->close();

    // ambil meta kuis
    $meta = ['name' => $cat, 'icon' => '✨'];
    if (
        $q = $mysqli->prepare("SELECT COALESCE(name,slug) name, COALESCE(icon,'✨') icon
                             FROM quiz_list WHERE slug=? LIMIT 1")
    ) {
        $q->bind_param('s', $cat);
        $q->execute();
        $meta = $q->get_result()->fetch_assoc() ?: $meta;
        $q->close();
    }
} else {
    /* ====== Ringkasan gabungan per kategori (hanya yang aktif) ====== */
    // Subquery id attempt terakhir per kategori (milik user)
    $sub = "SELECT category, MAX(id) AS last_id
          FROM quiz_attempts
          WHERE pengguna_id=?
          GROUP BY category";

    // Ambil nilai terakhir untuk persen / skor (kalau ada)
    $selectLast = [];
    if ($hasPercent)
        $selectLast[] = "qa.percent AS last_percent";
    if ($hasCorrect)
        $selectLast[] = "qa.correct_count AS last_correct";
    if ($hasTotal)
        $selectLast[] = "qa.total_count   AS last_total";
    if ($hasScoreRaw)
        $selectLast[] = "qa.$colScoreRaw AS last_score_raw";
    if ($hasScoreMax)
        $selectLast[] = "qa.$colScoreMax AS last_score_max";
    $extraLast = $selectLast ? ', ' . implode(', ', $selectLast) : '';

    // INNER JOIN quiz_list l AND l.is_active=1 → sembunyikan kuis yang dihapus/nonaktif
    $sql = "
    SELECT q.category,
           COALESCE(l.name, q.category) AS name,
           COALESCE(l.icon, '✨')       AS icon,
           COUNT(*) AS attempts,
           MAX(q.created_at) AS last_at
           $extraLast
    FROM quiz_attempts q
    JOIN ($sub) m ON m.category = q.category
    LEFT JOIN quiz_attempts qa ON qa.id = m.last_id
    JOIN quiz_list l ON l.slug = q.category AND l.is_active=1
    WHERE q.pengguna_id=?
    GROUP BY q.category, name, icon
    ORDER BY last_at DESC
  ";
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $uid, $uid);
    $st->execute();
    $rs = $st->get_result();
    $rows = $rs->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Riwayat Hasil Kuis • EmoCare</title>
    <link rel="stylesheet" href="css/styles.css" />
    <style>
        :root {
            --rose: #f472b6;
            --rose-200: #fbcfe8;
            --indigo: #6366f1;
            --ink: #0f172a;
            --muted: #6b7280;
            --bd: rgba(0, 0, 0, .06);
        }

        body {
            background: linear-gradient(180deg, #ffffff 0%, #faf5ff 100%)
        }

        .wrap {
            max-width: 1080px;
            margin: 28px auto;
            padding: 0 16px
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px
        }

        .title {
            font-size: 28px;
            font-weight: 900;
            color: var(--ink);
            letter-spacing: .2px
        }

        .btn {
            background: var(--rose);
            color: #fff;
            border: 0;
            border-radius: 12px;
            padding: 10px 14px;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(236, 72, 153, .18);
            cursor: pointer
        }

        .btn.ghost {
            background: #fff;
            color: #be185d;
            border: 1px solid #f9a8d4;
            box-shadow: none
        }

        .btn.link {
            background: transparent;
            border: 0;
            color: #be185d;
            font-weight: 900
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px
        }

        .card {
            border: 1px solid var(--bd);
            background: linear-gradient(180deg, rgba(255, 255, 255, .9), rgba(255, 255, 255, .75));
            border-radius: 20px;
            padding: 16px;
            box-shadow: 0 14px 34px rgba(99, 102, 241, .10), 0 4px 10px rgba(0, 0, 0, .04);
            backdrop-filter: saturate(1.1) blur(6px);
        }

        .head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px
        }

        .ico {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 20px;
            background: linear-gradient(180deg, #ffe7f2, #fff);
            border: 1px solid #f4cadd
        }

        .name {
            font-weight: 900;
            color: var(--ink);
            letter-spacing: .2px
        }

        .meta {
            color: var(--muted);
            font-size: 13px;
            margin: 4px 0
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            font-weight: 800;
            background: #fff;
            color: #334155
        }

        .foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            gap: 8px;
            flex-wrap: wrap
        }

        .cta {
            display: flex;
            gap: 8px
        }

        /* progress mini */
        .meter {
            height: 10px;
            background: #f1f5f9;
            border-radius: 999px;
            overflow: hidden
        }

        .meter>span {
            display: block;
            height: 10px;
            background: linear-gradient(90deg, var(--rose), var(--indigo))
        }

        .pct {
            font-weight: 900;
            color: var(--ink)
        }

        .muted {
            color: var(--muted)
        }

        /* DETAIL view */
        .detail-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px
        }

        .sub {
            color: var(--muted);
            font-weight: 700
        }

        .list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px
        }

        .item {
            border: 1px solid var(--bd);
            border-radius: 16px;
            padding: 12px;
            background: #fff
        }

        .item .row {
            display: flex;
            justify-content: space-between;
            gap: 10px
        }
    </style>
</head>

<body class="ec-body">
    <main class="wrap">
        <div class="topbar">
            <div class="title"><?= $cat ? 'Riwayat Kuis: ' . htmlspecialchars($cat) : 'Riwayat Hasil Kuis' ?></div>
            <div>
                <?php if ($cat): ?>
                    <a class="btn ghost" href="quiz_history.php">← Kembali</a>
                <?php else: ?>
                    <a class="btn ghost" href="home.php#quiz-cards">← Kembali ke Beranda</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="card">Belum ada riwayat yang ditampilkan.</div>

        <?php elseif ($cat): /* ====== DETAIL PER KATEGORI ====== */ ?>
            <div class="card">
                <div class="detail-head">
                    <div class="ico"><?= htmlspecialchars($meta['icon'] ?? '✨') ?></div>
                    <div class="name"><?= htmlspecialchars($meta['name'] ?? $cat) ?></div>
                    <div class="pill"><?= count($rows) ?> attempt</div>
                </div>
                <div class="list">
                    <?php foreach ($rows as $r):
                        // ambil nilai yang mungkin ada
                        $correct = ($hasCorrect && array_key_exists('correct_count', $r)) ? $r['correct_count'] : null;
                        $total = ($hasTotal && array_key_exists('total_count', $r)) ? $r['total_count'] : null;
                        $scoreRaw = ($hasScoreRaw && array_key_exists(($cols['score_raw'] ?? false) ? 'score_raw' : 'score', $r))
                            ? $r[($cols['score_raw'] ?? false) ? 'score_raw' : 'score'] : null;
                        $scoreMax = ($hasScoreMax && array_key_exists(($cols['score_max'] ?? false) ? 'score_max' : 'max_score', $r))
                            ? $r[($cols['score_max'] ?? false) ? 'score_max' : 'max_score'] : null;

                        // teks skor
                        if (!is_null($correct) && !is_null($total)) {
                            $scoreText = (int) $correct . '/' . (int) $total;
                        } elseif (!is_null($scoreRaw) && !is_null($scoreMax)) {
                            $scoreText = (int) $scoreRaw . '/' . (int) $scoreMax . ' poin';
                        } elseif (!is_null($scoreRaw)) {
                            $scoreText = (int) $scoreRaw . ' poin';
                        } else {
                            $scoreText = '—';
                        }

                        // Persentase
                        if ($hasPercent && array_key_exists('percent', $r) && $r['percent'] !== null) {
                            $percent = (int) round((float) $r['percent']);
                        } elseif (!is_null($correct) && !is_null($total) && (int) $total > 0) {
                            $percent = (int) round(((int) $correct * 100) / (int) $total);
                        } elseif (!is_null($scoreRaw) && !is_null($scoreMax) && (int) $scoreMax > 0) {
                            $percent = (int) round(((int) $scoreRaw * 100) / (int) $scoreMax);
                        } elseif (!is_null($scoreRaw) && is_numeric($scoreRaw) && $scoreRaw >= 0 && $scoreRaw <= 100) {
                            // Fallback: score_raw sudah skala 0–100 → jadikan persen
                            $percent = (int) round((float) $scoreRaw);
                        } else {
                            $percent = null;
                        }

                        ?>
                        <div class="item">
                            <div class="row"><span
                                    class="sub">Tanggal</span><strong><?= htmlspecialchars(fmtTime($r['ts'])) ?></strong></div>
                            <div class="row"><span class="sub">Skor</span><strong><?= htmlspecialchars($scoreText) ?></strong>
                            </div>

                            <div class="row" style="align-items:center;gap:8px">
                                <span class="sub">Persen</span>
                                <div style="flex:1">
                                    <div class="meter"><span style="width:<?= is_null($percent) ? 0 : $percent ?>%"></span>
                                    </div>
                                </div>
                                <strong class="pct"><?= is_null($percent) ? '—' : ($percent . '%') ?></strong>
                            </div>

                            <div class="foot" style="margin-top:10px">
                                <span class="pill">Attempt #<?= (int) $r['id'] ?></span>
                                <div class="cta"><a class="btn" href="quiz_result.php?id=<?= (int) $r['id'] ?>">Lihat</a></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

        <?php else: /* ====== RINGKAS PER KATEGORI (HANYA AKTIF) ====== */ ?>
            <div class="grid">
                <?php foreach ($rows as $r):
                    $ico = $r['icon'] ?: '✨';
                    $name = $r['name'] ?: $r['category'];
                    $times = (int) $r['attempts'];
                    $last = fmtTime($r['last_at']);

                    // Data "terakhir"
                    $lastPercent = array_key_exists('last_percent', $r) ? $r['last_percent'] : null;
                    $lastCorrect = array_key_exists('last_correct', $r) ? $r['last_correct'] : null;
                    $lastTotal = array_key_exists('last_total', $r) ? $r['last_total'] : null;
                    $lastScoreRaw = array_key_exists('last_score_raw', $r) ? $r['last_score_raw'] : null;
                    $lastScoreMax = array_key_exists('last_score_max', $r) ? $r['last_score_max'] : null;

                    // Teks skor terakhir
                    if (!is_null($lastCorrect) && !is_null($lastTotal)) {
                        $pair = (int) $lastCorrect . '/' . (int) $lastTotal;
                    } elseif (!is_null($lastScoreRaw) && !is_null($lastScoreMax)) {
                        $pair = (int) $lastScoreRaw . '/' . (int) $lastScoreMax . ' poin';
                    } elseif (!is_null($lastScoreRaw)) {
                        $pair = (int) $lastScoreRaw . ' poin';
                    } else {
                        $pair = '—';
                    }

                    // Persen terakhir (fallback)
                    if (!is_null($lastPercent)) {
                        $percent = (int) round((float) $lastPercent);
                    } elseif (!is_null($lastCorrect) && !is_null($lastTotal) && (int) $lastTotal > 0) {
                        $percent = (int) round(((int) $lastCorrect * 100) / (int) $lastTotal);
                    } elseif (!is_null($lastScoreRaw) && !is_null($lastScoreMax) && (int) $lastScoreMax > 0) {
                        $percent = (int) round(((int) $lastScoreRaw * 100) / (int) $lastScoreMax);
                    } elseif (!is_null($lastScoreRaw) && is_numeric($lastScoreRaw) && $lastScoreRaw >= 0 && $lastScoreRaw <= 100) {
                        // Fallback: score_raw 0–100 → persen
                        $percent = (int) round((float) $lastScoreRaw);
                    } else {
                        $percent = null;
                    }

                    ?>
                    <div class="card">
                        <div class="head">
                            <div class="ico"><?= htmlspecialchars($ico) ?></div>
                            <div class="name"><?= htmlspecialchars($name) ?></div>
                        </div>

                        <div class="meta">Terakhir: <strong><?= htmlspecialchars($last) ?></strong></div>
                        <div class="meta">Sudah dikerjakan: <strong><?= $times ?></strong> kali</div>
                        <div class="meta">Skor terakhir: <strong><?= htmlspecialchars($pair) ?></strong></div>

                        <?php if (!is_null($percent)): ?>
                            <div class="meta">Persen terakhir:</div>
                            <div class="meter" style="margin:6px 0 2px"><span style="width:<?= $percent ?>%"></span></div>
                            <div class="muted"><span class="pct"><?= $percent ?>%</span></div>
                        <?php else: ?>
                            <div class="meta">Persen terakhir: <strong>—</strong></div>
                        <?php endif; ?>

                        <div class="foot">
                            <span class="pill">Kategori: <?= htmlspecialchars($r['category']) ?></span>
                            <div class="cta">
                                <a class="btn ghost" href="quiz_history.php?cat=<?= urlencode($r['category']) ?>">Detail</a>
                                <a class="btn" href="quiz_result.php?cat=<?= urlencode($r['category']) ?>">Lihat Terbaru</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>