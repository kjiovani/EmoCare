<?php
// admin/quiz_manage.php — halaman generik
require_once __DIR__ . '/_init.php';

$slug = trim($_GET['cat'] ?? '');
if ($slug === '') {
    http_response_code(404);
    exit('Kuis tidak ditemukan');
}

// Ambil meta kuis
$st = $mysqli->prepare("SELECT id,name,slug,icon FROM quiz_list WHERE slug=? LIMIT 1");
$st->bind_param('s', $slug);
$st->execute();
$quiz = $st->get_result()->fetch_assoc();
$st->close();

if (!$quiz) {
    http_response_code(404);
    exit('Kuis tidak ditemukan');
}

$CATEGORY = $quiz['slug'];
$PAGE_TITLE = 'Kelola Kuis • ' . $quiz['name'];
$PAGE_HEADING = 'Kelola Kuis • ' . $quiz['name'];
$active = $CATEGORY;

$flash = '';

/* -------------------------
   Helper upload (opsional)
--------------------------*/
// upload helper (opsional gambar pertanyaan)
function qm_handle_upload(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
        return null;
    if ($file['error'] !== UPLOAD_ERR_OK)
        return null;
    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($ok[$mime]))
        return null;
    @mkdir(__DIR__ . '/../uploads/quiz', 0775, true);
    $name = uniqid('q_', true) . '.' . $ok[$mime];
    $dest = __DIR__ . '/../uploads/quiz/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest))
        return null;
    return 'uploads/quiz/' . $name; // path relatif dari /admin
}


