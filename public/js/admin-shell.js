(function () {
  if (window.__sefarAdminShellReady) {
    return;
  }

  window.__sefarAdminShellReady = true;

  const assets = {
    gsap: '/js/gsap.js',
  };

  const ready = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }

    callback();
  };

  const loadScript = (src, globalName) => new Promise((resolve) => {
    if (window[globalName]) {
      resolve(window[globalName]);
      return;
    }

    let settled = false;
    const finish = (value) => {
      if (settled) {
        return;
      }

      settled = true;
      window.clearTimeout(timer);
      resolve(value);
    };
    const timer = window.setTimeout(() => finish(null), 2500);
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => finish(window[globalName] || null);
    script.onerror = () => finish(null);
    document.head.appendChild(script);
  });

  const prefersReducedMotion = () => window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setupSidebarPreference = (sidebar) => {
    const body = document.body;
    const toggle = document.getElementById('sefarSidebarPinToggle');
    const storageKey = 'sefar.sidebar.pinned';
    const isDesktop = () => window.matchMedia('(min-width: 992px)').matches;

    const readPinned = () => {
      try {
        const preference = window.localStorage.getItem(storageKey);

        // Everyone starts with the sidebar fixed. We only collapse it when
        // the user has explicitly chosen that compact presentation.
        return preference === null ? true : preference === 'true';
      } catch (error) {
        return true;
      }
    };

    const persistPinned = (pinned) => {
      try {
        window.localStorage.setItem(storageKey, String(pinned));
      } catch (error) {
        // A blocked storage policy must not stop navigation from working.
      }
    };

    const updateToggle = (pinned) => {
      if (!toggle) {
        return;
      }

      const mobile = !isDesktop();
      const drawerOpen = body.classList.contains('sidebar-open');
      toggle.setAttribute('aria-pressed', String(pinned));
      toggle.setAttribute('aria-expanded', String(mobile ? drawerOpen : pinned));
      toggle.setAttribute('aria-label', mobile
        ? (drawerOpen ? 'Cerrar menú lateral' : 'Abrir menú lateral')
        : (pinned ? 'Contraer menú lateral' : 'Fijar menú lateral'));
      toggle.setAttribute('title', mobile
        ? (drawerOpen ? 'Cerrar menú lateral' : 'Abrir menú lateral')
        : (pinned ? 'Contraer menú lateral' : 'Fijar menú lateral'));
      const icon = toggle.querySelector('i');
      if (icon) {
        icon.className = mobile
          ? (drawerOpen ? 'fas fa-times' : 'fas fa-bars')
          : (pinned ? 'fas fa-thumbtack fa-rotate-90' : 'fas fa-bars');
      }
    };

    const setPinned = (pinned, save = true) => {
      if (!isDesktop()) {
        body.classList.remove('sefar-sidebar-pinned');
        body.classList.remove('sidebar-collapse');
        updateToggle(false);
        return;
      }

      body.classList.toggle('sefar-sidebar-pinned', pinned);
      body.classList.toggle('sidebar-collapse', !pinned);
      sidebar.classList.toggle('sefar-sidebar-is-pinned', pinned);
      updateToggle(pinned);

      if (save) {
        persistPinned(pinned);
      }
    };

    setPinned(readPinned(), false);

    if (toggle) {
      toggle.addEventListener('click', () => {
        if (!isDesktop()) {
          body.classList.toggle('sidebar-open');
          updateToggle(false);
          return;
        }

        setPinned(!body.classList.contains('sefar-sidebar-pinned'));
      });
    }

    window.addEventListener('resize', () => setPinned(readPinned(), false));
  };

  const setupSidebarStructure = (sidebar) => {
    sidebar.classList.add('sefar-shell-ready');

    sidebar.querySelectorAll('.nav-sidebar .has-treeview').forEach((item) => {
      item.classList.add('sefar-menu-group');
    });

  };

  const animateNavigation = async (sidebar) => {
    const gsap = await loadScript(assets.gsap, 'gsap');

    if (!gsap) {
      return;
    }

    const mm = gsap.matchMedia();
    sidebar.classList.add('sefar-gsap-active');

    mm.add(
      {
        reduceMotion: '(prefers-reduced-motion: reduce)',
        isDesktop: '(min-width: 992px)',
      },
      (context) => {
        const { reduceMotion, isDesktop } = context.conditions;

        if (reduceMotion) {
          return undefined;
        }

        gsap.from('.main-sidebar .nav-sidebar > .nav-item', {
          autoAlpha: 0,
          x: isDesktop ? -14 : -7,
          duration: 0.42,
          stagger: 0.025,
          ease: 'power2.out',
          clearProps: 'visibility,opacity,transform',
        });

        gsap.from('.sefar-topbar', {
          autoAlpha: 0,
          y: -8,
          duration: 0.35,
          ease: 'power2.out',
          clearProps: 'visibility,opacity,transform',
        });

        return undefined;
      }
    );

    sidebar.addEventListener('mouseover', (event) => {
      if (prefersReducedMotion()) {
        return;
      }

      const link = event.target.closest('.nav-sidebar .nav-link');

      if (!link || !sidebar.contains(link)) {
        return;
      }

      gsap.to(link, {
        x: 4,
        duration: 0.18,
        ease: 'power1.out',
        overwrite: 'auto',
      });
    });

    sidebar.addEventListener('mouseout', (event) => {
      const link = event.target.closest('.nav-sidebar .nav-link');

      if (!link || !sidebar.contains(link)) {
        return;
      }

      gsap.to(link, {
        x: 0,
        duration: 0.2,
        ease: 'power1.out',
        overwrite: 'auto',
      });
    });
  };

  ready(() => {
    const sidebar = document.querySelector('.main-sidebar');

    if (!sidebar) {
      return;
    }

    setupSidebarPreference(sidebar);
    setupSidebarStructure(sidebar);
    animateNavigation(sidebar);
  });
}());
