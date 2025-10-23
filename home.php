<?php
require_once __DIR__ . '/backend/config.php';
require_login();

/* --- identitas & role --- */
$uid = (int) ($_SESSION['user']['pengguna_id'] ?? 0);
$nama = $_SESSION['user']['nama'] ?? 'Pengguna';
$isAdmin = false;
$stmt = $mysqli->prepare("SELECT role FROM pengguna WHERE pengguna_id=? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$role = $stmt->get_result()->fetch_column();
$stmt->close();
$isAdmin = ($role === 'admin');

/* ------------------------------------
   Kuis Psikologi (auto dari database)
------------------------------------- */
$quizzes = [];
$sql = "
  SELECT l.id, l.name, l.slug, l.icon,
         COALESCE(l.description,'') AS description,
         COALESCE((
           SELECT COUNT(*) FROM quiz_questions q WHERE q.category = l.slug
         ),0) AS total_q,
         COALESCE((
           SELECT COUNT(*) FROM quiz_questions q WHERE q.category = l.slug AND q.is_active = 1
         ),0) AS active_q
  FROM quiz_list l
  WHERE l.is_active = 1
  ORDER BY l.sort_order ASC, l.id ASC
";
if ($res = $mysqli->query($sql)) {
  $quizzes = $res->fetch_all(MYSQLI_ASSOC);
}

/* --- flash & handlers Mood Tracker --- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'create')) {
  $mood = (int) ($_POST['mood_level'] ?? 0);
  $note = trim($_POST['catatan'] ?? '');
  if ($mood < 1 || $mood > 5) {
    $flash = 'Skala mood harus 1..5';
  } else {
    $stmt = $mysqli->prepare("INSERT INTO moodtracker (pengguna_id, tanggal, mood_level, catatan) VALUES (?, CURDATE(), ?, ?)");
    $stmt->bind_param('iis', $uid, $mood, $note);
    if ($stmt->execute()) {
      header('Location: home.php?saved=1');
      exit;
    } else {
      $flash = 'Gagal menyimpan: ' . $stmt->error;
    }
    $stmt->close();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete')) {
  $ids = array_values(array_filter(array_map('intval', $_POST['delete_ids'] ?? [])));
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM moodtracker WHERE pengguna_id=? AND mood_id IN ($in)";
    $stmt = $mysqli->prepare($sql);
    $types = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$uid], $ids);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    header('Location: home.php?deleted=1#top');
    exit;
  } else {
    $flash = 'Pilih minimal satu baris untuk dihapus.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'edit')) {
  $id = (int) ($_POST['mood_id'] ?? 0);
  $mood = (int) ($_POST['mood_level'] ?? 0);
  $note = trim($_POST['catatan'] ?? '');
  if ($id <= 0)
    $flash = 'Data tidak valid.';
  elseif ($mood < 1 || $mood > 5)
    $flash = 'Skala mood harus 1..5';
  else {
    $sql = "UPDATE moodtracker SET mood_level=?, catatan=? WHERE mood_id=? AND pengguna_id=?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('isii', $mood, $note, $id, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: home.php?updated=1#top');
    exit;
  }
}

/* --- ambil riwayat mood (dengan pencarian sederhana) --- */
$items = [];
$s = trim($_GET['s'] ?? '');
$conds = ['pengguna_id = ?'];
$types = 'i';
$vals = [$uid];
if ($s !== '') {
  $conds[] = '(tanggal LIKE ? OR catatan LIKE ? OR CAST(mood_level AS CHAR) LIKE ?)';
  $types .= 'sss';
  $like = '%' . $s . '%';
  array_push($vals, $like, $like, $like);
}
$sql = "SELECT mood_id, tanggal, mood_level, catatan
        FROM moodtracker
        WHERE " . implode(' AND ', $conds) . "
        ORDER BY tanggal DESC, mood_id DESC";
$q = $mysqli->prepare($sql);
$q->bind_param($types, ...$vals);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc())
  $items[] = $row;
