<?php if (isLoggedIn()): ?>
        </div> <!-- End main-content -->
    </div> <!-- End main-wrapper -->
    
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('show')"></div>
</div> <!-- End layout-wrapper -->
<?php else: ?>
</div> <!-- End unauthenticated main-content -->
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Theme Toggle Logic -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlEl = document.documentElement;

    if (!themeToggle) return; // Unauthenticated page

    function updateIcon(theme) {
        if (theme === 'dark') {
            themeIcon.className = 'bi bi-sun-fill fs-5 text-warning';
        } else {
            themeIcon.className = 'bi bi-moon-fill fs-5';
        }
    }

    // Set initial icon
    updateIcon(htmlEl.getAttribute('data-bs-theme'));

    themeToggle.addEventListener('click', () => {
        let currentTheme = htmlEl.getAttribute('data-bs-theme');
        let newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        htmlEl.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateIcon(newTheme);
    });
});
</script>
</body>
</html>
