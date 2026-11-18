    </div><!-- /.dash-content -->
  </div><!-- /.dash-main -->

  <script>
    // Responsive student sidebar toggle with mobile backdrop overlay
    (function() {
      const toggleBtn = document.getElementById('sidebar-toggle');
      const sidebar = document.getElementById('student-sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');

      function toggleStudentSidebar() {
        if (sidebar) sidebar.classList.toggle('mobile-open');
        if (backdrop) backdrop.classList.toggle('active');
      }

      function closeStudentSidebar() {
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (backdrop) backdrop.classList.remove('active');
      }

      if (toggleBtn) toggleBtn.addEventListener('click', toggleStudentSidebar);
      if (backdrop) backdrop.addEventListener('click', closeStudentSidebar);

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeStudentSidebar();
      });
    })();
  </script>
</body>
</html>
