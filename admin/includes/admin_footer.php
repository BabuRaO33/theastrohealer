    </div><!-- /.dash-content -->
  </div><!-- /.dash-main -->

  <script>
    // Responsive sidebar toggle with mobile backdrop overlay
    (function() {
      const toggleBtn = document.getElementById('sidebar-toggle');
      const sidebar = document.getElementById('admin-sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');

      function toggleAdminSidebar() {
        if (sidebar) sidebar.classList.toggle('mobile-open');
        if (backdrop) backdrop.classList.toggle('active');
      }

      function closeAdminSidebar() {
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (backdrop) backdrop.classList.remove('active');
      }

      if (toggleBtn) toggleBtn.addEventListener('click', toggleAdminSidebar);
      if (backdrop) backdrop.addEventListener('click', closeAdminSidebar);

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAdminSidebar();
      });
    })();
  </script>
</body>
</html>
