(function () {
  if (window.__sefarAuthModernReady) {
    return;
  }

  window.__sefarAuthModernReady = true;

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

  const prefersReducedMotion = () => window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
    const timer = window.setTimeout(() => finish(null), 3200);
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => finish(window[globalName] || null);
    script.onerror = () => finish(null);
    document.head.appendChild(script);
  });

  const withTimeout = (promise, milliseconds = 3600) => new Promise((resolve) => {
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

  const animateInterface = async (shell) => {
    const gsap = await loadScript(cdn.gsap, 'gsap');

    if (!gsap) {
      shell.dataset.sefarAuthMotion = 'css';
      return;
    }

    shell.dataset.sefarAuthMotion = 'gsap';

    const mm = gsap.matchMedia();
    mm.add(
      {
        reduceMotion: '(prefers-reduced-motion: reduce)',
        isDesktop: '(min-width: 901px)',
      },
      (context) => {
        const { reduceMotion, isDesktop } = context.conditions;

        if (reduceMotion) {
          return undefined;
        }

        gsap.from('.sefar-login-hero-logo, .sefar-login-kicker, .sefar-login-hero h1, .sefar-login-copy, .sefar-login-pulse', {
          autoAlpha: 0,
          y: 18,
          duration: 0.72,
          stagger: 0.08,
          ease: 'power3.out',
          clearProps: 'visibility,opacity,transform',
        });

        gsap.from('.sefar-login-card', {
          autoAlpha: 0,
          x: isDesktop ? 28 : 0,
          y: isDesktop ? 0 : 18,
          duration: 0.82,
          delay: 0.12,
          ease: 'power3.out',
          clearProps: 'visibility,opacity,transform',
        });

        gsap.from('.sefar-login-field, .sefar-login-options, .sefar-login-button, .sefar-login-register', {
          autoAlpha: 0,
          y: 12,
          duration: 0.46,
          delay: 0.34,
          stagger: 0.045,
          ease: 'power2.out',
          clearProps: 'visibility,opacity,transform',
        });

        gsap.to('.sefar-login-pulse span', {
          scaleX: 0.64,
          transformOrigin: 'left center',
          duration: 1.65,
          stagger: 0.18,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
        });

        return undefined;
      }
    );

    shell.addEventListener('mousemove', (event) => {
      if (prefersReducedMotion()) {
        return;
      }

      const rect = shell.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - 0.5;
      const y = (event.clientY - rect.top) / rect.height - 0.5;

      gsap.to('.sefar-login-card', {
        rotationY: x * -2.4,
        rotationX: y * 1.8,
        transformPerspective: 900,
        transformOrigin: 'center',
        duration: 0.45,
        ease: 'power2.out',
        overwrite: 'auto',
      });
    });

    shell.addEventListener('mouseleave', () => {
      gsap.to('.sefar-login-card', {
        rotationX: 0,
        rotationY: 0,
        duration: 0.45,
        ease: 'power2.out',
        overwrite: 'auto',
      });
    });
  };

  const setupCanvasFallback = (shell, canvas) => {
    const context = canvas.getContext('2d');

    if (!context) {
      return;
    }

    shell.dataset.sefarAuthRenderer = 'fallback';

    let frameId = null;
    let segments = [];
    let ratio = 1;
    let width = 1;
    let height = 1;

    const buildSegments = () => Array.from({ length: 58 }, () => ({
      x: Math.random() * width,
      y: Math.random() * height,
      length: 22 + Math.random() * 72,
      speed: 0.16 + Math.random() * 0.34,
      alpha: 0.12 + Math.random() * 0.28,
      tilt: -0.25 + Math.random() * 0.5,
    }));

    const resize = () => {
      const rect = shell.getBoundingClientRect();
      ratio = Math.min(window.devicePixelRatio || 1, 2);
      width = Math.max(1, Math.floor(rect.width));
      height = Math.max(1, Math.floor(rect.height));

      canvas.width = Math.floor(width * ratio);
      canvas.height = Math.floor(height * ratio);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
      segments = buildSegments();
    };

    const draw = () => {
      context.clearRect(0, 0, width, height);
      context.lineCap = 'round';

      segments.forEach((segment) => {
        context.beginPath();
        context.strokeStyle = `rgba(143, 216, 255, ${segment.alpha})`;
        context.lineWidth = 1;
        context.moveTo(segment.x, segment.y);
        context.lineTo(segment.x + segment.length, segment.y + (segment.length * segment.tilt));
        context.stroke();

        context.beginPath();
        context.strokeStyle = `rgba(219, 186, 114, ${segment.alpha * 0.8})`;
        context.lineWidth = 2;
        context.moveTo(segment.x - 6, segment.y + 4);
        context.lineTo(segment.x + 9, segment.y + 4);
        context.stroke();
      });
    };

    const render = () => {
      if (!prefersReducedMotion()) {
        segments.forEach((segment) => {
          segment.x += segment.speed;
          segment.y -= segment.speed * 0.18;

          if (segment.x > width + 90) {
            segment.x = -90;
            segment.y = Math.random() * height;
          }

          if (segment.y < -40) {
            segment.y = height + 40;
          }
        });
      }

      draw();
      frameId = window.requestAnimationFrame(render);
    };

    const observer = window.ResizeObserver
      ? new ResizeObserver(() => {
        resize();
        draw();
      })
      : null;

    if (observer) {
      observer.observe(shell);
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

    resize();
    render();
  };

  const setupThreeScene = async (shell, canvas) => {
    try {
      const THREE = window.THREE || await withTimeout(import(cdn.three));

      if (!THREE || prefersReducedMotion()) {
        setupCanvasFallback(shell, canvas);
        return;
      }

      shell.dataset.sefarAuthRenderer = 'three';

      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(46, 1, 0.1, 80);
      const renderer = new THREE.WebGLRenderer({
        canvas,
        alpha: true,
        antialias: true,
        powerPreference: 'low-power',
      });
      const group = new THREE.Group();
      const clock = new THREE.Clock();
      let frameId = null;
      let running = true;
      let targetX = 0;
      let targetY = 0;

      const wireMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.18,
        wireframe: true,
      });
      const goldMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.28,
        wireframe: true,
      });

      const primaryMesh = new THREE.Mesh(new THREE.IcosahedronGeometry(1.18, 2), wireMaterial);
      const ringMesh = new THREE.Mesh(new THREE.TorusGeometry(1.68, 0.012, 8, 130), goldMaterial);
      const secondaryRing = new THREE.Mesh(new THREE.TorusGeometry(2.08, 0.01, 8, 150), wireMaterial);

      primaryMesh.position.set(-2.2, 0.1, -1.2);
      ringMesh.position.set(-2.2, 0.1, -1.2);
      secondaryRing.position.set(-2.2, 0.1, -1.2);
      ringMesh.rotation.x = Math.PI / 2.55;
      secondaryRing.rotation.y = Math.PI / 2.4;

      const pointCount = 120;
      const positions = new Float32Array(pointCount * 3);

      for (let index = 0; index < pointCount; index += 1) {
        positions[index * 3] = (Math.random() - 0.5) * 9.5;
        positions[(index * 3) + 1] = (Math.random() - 0.5) * 5.8;
        positions[(index * 3) + 2] = (Math.random() - 0.5) * 4.6;
      }

      const pointGeometry = new THREE.BufferGeometry();
      pointGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
      const points = new THREE.Points(
        pointGeometry,
        new THREE.PointsMaterial({
          color: 0x8fd8ff,
          size: 0.024,
          transparent: true,
          opacity: 0.68,
          depthWrite: false,
        })
      );

      group.add(primaryMesh, ringMesh, secondaryRing, points);
      scene.add(group);
      camera.position.z = 7.2;

      const resize = () => {
        const rect = shell.getBoundingClientRect();
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
        group.rotation.y += ((targetX * 0.16) - group.rotation.y) * 0.035;
        group.rotation.x += ((targetY * 0.1) - group.rotation.x) * 0.035;
        primaryMesh.rotation.x += delta * 0.18;
        primaryMesh.rotation.y += delta * 0.12;
        ringMesh.rotation.z += delta * 0.16;
        secondaryRing.rotation.x -= delta * 0.11;
        points.rotation.y += delta * 0.025;
        renderer.render(scene, camera);
        frameId = window.requestAnimationFrame(render);
      };

      const dispose = () => {
        running = false;

        if (frameId) {
          window.cancelAnimationFrame(frameId);
        }

        primaryMesh.geometry.dispose();
        ringMesh.geometry.dispose();
        secondaryRing.geometry.dispose();
        pointGeometry.dispose();
        primaryMesh.material.dispose();
        ringMesh.material.dispose();
        secondaryRing.material.dispose();
        points.material.dispose();
        renderer.dispose();
      };

      shell.addEventListener('mousemove', (event) => {
        const rect = shell.getBoundingClientRect();
        targetX = ((event.clientX - rect.left) / rect.width) - 0.5;
        targetY = ((event.clientY - rect.top) / rect.height) - 0.5;
      });

      const observer = window.ResizeObserver
        ? new ResizeObserver(resize)
        : null;

      if (observer) {
        observer.observe(shell);
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
      setupCanvasFallback(shell, canvas);
    }
  };

  ready(() => {
    const shell = document.querySelector('[data-sefar-login]');

    if (!shell) {
      return;
    }

    const canvas = shell.querySelector('.sefar-login-canvas');

    if (!canvas) {
      return;
    }

    animateInterface(shell);
    setupThreeScene(shell, canvas);
  });
}());
