document.addEventListener("DOMContentLoaded", function () {

  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener("click", function (e) {
      const targetId = this.getAttribute("href");

      if (targetId && targetId.length > 1) {
        const target = document.querySelector(targetId);

        if (target) {
          e.preventDefault();
          target.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      }
    });
  });

  // Sidebar toggle
  const menuToggle = document.getElementById("menuToggle");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebarOverlay");

  function openSidebar() {
    if (sidebar) sidebar.classList.add("show");
    if (overlay) overlay.classList.add("show");
    document.body.classList.add("sidebar-open");
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove("show");
    if (overlay) overlay.classList.remove("show");
    document.body.classList.remove("sidebar-open");
  }

  function toggleSidebar() {
    if (!sidebar || !overlay) return;

    const isOpen = sidebar.classList.contains("show");
    if (isOpen) {
      closeSidebar();
    } else {
      openSidebar();
    }
  }

  if (menuToggle && sidebar && overlay) {
    menuToggle.addEventListener("click", function () {
      toggleSidebar();
    });

    overlay.addEventListener("click", function () {
      closeSidebar();
    });

    document.querySelectorAll("#sidebar .nav-link").forEach(function (link) {
      link.addEventListener("click", function () {
        if (window.innerWidth < 992) {
          closeSidebar();
        }
      });
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth >= 992) {
        closeSidebar();
      }
    });
  }

  // Auto active nav highlight
  const currentPath = window.location.pathname.split("/").pop();

  document.querySelectorAll("#sidebar .nav-link").forEach(function (link) {
    const href = link.getAttribute("href");
    if (!href) return;

    const linkPath = href.split("/").pop();

    if (linkPath === currentPath) {
      link.classList.add("active");
    }
  });
});
