<?php
// admin/_head.php
require_once __DIR__ . '/_init.php';

if (!isset($PAGE_TITLE))
    $PAGE_TITLE = 'EmoCare • Admin';
if (!isset($PAGE_HEADING))
    $PAGE_HEADING = 'Dashboard';
if (!isset($active))
    $active = 'dashboard';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= h($PAGE_TITLE) ?></title>

    <!-- Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink: #ec4899;
            --pink-200: #ffd6e9;
            --ink: #111827;
            --muted: #6b7280;
            --bd: #f7cfe0;
            --card: #fff;
            --shadow: 0 12px 28px rgba(236, 72, 153, .10);

            /* Lebar sidebar pakai variabel (gampang ubah): */
            --sb-w: 340px;
            /* semula 320px */
            --sb-w-collapsed: 96px;
            /* semula 92px */
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: "Roboto", system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial;
            color: #111;
            background: #fff
        }

        /* ===== Layout utama ===== */
        .layout {
            display: flex;
            min-height: 100vh;
            background: #fff
        }

        /* Sidebar fixed + gradient halus + tidak mepet */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 50;
            width: var(--sb-w);
            flex: 0 0 var(--sb-w);
            background: linear-gradient(180deg, #fff, #fff 70%, #fff7fb);
            border-right: 1px solid var(--bd);
            padding: 14px 12px 18px;
        }

        /* Konten digeser sebesar lebar sidebar */
        .content {
            flex: 1;
            margin-left: var(--sb-w);
            background: linear-gradient(180deg, #fff, #fff 55%, #fff7fb);
        }

        .content>.inner {
            min-height: 100vh;
            padding: 18px 28px 44px 12px;
            max-width: 1160px;
            margin-left: 0;
            margin-right: auto;
        }

        /* ===== TOPBAR (hamburger + title di satu kotak) ===== */
        .topbar {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid var(--bd);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 12px 16px;
            margin: 2px 0 18px 0;
        }

        .hamb {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--bd);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            cursor: pointer;
        }

        .hamb span {
            display: block;
            width: 20px;
            height: 2px;
            background: #d21f6f;
            margin: 3px 0;
            border-radius: 2px
        }

        .topbar h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #111
        }

        /* ===== Brand & Logo di sidebar ===== */
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 6px 12px 6px;
            margin: 0 4px 10px
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px
        }

        .logo-heart {
            width: 84px;
            height: 84px;
            border-radius: 24px;
            position: relative;
            flex: 0 0 84px;
            background: linear-gradient(180deg, #ffe8f3, #ffeef7);
            border: 1px solid var(--bd);
            box-shadow: 0 14px 30px rgba(236, 72, 153, .18), inset 0 1px 0 #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-heart::after {
            /* glossy overlay */
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 24px;
            background: radial-gradient(100px 60px at 50% -10%, rgba(255, 255, 255, .7), transparent 60%);
            pointer-events: none;
        }

        .logo-heart svg {
            width: 34px;
            height: 34px;
            fill: #e11d74;
            filter: drop-shadow(0 2px 2px rgba(236, 72, 153, .35))
        }

        .brand-text {
            display: flex;
            flex-direction: column
        }

        .brand-name {
            font-weight: 900;
            color: #111;
            font-size: 16px;
            line-height: 1
        }

        .admin-name {
            font-weight: 600;
            color: #9c185b;
            font-size: 12.5px;
            opacity: .9;
            margin-top: 4px
        }

        /* Collapsed: sembunyikan teks brand */
        body.sb-collapsed .brand-text {
            display: none
        }

        /* ===== Sidebar nav ===== */
        .menu-head {
            font-size: .76rem;
            color: #94a3b8;
            letter-spacing: .12em;
            margin: 12px 8px 6px 8px;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
            text-decoration: none;
            padding: 9px 10px;
            margin: 4px 6px;
            border-radius: 12px;
            border: 1px solid transparent;
        }

        .sidebar nav a:hover {
            background: #fff;
            border-color: #f8d6e8;
            box-shadow: 0 6px 16px rgba(236, 72, 153, .06)
        }

        .sidebar nav a.active {
            background: #fff;
            border-color: #f4cadd;
            box-shadow: 0 8px 18px rgba(236, 72, 153, .10)
        }

        /* Badge ikon agar konsisten */
        .sidebar nav a .ico {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-grid;
            place-items: center;
            background: #fff;
            border: 1px solid transparent;
            font-size: 18px;
            flex: 0 0 36px;
        }

        .sidebar nav a:hover .ico {
            border-color: #f8d6e8;
            background: #fff
        }

        .sidebar nav a.active .ico {
            border-color: #f4cadd;
            background: #fff
        }

        .sidebar nav a ._text {
            white-space: nowrap
        }

        /* Collapsed (desktop) */
        body.sb-collapsed .sidebar {
            width: var(--sb-w-collapsed);
            flex-basis: var(--sb-w-collapsed)
        }

        body.sb-collapsed .content {
            margin-left: var(--sb-w-collapsed)
        }

        body.sb-collapsed .sidebar nav a {
            justify-content: center
        }

        body.sb-collapsed .sidebar nav a ._text {
            display: none
        }

        /* Drawer (mobile) */
        @media (max-width:900px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 0 20px 60px rgba(0, 0, 0, .12)
            }

            body.sb-open .sidebar {
                transform: translateX(0)
            }

            .drawer-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .6);
                backdrop-filter: blur(1px);
                z-index: 40;
                display: none
            }

            body.sb-open .drawer-backdrop {
                display: block
            }

            /* abaikan collapsed di mobile */
            body.sb-collapsed .sidebar {
                width: var(--sb-w);
                flex-basis: var(--sb-w)
            }

            .content {
                margin-left: 0
            }
        }

        /* ===== Kartu & teks konten ===== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 20px
        }

        .card {
            background: #fff;
            border: 1px solid var(--bd);
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--shadow);
            line-height: 1.6
        }

        .card.soft {
            background: linear-gradient(180deg, #fff, #fff 70%, #fff8fc);
            border-color: #ffd6e9
        }

        .card-title {
            font-weight: 800;
            color: var(--pink);
            margin: 0 0 8px
        }

        .muted {
            color: var(--muted)
        }

        /* Tombol */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--pink);
            color: #fff;
            border: 0;
            border-radius: 14px;
            padding: 10px 16px;
            font-weight: 800;
            box-shadow: 0 6px 14px rgba(236, 72, 153, .12);
            cursor: pointer;
            transition: transform .05s ease, filter .15s ease
        }

        .btn:hover {
            filter: brightness(.98)
        }

        .btn:active {
            transform: translateY(1px)
        }

        .btn.ghost {
            background: #fff;
            border: 1px solid var(--bd);
            color: #111
        }

        /* ==== FIX: logo benar-benar “masuk” ke dalam sidebar ==== */
        .sidebar {
            /* biar efek glow & bayangan tidak keluar ke area konten */
            overflow: hidden;
            padding-right: 18px;
            /* beri ruang kanan agar tidak mepet garis */
        }

        /* ukuran & glow logo sedikit diperkecil agar tidak menyentuh border */
        .logo-heart {
            width: 76px;
            /* semula 84px */
            height: 76px;
            border-radius: 22px;
            box-shadow: 0 10px 24px rgba(236, 72, 153, .16), inset 0 1px 0 #fff;
            margin: 0 auto;
            /* pastikan center di sidebar */
        }

        .logo-heart::after {
            /* lingkaran cahaya dipersempit agar tidak sampai tepi */
            background: radial-gradient(90px 54px at 50% -8%, rgba(255, 255, 255, .75), transparent 58%);
        }

        /* ketika sidebar di-collapse, logo tetap center & tidak keluar */
        body.sb-collapsed .sidebar .brand {
            justify-content: center
        }

        body.sb-collapsed .sidebar .logo-heart {
            margin: 0 auto
        }

        /* — Kecilkan sedikit logo love di header sidebar — */
        .logo-heart {
            width: 80px;
            /* sebelumnya ±76–84px */
            height: 80px;
            border-radius: 20px;
        }

        .logo-heart svg {
            width: 30px;
            /* kecilkan ikon di dalamnya */
            height: 30px;
        }

        /* Saat sidebar collapsed juga sedikit lebih kecil agar konsisten */
        body.sb-collapsed .logo-heart {
            width: 50px;
            /* sebelumnya 60px */
            height: 78px;
            border-radius: 20px;
        }

        body.sb-collapsed .logo-heart svg {
            width: 30px;
            height: 30px;
        }

        /* === PINK BUTTONS === */
        .btn {
            background: #ec4899;
            /* pink utama */
            color: #fff;
            border: 0;
            border-radius: 14px;
            padding: 10px 16px;
            font-weight: 800;
            box-shadow: 0 6px 14px rgba(236, 72, 153, .18);
            transition: transform .05s ease, filter .15s ease, box-shadow .15s ease;
        }

        .btn:hover {
            filter: brightness(.98);
            box-shadow: 0 8px 18px rgba(236, 72, 153, .22);
        }

        .btn:active {
            transform: translateY(1px);
        }

        /* Tombol “ghost” juga jadi pink */
        .btn.ghost {
            background: #ec4899;
            color: #fff;
            border: 1px solid #ec4899;
        }

        .btn.ghost:hover {
            filter: brightness(.98);
        }

        /* (opsional) kapsul kecil di tabel/aksi ikut pink */
        .pill {
            background: #ffe1f0;
            border: 1px solid #ffb8d7;
            color: #b21463;
        }

        .pill.ghost {
            background: #ec4899;
            border-color: #ec4899;
            color: #fff;
        }

        /* Tabel cantik + nomor urut */
        .table.aesthetic {
            border: 1px solid var(--bd);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table.aesthetic thead th {
            background: linear-gradient(180deg, #fff, #fff 60%, #fff8fc);
            font-weight: 900;
            border-bottom: 2px solid var(--bd);
        }

        .table.aesthetic td {
            vertical-align: top
        }

        .badge-id {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid var(--bd);
            background: #fff;
            font-weight: 800;
            color: #9c185b;
        }

        /* Panel edit yang rapi di dalam baris */
        .editbox {
            display: inline-block;
            margin-left: 6px
        }

        .editbox[open] {
            background: #fff;
            border-radius: 12px;
            padding: 8px 10px;
            border: 1px solid var(--bd);
        }

        .editbox>summary {
            list-style: none;
            cursor: pointer;
            display: inline-block
        }

        .editbox>summary::-webkit-details-marker {
            display: none
        }

        .edit-form .inp {
            width: 100%;
            border: 1px solid var(--bd);
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff
        }

        .edit-form textarea.inp {
            min-height: 92px;
            line-height: 1.5;
            resize: vertical
        }
    </style>

    

<style>
/* ====== Aesthetic table untuk Daftar Kuis ====== */
.table.pretty {
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  background:#fff;
  border:1px solid var(--bd);
  border-radius:16px;
  overflow:hidden;
  box-shadow:var(--shadow);
}
.table.pretty thead th{
  position:sticky; top:0; z-index:1;
  background:linear-gradient(180deg,#fff,#fff 60%,#fff8fc);
  border-bottom:2px solid var(--bd);
  font-weight:900; color:#111; padding:12px 14px; text-align:left;
}
.table.pretty td{
  padding:14px; border-bottom:1px solid var(--bd);
  vertical-align:top; color:#374151;
}
.table.pretty tbody tr:last-child td{border-bottom:0}
.table.pretty tbody tr:hover{background:#fffafc}

/* sel kecil / aksesori */
.td-ico{width:72px}
.emoji-badge{
  width:38px;height:38px;border-radius:12px;display:grid;place-items:center;
  background:#fff;border:1px solid var(--bd); box-shadow:0 6px 14px rgba(236,72,153,.06);
  font-size:18px;
}
.slug{font-family:ui-monospace,SFMono-Regular,Menlo,monospace; color:#6b7280; font-size:.85rem}
.badge{
  display:inline-flex; align-items:center; gap:6px; height:28px; padding:0 10px;
  border-radius:999px; border:1px solid var(--bd); background:#fff; font-weight:800; color:#9c185b;
}
.tag{display:inline-flex; align-items:center; gap:6px; height:26px; padding:0 10px; border-radius:999px; background:#fff5fb; border:1px solid #ffd6e9; font-weight:800}
.tag.active{background:#eafff5;border-color:#c7f2d4;color:#0d7b4e}

/* tombol */
.btn{display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 14px;border-radius:12px;border:1px solid var(--bd);background:#fff;font-weight:800;cursor:pointer}
.btn:hover{filter:brightness(.98)}
.btn.pink{background:var(--pink);border-color:var(--pink);color:#fff;box-shadow:0 8px 18px rgba(236,72,153,.16)}
.btn.soft{background:#ffe7f2;color:#c21762}
.btn.ghost{background:#fff;border:1px solid var(--bd);color:#111}

/* sejajarkan aksi */
.actions{white-space:nowrap}
.row-actions{display:flex;align-items:center;gap:10px;flex-wrap:nowrap}

/* header kanan: jumlah item */
.card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.pill-count{display:inline-flex;align-items:center;gap:8px;height:32px;padding:0 12px;border-radius:999px;border:1px solid var(--bd);background:#fff;font-weight:800}
.pill-count .dot{width:8px;height:8px;border-radius:50%;background:#ec4899}

/* responsive kecil */
@media (max-width:900px){
  .table.pretty thead th:nth-child(4), .table.pretty td:nth-child(4){display:none}  /* sembunyikan 'Urutan' di mobile */
}

/* ====== Layout jarak antar section ====== */
.section { margin-bottom: 28px; }
.section + .section { margin-top: 28px; }

/* ====== Aksi tombol seragam ====== */
.btn { background: var(--pink); color:#fff; border:0; border-radius:14px; padding:10px 16px; font-weight:800; box-shadow:0 6px 14px rgba(236,72,153,.12); }
.btn:hover{ filter:brightness(.98) }
.btn.ghost{ background:#fff; color:#111; border:1px solid var(--bd) }
.btn.pill{ height:36px; padding:0 14px; border-radius:999px; display:inline-flex; align-items:center; gap:6px }

/* Tombol kecil untuk aksi tabel */
.btn.sm{ height:32px; padding:0 12px; border-radius:12px }

/* ====== Form Tambah Kuis Baru ====== */
.form-actions{ display:flex; justify-content:flex-end; margin-top:12px }
.card .field{ background:#fff; border:1px solid var(--bd); border-radius:14px; padding:12px 14px; box-shadow:var(--shadow) }

/* ====== Tabel Daftar Kuis ====== */
.quiz-table{ width:100%; border-collapse:separate; border-spacing:0; background:#fff; border:1px solid var(--bd); border-radius:18px; overflow:hidden; box-shadow:var(--shadow) }
.quiz-table thead th{
  position:sticky; top:0; z-index:1;
  background:linear-gradient(180deg,#fff,#fff 60%, #fff8fc);
  border-bottom:2px solid var(--bd); font-weight:900; color:#111; padding:12px 14px; text-align:left;
}
.quiz-table td{ padding:14px; border-bottom:1px solid var(--bd); vertical-align:middle }
.quiz-table tr:last-child td{ border-bottom:0 }
.quiz-table tbody tr:hover{ background:#fffafc }

/* Kolom angka/badge kecil */
.badge-round{ display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px;
  border-radius:999px; border:1px solid var(--bd); background:#fff; font-weight:900; color:#c21762 }

/* Status bulat kecil */
.chip{ display:inline-flex; align-items:center; height:28px; padding:0 10px; border-radius:999px; border:1px solid var(--bd); background:#fff }
.chip.ok{ background:#eafff2; border-color:#c7f3d9; color:#137a40 }
.chip.muted{ background:#fff; color:#6b7280 }

/* Kolom aksi: tombol sejajar */
.actions{ white-space:nowrap }
.actions .grp{ display:flex; gap:10px; align-items:center }

/* Header "Daftar Kuis" dengan badge jumlah */
.section-head{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px }
.count-badge{ display:inline-flex; align-items:center; gap:8px; height:36px; padding:0 12px; border-radius:999px; background:#fff; border:1px solid var(--bd); color:#6b7280; font-weight:700 }

/* Jarak lebih lega antar dua section utama di halaman */
#quizzes-create { margin-bottom: 36px; }    /* form atas */
#quizzes-list   { margin-top:  6px;  }      /* tabel bawah */

/* Responsif: rapikan tombol saat layar sempit */
@media (max-width: 900px){
  .actions .grp{ flex-wrap:wrap }
}

/* Saat <details> Edit (di daftar kuis) terbuka, sembunyikan hanya tombol tertentu */
.quiz-table .actions details[open] ~ .hide-on-edit,
.quiz-table .actions details[open] ~ .js-kelola-soal,
.quiz-table .actions details[open] ~ .js-hapus {
  display: none !important;
}


</style>


</head>

<body class="sb-collapsed"><!-- default: collapsed di desktop -->

    <!-- backdrop untuk mobile drawer -->
    <div class="drawer-backdrop" onclick="__EC_SB__ && __EC_SB__.closeDrawer()"></div>

    <div class="layout">
        <?php include __DIR__ . '/_sidebar.php'; ?>
        <div class="content">
            <div class="inner">
                <!-- Topbar (judul + hamburger) -->
                <div class="topbar">
                    <button class="hamb" aria-label="Menu" onclick="
          if (window.matchMedia('(max-width: 900px)').matches) { __EC_SB__.openDrawer(); }
          else { __EC_SB__.toggleCollapse(); }
        ">
                        <span></span><span></span><span></span>
                    </button>
                    <h2><?= h($PAGE_HEADING) ?></h2>
                </div>