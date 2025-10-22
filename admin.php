<?php
// EmoCare-3-main/admin.php  — versi psikometri (tanpa jawaban benar/salah)
require_once __DIR__ . '/backend/config.php'; // start session + $mysqli + require_login()
require_login();

/* ===== Helper ===== */
function current_user_id()
{
  return (int) ($_SESSION['user']['pengguna_id'] ?? 0);
}
function h($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function is_admin(mysqli $db): bool
{
  $uid = current_user_id();
  if ($uid <= 0)
    return false;
  $stmt = $db->prepare("SELECT role FROM pengguna WHERE pengguna_id=? LIMIT 1");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $role = $stmt->get_result()->fetch_column();
  $stmt->close();
  return $role === 'admin';
}
if (!is_admin($mysqli)) {
  http_response_code(403);
  die('Forbidden: Admin only.');
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ===== Handle POST: Create Quiz (tanpa correct) ===== */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_quiz') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(400);
    die('Invalid CSRF token');
  }

  $question = trim($_POST['question_text'] ?? '');
  $category = in_array($_POST['category'] ?? '', ['self_esteem', 'social_anxiety'], true) ? $_POST['category'] : 'self_esteem';
  $make_active = (int) ($_POST['make_active'] ?? 0) === 1;

  $opts = [
    trim($_POST['option_1'] ?? ''),
    trim($_POST['option_2'] ?? ''),
    trim($_POST['option_3'] ?? ''),
    trim($_POST['option_4'] ?? '')
  ];

  $valid_opts = array_values(array_filter($opts, fn($x) => $x !== ''));
  if ($question === '' || count($valid_opts) < 2) {
    $flash = 'Lengkapi pertanyaan dan minimal 2 opsi.';
  } else {
    $mysqli->begin_transaction();
    try {
      $stmt = $mysqli->prepare("INSERT INTO quiz_questions (question_text, category, is_active) VALUES (?, ?, ?)");
      $act = $make_active ? 1 : 0;
      $stmt->bind_param('ssi', $question, $category, $act);
      $stmt->execute();
      $qid = $stmt->insert_id;
      $stmt->close();

      // Simpan opsi tanpa "is_correct" (tetapkan 0)
      $stmtOpt = $mysqli->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, 0)");
      foreach ($opts as $o) {
        if ($o === '')
          continue;
        $stmtOpt->bind_param('is', $qid, $o);
        $stmtOpt->execute();
      }
      $stmtOpt->close();

      $mysqli->commit();
      header('Location: admin.php?saved=1#quiz');
      exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      $flash = 'Gagal menyimpan kuis: ' . $e->getMessage();
    }
  }
}