/* -------------------------
   1) HANDLE POST DULU
--------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad CSRF');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $qtext = trim($_POST['question_text'] ?? '');
        $opts = [
            trim($_POST['option_1'] ?? ''),
            trim($_POST['option_2'] ?? ''),
            trim($_POST['option_3'] ?? ''),
            trim($_POST['option_4'] ?? ''),
        ];
        $valid = array_values(array_filter($opts, fn($x) => $x !== ''));

        if ($qtext === '' || count($valid) < 2) {
            $flash = 'Lengkapi pertanyaan dan minimal 2 opsi.';
        } else {
            $img = qm_handle_upload($_FILES['question_image'] ?? null);
            $act = !empty($_POST['make_active']) ? 1 : 0;

            // simpan pertanyaan
            $stmt = $mysqli->prepare("INSERT INTO quiz_questions (question_text, category, image_path, is_active) VALUES (?,?,?,?)");
            $stmt->bind_param('sssi', $qtext, $CATEGORY, $img, $act);
            $stmt->execute();
            $qid = (int) $stmt->insert_id;
            $stmt->close();

            // simpan opsi (perhatikan option_order)
            $ins = $mysqli->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct, option_order) VALUES (?,?,0,?)");
            $order = 1;
            foreach ($valid as $op) {
                $ins->bind_param('isi', $qid, $op, $order);
                $ins->execute();
                $order++;
            }
            $ins->close();

            header('Location: quiz_manage.php?cat=' . urlencode($CATEGORY) . '&saved=1');
            exit;
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE quiz_questions SET is_active = IF(is_active=1,0,1) WHERE id=? AND category=?");
        $stmt->bind_param('is', $id, $CATEGORY);
        $stmt->execute();
        $stmt->close();

        header('Location: quiz_manage.php?cat=' . urlencode($CATEGORY) . '&updated=1');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $mysqli->begin_transaction();
        $del = $mysqli->prepare("DELETE FROM quiz_options   WHERE question_id=?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        $del2 = $mysqli->prepare("DELETE FROM quiz_questions WHERE id=? AND category=?");
        $del2->bind_param('is', $id, $CATEGORY);
        $del2->execute();
        $del2->close();
        $mysqli->commit();

        header('Location: quiz_manage.php?cat=' . urlencode($CATEGORY) . '&deleted=1');
        exit;
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $text = trim($_POST['question_text'] ?? '');
        $on = !empty($_POST['is_active']) ? 1 : 0;

        // opsi (boleh kosong; minimal 2 non-kosong akan divalidasi)
        $opt1 = trim($_POST['option_1'] ?? '');
        $opt2 = trim($_POST['option_2'] ?? '');
        $opt3 = trim($_POST['option_3'] ?? '');
        $opt4 = trim($_POST['option_4'] ?? '');
        $opts = array_values(array_filter([$opt1, $opt2, $opt3, $opt4], fn($x) => $x !== ''));

        if ($id <= 0 || $text === '' || count($opts) < 2) {
            $flash = 'Lengkapi pertanyaan dan minimal 2 opsi.';
        } else {
            // ambil image_path lama
            $oldPath = '';
            $s = $mysqli->prepare("SELECT image_path FROM quiz_questions WHERE id=? AND category=?");
            $s->bind_param('is', $id, $CATEGORY);
            $s->execute();
            $oldPath = (string) ($s->get_result()->fetch_column() ?? '');
            $s->close();

            // cek jika user minta hapus gambar
            $removeImg = !empty($_POST['remove_image']);

            // jika upload gambar baru
            $newPath = qm_handle_upload($_FILES['question_image'] ?? null);

            // tentukan path final
            $finalPath = $oldPath;
            if ($removeImg)
                $finalPath = null;
            if ($newPath)
                $finalPath = $newPath;

            // update pertanyaan
            $u = $mysqli->prepare("UPDATE quiz_questions SET question_text=?, is_active=?, image_path=? WHERE id=? AND category=?");
            // binding jenis 's i s i s' → null harus pakai set_null
            if ($finalPath === null) {
                $null = null;
                $u->bind_param('sisis', $text, $on, $null, $id, $CATEGORY);
            } else {
                $u->bind_param('sisis', $text, $on, $finalPath, $id, $CATEGORY);
            }
            $u->execute();
            $u->close();

            // replace semua opsi (sederhana & aman)
            $mysqli->begin_transaction();
            $d = $mysqli->prepare("DELETE FROM quiz_options WHERE question_id=?");
            $d->bind_param('i', $id);
            $d->execute();
            $d->close();

            $ins = $mysqli->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct, option_order) VALUES (?,?,0,?)");
            $ord = 1;
            foreach ($opts as $op) {
                $ins->bind_param('isi', $id, $op, $ord);
                $ins->execute();
                $ord++;
            }
            $ins->close();
            $mysqli->commit();

            // hapus file lama jika diganti/di-remove
            if (($removeImg || $newPath) && $oldPath) {
                $abs = realpath(__DIR__ . '/../' . $oldPath);
                if ($abs && is_file($abs))
                    @unlink($abs);
            }

            header('Location: quiz_manage.php?cat=' . urlencode($CATEGORY) . '&updated=1');
            exit;
        }
    }


}

/* -------------------------
   2) AMBIL DATA UNTUK VIEW
--------------------------*/
$res = $mysqli->prepare("
  SELECT id, question_text, image_path, is_active,
         DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') AS created_at
  FROM quiz_questions
  WHERE category=?
  ORDER BY id DESC
");
$res->bind_param('s', $CATEGORY);
$res->execute();
$items = $res->get_result()->fetch_all(MYSQLI_ASSOC);
$res->close();

// Kumpulkan id pertanyaan
$qids = array_column($items, 'id');
$optsByQ = [];
if ($qids) {
    $in = implode(',', array_map('intval', $qids));
    $qr = $mysqli->query("SELECT question_id, option_text, option_order
                        FROM quiz_options
                        WHERE question_id IN ($in)
                        ORDER BY question_id ASC, option_order ASC");
    while ($row = $qr->fetch_assoc()) {
        $qid = (int) $row['question_id'];
        if (!isset($optsByQ[$qid]))
            $optsByQ[$qid] = [];
        $optsByQ[$qid][] = $row['option_text'];
    }
}


/* -------------------------
   3) BARU MULAI OUTPUT HTML
--------------------------*/
include __DIR__ . '/_head.php';
?>

<style>
    /* —— form grid & card field —— */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px
    }

    @media (max-width:900px) {
        .form-grid {
            grid-template-columns: 1fr
        }
    }

    .field {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 14px;
        padding: 12px 14px;
        box-shadow: var(--shadow)
    }

    .field .label {
        font-size: .9rem;
        font-weight: 700;
        color: #9c185b;
        margin-bottom: 6px
    }

    .inp,
    textarea.inp {
        width: 100%;
        border: 1px solid var(--bd);
        border-radius: 12px;
        padding: 10px 12px;
        background: #fff
    }

    textarea.inp {
        resize: vertical;
        min-height: 92px;
        line-height: 1.5
    }

    /* —— dropzone drag & drop —— */
    .dropzone {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border: 2px dashed #f4cadd;
        background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
        border-radius: 14px;
        padding: 18px;
        color: #6b7280;
        transition: .12s ease
    }

    .dropzone.drag {
        background: #fff;
        border-color: #ec4899;
        box-shadow: 0 0 0 4px rgba(236, 72, 153, .12)
    }

    .dz-preview {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-top: 10px
    }

    .dz-preview img {
        max-width: 120px;
        border-radius: 12px;
        border: 1px solid var(--bd)
    }

    .dz-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px
    }

    /* —— tabel cakep —— */
    .table.pretty {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden
    }

    .table.pretty thead th {
        position: sticky;
        top: 0;
        background: #fff;
        border-bottom: 2px solid var(--bd);
        font-weight: 800
    }

    .table.pretty th,
    .table.pretty td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--bd);
        vertical-align: top
    }

    .table.pretty tbody tr:hover {
        background: #fffafc
    }

    .pill {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        background: #ffe7f2;
        border: 1px solid #ffcfe4;
        font-weight: 800
    }

    .pill.ghost {
        background: #fff;
        border: 1px solid var(--bd);
        color: #111
    }

    /* Semua tombol di kolom Aksi segaris */
    .table .actions {
        white-space: nowrap;
    }

    /* cegah line-break */
    .table .actions .row-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
        /* jangan turun ke bawah */
    }

    /* Samakan tinggi & gaya "pill" pada semua tombol/summary */
    .pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        font-weight: 800;
        border: 1px solid var(--bd);
        background: #ffe7f2;
    }

    .pill.ghost {
        background: #fff;
        border: 1px solid var(--bd);
        color: #111
    }

    /* <details> -> <summary> biar tampak seperti tombol */
    .editbox>summary {
        list-style: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        border: 1px solid var(--bd);
        background: #fff;
        font-weight: 800;
    }

    .editbox>summary::-webkit-details-marker {
        display: none
    }

    /* Saat editbox dibuka, panelnya tetap rapi */
    .editbox[open] {
        padding: 8px 10px;
        border: 1px solid var(--bd);
        border-radius: 12px;
        background: #fff;
    }

    .editbox form {
        margin-top: 8px
    }
