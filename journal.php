<?php
require_once __DIR__ . '/backend/config.php';
require_login();

/* --- identitas --- */
$uid  = (int) ($_SESSION['user']['pengguna_id'] ?? 0);
$nama = $_SESSION['user']['nama'] ?? 'Pengguna';

$flash = '';

/* ===== Handlers ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['j_action'] ?? '';

  if ($act === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tanggal = preg_replace('~[^0-9-]~', '', $_POST['tanggal'] ?? date('Y-m-d'));
    $waktu = preg_replace('~[^0-9:]~', '', $_POST['waktu'] ?? '');
    $mood = (int) ($_POST['mood_level'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');

    if ($title === '' || $content === '') {
      $flash = 'Judul dan isi jurnal wajib diisi.';
    } else {
      if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $tanggal)) $tanggal = date('Y-m-d');
      if ($waktu !== '' && !preg_match('~^\d{2}:\d{2}$~', $waktu)) $waktu = null;
      if ($mood < 1 || $mood > 5) $mood = null;

      $sql = "INSERT INTO journal_entries(pengguna_id,tanggal,waktu,title,content,mood_level,tags,created_at,updated_at)
              VALUES(?,?,?,?,?,?,?,NOW(),NOW())";
      $st = $mysqli->prepare($sql);
      $st->bind_param('issssis', $uid, $tanggal, $waktu, $title, $content, $mood, $tags);
      if ($st->execute()) { header('Location: journal.php?saved=1'); exit; }
      else $flash = 'Gagal menyimpan: '.$st->error;
      $st->close();
    }
  }

  if ($act === 'delete') {
    $ids = array_values(array_filter(array_map('intval', $_POST['del_ids'] ?? [])));
    if ($ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $types = 'i' . str_repeat('i', count($ids));
      $params = array_merge([$uid], $ids);
      $st = $mysqli->prepare("DELETE FROM journal_entries WHERE pengguna_id=? AND id IN ($in)");
      $st->bind_param($types, ...$params);
      $st->execute(); $st->close();
      header('Location: journal.php?deleted=1'); exit;
    } else $flash = 'Pilih minimal satu entri untuk dihapus.';
  }

  if ($act === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tanggal = preg_replace('~[^0-9-]~', '', $_POST['tanggal'] ?? date('Y-m-d'));
    $waktu = preg_replace('~[^0-9:]~', '', $_POST['waktu'] ?? '');
    $mood = (int) ($_POST['mood_level'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');

    if ($id <= 0) $flash = 'Data tidak valid.';
    elseif ($title === '' || $content === '') $flash = 'Judul dan isi jurnal wajib diisi.';
    else {
      if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $tanggal)) $tanggal = date('Y-m-d');
      if ($waktu !== '' && !preg_match('~^\d{2}:\d{2}$~', $waktu)) $waktu = null;
      if ($mood < 1 || $mood > 5) $mood = null;

      $sql = "UPDATE journal_entries SET tanggal=?, waktu=?, title=?, content=?, mood_level=?, tags=?, updated_at=NOW()
              WHERE id=? AND pengguna_id=?";
      $st = $mysqli->prepare($sql);
      $st->bind_param('ssssissi', $tanggal, $waktu, $title, $content, $mood, $tags, $id, $uid);
      $st->execute(); $st->close();
      header('Location: journal.php?updated=1'); exit;
    }
  }
}

/* ===== Filters & Query list ===== */
$q = trim($_GET['q'] ?? '');
$date_from = preg_replace('~[^0-9-]~', '', $_GET['from'] ?? '');
$date_to   = preg_replace('~[^0-9-]~', '', $_GET['to'] ?? '');

$conds = ["pengguna_id=?"];
$types = 'i';
$args  = [$uid];

if ($q !== '') {
  $conds[] = "(title LIKE ? OR content LIKE ? OR tags LIKE ?)";
  $types  .= 'sss';
  $like = "%$q%";
  array_push($args, $like, $like, $like);
}
if ($date_from !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $date_from)) {
  $conds[] = "tanggal >= ?";
  $types  .= 's'; $args[] = $date_from;
}
if ($date_to !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $date_to)) {
  $conds[] = "tanggal <= ?";
  $types  .= 's'; $args[] = $date_to;
}