/* ===== Toggle Active ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(400);
    die('Invalid CSRF');
  }
  $qid = (int) ($_POST['question_id'] ?? 0);
  if ($qid > 0) {
    $stmt = $mysqli->prepare("UPDATE quiz_questions SET is_active = IF(is_active=1,0,1) WHERE id=?");
    $stmt->bind_param('i', $qid);
    $stmt->execute();
    $stmt->close();
    header('Location: admin.php?updated=1#quiz');
    exit;
  }
}

/* ===== Delete ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_quiz') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(400);
    die('Invalid CSRF');
  }
  $qid = (int) ($_POST['question_id'] ?? 0);
  if ($qid > 0) {
    $mysqli->begin_transaction();
    try {
      $delOpt = $mysqli->prepare("DELETE FROM quiz_options WHERE question_id=?");
      $delOpt->bind_param('i', $qid);
      $delOpt->execute();
      $delOpt->close();
      $delQ = $mysqli->prepare("DELETE FROM quiz_questions WHERE id=?");
      $delQ->bind_param('i', $qid);
      $delQ->execute();
      $delQ->close();
      $mysqli->commit();
      header('Location: admin.php?deleted=1#quiz');
      exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      $flash = 'Gagal menghapus kuis: ' . $e->getMessage();
    }
  }
}

/* ===== Update/Edit (tanpa correct) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_quiz') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(400);
    die('Invalid CSRF');
  }

  $qid = (int) ($_POST['edit_id'] ?? 0);
  $qtext = trim($_POST['edit_question'] ?? '');
  $cat = in_array($_POST['edit_category'] ?? '', ['self_esteem', 'social_anxiety'], true) ? $_POST['edit_category'] : 'self_esteem';
  $opts = [
    trim($_POST['edit_opt1'] ?? ''),
    trim($_POST['edit_opt2'] ?? ''),
    trim($_POST['edit_opt3'] ?? ''),
    trim($_POST['edit_opt4'] ?? ''),
  ];

  if ($qid <= 0 || $qtext === '' || count(array_filter($opts)) < 2) {
    $flash = 'Lengkapi data edit (pertanyaan + minimal 2 opsi).';
  } else {
    $mysqli->begin_transaction();
    try {
      $stmt = $mysqli->prepare("UPDATE quiz_questions SET question_text=?, category=? WHERE id=?");
      $stmt->bind_param('ssi', $qtext, $cat, $qid);
      $stmt->execute();
      $stmt->close();

      $del = $mysqli->prepare("DELETE FROM quiz_options WHERE question_id=?");
      $del->bind_param('i', $qid);
      $del->execute();
      $del->close();

      $ins = $mysqli->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?,?,0)");
      foreach ($opts as $o) {
        if ($o === '')
          continue;
        $ins->bind_param('is', $qid, $o);
        $ins->execute();
      }
      $ins->close();

      $mysqli->commit();
      header('Location: admin.php?updated=1#quiz');
      exit;
    } catch (Throwable $e) {
      $mysqli->rollback();
      $flash = 'Gagal update kuis: ' . $e->getMessage();
    }
  }
}

/* ===== Load Data ===== */
$edit = null;
if (!empty($_GET['edit_id'])) {
  $eid = (int) $_GET['edit_id'];
  if ($eid > 0) {
    $stmt = $mysqli->prepare("SELECT id, question_text, category FROM quiz_questions WHERE id=?");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($edit) {
      $ops = $mysqli->prepare("SELECT option_text FROM quiz_options WHERE question_id=? ORDER BY id");
      $ops->bind_param('i', $eid);
      $ops->execute();
      $edit['options'] = $ops->get_result()->fetch_all(MYSQLI_ASSOC);
      $ops->close();
    }
  }
}

$admins = [];
if ($res = $mysqli->query("SELECT pengguna_id, nama, email, COALESCE(DATE_FORMAT(created_at,'%Y-%m-%d %H:%i'), '') AS created_at FROM pengguna WHERE role='admin' ORDER BY pengguna_id DESC")) {
  $admins = $res->fetch_all(MYSQLI_ASSOC);
}
$users = [];
if ($res2 = $mysqli->query("SELECT pengguna_id, nama, email, COALESCE(DATE_FORMAT(created_at,'%Y-%m-%d %H:%i'), '') AS created_at FROM pengguna WHERE role='user' ORDER BY pengguna_id DESC")) {
  $users = $res2->fetch_all(MYSQLI_ASSOC);
}
$quizzes = [];
if ($res3 = $mysqli->query("SELECT id, question_text, category, is_active, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS created_at FROM quiz_questions ORDER BY id DESC LIMIT 10")) {
  $quizzes = $res3->fetch_all(MYSQLI_ASSOC);
}

/* ===== View ===== */
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin • EmoCare</title>
  <link rel="stylesheet" href="css/admin.css">
  <style>
    .actions {
      display: flex;
      gap: 6px;
      align-items: center
    }

    .actions form {
      display: inline
    }

    .qtext {
      max-width: 70ch;
      overflow: hidden;
      text-overflow: ellipsis;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }
  </style>
</head>

