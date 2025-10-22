<?php
require_once __DIR__.'/_init.php';

$PAGE_TITLE   = 'Kelola Kuis • EmoCare';
$PAGE_HEADING = 'Kelola Kuis';
$active       = 'quizzes';

$flash = '';

// ====== HANDLE POST DULU (sebelum _head.php) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); exit('Bad CSRF');
  }

  $act = $_POST['action'] ?? '';

  if ($act === 'create') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $icon = trim($_POST['icon'] ?? '✨');
    $desc = trim($_POST['description'] ?? '');
    $ord  = (int)($_POST['sort_order'] ?? 100);

    if ($name === '' || $slug === '') {
      $flash = 'Nama & slug wajib diisi.';
    } else {
      $cek = $mysqli->prepare("SELECT 1 FROM quiz_list WHERE slug=? LIMIT 1");
      $cek->bind_param('s',$slug); $cek->execute();
      $exists = (bool)$cek->get_result()->fetch_row(); $cek->close();

      if ($exists) {
        $flash = 'Slug sudah dipakai. Gunakan slug lain.';
      } else {
        $st=$mysqli->prepare("INSERT INTO quiz_list(name,slug,icon,description,sort_order,is_active) VALUES(?,?,?,?,?,1)");
        $st->bind_param('ssssi',$name,$slug,$icon,$desc,$ord);
        if(!$st->execute()) $flash='Gagal menyimpan: '.$st->error;
        $st->close();
        if(!$flash){ header('Location: quizzes.php?saved=1'); exit; }
      }
    }
  }

  if ($act === 'update') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $icon = trim($_POST['icon'] ?? '✨');
    $desc = trim($_POST['description'] ?? '');
    $ord  = (int)($_POST['sort_order'] ?? 100);
    $on   = !empty($_POST['is_active']) ? 1 : 0;

    if ($id<=0 || $name==='' || $slug==='') {
      $flash = 'Data tidak valid.';
    } else {
      $cek = $mysqli->prepare("SELECT 1 FROM quiz_list WHERE slug=? AND id<>? LIMIT 1");
      $cek->bind_param('si',$slug,$id); $cek->execute();
      $exists = (bool)$cek->get_result()->fetch_row(); $cek->close();

      if ($exists) {
        $flash = 'Slug sudah dipakai kuis lain.';
      } else {
        $st=$mysqli->prepare("UPDATE quiz_list SET name=?,slug=?,icon=?,description=?,sort_order=?,is_active=? WHERE id=?");
        $st->bind_param('ssssiii',$name,$slug,$icon,$desc,$ord,$on,$id);
        if(!$st->execute()) $flash='Gagal update: '.$st->error;
        $st->close();
        if(!$flash){ header('Location: quizzes.php?updated=1'); exit; }
      }
    }
  }

  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0){
      $g=$mysqli->prepare("SELECT slug FROM quiz_list WHERE id=?");
      $g->bind_param('i',$id); $g->execute();
      $slug = (string)($g->get_result()->fetch_column() ?? '');
      $g->close();

      if ($slug){
        $cnt=(int)$mysqli->query(
          "SELECT COUNT(*) FROM quiz_questions WHERE category='".$mysqli->real_escape_string($slug)."'"
        )->fetch_row()[0];

        if ($cnt>0) {
          $flash='Tidak bisa dihapus: masih ada pertanyaan di kuis ini.';
        } else {
          $d=$mysqli->prepare("DELETE FROM quiz_list WHERE id=?");
          $d->bind_param('i',$id); $d->execute(); $d->close();
          header('Location: quizzes.php?deleted=1'); exit;
        }
      }
    }
  }
}

// ====== SETELAH SEMUA POST/REDIRECT, BARU RENDER ======
include __DIR__.'/_head.php';

