(function () {
  if (window.__sefarAdminShellReady) {
    return;
  }

  window.__sefarAdminShellReady = true;

  const cdn = {
    gsap: 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
    three: 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js',
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

  const withTimeout = (promise, milliseconds = 2500) => new Promise((resolve) => {
    const timer = window.setTimeout(() => resolve(null), milliseconds);

    promise
      .then((value) => {
        window.clearTimeout(timer);
        resolve(value);
      })
      .catch(() => {
        window.clearTimeout(timer);
        resolve(null);
      });
  });

  const prefersReducedMotion = () => window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setupSidebarStructure = (sidebar) => {
    sidebar.classList.add('sefar-shell-ready');

    if (!sidebar.querySelector('.sefar-nav-canvas')) {
      const canvas = document.createElement('canvas');
      canvas.className = 'sefar-nav-canvas';
      canvas.setAttribute('aria-hidden', 'true');
      sidebar.prepend(canvas);
    }

    sidebar.querySelectorAll('.nav-sidebar .has-treeview').forEach((item) => {
      item.classList.add('sefar-menu-group');
    });
  };

  const animateNavigation = async (sidebar) => {
    const gsap = await loadScript(cdn.gsap, 'gsap');

    if (!gsap) {
      return;
    }

    const mm = gsap.matchMedia();

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

  const setupCanvasFallback = (sidebar, canvas, staticOnly = false) => {
    const context = canvas.getContext('2d');

    if (!context) {
      return;
    }

    let frameId = null;
    let particles = [];

    const createParticles = (width, height) => Array.from({ length: 72 }, () => ({
      x: Math.random() * width,
      y: Math.random() * height,
      radius: 0.7 + Math.random() * 1.8,
      speed: 0.12 + Math.random() * 0.28,
      alpha: 0.16 + Math.random() * 0.28,
    }));

    const resize = () => {
      const rect = sidebar.getBoundingClientRect();
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      const width = Math.max(1, Math.floor(rect.width));
      const height = Math.max(1, Math.floor(rect.height));

      canvas.width = Math.floor(width * ratio);
      canvas.height = Math.floor(height * ratio);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
      particles = createParticles(width, height);
    };

    const draw = () => {
      const width = canvas.width / Math.min(window.devicePixelRatio || 1, 2);
      const height = canvas.height / Math.min(window.devicePixelRatio || 1, 2);

      context.clearRect(0, 0, width, height);

      particles.forEach((particle) => {
        context.beginPath();
        context.fillStyle = `rgba(143, 216, 255, ${particle.alpha})`;
        context.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
        context.fill();
      });
    };

    const render = () => {
      if (!staticOnly && !prefersReducedMotion()) {
        const height = canvas.height / Math.min(window.devicePixelRatio || 1, 2);

        particles.forEach((particle) => {
          particle.y += particle.speed;

          if (particle.y > height + 4) {
            particle.y = -4;
          }
        });
      }

      draw();

      if (!staticOnly && !prefersReducedMotion()) {
        frameId = window.requestAnimationFrame(render);
      }
    };

    const observer = window.ResizeObserver
      ? new ResizeObserver(() => {
        resize();
        draw();
      })
      : null;

    if (observer) {
      observer.observe(sidebar);
    } else {
      window.addEventListener('resize', resize);
    }

    window.addEventListener('beforeunload', () => {
      if (frameId) {
        window.cancelAnimationFrame(frameId);
      }

      if (observer) {
        observer.disconnect();
      } else {
        window.removeEventListener('resize', resize);
      }
    }, { once: true });

    sidebar.classList.add('sefar-canvas-fallback');
    resize();
    render();
  };

  const setupSidebarScene = async (sidebar) => {
    const canvas = sidebar.querySelector('.sefar-nav-canvas');

    if (!canvas) {
      return;
    }

    try {
      const THREE = window.THREE || await withTimeout(import(cdn.three));

      if (!THREE) {
        setupCanvasFallback(sidebar, canvas);
        return;
      }

      if (prefersReducedMotion()) {
        setupCanvasFallback(sidebar, canvas, true);
        return;
      }

      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(48, 1, 0.1, 20);
      const renderer = new THREE.WebGLRenderer({
        canvas,
        alpha: true,
        antialias: true,
        powerPreference: 'low-power',
      });
      const group = new THREE.Group();
      const pointsCount = 90;
      const positions = new Float32Array(pointsCount * 3);

      for (let i = 0; i < pointsCount; i += 1) {
        positions[i * 3] = (Math.random() - 0.5) * 2.4;
        positions[(i * 3) + 1] = (Math.random() - 0.5) * 7.2;
        positions[(i * 3) + 2] = (Math.random() - 0.5) * 2.2;
      }

      const geometry = new THREE.BufferGeometry();
      geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

      const material = new THREE.PointsMaterial({
        color: 0x8fd8ff,
        size: 0.028,
        transparent: true,
        opacity: 0.58,
        depthWrite: false,
      });

      const points = new THREE.Points(geometry, material);
      const clock = new THREE.Clock();
      let frameId = null;
      let running = true;

      camera.position.z = 3.6;
      group.add(points);
      scene.add(group);

      const resize = () => {
        const rect = sidebar.getBoundingClientRect();
        const width = Math.max(1, Math.floor(rect.width));
        const height = Math.max(1, Math.floor(rect.height));

        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(width, height, false);
      };

      const render = () => {
        if (!running) {
          return;
        }

        const delta = clock.getDelta();
        group.rotation.y += delta * 0.045;
        points.rotation.z += delta * 0.012;
        renderer.render(scene, camera);
        frameId = window.requestAnimationFrame(render);
      };

      const dispose = () => {
        running = false;

        if (frameId) {
          window.cancelAnimationFrame(frameId);
        }

        geometry.dispose();
        material.dispose();
        renderer.dispose();
      };

      const observer = window.ResizeObserver
        ? new ResizeObserver(resize)
        : null;

      if (observer) {
        observer.observe(sidebar);
      } else {
        window.addEventListener('resize', resize);
      }

      document.addEventListener('visibilitychange', () => {
        running = !document.hidden;

        if (running) {
          clock.getDelta();
          render();
        }
      });

      window.addEventListener('beforeunload', () => {
        if (observer) {
          observer.disconnect();
        } else {
          window.removeEventListener('resize', resize);
        }

        dispose();
      }, { once: true });

      resize();
      render();
    } catch (error) {
      sidebar.classList.add('sefar-no-webgl');
      setupCanvasFallback(sidebar, canvas);
    }
  };

  ready(() => {
    const sidebar = document.querySelector('.main-sidebar');

    if (!sidebar) {
      return;
    }

    setupSidebarStructure(sidebar);
    animateNavigation(sidebar);
    setupSidebarScene(sidebar);
  });
}());