</style>

<style>
    /* —— EDIT PANEL —— */
    .edit {
        display: block
    }

    .edit>summary {
        list-style: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid var(--bd);
        background: #fff;
        font-weight: 800;
        box-shadow: 0 6px 14px rgba(236, 72, 153, .06);
    }

    .edit>summary::-webkit-details-marker {
        display: none
    }

    .edit[open]>summary {
        background: #fff0f7;
        border-color: #ffcfe4
    }

    .edit-card {
        margin-top: 12px;
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 16px;
        padding: 16px;
        box-shadow: var(--shadow);
    }

    .edit-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px
    }

    .edit-title {
        font-weight: 900;
        color: #c21762
    }

    .edit-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 14px
    }

    @media (max-width: 900px) {
        .edit-grid {
            grid-template-columns: 1fr
        }
    }

    .field {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 14px;
        padding: 12px
    }

    .field .label {
        font-size: .85rem;
        font-weight: 800;
        color: #9c185b;
        margin: 0 0 6px
    }

    .inp,
    textarea.inp {
        width: 100%;
        border: 1px solid var(--bd);
        border-radius: 12px;
        padding: 10px 12px;
        background: #fff
    }

    textarea.inp {
        min-height: 120px;
        resize: vertical;
        line-height: 1.5
    }

    /* dropzone kecil utk edit */
    .dz-mini {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border: 2px dashed #f4cadd;
        background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
        border-radius: 12px;
        padding: 14px;
        color: #6b7280
    }

    .dz-mini.drag {
        background: #fff;
        border-color: #ec4899;
        box-shadow: 0 0 0 4px rgba(236, 72, 153, .12)
    }

    .dz-mini img {
        max-width: 140px;
        border-radius: 12px;
        border: 1px solid var(--bd)
    }

    .dz-mini .dz-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
        justify-content: center
    }

    .actions-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 10px
    }

    .btn.sm {
        padding: 8px 12px;
        border-radius: 12px
    }

    .btn.soft {
        background: #ffe7f2;
        color: #c21762
    }

    /* ——— Aesthetic for actions row ——— */
    .table .actions {
        white-space: nowrap;
    }

    .table .row-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    /* Saat baris masuk mode edit, sembunyikan tombol toggle & hapus */
    tr.editing .toggle-wrap,
    tr.editing .del-wrap {
        display: none !important;
    }

    /* Edit <details> tampil rapi */
    details.edit>summary {
        list-style: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        border: 1px solid var(--bd);
        background: #fff;
        font-weight: 800;
        box-shadow: 0 6px 14px rgba(236, 72, 153, .06);
    }

    details.edit[open]>summary {
        background: #fff0f7;
        border-color: #ffcfe4
    }

    .edit-card {
        margin-top: 12px;
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 16px;
        padding: 16px;
        box-shadow: var(--shadow)
    }

    .edit-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px
    }

    .edit-title {
        font-weight: 900;
        color: #c21762
    }

    .edit-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 14px
    }

    @media (max-width:900px) {
        .edit-grid {
            grid-template-columns: 1fr
        }
    }

    .edit-card .field {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 14px;
        padding: 12px
    }

    .edit-card .label {
        font-size: .85rem;
        font-weight: 800;
        color: #9c185b;
        margin: 0 0 6px
    }

    .edit-card .inp,
    .edit-card textarea.inp {
        width: 100%;
        border: 1px solid var(--bd);
        border-radius: 12px;
        padding: 10px 12px;
        background: #fff
    }

    .edit-card textarea.inp {
        min-height: 110px;
        resize: vertical;
        line-height: 1.5
    }

    /* mini dropzone */
    .dz-mini {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border: 2px dashed #f4cadd;
        background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
        border-radius: 12px;
        padding: 14px;
        color: #6b7280
    }

    .dz-mini.drag {
        background: #fff;
        border-color: #ec4899;
        box-shadow: 0 0 0 4px rgba(236, 72, 153, .12)
    }

    .dz-mini img {
        max-width: 140px;
        border-radius: 12px;
        border: 1px solid var(--bd)
    }

    .dz-mini .dz-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
        justify-content: center
    }
