(function () {
  'use strict';

  function toggle(el) { el && el.classList.toggle('show'); }
  function closeAll(except) {
    document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
      if (m !== except) m.classList.remove('show');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var notifBtn = document.getElementById('notifBtn');
    var notifMenu = document.getElementById('notifMenu');
    var userBtn = document.getElementById('userBtn');
    var userMenu = document.getElementById('userMenu');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');

    if (notifBtn) notifBtn.addEventListener('click', function (e) { e.stopPropagation(); closeAll(notifMenu); toggle(notifMenu); });
    if (userBtn) userBtn.addEventListener('click', function (e) { e.stopPropagation(); closeAll(userMenu); toggle(userMenu); });
    document.addEventListener('click', function () { closeAll(); });

    if (sidebarToggle) sidebarToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      sidebar.classList.toggle('show');
    });

    // Confirm dialogs for destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
      });
    });

    // Generic modal open/close via data attributes
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

    // Auto-submit filter selects inside .filters-bar forms
    document.querySelectorAll('.filters-bar select[data-autosubmit]').forEach(function (sel) {
      sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // Inline quick-edit selects (status/priority/assignment) auto-submit their own tiny form
    document.querySelectorAll('.quick-edit-select').forEach(function (sel) {
      sel.addEventListener('change', function () { sel.form.submit(); });
    });
  });
})();