/* Data untuk tabel */
$rows=[];
$r=$mysqli->query("SELECT l.*,
 (SELECT COUNT(*) FROM quiz_questions q WHERE q.category=l.slug) total_q,
 (SELECT COUNT(*) FROM quiz_questions q WHERE q.category=l.slug AND q.is_active=1) active_q
 FROM quiz_list l
 ORDER BY sort_order ASC, id ASC");
if ($r) $rows=$r->fetch_all(MYSQLI_ASSOC);
?>

<style>
  /* —— form grid & card field —— */
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media (max-width:900px){ .form-grid{grid-template-columns:1fr} }
  .field{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:12px 14px;box-shadow:var(--shadow)}
  .field .label{font-size:.9rem;font-weight:700;color:#9c185b;margin-bottom:6px}
  .inp, textarea.inp{width:100%;border:1px solid var(--bd);border-radius:12px;padding:10px 12px;background:#fff}
  textarea.inp{resize:vertical;min-height:92px;line-height:1.5}

  /* —— tabel cantik —— */
  .table.pretty{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden}
  .table.pretty thead th{position:sticky;top:0;background:#fff;border-bottom:2px solid var(--bd);font-weight:800}
  .table.pretty th,.table.pretty td{padding:12px 14px;border-bottom:1px solid var(--bd);vertical-align:top}
  .table.pretty tbody tr:hover{background:#fffafc}

  /* —— badges & tombol —— */
  .pill{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;background:#ffe7f2;border:1px solid #ffcfe4;font-weight:800;cursor:pointer}
  .pill.ghost{background:#fff;border:1px solid var(--bd);color:#111}
  .stat{display:inline-flex;gap:6px;align-items:center;padding:6px 10px;border-radius:999px;border:1px solid var(--bd);background:#fff;font-weight:700}

  /* === Emocare Alerts === */
.alert{
  --bg:#fff; --bd:#f7cfe0; --ink:#111; --ring:rgba(236,72,153,.14);
  --accent:#ec4899; --accent-200:#ffd6e9;
  display:flex; align-items:flex-start; gap:12px;
  background:linear-gradient(180deg,#fff,#fff 70%,#fff8fc);
  border:1px solid var(--bd); color:var(--ink);
  border-radius:16px; padding:12px 14px;
  box-shadow:0 12px 28px var(--ring); position:relative;
  animation:alert-in .25s ease;
}
@keyframes alert-in{from{transform:translateY(-4px);opacity:0}to{transform:translateY(0);opacity:1}}

.alert .ico{
  width:34px;height:34px;flex:0 0 34px;border-radius:12px;
  display:grid;place-items:center;font-size:18px;
  background:#fff;border:1px solid var(--accent-200);
  box-shadow:0 8px 16px rgba(236,72,153,.10);
}
.alert .body{line-height:1.45}
.alert .title{font-weight:900;margin-bottom:2px}
.alert .msg{color:#6b7280}

/* tombol tutup */
.alert .close{
  margin-left:auto; border:0; background:#fff; cursor:pointer;
  width:28px;height:28px;border-radius:10px;
  border:1px solid var(--bd);
}
.alert .close:hover{filter:brightness(.98)}

/* Variants */
.alert.success{ --accent:#10b981; --accent-200:#ccf5e7; --ring:rgba(16,185,129,.14) }
.alert.warn   { --accent:#f59e0b; --accent-200:#ffe9c4; --ring:rgba(245,158,11,.14) }
.alert.error  { --accent:#ef4444; --accent-200:#ffd2d2; --ring:rgba(239,68,68,.14) }
.alert.info   { --accent:#3b82f6; --accent-200:#d7e5ff; --ring:rgba(59,130,246,.14) }

.alert.success .ico{border-color:#b7efde}
.alert.warn    .ico{border-color:#ffe0a3}
.alert.error   .ico{border-color:#ffc3c3}
.alert.info    .ico{border-color:#c5d9ff}

/* Toast stack (opsional) */
.alert-stack{
  position:fixed; right:24px; top:24px; z-index:70;
  display:flex; flex-direction:column; gap:10px;
}
@media (max-width:900px){ .alert-stack{ left:12px; right:12px } }

</style>




<?php
$alerts = [];
if (!empty($_GET['saved']))   $alerts[] = ['type'=>'success','title'=>'Berhasil','msg'=>'Kuis tersimpan.'];
if (!empty($_GET['updated'])) $alerts[] = ['type'=>'success','title'=>'Sukses','msg'=>'Data diperbarui.'];
if (!empty($_GET['deleted'])) $alerts[] = ['type'=>'info','title'=>'Terhapus','msg'=>'Kuis berhasil dihapus.'];
if (!empty($flash))           $alerts[] = ['type'=>'warn','title'=>'Perlu perhatian','msg'=>$flash];
?>

<?php if ($alerts): ?>
  <div class="alert-stack">
    <?php foreach ($alerts as $a): ?>
      <div class="alert <?= h($a['type']) ?>">
        <div class="ico">
          <?php if ($a['type']==='success'): ?>✅
          <?php elseif ($a['type']==='warn'): ?>⚠️
          <?php elseif ($a['type']==='error'): ?>⛔
          <?php else: ?>ℹ️<?php endif; ?>
        </div>
        <div class="body">
          <div class="title"><?= h($a['title']) ?></div>
          <div class="msg"><?= h($a['msg']) ?></div>
        </div>
        <button class="close" aria-label="Tutup" onclick="this.closest('.alert').remove()">✕</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ==== Tambah Kuis Baru ==== -->
<section class="card section" id="quizzes-create">
  <h2 class="card-title">Tambah Kuis Baru</h2>
  <form method="post" class="row" style="margin-top:8px">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="create">

    <div class="form-grid">
      <div class="field">
        <div class="label">Nama</div>
        <input class="inp" name="name" placeholder="Refleksi Diri" required>
      </div>
      <div class="field">
        <div class="label">Slug (tanpa spasi)</div>
        <input class="inp" name="slug" placeholder="self_reflection" required>
      </div>
      <div class="field">
        <div class="label">Icon/Emoji</div>
        <input class="inp" name="icon" value="💗" placeholder="💗">
      </div>
      <div class="field">
        <div class="label">Urutan</div>
        <input class="inp" type="number" name="sort_order" value="100">
      </div>
      <div class="field" style="grid-column:1/-1">
        <div class="label">Deskripsi (opsional)</div>
        <input class="inp" name="description" placeholder="Kuis untuk mengevaluasi diri.">
      </div>
    </div>

    <div class="form-actions">
      <button class="btn pink">Simpan</button>
    </div>
  </form>
</section>

<section class="card section" id="quizzes-list">
  <div class="section-head">
    <h2 class="card-title" style="margin:0">Daftar Kuis</h2>
    <div class="count-badge">🔢 <?= count($rows) ?> item</div>
  </div>

  <table class="quiz-table">
    <thead>
      <tr>
        <th style="width:72px">Icon</th>
        <th>Nama</th>
        <th style="width:160px">Slug</th>
        <th>Deskripsi</th>
        <th style="width:90px">Urutan</th>
        <th style="width:90px">Aktif?</th>
        <th style="width:110px">Pertanyaan</th>
        <th style="width:260px">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $c): ?>
      <tr>
        <td><span class="badge-round"><?= h($c['icon']) ?></span></td>
        <td><strong><?= h($c['name']) ?></strong></td>
        <td><code><?= h($c['slug']) ?></code></td>
        <td><?= h($c['description'] ?? '') ?></td>
        <td><span class="badge-round"><?= (int)$c['sort_order'] ?></span></td>
        <td><?= $c['is_active'] ? '<span class="chip ok">Aktif</span>' : '<span class="chip muted">Nonaktif</span>' ?></td>
        <td><span class="chip"><?= (int)$c['active_q'] ?>/<?= (int)$c['total_q'] ?></span></td>
        <td class="actions">
          <div class="grp">
            <!-- Edit (collapsible, tetap satu tinggi dengan tombol lain) -->
            <details>
              <summary class="btn sm ghost">Edit</summary>
              <form method="post" class="row" style="margin-top:10px;min-width:480px">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

                <div class="row row-2">
                  <label style="display:block;flex:1">
                    <div class="small muted">Nama</div>
                    <input class="inp" name="name" value="<?= h($c['name']) ?>" required>
                  </label>
                  <label style="display:block;flex:1">
                    <div class="small muted">Slug</div>
                    <input class="inp" name="slug" value="<?= h($c['slug']) ?>" required>
                  </label>
                  <label style="display:block;flex:1">
                    <div class="small muted">Icon/Emoji</div>
                    <input class="inp" name="icon" value="<?= h($c['icon']) ?>">
                  </label>
                  <label style="display:block;flex:1">
                    <div class="small muted">Urutan</div>
                    <input class="inp" type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>">
                  </label>
                </div>

                <label style="display:block;margin-top:8px">
                  <div class="small muted">Deskripsi</div>
                  <input class="inp" name="description" value="<?= h($c['description']) ?>">
                </label>

                <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
                  <input type="checkbox" name="is_active" value="1" <?= $c['is_active']?'checked':''; ?>>
                  Aktif
                </label>

                <div style="margin-top:10px;display:flex;gap:10px;justify-content:flex-end">
                  <button class="btn pink">Simpan</button>
                </div>
              </form>
            </details>

            <!-- Hapus -->
            <form method="post" class="hide-on-edit" onsubmit="return confirm('Hapus kuis ini?')" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn sm ghost">Hapus</button>
            </form>

           <!-- Kelola Soal -->
            <a class="btn sm pink js-kelola-soal" href="quiz_manage.php?cat=<?= urlencode($c['slug']) ?>">Kelola Soal</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
  // Auto-dismiss lebih lama (±8s) + pause saat hover
  document.querySelectorAll('.alert-stack .alert').forEach((el, i) => {
    let timer;

    const hide = () => {
      el.style.transition = 'opacity .25s ease, transform .25s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 260);
    };

    const start = () => {
      timer = setTimeout(hide, 8000 + i * 400); // 8 detik + sedikit jeda antar toast
    };

    const stop = () => {
      if (timer) { clearTimeout(timer); timer = null; }
    };

    el.addEventListener('mouseenter', stop);
    el.addEventListener('mouseleave', start);

    start(); // mulai hitung waktu
  });
</script>


<?php include __DIR__.'/_foot.php'; ?>