</style>

<style>
    /* ===== Pretty Alerts (Aesthetic Pink) ===== */
    .alerts {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin: 8px 0 16px
    }

    .alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 14px;
        border: 1px solid var(--bd);
        border-radius: 14px;
        background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
        box-shadow: 0 10px 24px rgba(236, 72, 153, .08);
        color: #374151;
        line-height: 1.45;
        position: relative;
        transform: translateY(-4px);
        opacity: 0;
        animation: alert-in .28s ease forwards;
    }

    @keyframes alert-in {
        to {
            transform: translateY(0);
            opacity: 1
        }
    }

    .alert .ico {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border: 1px solid #f7cfe0;
        background: #fff;
        box-shadow: 0 6px 14px rgba(236, 72, 153, .06);
    }

    .alert .txt {
        flex: 1
    }

    .alert .ttl {
        font-weight: 900;
        letter-spacing: .01em;
        margin-bottom: 2px
    }

    .alert .msg {
        margin: 0
    }

    .alert .close {
        appearance: none;
        border: 0;
        background: #fff;
        color: #9ca3af;
        border: 1px solid var(--bd);
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        cursor: pointer;
    }

    .alert .close:hover {
        filter: brightness(.98)
    }

    .alert.success {
        border-color: #c7f2d4;
        background: linear-gradient(180deg, #fff, #fff 70%, #f3fff7)
    }

    .alert.success .ico {
        border-color: #d9f7e4
    }

    .alert.warn {
        border-color: #fde9c3;
        background: linear-gradient(180deg, #fff, #fff 70%, #fff9ef)
    }

    .alert.warn .ico {
        border-color: #fee7c9
    }

    .alert.info {
        border-color: #dbeafe;
        background: linear-gradient(180deg, #fff, #fff 70%, #f5faff)
    }

    .alert.info .ico {
        border-color: #dbeafe
    }

    .alert.danger {
        border-color: #fed7df;
        background: linear-gradient(180deg, #fff, #fff 70%, #fff1f4)
    }

    .alert.danger .ico {
        border-color: #fed7df
    }

    /* pill action on alert (optional) */
    .alert .actions {
        display: flex;
        gap: 8px;
        margin-top: 6px
    }

    .alert .pill {
        height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        border: 1px solid var(--bd);
        background: #ffe7f2;
        font-weight: 800
    }
</style>


<div class="alerts" id="alerts">
    <?php if (!empty($_GET['saved'])): ?>
        <div class="alert success" role="status">
            <div class="ico">
                <!-- check -->
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#10b981">
                    <path d="M7.8 14.6 3.9 10.7l1.4-1.4 2.5 2.5 6-6 1.4 1.4-7.4 7.4z" />
                </svg>
            </div>
            <div class="txt">
                <div class="ttl">Berhasil</div>
                <p class="msg">Pertanyaan tersimpan.</p>
            </div>
            <button class="close" aria-label="Tutup" data-close-alert>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="#6b7280">
                    <path d="M5 5l10 10M15 5 5 15" stroke="#6b7280" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['updated'])): ?>
        <div class="alert info" role="status">
            <div class="ico">
                <!-- info -->
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#3b82f6">
                    <path d="M10 2a8 8 0 110 16 8 8 0 010-16zm1 11H9V9h2v4zm0-6H9V5h2v2z" />
                </svg>
            </div>
            <div class="txt">
                <div class="ttl">Status diperbarui</div>
                <p class="msg">Perubahan telah disimpan.</p>
            </div>
            <button class="close" aria-label="Tutup" data-close-alert>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="#6b7280">
                    <path d="M5 5l10 10M15 5 5 15" stroke="#6b7280" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['deleted'])): ?>
        <div class="alert danger" role="alert">
            <div class="ico">
                <!-- danger -->
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#ef4444">
                    <path d="M10 2l8 14H2L10 2zm-1 5h2v4H9V7zm0 6h2v2H9v-2z" />
                </svg>
            </div>
            <div class="txt">
                <div class="ttl">Terhapus</div>
                <p class="msg">Pertanyaan telah dihapus.</p>
            </div>
            <button class="close" aria-label="Tutup" data-close-alert>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="#6b7280">
                    <path d="M5 5l10 10M15 5 5 15" stroke="#6b7280" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash)): ?>
        <div class="alert warn" role="alert">
            <div class="ico">
                <!-- warn -->
                <svg width="18" height="18" viewBox="0 0 20 20" fill="#f59e0b">
                    <path d="M10 2l8 14H2L10 2zm-1 9h2V7H9v4zm0 4h2v-2H9v2z" />
                </svg>
            </div>
            <div class="txt">
                <div class="ttl">Perlu perhatian</div>
                <p class="msg"><?= h($flash) ?></p>
            </div>
            <button class="close" aria-label="Tutup" data-close-alert>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="#6b7280">
                    <path d="M5 5l10 10M15 5 5 15" stroke="#6b7280" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        const container = document.getElementById('alerts');
        if (!container) return;

        // close by button
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-close-alert]');
            if (!btn) return;
            const card = btn.closest('.alert');
            if (card) {
                card.style.transition = 'opacity .18s ease, transform .18s ease';
                card.style.opacity = '0'; card.style.transform = 'translateY(-4px)';
                setTimeout(() => card.remove(), 180);
            }
        });

        // auto dismiss
        container.querySelectorAll('.alert').forEach((card) => {
            let timeout = setTimeout(() => {
                card.querySelector('[data-close-alert]')?.click();
            }, 4500);

            card.addEventListener('mouseenter', () => clearTimeout(timeout));
            card.addEventListener('mouseleave', () => {
                timeout = setTimeout(() => {
                    card.querySelector('[data-close-alert]')?.click();
                }, 2000);
            });
        });
    })();