<body data-page="admin">
  <div class="wrap">
    <div class="hdr">
      <h1>Admin Dashboard • EmoCare</h1>
      <span class="muted">Kelola kuis psikometri (tanpa jawaban benar/salah), profil admin, dan akun.</span>
      <div class="right">
        <a class="btn ghost" href="admin_profile.php">Profil Admin</a>
        <a class="btn" href="home.php">Kembali ke Home</a>
      </div>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
      <div class="ok">Kuis berhasil disimpan.</div><?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?>
      <div class="ok">Data berhasil diperbarui.</div><?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
      <div class="ok">Data berhasil dihapus.</div><?php endif; ?>
    <?php if ($flash): ?>
      <div class="warn"><?= h($flash) ?></div><?php endif; ?>

    <!-- BUAT KUIS -->
    <section class="card full" id="quiz">
      <div class="hd">
        <h2>Buat Kuis</h2>
      </div>
      <div class="bd">
        <form method="post" class="row">
          <input type="hidden" name="action" value="create_quiz" />
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>" />

          <label>
            <div class="muted small">Pertanyaan</div>
            <textarea name="question_text" class="inp" rows="3" required
              placeholder="Contoh: Saya sering merasa tegang tanpa alasan yang jelas."></textarea>
          </label>

          <div class="row row-2">
            <label>
              <div class="muted small">Opsi 1</div><input class="inp" name="option_1" required
                placeholder="Tidak Pernah" />
            </label>
            <label>
              <div class="muted small">Opsi 2</div><input class="inp" name="option_2" required placeholder="Jarang" />
            </label>
            <label>
              <div class="muted small">Opsi 3</div><input class="inp" name="option_3" placeholder="Sering" />
            </label>
            <label>
              <div class="muted small">Opsi 4</div><input class="inp" name="option_4" placeholder="Sangat Sering" />
            </label>
          </div>

          <div class="row row-2">
            <label>
              <div class="muted small">Kategori</div>
              <select class="inp" name="category" required>
                <option value="self_esteem">Self-Esteem</option>
                <option value="social_anxiety">Kecemasan Sosial</option>
              </select>
            </label>
            <label style="display:flex; align-items:flex-end; gap:8px;">
              <input type="checkbox" name="make_active" value="1" />
              <span class="muted small">Aktifkan setelah disimpan</span>
            </label>
          </div>

          <div><button class="btn" type="submit">Simpan Kuis</button></div>
        </form>

        <?php if ($edit): ?>
          <hr style="border:none; border-top:1px solid var(--bd); margin:16px 0;">
          <div class="muted" style="margin:8px 0">Edit Kuis (ID: <?= (int) $edit['id'] ?>)</div>
          <form method="post" class="row">
            <input type="hidden" name="action" value="update_quiz" />
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>" />
            <input type="hidden" name="edit_id" value="<?= (int) $edit['id'] ?>" />

            <label>
              <div class="muted small">Pertanyaan</div>
              <textarea name="edit_question" class="inp" rows="3" required><?= h($edit['question_text']) ?></textarea>
            </label>

            <?php
            $o1 = $o2 = $o3 = $o4 = '';
            if (!empty($edit['options'])) {
              $optsA = array_values($edit['options']);
              if (isset($optsA[0]))
                $o1 = $optsA[0]['option_text'];
              if (isset($optsA[1]))
                $o2 = $optsA[1]['option_text'];
              if (isset($optsA[2]))
                $o3 = $optsA[2]['option_text'];
              if (isset($optsA[3]))
                $o4 = $optsA[3]['option_text'];
            }
            ?>
            <div class="row row-2">
              <label>
                <div class="muted small">Opsi 1</div><input class="inp" name="edit_opt1" value="<?= h($o1) ?>" required />
              </label>
              <label>
                <div class="muted small">Opsi 2</div><input class="inp" name="edit_opt2" value="<?= h($o2) ?>" required />
              </label>
              <label>
                <div class="muted small">Opsi 3</div><input class="inp" name="edit_opt3" value="<?= h($o3) ?>" />
              </label>
              <label>
                <div class="muted small">Opsi 4</div><input class="inp" name="edit_opt4" value="<?= h($o4) ?>" />
              </label>
            </div>

            <label>
              <div class="muted small">Kategori</div>
              <select class="inp" name="edit_category" required>
                <option value="self_esteem" <?= ($edit['category'] ?? '') === 'self_esteem' ? 'selected' : '' ?>>Self-Esteem
                </option>
                <option value="social_anxiety" <?= ($edit['category'] ?? '') === 'social_anxiety' ? 'selected' : '' ?>>Kecemasan
                  Sosial</option>
              </select>
            </label>

            <div><button class="btn" type="submit">Simpan Perubahan</button></div>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <!-- KUIS TERBARU -->
    <?php if (!empty($quizzes)): ?>
      <section class="card full">
        <div class="hd">
          <h2>Kuis Terbaru</h2>
        </div>
        <div class="bd table-responsive">
          <table class="ec-table">
            <thead>
              <tr>
                <th style="width:60px">ID</th>
                <th>Pertanyaan</th>
                <th style="width:140px">Kategori</th>
                <th style="width:100px">Aktif?</th>
                <th style="width:160px">Dibuat</th>
                <th style="width:240px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($quizzes as $q): ?>
                <tr>
                  <td><?= (int) $q['id'] ?></td>
                  <td>
                    <div class="qtext"><?= h($q['question_text']) ?></div>
                  </td>
                  <td><?= h($q['category'] === 'self_esteem' ? 'Self-Esteem' : 'Kecemasan Sosial') ?></td>
                  <td><?= $q['is_active'] ? '<span class="tag active">Aktif</span>' : '<span class="tag">Nonaktif</span>' ?>
                  </td>
                  <td><?= h($q['created_at']) ?></td>
                  <td class="actions">
                    <form method="post">
                      <input type="hidden" name="action" value="toggle_active" />
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>" />
                      <input type="hidden" name="question_id" value="<?= (int) $q['id'] ?>" />
                      <button class="btn"><?= $q['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <a class="btn ghost" href="admin.php?edit_id=<?= (int) $q['id'] ?>#quiz">Edit</a>
                    <form method="post" onsubmit="return confirm('Hapus kuis ini?')">
                      <input type="hidden" name="action" value="delete_quiz" />
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>" />
                      <input type="hidden" name="question_id" value="<?= (int) $q['id'] ?>" />
                      <button class="btn ghost">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <!-- DAFTAR ADMIN -->
    <section class="card full">
      <div class="hd">
        <h2>Daftar Akun Admin</h2>
      </div>
      <div class="bd table-responsive">
        <?php if (empty($admins)): ?>
          <div class="muted">Belum ada akun admin lain.</div>
        <?php else: ?>
          <table class="ec-table">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Nama</th>
                <th>Email</th>
                <th style="width:160px">Dibuat</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $a): ?>
                <tr>
                  <td><?= (int) $a['pengguna_id'] ?></td>
                  <td><?= h($a['nama']) ?></td>
                  <td><?= h($a['email']) ?></td>
                  <td><?= h($a['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <!-- DAFTAR USER -->
    <section class="card full">
      <div class="hd">
        <h2>Daftar Akun User</h2>
      </div>
      <div class="bd table-responsive">
        <?php if (empty($users)): ?>
          <div class="muted">Belum ada akun user.</div>
        <?php else: ?>
          <table class="ec-table">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Nama</th>
                <th>Email</th>
                <th style="width:160px">Dibuat</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int) $u['pengguna_id'] ?></td>
                  <td><?= h($u['nama']) ?></td>
                  <td><?= h($u['email']) ?></td>
                  <td><?= h($u['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

  </div>

  <!-- Alert PRG gabungan -->
  <script>
    (function () {
      const url = new URL(location.href);
      const msg =
        url.searchParams.get('saved') ? 'Kuis berhasil disimpan.' :
          url.searchParams.get('updated') ? 'Data berhasil diperbarui.' :
            url.searchParams.get('deleted') ? 'Data berhasil dihapus.' : '';
      if (msg) alert(msg);
      ['saved', 'updated', 'deleted'].forEach(k => url.searchParams.delete(k));
      const qs = url.searchParams.toString();
      history.replaceState(null, '', url.pathname + (qs ? '?' + qs : '') + url.hash);
    })();
  </script>
</body>

</html>