$sql = "SELECT id,tanggal,COALESCE(waktu,'') AS waktu,title,content,COALESCE(mood_level,'') AS mood_level,COALESCE(tags,'') AS tags
        FROM journal_entries
        WHERE ".implode(' AND ', $conds)."
        ORDER BY tanggal DESC, COALESCE(waktu,'23:59') DESC, id DESC
        LIMIT 200"; // batasi tampilan awal
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$args);
$st->execute();
$list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Jurnal Digital • EmoCare</title>
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
  <style>
    .jr-wrap{display:grid;gap:16px}
    .jr-form{
      display:grid; gap:10px; grid-template-columns:1fr 140px;
      background:linear-gradient(180deg,#fff,#fff9fd);
      border:1px solid #f8cde0; border-radius:18px; padding:14px;
    }
    @media (max-width:720px){ .jr-form{ grid-template-columns:1fr; } }
    .jr-field{display:flex;flex-direction:column;gap:6px}
    .jr-label{font-weight:800;color:#9d174d}
    .jr-input, .jr-text{
      width:100%; border:1px solid #f2b9d3; border-radius:12px; padding:10px 12px; min-height:38px;
      outline:none; transition:border-color .15s, box-shadow .15s;
      background:#fff;
    }
    .jr-input:focus,.jr-text:focus{border-color:#f472b6; box-shadow:0 0 0 3px rgba(244,114,182,.18)}
    .jr-text{min-height:120px; resize:vertical}
    .jr-row{grid-column:1/-1; display:flex; gap:10px; flex-wrap:wrap}
    .jr-tags{font-size:.9rem; color:#b91c65}
    .jr-toolbar{display:flex; gap:8px; align-items:center; justify-content:space-between}
    .jr-search{display:flex; gap:8px; align-items:center; flex-wrap:wrap}
    .jr-table .ec-table td{vertical-align:top}
    .mood-badge{display:inline-block; padding:4px 8px; border-radius:999px; background:#ffe6f3; border:1px solid #f7c3d8; color:#9d174d; font-weight:800; font-size:.8rem}
    .btn{background:#f472b6;color:#fff;border:0;border-radius:14px;padding:10px 14px;font-weight:800;cursor:pointer;box-shadow:0 6px 14px rgba(236,72,153,.12);transition:box-shadow .2s, transform .2s}
    .btn:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(236,72,153,.28),0 0 0 2px rgba(236,72,153,.18)}
    .btn.ghost{background:#fff;color:#be185d;border:1px solid #f9a8d4}
  </style>
</head>
<body class="ec-body">
  <header class="ec-nav">
    <div class="ec-nav-inner">
      <div class="ec-brand"><span class="ec-brand-name" style="font-weight:500;color:#6b7280;">EmoCare</span></div>
      <nav class="ec-nav-links">
        <a href="home.php#top">Beranda</a>
        <a href="self_care.php">Self-Care</a>
        <a href="journal.php" class="active">Jurnal</a>
        <a href="home.php#stats">Statistik</a>
      </nav>
      <form action="backend/auth_logout.php" method="post" style="margin:0"><button class="ec-btn-outline">Keluar</button></form>
    </div>
  </header>

  <main class="ec-container jr-wrap">
    <section class="ec-card">
      <h2 class="ec-section-title">Tulis Jurnal</h2>
      <?php if (!empty($_GET['saved'])): ?><p class="form-success">Jurnal tersimpan!</p>
      <?php elseif (!empty($_GET['updated'])): ?><p class="form-success">Jurnal diperbarui.</p>
      <?php elseif (!empty($_GET['deleted'])): ?><p class="form-success">Jurnal terpilih dihapus.</p>
      <?php elseif ($flash): ?><p class="form-error"><?= htmlspecialchars($flash) ?></p><?php endif; ?>

      <form class="jr-form" method="post" action="journal.php">
        <input type="hidden" name="j_action" value="create">
        <div class="jr-field">
          <label class="jr-label">Judul*</label>
          <input class="jr-input" type="text" name="title" placeholder="Contoh: Syukur kecil hari ini" required>
        </div>
        <div class="jr-field">
          <label class="jr-label">Tanggal</label>
          <input class="jr-input" type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="jr-field" style="grid-column:1/-1">
          <label class="jr-label">Isi Jurnal*</label>
          <textarea class="jr-text" name="content" placeholder="Tulis refleksi harianmu di sini..." required></textarea>
        </div>
        <div class="jr-row">
          <label class="jr-label" style="display:flex;align-items:center;gap:8px">
            Jam:
            <input class="jr-input" type="time" name="waktu" style="min-width:130px">
          </label>
          <label class="jr-label" style="display:flex;align-items:center;gap:8px">
            Mood:
            <select class="jr-input" name="mood_level" style="min-width:130px">
              <option value="">—</option>
              <option value="1">1. Senang Banget</option>
              <option value="2">2. Senang</option>
              <option value="3">3. Biasa</option>
              <option value="4">4. Cemas</option>
              <option value="5">5. Stress</option>
            </select>
          </label>
          <label class="jr-label" style="flex:1; min-width:200px">
            Tag (opsional)
            <input class="jr-input" type="text" name="tags" placeholder="pisah dengan koma, mis: syukur, refleksi">
          </label>
          <div style="margin-left:auto">
            <button type="reset" class="btn ghost">Reset</button>
            <button type="submit" class="btn">Simpan</button>
          </div>
        </div>
      </form>
    </section>

    <section class="ec-card jr-table">
      <div class="jr-toolbar">
        <h2 class="ec-section-title" style="margin:0">Riwayat Jurnal</h2>
        <form class="jr-search" method="get" action="journal.php">
          <input class="jr-input" type="text" name="q" placeholder="Cari judul/isi/tag…" value="<?= htmlspecialchars($q) ?>" style="min-width:200px">
          <input class="jr-input" type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">
          <span> s/d </span>
          <input class="jr-input" type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">
          <button class="btn ghost" type="submit">Filter</button>
        </form>
      </div>

      <form id="del-form" method="post" action="journal.php" onsubmit="return confirm('Hapus entri terpilih?');">
        <input type="hidden" name="j_action" value="delete">
        <div class="table-wrapper">
          <table class="ec-table" aria-label="Tabel Jurnal">
            <thead>
              <tr>
                <th style="width:42px;text-align:center"><input type="checkbox" id="chkAll"></th>
                <th style="width:120px">Tanggal</th>
                <th>Judul & Isi</th>
                <th style="width:120px">Mood</th>
                <th style="width:220px">Tag</th>
                <th style="width:80px">Aksi</th>
              </tr>
            </thead>
            <tbody id="tb">
              <?php if (empty($list)): ?>
                <tr class="empty"><td colspan="6">Belum ada jurnal.</td></tr>
              <?php else: foreach ($list as $row): ?>
                <tr data-id="<?= (int)$row['id'] ?>"
                    data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                    data-content="<?= htmlspecialchars($row['content'], ENT_QUOTES) ?>"
                    data-tanggal="<?= htmlspecialchars($row['tanggal']) ?>"
                    data-waktu="<?= htmlspecialchars($row['waktu']) ?>"
                    data-mood="<?= htmlspecialchars($row['mood_level']) ?>"
                    data-tags="<?= htmlspecialchars($row['tags'], ENT_QUOTES) ?>">
                  <td style="text-align:center"><input type="checkbox" class="rchk" name="del_ids[]" value="<?= (int)$row['id'] ?>"></td>
                  <td>
                    <?= htmlspecialchars($row['tanggal']) ?><br>
                    <small style="color:#6b7280"><?= $row['waktu'] ? htmlspecialchars(substr($row['waktu'],0,5)) : '—' ?></small>
                  </td>
                  <td>
                    <div style="font-weight:900;color:#0f172a"><?= htmlspecialchars($row['title']) ?></div>
                    <div style="color:#6b7280;white-space:pre-line;max-height:8.6em;overflow:hidden"><?= htmlspecialchars($row['content']) ?></div>
                  </td>
                  <td><?= $row['mood_level'] !== '' ? '<span class="mood-badge">'.(int)$row['mood_level'].'</span>' : '—' ?></td>
                  <td class="jr-tags"><?= $row['tags'] ? htmlspecialchars($row['tags']) : '—' ?></td>
                  <td><button type="button" class="btn ghost btn-edit" style="padding:6px 10px">Edit</button></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:8px">
          <button type="submit" class="btn ghost" id="btnDelete" disabled>Hapus Terpilih</button>
        </div>
      </form>

      <!-- editor inline -->
      <form id="edit-form" class="jr-form" method="post" action="journal.php" style="display:none; margin-top:12px">
        <input type="hidden" name="j_action" value="edit">
        <input type="hidden" name="id" id="ef-id">
        <div class="jr-field">
          <label class="jr-label">Judul*</label>
          <input class="jr-input" type="text" name="title" id="ef-title" required>
        </div>
        <div class="jr-field">
          <label class="jr-label">Tanggal</label>
          <input class="jr-input" type="date" name="tanggal" id="ef-tanggal" required>
        </div>
        <div class="jr-field" style="grid-column:1/-1">
          <label class="jr-label">Isi Jurnal*</label>
          <textarea class="jr-text" name="content" id="ef-content" required></textarea>
        </div>
        <div class="jr-row">
          <label class="jr-label" style="display:flex;align-items:center;gap:8px">
            Jam:
            <input class="jr-input" type="time" name="waktu" id="ef-waktu" style="min-width:130px">
          </label>
          <label class="jr-label" style="display:flex;align-items:center;gap:8px">
            Mood:
            <select class="jr-input" name="mood_level" id="ef-mood" style="min-width:130px">
              <option value="">—</option>
              <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option>
            </select>
          </label>
          <label class="jr-label" style="flex:1; min-width:200px">
            Tag
            <input class="jr-input" type="text" name="tags" id="ef-tags" placeholder="pisah dengan koma">
          </label>
          <div style="margin-left:auto">
            <button type="button" class="btn ghost" id="ef-cancel">Batal</button>
            <button type="submit" class="btn">Simpan</button>
          </div>
        </div>
      </form>
    </section>
  </main>

  <script>
    // checkbox bulk delete
    const chkAll = document.getElementById('chkAll');
    const btnDelete = document.getElementById('btnDelete');
    function refreshBulk(){
      const any = document.querySelectorAll('.rchk:checked').length>0;
      if (btnDelete) btnDelete.disabled = !any;
    }
    chkAll?.addEventListener('change', ()=>{
      document.querySelectorAll('.rchk').forEach(c=>c.checked = chkAll.checked);
      refreshBulk();
    });
    document.addEventListener('change', e=>{
      if (e.target?.classList?.contains('rchk')) {
        if (!e.target.checked && chkAll) chkAll.checked = false;
        refreshBulk();
      }
    });

    // edit inline
    const ef = {
      wrap: document.getElementById('edit-form'),
      id:   document.getElementById('ef-id'),
      t:    document.getElementById('ef-title'),
      c:    document.getElementById('ef-content'),
      d:    document.getElementById('ef-tanggal'),
      w:    document.getElementById('ef-waktu'),
      m:    document.getElementById('ef-mood'),
      g:    document.getElementById('ef-tags'),
      cancel: document.getElementById('ef-cancel')
    };
    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('.btn-edit');
      if (!btn) return;
      const tr = btn.closest('tr');
      ef.id.value = tr.dataset.id;
      ef.t.value  = tr.dataset.title || '';
      ef.c.value  = tr.dataset.content || '';
      ef.d.value  = tr.dataset.tanggal || '<?= date('Y-m-d') ?>';
      ef.w.value  = tr.dataset.waktu || '';
      ef.m.value  = tr.dataset.mood || '';
      ef.g.value  = tr.dataset.tags || '';
      ef.wrap.style.display = '';
      ef.t.scrollIntoView({behavior:'smooth', block:'center'});
      setTimeout(()=>ef.t.focus(), 50);
    });
    ef.cancel?.addEventListener('click', ()=>{ ef.wrap.style.display='none'; });
    refreshBulk();
  </script>
</body>
</html>