</script>



<section class="card">
    <h2 class="card-title">Buat Pertanyaan</h2>

    <form method="post" enctype="multipart/form-data" id="qform">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="create">

        <div class="field" style="margin-bottom:16px">
            <div class="label">Pertanyaan</div>
            <textarea name="question_text" class="inp"
                placeholder="Tulis pertanyaan psikometri (tidak ada jawaban benar/salah)..." required></textarea>
        </div>

        <div class="form-grid">
            <div class="field">
                <div class="label">Opsi 1</div>
                <input class="inp" name="option_1" required>
            </div>
            <div class="field">
                <div class="label">Opsi 2</div>
                <input class="inp" name="option_2" required>
            </div>
            <div class="field">
                <div class="label">Opsi 3 (opsional)</div>
                <input class="inp" name="option_3">
            </div>
            <div class="field">
                <div class="label">Opsi 4 (opsional)</div>
                <input class="inp" name="option_4">
            </div>
        </div>

        <div class="form-grid" style="margin-top:16px">
            <!-- Dropzone -->
            <div class="field">
                <div class="label">Gambar (opsional)</div>

                <!-- input file disembunyikan, di-triger oleh dropzone -->
                <input id="fileInput" type="file" name="question_image" accept="image/*" style="display:none">

                <div id="dz" class="dropzone">
                    <div>
                        <div style="font-weight:700;color:#ec4899">Tarik & letakkan gambar di sini</div>
                        <div class="muted" style="margin-top:4px">atau klik untuk memilih file (PNG, JPG, WEBP)</div>
                    </div>
                </div>

                <!-- preview -->
                <div id="dzPreview" class="dz-preview" style="display:none">
                    <img id="dzImg" src="" alt="preview">
                    <div class="dz-actions">
                        <button class="btn" type="button" id="dzChange">Ganti</button>
                        <button class="btn ghost" type="button" id="dzRemove">Hapus</button>
                    </div>
                </div>
            </div>

            <div class="field" style="display:flex;align-items:flex-end;gap:10px">
                <label style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="make_active" value="1">
                    <span class="muted">Aktifkan setelah disimpan</span>
                </label>
                <div style="margin-left:auto">
                    <button class="btn" type="submit">Simpan</button>
                </div>
            </div>
        </div>
    </form>
