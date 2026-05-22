<?php
// Footer: closes main content + HTML
?>
        </div><!-- /.container-fluid -->
    </main><!-- /.crm-main -->
</div><!-- /.crm-wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Sidebar toggle (mobile)
document.addEventListener('DOMContentLoaded', function() {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('crmSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('active');
        });
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    }

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(function(el) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>
