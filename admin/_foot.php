</div><!-- .inner -->
</div><!-- .content -->
</div><!-- .layout -->

<script>
  // Util sederhana untuk menyimpan state collapse di desktop
  (function () {
    const KEY = 'ec_admin_sb_collapsed';
    const body = document.body;

    // state default: collapsed di desktop (body class ditaruh di _head)
    // buka drawer (mobile)
    function openDrawer() {
      body.classList.add('sb-open');
    }
    function closeDrawer() {
      body.classList.remove('sb-open');
    }
    function toggleCollapse() {
      body.classList.toggle('sb-collapsed');
      try { localStorage.setItem(KEY, body.classList.contains('sb-collapsed') ? '1' : '0'); } catch (e) { }
    }
    // restore state collapse (desktop)
    try {
      if (localStorage.getItem(KEY) === '0') {
        body.classList.remove('sb-collapsed');
      }
    } catch (e) { }

    // expose ke global utk tombol hamburger di _head
    window.__EC_SB__ = {
      openDrawer,
      closeDrawer,
      toggleCollapse
    };

    // klik backdrop menutup drawer mobile
    const backdrop = document.querySelector('.drawer-backdrop');
    if (backdrop) backdrop.addEventListener('click', closeDrawer);
  })();
</script>
</body>

</html>