</section>

<section class="card" style="margin-top:18px">
    <h2 class="card-title">Daftar Pertanyaan</h2>

    <table class="table pretty aesthetic">
        <thead>
            <tr>
                <th style="width:70px">No.</th>
                <th>Pertanyaan</th>
                <th style="width:120px">Aktif?</th>
                <th style="width:160px">Dibuat</th>
                <th style="width:280px">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1;
            foreach ($items as $it): ?>
                <tr>
                    <td><span class="badge-id"><?= $no++ ?></span></td>

                    <td>
                        <div class="qtext"><?= nl2br(h($it['question_text'])) ?></div>
                        <?php if ($it['image_path']): ?>
                            <div class="muted small" style="margin-top:8px">
                                <img src="../<?= h($it['image_path']) ?>" alt=""
                                    style="max-width:220px;border-radius:12px;border:1px solid var(--bd)">
                            </div>
                        <?php endif; ?>
                    </td>

                    <td><?= $it['is_active'] ? '<span class="tag active">Aktif</span>' : '<span class="tag">Nonaktif</span>' ?>
                    </td>
                    <td><?= h($it['created_at']) ?></td>

                    <td class="actions">
                        <div class="row-actions">
                            <!-- TOGGLE: beri class toggle-wrap -->
                            <form method="post" class="toggle-wrap" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                                <button class="pill"><?= $it['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                            </form>

                            <!-- HAPUS: beri class del-wrap -->
                            <form method="post" class="del-wrap" style="display:inline"
                                onsubmit="return confirm('Hapus pertanyaan ini?')">
                                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                                <button class="pill ghost">Hapus</button>
                            </form>

                            <!-- EDIT -->
                            <details class="edit">
                                <summary>Edit</summary>

                                <div class="edit-card">
                                    <div class="edit-head">
                                        <div class="edit-title">Ubah pertanyaan</div>
                                    </div>

                                    <?php
                                    $ops = $optsByQ[(int) $it['id']] ?? [];
                                    $o1 = $ops[0] ?? '';
                                    $o2 = $ops[1] ?? '';
                                    $o3 = $ops[2] ?? '';
                                    $o4 = $ops[3] ?? '';
                                    ?>

                                    <!-- penting: action disamakan dengan handler PHP (edit) -->
                                    <form method="post" enctype="multipart/form-data" class="edit-form">
                                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">

                                        <div class="edit-grid">
                                            <!-- kiri -->
                                            <div class="field">
                                                <div class="label">Pertanyaan</div>
                                                <textarea class="inp" name="question_text"
                                                    required><?= h($it['question_text']) ?></textarea>
                                            </div>

                                            <!-- kanan (status boleh tampil; jika ingin hilang, hapus blok ini) -->


                                            <div class="field">
                                                <div class="label">Opsi 1</div>
                                                <input class="inp" name="option_1" value="<?= h($o1) ?>" required>
                                            </div>
                                            <div class="field">
                                                <div class="label">Opsi 2</div>
                                                <input class="inp" name="option_2" value="<?= h($o2) ?>" required>
                                            </div>
                                            <div class="field">
                                                <div class="label">Opsi 3 (opsional)</div>
                                                <input class="inp" name="option_3" value="<?= h($o3) ?>">
                                            </div>
                                            <div class="field">
                                                <div class="label">Opsi 4 (opsional)</div>
                                                <input class="inp" name="option_4" value="<?= h($o4) ?>">
                                            </div>

                                            <!-- Gambar -->
                                            <div class="field" style="grid-column:1/-1">
                                                <div class="label">Gambar (opsional)</div>
                                                <input id="file-<?= (int) $it['id'] ?>" type="file" name="question_image"
                                                    accept="image/*" style="display:none">

                                                <div id="dz-<?= (int) $it['id'] ?>" class="dz-mini" <?= $it['image_path'] ? 'style="display:none"' : '' ?>>
                                                    <div>
                                                        <div style="font-weight:700;color:#ec4899">Tarik & letakkan file di
                                                            sini</div>
                                                        <div class="muted" style="margin-top:4px">atau klik untuk memilih
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="dzp-<?= (int) $it['id'] ?>" class="dz-mini" <?= !$it['image_path'] ? 'style="display:none"' : '' ?>>
                                                    <div>
                                                        <img id="dzimg-<?= (int) $it['id'] ?>"
                                                            src="<?= $it['image_path'] ? '../' . h($it['image_path']) : '' ?>"
                                                            alt="">
                                                        <div class="dz-actions">
                                                            <button class="btn sm soft" type="button"
                                                                data-change="<?= (int) $it['id'] ?>">Ganti</button>
                                                            <button class="btn sm ghost" type="button"
                                                                data-remove="<?= (int) $it['id'] ?>">Hapus</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top:12px;text-align:right">
                                            <button class="btn">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </td>


                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</section>

<script>
    (function () {
        const dz = document.getElementById('dz');
        const input = document.getElementById('fileInput');
        const preview = document.getElementById('dzPreview');
        const img = document.getElementById('dzImg');
        const btnChange = document.getElementById('dzChange');
        const btnRemove = document.getElementById('dzRemove');

        function setPreview(file) {
            if (!file) return;
            const ok = ['image/jpeg', 'image/png', 'image/webp'];
            if (!ok.includes(file.type)) { alert('Format harus JPG/PNG/WEBP'); return; }
            const r = new FileReader();
            r.onload = e => { img.src = e.target.result; preview.style.display = 'flex'; dz.style.display = 'none'; };
            r.readAsDataURL(file);
        }

        dz.addEventListener('click', () => input.click());
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
        dz.addEventListener('drop', e => {
            e.preventDefault(); dz.classList.remove('drag');
            const file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) { input.files = e.dataTransfer.files; setPreview(file); }
        });

        input.addEventListener('change', e => {
            const file = e.target.files && e.target.files[0];
            if (file) setPreview(file);
        });

        btnChange.addEventListener('click', () => input.click());
        btnRemove.addEventListener('click', () => {
            input.value = ''; img.src = ''; preview.style.display = 'none'; dz.style.display = 'flex';
        });
    })();
