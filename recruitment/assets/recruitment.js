(function () {
  'use strict';

  var root = document.documentElement;
  var menu = document.getElementById('layout-menu');
  var overlay = document.querySelector('.layout-overlay');
  var toggles = document.querySelectorAll('.layout-menu-toggle');
  var visiblePages = Array.isArray(window.taascorRecruitmentVisiblePages)
    ? window.taascorRecruitmentVisiblePages
    : [];

  visiblePages.forEach(function (pageId) {
    var item = document.getElementById('a' + String(pageId));
    if (item && item.dataset.secondaryNavigation !== 'true') {
      item.style.display = 'block';
    }
  });

  var currentPath = window.location.pathname.replace(/\/+$/, '');
  document.querySelectorAll('#side-ul a[href]').forEach(function (link) {
    if (!link.href || link.href.indexOf('javascript:') === 0) {
      return;
    }
    var target = new URL(link.href, window.location.origin);
    if (target.pathname.replace(/\/+$/, '') === currentPath) {
      link.setAttribute('aria-current', 'page');
      if (link.parentElement) {
        link.parentElement.classList.add('active');
      }
    }
  });

  document.querySelectorAll('#side-ul .menu-item.master').forEach(function (item, index) {
    var toggle = item.querySelector(':scope > .menu-toggle');
    var submenu = item.querySelector(':scope > .menu-sub');
    if (!toggle || !submenu) {
      return;
    }
    if (!submenu.id) {
      submenu.id = 'recruitment-menu-' + String(index);
    }
    toggle.setAttribute('aria-controls', submenu.id);
    toggle.setAttribute('aria-expanded', item.classList.contains('open') ? 'true' : 'false');
    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      item.classList.toggle('open');
      toggle.setAttribute('aria-expanded', item.classList.contains('open') ? 'true' : 'false');
    });
  });

  function closeMenu() {
    root.classList.remove('layout-menu-expanded');
  }

  function toggleMenu(event) {
    if (event) {
      event.preventDefault();
    }
    root.classList.toggle('layout-menu-expanded');
  }

  toggles.forEach(function (toggle) {
    toggle.addEventListener('click', toggleMenu);
  });

  if (overlay) {
    overlay.addEventListener('click', closeMenu);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth >= 1200) {
      closeMenu();
    }
  });

  if (!menu) {
    closeMenu();
  }
}());
