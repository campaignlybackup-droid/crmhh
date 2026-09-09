(function () {
  'use strict';

  function toggle(el) { el && el.classList.toggle('show'); }
  function closeAll(except) {
    document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
      if (m !== except) m.classList.remove('show');
    });
  }

  // ─── Real-time notification badge polling ─────────────────────────────────
  var notifBadge = null;
  var notifBtn = null;
  var lastCount = -1;

  function pollNotifications() {
    if (!notifBtn) return;
    fetch('?page=notifications&action=api_unread', { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(data) {
        if (!data) return;
        var count = data.count || 0;
        if (count === lastCount) return; // no change
        lastCount = count;

        // Remove old badge if any
        var old = notifBtn.querySelector('.badge-dot');
        if (old) old.remove();

        if (count > 0) {
          var dot = document.createElement('span');
          dot.className = 'badge-dot';
          dot.textContent = count > 9 ? '9+' : count;
          notifBtn.appendChild(dot);
          // Subtle pulse to hint new notifications
          notifBtn.classList.add('notif-pulse');
          setTimeout(function(){ notifBtn.classList.remove('notif-pulse'); }, 1200);
        }
      })
      .catch(function(){});
  }

  // ─── Loading bar on navigation ────────────────────────────────────────────
  var progressBar = null;

  function showProgress() {
    if (!progressBar) {
      progressBar = document.createElement('div');
      progressBar.id = 'nav-progress';
      progressBar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0%;background:var(--primary,#4f46e5);z-index:99999;transition:width .3s ease;';
      document.body.appendChild(progressBar);
    }
    progressBar.style.width = '30%';
    progressBar.style.opacity = '1';
    setTimeout(function(){ if(progressBar) progressBar.style.width = '70%'; }, 200);
  }

  function hideProgress() {
    if (!progressBar) return;
    progressBar.style.width = '100%';
    setTimeout(function(){ progressBar.style.opacity = '0'; }, 150);
    setTimeout(function(){ progressBar.style.width = '0%'; }, 350);
  }

  document.addEventListener('DOMContentLoaded', function () {
    notifBtn = document.getElementById('notifBtn');
    var notifMenu = document.getElementById('notifMenu');
    var userBtn = document.getElementById('userBtn');
    var userMenu = document.getElementById('userMenu');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    var sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (notifBtn) notifBtn.addEventListener('click', function (e) { e.stopPropagation(); closeAll(notifMenu); toggle(notifMenu); });
    if (userBtn) userBtn.addEventListener('click', function (e) { e.stopPropagation(); closeAll(userMenu); toggle(userMenu); });
    document.addEventListener('click', function () { closeAll(); });

    if (sidebarToggle) sidebarToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      sidebar.classList.toggle('show');
      if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show');
    });
    if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', function () {
      sidebar.classList.remove('show');
      sidebarBackdrop.classList.remove('show');
    });

    // ─── Navigation loading bar ─────────────────────────────────────────────
    document.querySelectorAll('a[href]').forEach(function(a) {
      var href = a.getAttribute('href');
      // Only intercept same-origin internal links (not #anchors, not mailto, not external)
      if (!href || href.charAt(0) === '#' || href.indexOf('://') !== -1 || href.indexOf('mailto:') === 0) return;
      a.addEventListener('click', function(e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey) return; // let browser handle
        showProgress();
      });
    });

    // Form submissions also trigger the bar
    document.querySelectorAll('form[method="post"], form[method="get"]').forEach(function(f) {
      // Skip AJAX forms (ones with no action or same-page action)
      f.addEventListener('submit', function() { showProgress(); });
    });

    hideProgress(); // cleanup in case of back-navigation

    // ─── Confirm dialogs ────────────────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
      });
    });

    // ─── Modal open/close ───────────────────────────────────────────────────
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var modal = document.getElementById(btn.getAttribute('data-modal-open'));
        if (modal) modal.classList.add('show');
      });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var modal = btn.closest('.modal-overlay');
        if (modal) modal.classList.remove('show');
      });
    });
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) overlay.classList.remove('show');
      });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
      }
    });

    // ─── Auto-submit filter selects ─────────────────────────────────────────
    document.querySelectorAll('.filters-bar select[data-autosubmit]').forEach(function (sel) {
      sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // ─── Inline quick-edit selects (status/priority/assignment) ────────────
    document.querySelectorAll('.quick-edit-select').forEach(function (sel) {
      sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // ─── Start notification polling (every 30 seconds) ──────────────────────
    if (notifBtn) {
      pollNotifications(); // immediate first check
      setInterval(pollNotifications, 30000);
    }

    // ─── Flash message auto-dismiss after 5 seconds ─────────────────────────
    var flash = document.querySelector('.flash-message, .alert, [class*="flash"]');
    if (flash) {
      setTimeout(function() {
        flash.style.transition = 'opacity 0.5s ease';
        flash.style.opacity = '0';
        setTimeout(function(){ flash.style.display = 'none'; }, 500);
      }, 5000);
    }
  });
})();