</script>

<script>
    // aktifkan dropzone mini untuk tiap baris edit
    document.querySelectorAll('[id^="dz-"]').forEach(dz => {
        const id = dz.id.split('-')[1];
        const input = document.getElementById('file-' + id);
        const panel = document.getElementById('dzp-' + id);
        const img = document.getElementById('dzimg-' + id);

        function setPreview(file) {
            if (!file) return;
            const ok = ['image/jpeg', 'image/png', 'image/webp'];
            if (!ok.includes(file.type)) { alert('Format harus JPG / PNG / WEBP'); return; }
            const r = new FileReader();
            r.onload = e => { img.src = e.target.result; panel.style.display = 'flex'; dz.style.display = 'none'; };
            r.readAsDataURL(file);
        }

        dz.addEventListener('click', () => input.click());
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
        dz.addEventListener('drop', e => {
            e.preventDefault(); dz.classList.remove('drag');
            const file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) { input.files = e.dataTransfer.files; setPreview(file); }
        });
        input.addEventListener('change', e => {
            const f = e.target.files && e.target.files[0];
            if (f) setPreview(f);
        });

        // tombol ganti/hapus
        const btnChange = document.querySelector('[data-change="' + id + '"]');
        const btnRemove = document.querySelector('[data-remove="' + id + '"]');
        if (btnChange) btnChange.addEventListener('click', () => input.click());
        if (btnRemove) btnRemove.addEventListener('click', () => {
            input.value = ''; img.src = ''; panel.style.display = 'none'; dz.style.display = 'flex';
        });
    });
</script>

<script>
    // Tandai baris yang sedang membuka <details class="edit">
    (function () {
        const edits = document.querySelectorAll('details.edit');

        function closeAllExcept(me) {
            edits.forEach(d => {
                if (d !== me) {
                    d.open = false;
                    const tr = d.closest('tr');
                    tr && tr.classList.remove('editing');
                }
            });
        }

        edits.forEach(d => {
            d.addEventListener('toggle', () => {
                const tr = d.closest('tr');
                if (!tr) return;

                if (d.open) {
                    closeAllExcept(d);
                    tr.classList.add('editing');   // => CSS menyembunyikan .toggle-wrap & .del-wrap
                } else {
                    tr.classList.remove('editing');
                }
            });
        });
    })();
</script>

<?php include __DIR__ . '/_foot.php'; ?>