$q->close();
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Home • EmoCare</title>
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/mood.css" />
  <style>
    .bg-hero {
      background: linear-gradient(135deg, #ffe0ea 0%, #e7dcff 50%, #dfe9ff 100%)
    }

    /* ====== Kuis Psikologi (grid) ====== */
    .quiz-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px
    }

    .quiz-card {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: #fff;
      border: 1px solid var(--bd);
      border-radius: 18px;
      padding: 16px;
      box-shadow: var(--shadow);
    }

    .quiz-ico {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      flex: 0 0 48px;
      display: grid;
      place-items: center;
      font-size: 22px;
      background: linear-gradient(180deg, #ffe7f2, #fff);
      border: 1px solid var(--bd);
    }

    .quiz-body {
      flex: 1
    }

    .quiz-name {
      font-weight: 900;
      color: #111;
      margin-bottom: 4px
    }

    .quiz-desc {
      color: #6b7280;
      line-height: 1.5;
      margin-bottom: 10px
    }

    .quiz-meta {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #fff;
      border: 1px solid var(--bd);
      font-weight: 700;
      color: #c21762;
    }

    .btn {
      background: #f472b6;
      color: #fff;
      border: 0;
      border-radius: 14px;
      padding: 10px 14px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 6px 14px rgba(236, 72, 153, .12)
    }

    .btn[disabled] {
      opacity: .55;
      cursor: not-allowed
    }

    .btn.ghost {
      background: #fff;
      color: #be185d;
      border: 1px solid #f9a8d4
    }

    /* tabel riwayat dsb (sudah ada pada css utama, hanya pelengkap kecil) */
    .empty {
      padding: 18px;
      border: 1px dashed #f4cadd;
      border-radius: 14px;
      background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
      color: #6b7280;
      text-align: center;
      font-weight: 600;
    }

    /* Sembunyikan info "soal aktif" untuk pengguna biasa */
    .role-user .qp-meta {
      display: none !important;
    }
  </style>
</head>

<body class="ec-body <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
  <header class="ec-nav">
    <div class="ec-nav-inner">
      <div class="ec-brand"><span class="ec-brand-name" style="font-weight:500;color:#6b7280;">EmoCare</span></div>
      <nav class="ec-nav-links">
        <a href="#top">Beranda</a>
        <a href="#features">Fitur</a>
        <a href="#stats">Statistik</a>
        <?php if ($isAdmin): ?><a href="admin/dashboard.php" class="btn ghost"
            style="margin-left:8px">Admin</a><?php endif; ?>
      </nav>
      <form action="backend/auth_logout.php" method="post" style="margin:0"><button
          class="ec-btn-outline">Keluar</button></form>
    </div>
  </header>

  <main id="top" class="ec-container">
    <!-- Greeting / Hero -->
    <section class="ec-card ec-hero" id="greetingCard">
      <div class="ec-hero-left">
        <div class="ec-hero-title">Selamat <span id="greetTimeWord">Pagi</span>, <span
            id="greetUsername"><?= htmlspecialchars($nama) ?></span>! 🙌🏻</div>
        <div class="ec-hero-sub">Gimana hari ini?</div>
      </div>
      <div class="ec-hero-right">
        <div class="ec-clock"><span id="greetClock"></span></div>
        <div class="ec-streak">🔥 <span id="greetStreak">0</span> hari beruntun</div>
      </div>
    </section>

    <!-- VIDEO QUOTES -->
    <section class="ec-card ec-video">
      <h3 class="ec-section-title">Tonton dulu Yuk😻</h3>
      <div class="ec-video-frame" id="video-frame">
        <iframe width="560" height="315" src="https://www.youtube.com/embed/WWloIAQpMcQ" title="Quotes" frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
      </div>
    </section>

    <!-- KUIS PSIKOLOGI: auto dari quiz_list -->
    <section class="ec-card" id="quiz-cards">
      <h2 class="ec-section-title">Kuis Psikologi</h2>

      <?php if (empty($quizzes)): ?>
        <div class="empty">Belum ada kuis aktif. Admin dapat menambahkannya di Kelola Kuis.</div>
      <?php else: ?>
        <div class="quiz-grid">
          <?php foreach ($quizzes as $q): ?>
            <article class="quiz-card">
              <div class="quiz-ico"><?= htmlspecialchars($q['icon'] ?: '✨') ?></div>
              <div class="quiz-body">
                <div class="quiz-name"><?= htmlspecialchars($q['name']) ?></div>
                <?php if (!empty($q['description'])): ?>
                  <div class="quiz-desc"><?= htmlspecialchars($q['description']) ?></div>
                <?php endif; ?>
                <div class="quiz-meta">
                  <?php if ($isAdmin): ?>
                    <span class="chip"><?= (int) $q['active_q'] ?>/<?= (int) $q['total_q'] ?> soal aktif</span>
                  <?php endif; ?>
                  <?php if ((int) $q['active_q'] > 0): ?>
                    <a class="btn" href="play_quiz.php?cat=<?= urlencode($q['slug']) ?>">Mulai Kuis</a>
                  <?php else: ?>
                    <button class="btn" disabled>Belum ada soal</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- Mood Tracker -->
    <section id="mood-tracker" class="ec-mood-grid">
      <!-- Form -->
      <article class="card ec-mood-card">
        <div class="card-bar">
          <div class="bar-dot"></div>
          <h3 class="bar-title">Mood Tracker</h3>
        </div>

        <?php if (!empty($_GET['saved'])): ?>
          <p class="form-success" role="status">Mood tersimpan!</p>
        <?php elseif (!empty($_GET['updated'])): ?>
          <p class="form-success" role="status">Riwayat berhasil diperbarui.</p>
        <?php elseif (!empty($_GET['deleted'])): ?>
          <p class="form-success" role="status">Riwayat terpilih berhasil dihapus.</p>
        <?php elseif ($flash): ?>
          <p class="form-error" role="alert"><?= htmlspecialchars($flash) ?></p>
        <?php endif; ?>

        <form action="home.php" method="POST" class="ec-form" autocomplete="off">
          <input type="hidden" name="action" value="create">
          <div class="form-row">
            <label>Skala Mood</label>
            <div class="scale-group" role="radiogroup" aria-label="Skala Mood">
              <label class="scale-pill"><input type="radio" name="mood_level" value="1" required>1. Senang
                Banget</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="2">2. Senang</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="3">3. Biasa</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="4">4. Cemas</label>
              <label class="scale-pill"><input type="radio" name="mood_level" value="5">5. Stress</label>
            </div>
            <small class="helper">Tanggal otomatis (hari ini).</small>
          </div>

          <div class="form-row">
            <label for="mood-note">Catatan</label>
            <textarea id="mood-note" name="catatan" rows="3" placeholder="Tulis catatan singkat…"></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <button type="reset" class="btn btn-ghost">Reset</button>
          </div>
        </form>
      </article>

      <!-- Riwayat -->
      <article class="card ec-history-card">
        <div class="card-bar">
          <div class="bar-dot"></div>
          <h3 class="bar-title">Riwayat Mood</h3>
        </div>

        <div class="ec-toolbar" style="display:flex; align-items:center; gap:8px; width:100%; margin:12px 0;">
          <form action="home.php#top" method="get" style="display:flex; align-items:center; gap:8px; flex:1;">
            <input type="text" name="s" placeholder="Cari…" value="<?= htmlspecialchars($_GET['s'] ?? '') ?>"
              style="flex:1; min-width:0; height:36px;">
            <button type="submit" class="btn btn-primary">Cari</button>
          </form>
          <button type="button" id="btnEdit" class="btn btn-ghost" disabled>Edit</button>
          <button type="submit" form="del-form" id="btnDelete" class="btn btn-ghost" disabled
            onclick="return confirm('Hapus baris yang dipilih?');">Hapus</button>
        </div>

        <form id="edit-form" action="home.php#top" method="post"
          style="display:none; border:1px dashed #e5e7eb; padding:10px; border-radius:10px; margin:-4px 0 12px;">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="mood_id" id="edit-id">
          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <label> Mood:
              <select name="mood_level" id="edit-mood" required>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
              </select>
            </label>
            <label style="flex:1; min-width:240px;">
              Catatan:
              <input type="text" name="catatan" id="edit-note" placeholder="Ubah catatan…" style="width:100%;">
            </label>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <button type="button" id="edit-cancel" class="btn btn-ghost">Batal</button>
          </div>
        </form>

        <form id="del-form" action="home.php#top" method="post">
          <input type="hidden" name="action" value="delete">
          <div class="table-wrapper">
            <table class="ec-table" aria-label="Tabel Riwayat Mood">
              <thead>
                <tr>
                  <th style="width:42px;text-align:center;"><input type="checkbox" id="chkAll"></th>
                  <th>No</th>
                  <th>Tanggal</th>
                  <th>Mood</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody id="tbody-history">
                <?php if (empty($items)): ?>
                  <tr class="empty">
                    <td colspan="5">Belum ada data.</td>
                  </tr>
                <?php else:
                  foreach ($items as $i => $it): ?>
                    <tr>
                      <td style="text-align:center;">
                        <input type="checkbox" class="rowchk" name="delete_ids[]" value="<?= (int) $it['mood_id'] ?>"
                          data-mood="<?= (int) $it['mood_level'] ?>"
                          data-note="<?= htmlspecialchars($it['catatan'] ?? '', ENT_QUOTES) ?>">
                      </td>
                      <td><?= $i + 1 ?></td>
                      <td><?= htmlspecialchars($it['tanggal']) ?></td>
                      <td><?= (int) $it['mood_level'] ?></td>
                      <td><?= nl2br(htmlspecialchars($it['catatan'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </form>
      </article>
    </section>

    <!-- Statistik ringkas -->
    <section id="stats" class="ec-card">
      <h2 class="ec-section-title">Statistik &amp; Progress</h2>
      <div class="ec-tiles">
        <div class="ec-tile">
          <div class="k">Total Aktivitas</div>
          <div class="v"><?= count($items) ?></div>
        </div>
        <div class="ec-tile">
          <div class="k">Streak Harian</div>
          <div class="v" id="tileStreak">0 hari</div>
        </div>
        <div class="ec-tile">
          <div class="k">Rata-rata Mood</div>
          <div class="v">
            <?php
            if ($items) {
              $sum = 0;
              foreach ($items as $it)
                $sum += (int) $it['mood_level'];
              echo round($sum / count($items), 2) . '/5';
            } else
              echo '0/5';
            ?>
          </div>
        </div>
        <div class="ec-tile">
          <div class="k">Kuis Selesai</div>
          <div class="v" id="tileQuizDone">0</div>
        </div>
      </div>
    </section>
  </main>

  <script>
    // Jam & ucapan sederhana
    const clock = document.getElementById('greetClock'), word = document.getElementById('greetTimeWord');
    function tick() {
      const d = new Date();
      clock.textContent = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
      const h = d.getHours(); word.textContent = (h < 11) ? 'Pagi' : (h < 15) ? 'Siang' : (h < 18) ? 'Sore' : 'Malam';
    } tick(); setInterval(tick, 30000);
  </script>

  <script>
    (function () {
      const btnDelete = document.getElementById('btnDelete');
      const btnEdit = document.getElementById('btnEdit');
      const chkAll = document.getElementById('chkAll');
      const editForm = document.getElementById('edit-form');
      const editId = document.getElementById('edit-id');
      const editMood = document.getElementById('edit-mood');
      const editNote = document.getElementById('edit-note');
      const editCancel = document.getElementById('edit-cancel');

      function getChecked() { return Array.from(document.querySelectorAll('.rowchk:checked')) }
      function refresh() {
        const rows = getChecked();
        if (btnDelete) btnDelete.disabled = (rows.length === 0);
        if (btnEdit) btnEdit.disabled = (rows.length !== 1);
        if (rows.length !== 1 && editForm) editForm.style.display = 'none';
      }

      chkAll?.addEventListener('change', () => {
        document.querySelectorAll('.rowchk').forEach(c => c.checked = chkAll.checked);
        refresh();
      });
      document.addEventListener('change', e => {
        if (e.target?.classList.contains('rowchk')) { if (!e.target.checked && chkAll) chkAll.checked = false; refresh(); }
      });
      btnEdit?.addEventListener('click', () => {
        const r = getChecked(); if (r.length !== 1) return;
        editId.value = r[0].value; editMood.value = r[0].dataset.mood || ''; editNote.value = r[0].dataset.note || '';
        editForm.style.display = ''; setTimeout(() => editNote?.focus(), 0);
      });
      editCancel?.addEventListener('click', () => { editForm.style.display = 'none'; });
      refresh();
    })();
  </script>

  <?php if (!empty($_GET['updated']) || !empty($_GET['deleted'])): ?>
    <script>
      (function () {
        const url = new URL(location.href);
        const msg = url.searchParams.has('updated') ? 'Riwayat berhasil diperbarui.' : 'Riwayat terpilih berhasil dihapus.';
        alert(msg);
        ['deleted', 'saved', 'updated'].forEach(k => url.searchParams.delete(k));
        const qs = url.searchParams.toString();
        history.replaceState(null, '', url.pathname + (qs ? ('?' + qs) : '') + url.hash);
      })();
    </script>
  <?php endif; ?>
</body>

</html>