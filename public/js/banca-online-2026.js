(function () {
  const state = {
    navigationBound: false,
    popBound: false,
    stripeLoading: null,
    constellation: null,
    motionLibraries: null,
  };

  const motionSources = {
    THREE: [
      'https://cdn.jsdelivr.net/npm/three@0.128.0/build/three.min.js',
      'https://unpkg.com/three@0.128.0/build/three.min.js',
      'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js',
    ],
    gsap: [
      'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js',
      'https://unpkg.com/gsap@3.12.5/dist/gsap.min.js',
      'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
    ],
  };
  const cartStorageKey = 'sefar:banca-online:cart:v1';
  const cartMaxAgeMs = 14 * 24 * 60 * 60 * 1000;

  function browserStorage() {
    try {
      return window.localStorage;
    } catch (error) {
      return null;
    }
  }

  function readBancaCart() {
    const storage = browserStorage();
    if (!storage) return null;

    try {
      const raw = storage.getItem(cartStorageKey);
      if (!raw) return null;

      const cart = JSON.parse(raw);
      if (!cart || typeof cart !== 'object') {
        storage.removeItem(cartStorageKey);
        return null;
      }

      const updatedAt = Number(cart.updated_at_ms || Date.parse(cart.updated_at || ''));
      if (!updatedAt || Date.now() - updatedAt > cartMaxAgeMs) {
        storage.removeItem(cartStorageKey);
        return null;
      }

      return cart;
    } catch (error) {
      storage.removeItem(cartStorageKey);
      return null;
    }
  }

  function writeBancaCart(cart) {
    const storage = browserStorage();
    if (!storage) return null;

    const next = {
      ...(cart || {}),
      version: 1,
      updated_at: new Date().toISOString(),
      updated_at_ms: Date.now(),
    };

    try {
      storage.setItem(cartStorageKey, JSON.stringify(next));
      return next;
    } catch (error) {
      return null;
    }
  }

  function clearBancaCart() {
    const storage = browserStorage();
    if (!storage) return;

    try {
      storage.removeItem(cartStorageKey);
    } catch (error) {}
  }

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function money(value) {
    return new Intl.NumberFormat('es-ES', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(Number(value || 0));
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function loadScriptOnce(globalName, sources) {
    if (window[globalName]) return Promise.resolve(window[globalName]);

    const urls = Array.isArray(sources) ? sources : [sources];

    function trySource(index) {
      const src = urls[index];
      if (!src) return Promise.reject(new Error(`${globalName} failed`));

      const current = document.querySelector(`script[data-bo-lib="${globalName}"][src="${src}"]`);

      return new Promise((resolve, reject) => {
        const script = current || document.createElement('script');
        const timer = window.setTimeout(() => reject(new Error(`${globalName} timeout`)), 5200);

        script.addEventListener('load', () => {
          window.clearTimeout(timer);
          script.dataset.loaded = '1';
          resolve(window[globalName]);
        }, { once: true });

        script.addEventListener('error', () => {
          window.clearTimeout(timer);
          reject(new Error(`${globalName} failed`));
        }, { once: true });

        if (!current) {
          script.src = src;
          script.async = true;
          script.dataset.boLib = globalName;
          document.head.appendChild(script);
        }
      }).catch(() => trySource(index + 1));
    }

    return trySource(0);
  }

  function loadMotionLibraries() {
    if (state.motionLibraries) return state.motionLibraries;

    state.motionLibraries = Promise
      .allSettled([
        loadScriptOnce('THREE', motionSources.THREE),
        loadScriptOnce('gsap', motionSources.gsap),
      ])
      .then(() => ({
        THREE: window.THREE,
        gsap: window.gsap,
      }));

    return state.motionLibraries;
  }

  function supportsWebGL() {
    try {
      const canvas = document.createElement('canvas');
      return Boolean(window.WebGLRenderingContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
    } catch (error) {
      return false;
    }
  }

  function createThreeConstellation() {
    const THREE = window.THREE;
    if (!THREE || !supportsWebGL()) return null;

    let renderer;
    try {
      renderer = new THREE.WebGLRenderer({
        alpha: true,
        antialias: true,
        powerPreference: 'high-performance',
      });
    } catch (error) {
      return null;
    }

    const canvas = renderer.domElement;
    canvas.className = 'bo-constellation-canvas is-three';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.prepend(canvas);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(44, 1, 0.1, 1000);
    const group = new THREE.Group();
    const targetRotation = { x: 0, z: 0 };
    const reducedMotion = prefersReducedMotion();
    const clock = new THREE.Clock();
    let raf = null;
    let paused = false;

    camera.position.z = 92;
    scene.add(group);

    const count = Math.max(86, Math.min(150, Math.floor((window.innerWidth * window.innerHeight) / 10500)));
    const points = [];
    const positions = new Float32Array(count * 3);
    const colors = new Float32Array(count * 3);
    const palette = [
      new THREE.Color(0x66727c),
      new THREE.Color(0x093143),
      new THREE.Color(0xa17f48),
      new THREE.Color(0x0f766e),
    ];

    for (let i = 0; i < count; i++) {
      const x = (Math.random() - 0.5) * 134;
      const y = (Math.random() - 0.5) * 78;
      const z = (Math.random() - 0.5) * 64;
      const color = palette[Math.random() < 0.74 ? 0 : Math.floor(1 + Math.random() * 3)];

      points.push({ x, y, z });
      positions[i * 3] = x;
      positions[i * 3 + 1] = y;
      positions[i * 3 + 2] = z;
      colors[i * 3] = color.r;
      colors[i * 3 + 1] = color.g;
      colors[i * 3 + 2] = color.b;
    }

    const pointGeometry = new THREE.BufferGeometry();
    pointGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    pointGeometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

    const pointMaterial = new THREE.PointsMaterial({
      size: 2.35,
      sizeAttenuation: true,
      transparent: true,
      opacity: 0.78,
      vertexColors: true,
    });

    const particleField = new THREE.Points(pointGeometry, pointMaterial);
    group.add(particleField);

    const linePositions = [];
    for (let i = 0; i < points.length; i++) {
      for (let j = i + 1; j < points.length; j++) {
        const a = points[i];
        const b = points[j];
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        const dz = a.z - b.z;
        const distance = Math.sqrt(dx * dx + dy * dy + dz * dz);

        if (distance < 24 && Math.random() > 0.58 && linePositions.length < 1500) {
          linePositions.push(a.x, a.y, a.z, b.x, b.y, b.z);
        }
      }
    }

    const lineGeometry = new THREE.BufferGeometry();
    lineGeometry.setAttribute('position', new THREE.Float32BufferAttribute(linePositions, 3));
    const lineMaterial = new THREE.LineBasicMaterial({
      color: 0x6f7d86,
      transparent: true,
      opacity: 0.2,
    });
    const lineField = new THREE.LineSegments(lineGeometry, lineMaterial);
    group.add(lineField);

    function resize() {
      const width = window.innerWidth;
      const height = window.innerHeight;
      const scale = Math.max(0.82, Math.min(1.24, width / 1280));

      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      renderer.setSize(width, height, false);
      camera.aspect = width / Math.max(height, 1);
      camera.updateProjectionMatrix();
      group.scale.setScalar(scale);
    }

    function pointerMove(event) {
      targetRotation.z = (event.clientX / Math.max(window.innerWidth, 1) - 0.5) * 0.34;
      targetRotation.x = (event.clientY / Math.max(window.innerHeight, 1) - 0.5) * 0.22;
    }

    function render() {
      const elapsed = clock.getElapsedTime();

      if (!reducedMotion) {
        group.rotation.y += 0.0014;
        group.rotation.x += (targetRotation.x - group.rotation.x) * 0.025;
        group.rotation.z += (targetRotation.z - group.rotation.z) * 0.025;
        pointMaterial.size = 2.35 + Math.sin(elapsed * 1.8) * 0.32;
      }

      renderer.render(scene, camera);

      if (!paused) {
        raf = window.requestAnimationFrame(render);
      }
    }

    function visibilityChange() {
      paused = document.hidden;
      window.cancelAnimationFrame(raf);
      if (!paused) raf = window.requestAnimationFrame(render);
    }

    resize();
    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', pointerMove);
    document.addEventListener('visibilitychange', visibilityChange);

    if (window.gsap && !reducedMotion) {
      window.gsap.to(group.rotation, {
        y: Math.PI * 2,
        duration: 96,
        repeat: -1,
        ease: 'none',
        overwrite: false,
      });
      window.gsap.to(lineMaterial, {
        opacity: 0.34,
        duration: 2.8,
        yoyo: true,
        repeat: -1,
        ease: 'sine.inOut',
      });
    }

    render();

    return {
      type: 'three',
      destroy() {
        paused = true;
        window.cancelAnimationFrame(raf);
        window.removeEventListener('resize', resize);
        window.removeEventListener('mousemove', pointerMove);
        document.removeEventListener('visibilitychange', visibilityChange);
        pointGeometry.dispose();
        pointMaterial.dispose();
        lineGeometry.dispose();
        lineMaterial.dispose();
        renderer.dispose();
        canvas.remove();
      },
    };
  }

  function createCanvasConstellation() {
    if (!document.body.classList.contains('bo-page')) return null;

    const canvas = document.createElement('canvas');
    canvas.className = 'bo-constellation-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.prepend(canvas);

    const ctx = canvas.getContext('2d');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pointer = { x: 0, y: 0, active: false };
    const scene = {
      width: 0,
      height: 0,
      dpr: 1,
      nodes: [],
      comets: [],
      raf: null,
      paused: false,
    };

    function makeNode(width, height) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 0.12 + Math.random() * 0.26;

      return {
        x: Math.random() * width,
        y: Math.random() * height,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        r: 1.25 + Math.random() * 2.45,
        accent: Math.random() > 0.84,
        pulse: Math.random() * Math.PI * 2,
      };
    }

    function makeComet(width, height) {
      return {
        progress: Math.random(),
        y: height * (0.12 + Math.random() * 0.74),
        speed: 0.0017 + Math.random() * 0.0023,
        length: 90 + Math.random() * 110,
      };
    }

    function resize() {
      scene.dpr = Math.min(window.devicePixelRatio || 1, 2);
      scene.width = window.innerWidth;
      scene.height = window.innerHeight;
      canvas.width = Math.floor(scene.width * scene.dpr);
      canvas.height = Math.floor(scene.height * scene.dpr);
      canvas.style.width = `${scene.width}px`;
      canvas.style.height = `${scene.height}px`;
      ctx.setTransform(scene.dpr, 0, 0, scene.dpr, 0, 0);

      const count = Math.max(56, Math.min(118, Math.floor((scene.width * scene.height) / 15500)));
      if (scene.nodes.length > count) {
        scene.nodes = scene.nodes.slice(0, count);
      }

      while (scene.nodes.length < count) {
        scene.nodes.push(makeNode(scene.width, scene.height));
      }

      const cometCount = scene.width < 720 ? 1 : 2;
      if (scene.comets.length > cometCount) {
        scene.comets = scene.comets.slice(0, cometCount);
      }
      while (scene.comets.length < cometCount) {
        scene.comets.push(makeComet(scene.width, scene.height));
      }
    }

    function moveNode(node) {
      node.x += node.vx;
      node.y += node.vy;
      node.pulse += 0.018;

      if (node.x < -20) node.x = scene.width + 20;
      if (node.x > scene.width + 20) node.x = -20;
      if (node.y < -20) node.y = scene.height + 20;
      if (node.y > scene.height + 20) node.y = -20;
    }

    function drawLines(nodes) {
      const maxDistance = Math.min(178, Math.max(110, scene.width / 7.8));

      for (let i = 0; i < nodes.length; i++) {
        for (let j = i + 1; j < nodes.length; j++) {
          const a = nodes[i];
          const b = nodes[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const distance = Math.sqrt(dx * dx + dy * dy);
          if (distance > maxDistance) continue;

          const alpha = (1 - distance / maxDistance) * 0.42;
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.strokeStyle = a.accent || b.accent
            ? `rgba(122, 100, 62, ${alpha * 0.72})`
            : `rgba(72, 86, 96, ${alpha})`;
          ctx.lineWidth = 1;
          ctx.stroke();
        }
      }
    }

    function drawPointerLines(nodes) {
      if (!pointer.active) return;

      nodes.forEach((node) => {
        const dx = node.x - pointer.x;
        const dy = node.y - pointer.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        if (distance > 210) return;

        ctx.beginPath();
        ctx.moveTo(node.x, node.y);
        ctx.lineTo(pointer.x, pointer.y);
        ctx.strokeStyle = `rgba(9, 49, 67, ${(1 - distance / 210) * 0.32})`;
        ctx.lineWidth = 1;
        ctx.stroke();
      });

      ctx.beginPath();
      ctx.arc(pointer.x, pointer.y, 28, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(122, 100, 62, 0.16)';
      ctx.lineWidth = 1;
      ctx.stroke();
    }

    function drawNodes(nodes) {
      nodes.forEach((node) => {
        const radius = node.r + Math.sin(node.pulse) * 0.35;
        ctx.beginPath();
        ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);
        ctx.fillStyle = node.accent ? 'rgba(122, 100, 62, 0.5)' : 'rgba(72, 86, 96, 0.48)';
        ctx.fill();
      });
    }

    function drawComets(comets) {
      comets.forEach((comet) => {
        const x = comet.progress * (scene.width + 260) - 130;
        const y = comet.y + Math.sin(comet.progress * Math.PI * 2) * 28;
        const gradient = ctx.createLinearGradient(x - comet.length, y + 22, x, y);

        gradient.addColorStop(0, 'rgba(161, 127, 72, 0)');
        gradient.addColorStop(0.75, 'rgba(122, 100, 62, 0.18)');
        gradient.addColorStop(1, 'rgba(9, 49, 67, 0.34)');

        ctx.beginPath();
        ctx.moveTo(x - comet.length, y + 22);
        ctx.lineTo(x, y);
        ctx.strokeStyle = gradient;
        ctx.lineWidth = 1.5;
        ctx.stroke();

        if (!reducedMotion.matches) {
          comet.progress += comet.speed;
          if (comet.progress > 1.08) {
            comet.progress = -0.08;
            comet.y = scene.height * (0.12 + Math.random() * 0.74);
          }
        }
      });
    }

    function frame() {
      ctx.clearRect(0, 0, scene.width, scene.height);

      if (!reducedMotion.matches) {
        scene.nodes.forEach(moveNode);
      }

      drawLines(scene.nodes);
      drawPointerLines(scene.nodes);
      drawComets(scene.comets);
      drawNodes(scene.nodes);

      if (!scene.paused) {
        scene.raf = window.requestAnimationFrame(frame);
      }
    }

    function start() {
      scene.paused = false;
      window.cancelAnimationFrame(scene.raf);
      scene.raf = window.requestAnimationFrame(frame);
    }

    function pause() {
      scene.paused = true;
      window.cancelAnimationFrame(scene.raf);
    }

    window.addEventListener('resize', resize);
    const pointerMove = (event) => {
      pointer.x = event.clientX;
      pointer.y = event.clientY;
      pointer.active = true;
    };
    const pointerOut = () => {
      pointer.active = false;
    };
    const visibilityChange = () => {
      if (document.hidden) {
        pause();
      } else {
        start();
      }
    };

    window.addEventListener('mousemove', pointerMove);
    window.addEventListener('mouseout', pointerOut);
    document.addEventListener('visibilitychange', visibilityChange);

    resize();
    start();

    return {
      type: 'canvas',
      destroy() {
        pause();
        window.removeEventListener('resize', resize);
        window.removeEventListener('mousemove', pointerMove);
        window.removeEventListener('mouseout', pointerOut);
        document.removeEventListener('visibilitychange', visibilityChange);
        canvas.remove();
      },
    };
  }

  function initConstellation() {
    if (!document.body.classList.contains('bo-page') || state.constellation) return;

    state.constellation = createCanvasConstellation();

    loadMotionLibraries().then((libraries) => {
      if (libraries.gsap) {
        animateCurrentView(document);
      }
    });
  }

  function clearMotionStage(stage) {
    if (stage._boStageCleanup) {
      stage._boStageCleanup();
      stage._boStageCleanup = null;
    }
    stage.querySelectorAll('canvas').forEach((canvas) => canvas.remove());
  }

  function createCanvasMotionStage(stage) {
    clearMotionStage(stage);

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const reduced = prefersReducedMotion();
    const dots = Array.from({ length: 68 }, (_, index) => ({
      orbit: 34 + (index % 5) * 22 + Math.random() * 18,
      angle: Math.random() * Math.PI * 2,
      speed: (0.0024 + Math.random() * 0.0055) * (index % 2 ? 1 : -1),
      z: 0.45 + Math.random() * 0.85,
      accent: index % 6 === 0,
    }));
    const runners = Array.from({ length: 5 }, (_, index) => ({
      orbit: 58 + index * 18,
      angle: Math.random() * Math.PI * 2,
      speed: 0.018 + index * 0.003,
      reverse: index % 2 === 0,
      accent: index % 2 === 0,
    }));
    let width = 0;
    let height = 0;
    let dpr = 1;
    let raf = null;

    canvas.setAttribute('aria-hidden', 'true');
    stage.appendChild(canvas);

    function resize() {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = Math.max(stage.clientWidth, 1);
      height = Math.max(stage.clientHeight, 1);
      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function drawOrbit(cx, cy, rx, ry, alpha, rotation) {
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(rotation || -0.35);
      ctx.beginPath();
      ctx.ellipse(0, 0, rx, ry, 0, 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(219, 186, 114, ${alpha})`;
      ctx.lineWidth = 1;
      ctx.stroke();
      ctx.restore();
    }

    function drawSweep(cx, cy, elapsed) {
      const radius = Math.min(width, height) * 0.46;
      const angle = elapsed * 0.55;
      const gradient = ctx.createLinearGradient(cx, cy, cx + Math.cos(angle) * radius, cy + Math.sin(angle) * radius);

      gradient.addColorStop(0, 'rgba(219, 186, 114, 0.36)');
      gradient.addColorStop(1, 'rgba(219, 186, 114, 0)');

      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(angle);
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.lineTo(radius, 0);
      ctx.strokeStyle = gradient;
      ctx.lineWidth = 1.4;
      ctx.stroke();
      ctx.restore();
    }

    function drawCore(cx, cy, elapsed) {
      const sides = 6;
      const radius = 26 + Math.sin(elapsed * 1.8) * 2;

      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(elapsed * 0.35);
      ctx.beginPath();
      for (let index = 0; index <= sides; index++) {
        const angle = (Math.PI * 2 * index) / sides;
        const x = Math.cos(angle) * radius;
        const y = Math.sin(angle) * radius;
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      }
      ctx.strokeStyle = 'rgba(219, 186, 114, 0.58)';
      ctx.lineWidth = 1.25;
      ctx.stroke();
      ctx.restore();
    }

    function drawRunners(cx, cy) {
      runners.forEach((runner) => {
        if (!reduced) {
          runner.angle += runner.speed * (runner.reverse ? -1 : 1);
        }

        for (let index = 0; index < 9; index++) {
          const trailAngle = runner.angle - index * 0.055 * (runner.reverse ? -1 : 1);
          const alpha = Math.max(0, 0.8 - index * 0.085);
          const x = cx + Math.cos(trailAngle) * runner.orbit;
          const y = cy + Math.sin(trailAngle) * runner.orbit * 0.42;

          ctx.beginPath();
          ctx.arc(x, y, index === 0 ? 4.2 : 2.4, 0, Math.PI * 2);
          ctx.fillStyle = runner.accent
            ? `rgba(219, 186, 114, ${alpha})`
            : `rgba(232, 242, 245, ${alpha * 0.72})`;
          ctx.fill();
        }
      });
    }

    function frame() {
      const elapsed = performance.now() * 0.001;
      const cx = width * 0.5;
      const cy = height * 0.5;
      ctx.clearRect(0, 0, width, height);

      drawSweep(cx, cy, elapsed);
      drawOrbit(cx, cy, width * 0.33, height * 0.17, 0.36, -0.35 + elapsed * 0.1);
      drawOrbit(cx, cy, width * 0.22, height * 0.34, 0.2, 0.86 - elapsed * 0.08);
      drawOrbit(cx, cy, width * 0.41, height * 0.25, 0.16, 0.22 + elapsed * 0.06);
      drawCore(cx, cy, elapsed);
      drawRunners(cx, cy);

      const projected = dots.map((dot) => {
        if (!reduced) dot.angle += dot.speed;
        const x = cx + Math.cos(dot.angle) * dot.orbit * dot.z;
        const y = cy + Math.sin(dot.angle) * dot.orbit * 0.46;
        return { x, y, dot };
      });

      for (let i = 0; i < projected.length; i++) {
        for (let j = i + 1; j < projected.length; j++) {
          const a = projected[i];
          const b = projected[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const distance = Math.sqrt(dx * dx + dy * dy);
          if (distance > 96) continue;
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.strokeStyle = `rgba(255, 255, 255, ${(1 - distance / 96) * 0.26})`;
          ctx.stroke();
        }
      }

      projected.forEach(({ x, y, dot }) => {
        const radius = dot.accent ? 3.7 : 2.35;
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fillStyle = dot.accent ? 'rgba(219, 186, 114, 0.95)' : 'rgba(232, 242, 245, 0.82)';
        ctx.fill();
      });

      raf = window.requestAnimationFrame(frame);
    }

    resize();
    window.addEventListener('resize', resize);
    frame();

    stage.dataset.boStageBound = 'canvas';
    stage._boStageCleanup = () => {
      window.cancelAnimationFrame(raf);
      window.removeEventListener('resize', resize);
      canvas.remove();
    };
  }

  function createThreeMotionStage(stage) {
    const THREE = window.THREE;
    if (!THREE || !supportsWebGL()) return false;

    clearMotionStage(stage);

    let renderer;
    try {
      renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' });
    } catch (error) {
      return false;
    }

    const canvas = renderer.domElement;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(44, 1, 0.1, 1000);
    const group = new THREE.Group();
    const reduced = prefersReducedMotion();
    let raf = null;

    canvas.setAttribute('aria-hidden', 'true');
    stage.appendChild(canvas);
    camera.position.z = 74;
    scene.add(group);

    const coreGeometry = new THREE.IcosahedronGeometry(12, 1);
    const coreMaterial = new THREE.MeshBasicMaterial({
      color: 0xdbba72,
      wireframe: true,
      transparent: true,
      opacity: 0.72,
    });
    const core = new THREE.Mesh(coreGeometry, coreMaterial);
    group.add(core);

    const torusMaterial = new THREE.MeshBasicMaterial({ color: 0xe8f2f5, wireframe: true, transparent: true, opacity: 0.26 });
    [
      [34, 0.1, 0.2, 0.5],
      [46, 0.6, -0.35, -0.15],
      [56, -0.45, 0.15, 0.7],
    ].forEach(([radius, x, y, z]) => {
      const torus = new THREE.Mesh(new THREE.TorusGeometry(radius, 0.08, 8, 96), torusMaterial.clone());
      torus.rotation.set(x, y, z);
      group.add(torus);
    });

    const positions = [];
    const colors = [];
    const palette = [new THREE.Color(0xe8f2f5), new THREE.Color(0xdbba72), new THREE.Color(0x0f766e)];
    for (let i = 0; i < 84; i++) {
      const angle = Math.random() * Math.PI * 2;
      const radius = 18 + Math.random() * 44;
      const z = (Math.random() - 0.5) * 28;
      const color = palette[i % 9 === 0 ? 1 : (i % 13 === 0 ? 2 : 0)];
      positions.push(Math.cos(angle) * radius, Math.sin(angle) * radius * 0.48, z);
      colors.push(color.r, color.g, color.b);
    }
    const pointGeometry = new THREE.BufferGeometry();
    pointGeometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    pointGeometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
    const pointMaterial = new THREE.PointsMaterial({ size: 2.6, transparent: true, opacity: 0.9, vertexColors: true });
    group.add(new THREE.Points(pointGeometry, pointMaterial));

    function resize() {
      const width = Math.max(stage.clientWidth, 1);
      const height = Math.max(stage.clientHeight, 1);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
    }

    function frame() {
      if (!reduced) {
        group.rotation.y += 0.006;
        group.rotation.x = Math.sin(Date.now() * 0.0007) * 0.14;
        core.rotation.x += 0.008;
        core.rotation.y += 0.011;
      }
      renderer.render(scene, camera);
      raf = window.requestAnimationFrame(frame);
    }

    resize();
    window.addEventListener('resize', resize);
    frame();

    if (window.gsap && !reduced) {
      window.gsap.fromTo(stage, { scale: 0.96, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.8, ease: 'power3.out' });
    }

    stage.dataset.boStageBound = 'three';
    stage._boStageCleanup = () => {
      window.cancelAnimationFrame(raf);
      window.removeEventListener('resize', resize);
      coreGeometry.dispose();
      coreMaterial.dispose();
      torusMaterial.dispose();
      pointGeometry.dispose();
      pointMaterial.dispose();
      renderer.dispose();
      canvas.remove();
    };

    return true;
  }

  function initMotionStages(root, preferThree) {
    root.querySelectorAll('[data-bo-motion-stage]').forEach((stage) => {
      if (preferThree && window.THREE && stage.dataset.boStageBound !== 'three') {
        if (createThreeMotionStage(stage)) return;
      }

      if (!stage.dataset.boStageBound) {
        createCanvasMotionStage(stage);
      }
    });
  }

  function setBusy(button, busy, text) {
    if (!button) return;

    if (busy) {
      button.dataset.originalText = button.textContent;
      button.disabled = true;
      button.textContent = text;
      return;
    }

    button.disabled = false;
    button.textContent = button.dataset.originalText || button.textContent;
  }

  function showAlert(container, message) {
    const current = container.querySelector('.bo-alert[data-js-alert="1"]');
    if (current) current.remove();

    const alert = document.createElement('div');
    alert.className = 'bo-alert';
    alert.dataset.jsAlert = '1';
    alert.textContent = message;
    container.prepend(alert);
  }

  function isBancaUrl(url) {
    return url.origin === window.location.origin && url.pathname.startsWith('/banca-online-2026');
  }

  function bancaRoutePayload(url) {
    const segments = url.pathname.split('/').filter(Boolean);
    const baseIndex = segments.indexOf('banca-online-2026');

    return {
      country: segments[baseIndex + 1] || '',
      plan: segments[baseIndex + 2] || '',
      status: url.searchParams.get('status') || url.searchParams.get('selected_case_status') || '',
      path: url.pathname,
    };
  }

  function trackBancaEvent(event, payload) {
    const token = csrfToken();
    if (!token) return;

    fetch('/banca-online-2026/eventos', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ event, payload: payload || {} }),
      keepalive: true,
    }).catch(() => {});
  }

  function isLandingDocument(doc) {
    return Boolean(doc.querySelector('.bo-intro') && doc.querySelector('.bo-case-panel') && doc.querySelector('.bo-strategy-grid'));
  }

  function replaceFromDocument(doc, selector) {
    const current = document.querySelector(selector);
    const next = doc.querySelector(selector);

    if (current && next) {
      current.replaceWith(next);
    }
  }

  function replaceOptionalFromDocument(doc, selector, fallbackPreviousSelector) {
    const current = document.querySelector(selector);
    const next = doc.querySelector(selector);

    if (current && next) {
      current.replaceWith(next);
      return;
    }

    if (current && !next) {
      current.remove();
      return;
    }

    if (!current && next) {
      const previous = document.querySelector(fallbackPreviousSelector);
      if (previous) previous.insertAdjacentElement('afterend', next);
    }
  }

  function replaceStrategyRevealRow(doc) {
    const current = document.querySelector('.bo-strategy-grid + .bo-reveal-row');
    const next = doc.querySelector('.bo-strategy-grid + .bo-reveal-row');

    if (current && next) {
      current.replaceWith(next);
      return;
    }

    if (current && !next) {
      current.remove();
      return;
    }

    if (!current && next) {
      const grid = document.querySelector('.bo-strategy-grid');
      if (grid) grid.insertAdjacentElement('afterend', next);
    }
  }

  function patchLandingDocument(doc, target, replace) {
    document.title = doc.title;
    replaceFromDocument(doc, '.bo-brand');
    replaceFromDocument(doc, '.bo-country-tabs');
    replaceFromDocument(doc, '.bo-switch-copy');
    replaceFromDocument(doc, '.bo-case-panel');
    replaceOptionalFromDocument(doc, '.bo-recommendation-band', '.bo-case-panel');
    replaceFromDocument(doc, '.bo-strategy-grid');
    replaceStrategyRevealRow(doc);
    replaceFromDocument(doc, '.bo-note');

    if (replace) {
      window.history.replaceState({}, '', target.href);
    } else {
      window.history.pushState({}, '', target.href);
    }

    initRevealToggles(document);
    animateCurrentView(document);
  }

  async function navigate(url, replace) {
    const target = new URL(url, window.location.href);
    if (!isBancaUrl(target)) {
      window.location.href = target.href;
      return;
    }

    const response = await fetch(target.href, {
      headers: {
        Accept: 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) {
      window.location.href = target.href;
      return;
    }

    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const nextTopbar = doc.querySelector('.bo-topbar');
    const nextMain = doc.querySelector('main');
    const currentTopbar = document.querySelector('.bo-topbar');
    const currentMain = document.querySelector('main');

    if (!nextMain || !currentMain) {
      window.location.href = target.href;
      return;
    }

    if (isLandingDocument(document) && isLandingDocument(doc)) {
      patchLandingDocument(doc, target, replace);
      return;
    }

    document.title = doc.title;

    if (nextTopbar && currentTopbar) {
      currentTopbar.replaceWith(nextTopbar);
    }

    currentMain.replaceWith(nextMain);

    if (replace) {
      window.history.replaceState({}, '', target.href);
    } else {
      window.history.pushState({}, '', target.href);
    }

    window.scrollTo({ top: 0, behavior: 'instant' });
    init();
  }

  function bindNavigation() {
    if (state.navigationBound) return;
    state.navigationBound = true;

    document.addEventListener('click', function (event) {
      const link = event.target.closest('a');
      if (!link || link.target || link.hasAttribute('download')) return;

      const target = new URL(link.href, window.location.href);
      if (target.pathname === window.location.pathname && target.search === window.location.search && target.hash) {
        const anchor = document.querySelector(target.hash);
        if (!anchor) return;

        event.preventDefault();
        anchor.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
        window.history.pushState({}, '', target.href);
        return;
      }

      if (!isBancaUrl(target)) return;

      event.preventDefault();
      const payload = bancaRoutePayload(target);

      if (link.closest('.bo-case-option')) {
        trackBancaEvent('bo_case_status_selected', payload);
      } else if (link.closest('.bo-country-tabs')) {
        trackBancaEvent('bo_nationality_selected', payload);
      }

      navigate(target.href, false).catch(() => {
        window.location.href = target.href;
      });
    });

    if (!state.popBound) {
      state.popBound = true;
      window.addEventListener('popstate', function () {
        navigate(window.location.href, true).catch(() => window.location.reload());
      });
    }
  }

  function initConfigurator(root) {
    const form = root.querySelector('#bancaCheckoutForm');
    if (!form || form.dataset.boBound === '1') return;
    form.dataset.boBound = '1';

    const totalNode = root.querySelector('#totalAmount');
    const selectedPackageName = root.querySelector('#selectedPackageName');
    const selectedList = root.querySelector('#selectedList');
    const options = Array.from(root.querySelectorAll('.package-option'));
    const emailInput = root.querySelector('#emailLookup');
    const lookupStatus = root.querySelector('#lookupStatus');
    const lookupExpediente = root.querySelector('#boLookupExpediente');
    const cartResume = root.querySelector('#boCartResume');
    const cartResumeTitle = root.querySelector('#boCartResumeTitle');
    const cartResumeText = root.querySelector('#boCartResumeText');
    const cartContinue = root.querySelector('#boCartContinue');
    const cartClear = root.querySelector('#boCartClear');
    let lookupTimer = null;
    let lookupController = null;

    function lookupRouteContext() {
      const actionUrl = new URL(form.action, window.location.href);
      const segments = actionUrl.pathname.split('/').filter(Boolean);
      const baseIndex = segments.indexOf('banca-online-2026');
      const countryInput = form.querySelector('input[name="country"]');

      return {
        country: segments[baseIndex + 1] || (countryInput ? countryInput.value : ''),
        plan: segments[baseIndex + 2] || segments[baseIndex + 1] || '',
      };
    }

    function fieldValue(name) {
      const field = form.querySelector(`[name="${name}"]`);
      return field ? field.value : '';
    }

    function currentCartContext() {
      const context = lookupRouteContext();

      return {
        country: context.country,
        plan: context.plan,
        selected_case_status: fieldValue('selected_case_status'),
        entry_point: fieldValue('entry_point'),
      };
    }

    function selectedPackageInput() {
      return options.find((input) => input.checked);
    }

    function cartMatchesCurrentContext(cart) {
      if (!cart || cart.type !== 'banca_online_2026') return false;

      const context = currentCartContext();
      const countryMatches = !cart.country || !context.country || cart.country === context.country;
      const planMatches = !cart.plan || !context.plan || cart.plan === context.plan;

      return countryMatches && planMatches;
    }

    function selectedPackageSnapshot(input) {
      const selected = input || selectedPackageInput();
      if (!selected) {
        return {
          package_id: '',
          package_title: '',
          total: 0,
          total_label: '',
        };
      }

      return {
        package_id: selected.value,
        package_title: selected.dataset.name || '',
        total: Number(selected.dataset.price || 0),
        total_label: money(selected.dataset.price || 0),
      };
    }

    function formCart(status, extra) {
      return {
        type: 'banca_online_2026',
        status: status || 'draft',
        ...currentCartContext(),
        ...selectedPackageSnapshot(),
        email: emailInput ? emailInput.value.trim() : '',
        page_url: window.location.href,
        ...(extra || {}),
      };
    }

    function renderCartResume(cart) {
      if (!cartResume) return;

      const current = cart || readBancaCart();
      if (!current || !cartMatchesCurrentContext(current)) {
        cartResume.classList.add('is-hidden');
        return;
      }

      const hasCheckout = Boolean(current.payment_url);
      const packageTitle = current.package_title || 'alcance seleccionado';
      const email = current.email ? ` para ${current.email}` : '';

      if (cartResumeTitle) {
        cartResumeTitle.textContent = hasCheckout ? 'Pago pendiente guardado' : 'Progreso guardado';
      }

      if (cartResumeText) {
        cartResumeText.textContent = hasCheckout
          ? `Puedes continuar el pago de ${packageTitle}${email} sin repetir la seleccion.`
          : `Tu seleccion${email} queda guardada en este navegador. Si cierras la pagina, podras volver y continuar.`;
      }

      if (cartContinue) {
        cartContinue.classList.toggle('is-hidden', !hasCheckout);
        if (hasCheckout) {
          cartContinue.href = current.payment_url;
        }
      }

      cartResume.classList.remove('is-hidden');
    }

    function persistDraftCart(status, extra) {
      const hasDraft = Boolean(selectedPackageInput() || (emailInput && emailInput.value.trim()));
      if (!hasDraft && status === 'draft') return null;

      const cart = writeBancaCart(formCart(status, extra));
      renderCartResume(cart);

      return cart;
    }

    function persistCheckoutCart(checkout) {
      if (!checkout) return null;

      return writeBancaCart(formCart('checkout_created', {
        token: checkout.token || '',
        payment_url: checkout.payment_url || '',
        process_url: checkout.process_url || '',
        thank_you_url: checkout.thank_you_url || '',
        requested_service: checkout.requested_service || '',
        plan_title: checkout.plan_title || '',
        package_title: checkout.package_title || selectedPackageSnapshot().package_title,
        currency: checkout.currency || 'EUR',
        total: checkout.contract_total || checkout.total || 0,
        total_label: checkout.contract_total_label || checkout.total_label || '',
        checkout_total: checkout.total || 0,
        checkout_total_label: checkout.total_label || '',
      }));
    }

    function restoreCart() {
      const cart = readBancaCart();
      if (!cart || !cartMatchesCurrentContext(cart)) return;

      if (cart.package_id) {
        const packageInput = options.find((input) => input.value === String(cart.package_id) && !input.disabled);
        if (packageInput) packageInput.checked = true;
      }

      if (cart.email && emailInput && !emailInput.value) {
        emailInput.value = cart.email;
      }

      refreshCards();
      renderCartResume(cart);
    }

    function setLookupStatus(message, tone) {
      if (!lookupStatus) return;

      lookupStatus.textContent = message || '';
      lookupStatus.classList.toggle('is-error', tone === 'error');
      lookupStatus.classList.toggle('is-success', tone === 'success');
    }

    function renderLookupExpediente(context) {
      if (!lookupExpediente) return;

      lookupExpediente.replaceChildren();

      if (!context || (!context.visible && !context.has_private_context)) {
        lookupExpediente.classList.add('is-hidden');
        return;
      }

      const title = document.createElement('h3');
      const copy = document.createElement('p');
      title.textContent = context.stage_label || 'Expediente asociado';
      copy.textContent = context.summary || 'Encontramos informacion de expediente asociada a este correo.';
      lookupExpediente.append(title, copy);

      if (context.visible && context.next_action && context.next_action.title) {
        const action = document.createElement('p');
        action.innerHTML = `<strong>Siguiente paso:</strong> ${escapeHtml(context.next_action.title)}`;
        lookupExpediente.append(action);
      }

      if (context.visible && context.documents) {
        const pending = Number(context.documents.pending_count || 0);
        const missing = Number(context.documents.missing_count || 0);
        if (pending || missing) {
          const docs = document.createElement('p');
          docs.textContent = `${pending} documento(s) pendiente(s), ${missing} documento(s) no disponible(s).`;
          lookupExpediente.append(docs);
        }
      }

      lookupExpediente.classList.remove('is-hidden');
    }

    function focusEmailLookup() {
      if (!emailInput) return;

      const panel = emailInput.closest('.bo-client-panel') || emailInput;
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.setTimeout(() => emailInput.focus({ preventScroll: true }), 320);
    }

    function renderSelectedList(selected) {
      if (!selectedList) return;

      selectedList.replaceChildren();
      selected.forEach((entry) => {
        const service = typeof entry === 'string' ? { name: entry } : entry;
        const item = document.createElement('li');
        const icon = document.createElement('i');
        const copy = document.createElement('span');
        const label = document.createElement('strong');
        icon.className = 'fas fa-check';
        copy.className = 'bo-service-line';
        label.textContent = service.name || 'Servicio incluido';
        copy.append(label);

        if (service.description) {
          const description = document.createElement('small');
          description.textContent = service.description;
          copy.append(description);
        }

        if (service.price !== undefined) {
          const price = document.createElement('span');
          price.textContent = `${money(service.price)} EUR`;
          copy.append(price);
        }

        item.append(icon, copy);
        selectedList.appendChild(item);
      });
    }

    function refreshCards() {
      const selectedInput = selectedPackageInput();

      options.forEach((input) => {
        const card = input.closest('.bo-package-card');
        if (!card) return;

        card.classList.toggle('selected', input.checked);

        const selectLabel = card.querySelector('.bo-package-select');
        if (!selectLabel) return;

        const label = input.checked
          ? (selectLabel.dataset.selectedLabel || 'Seleccionado')
          : (selectLabel.dataset.selectLabel || 'Seleccionar alcance');
        const iconClass = input.checked ? 'fas fa-check' : 'fas fa-arrow-right';

        selectLabel.replaceChildren(document.createTextNode(label + ' '));

        if (!input.disabled) {
          const icon = document.createElement('i');
          icon.className = iconClass;
          icon.setAttribute('aria-hidden', 'true');
          selectLabel.appendChild(icon);
        }
      });

      if (!selectedInput) {
        if (totalNode) totalNode.textContent = money(0);
        if (selectedPackageName) selectedPackageName.textContent = 'Selecciona una opcion';
        renderSelectedList([]);
        return;
      }

      let components = [];
      try {
        components = JSON.parse(selectedInput.dataset.components || '[]');
      } catch (error) {
        components = [];
      }

      if (totalNode) totalNode.textContent = money(selectedInput.dataset.price);
      if (selectedPackageName) selectedPackageName.textContent = selectedInput.dataset.name || 'Alcance seleccionado';
      renderSelectedList(components);
    }

    async function verifyEmail() {
      if (!emailInput || !lookupStatus) return true;

      if (lookupController) lookupController.abort();
      lookupController = new AbortController();
      emailInput.setCustomValidity('');

      try {
        const email = emailInput.value.trim();
        if (!email) {
          setLookupStatus('');
          renderLookupExpediente(null);
          return true;
        }

        if (!emailInput.checkValidity()) {
          setLookupStatus('');
          renderLookupExpediente(null);
          return false;
        }

        setLookupStatus('Verificando...');

        const context = lookupRouteContext();
        const lookupUrl = new URL('/banca-online-2026/cliente', window.location.origin);
        lookupUrl.searchParams.set('email', email);
        lookupUrl.searchParams.set('country', context.country);
        lookupUrl.searchParams.set('plan', context.plan);

        const response = await fetch(lookupUrl.href, {
          headers: { Accept: 'application/json' },
          signal: lookupController.signal,
        });
        const data = await response.json();
        renderLookupExpediente(data.expediente_context);

        if (data.has_paid_similar_plan) {
          const message = data.message || 'Este correo ya tiene una activacion registrada para este tipo de estrategia. Puede activar una estrategia inicial, una administrativa y una judicial; para registrar a otro familiar, usa un correo diferente.';
          emailInput.setCustomValidity(message);
          setLookupStatus(message, 'error');
          return false;
        }

        if (data.has_pending_similar_activation && data.pending_activation && data.pending_activation.payment_url) {
          const pending = data.pending_activation;
          const pendingCart = writeBancaCart(formCart('checkout_created', {
            token: pending.checkout_token || '',
            payment_url: pending.payment_url,
            package_id: pending.package_id || selectedPackageSnapshot().package_id,
            package_title: pending.package_title || selectedPackageSnapshot().package_title,
            plan: pending.plan_slug || lookupRouteContext().plan,
            plan_title: pending.plan_title || '',
            country: pending.country_slug || lookupRouteContext().country,
            total: pending.total || selectedPackageSnapshot().total,
            total_label: pending.total_label || selectedPackageSnapshot().total_label,
          }));
          renderCartResume(pendingCart);
          setLookupStatus('Tienes una activacion pendiente para esta estrategia. Puedes continuar desde el aviso guardado.', 'success');
          return true;
        }

        if (!data.can_activate_banca_online) {
          const blocker = data.activation_blocker || {};
          const message = blocker.message || 'Este correo aun no esta listo para activar Banca Online.';
          emailInput.setCustomValidity(message);
          setLookupStatus(message, 'error');
          return false;
        }

        if (data.exists) {
          const profile = data.client_stage && data.client_stage.profile;
          const message = profile === 'represented'
            ? 'Cliente representado verificado. Puedes continuar con la activacion.'
            : 'Correo verificado. Puedes activar Banca Online y completar el registro inicial despues.';
          setLookupStatus(message, 'success');
        } else {
          setLookupStatus('Crearemos tu cuenta Sefar con este correo y podras completar el registro inicial despues.', 'success');
        }

        return true;
      } catch (error) {
        if (error.name !== 'AbortError') {
          emailInput.setCustomValidity('');
          setLookupStatus('No se pudo verificar el correo. Intenta de nuevo antes de continuar.', 'error');
          renderLookupExpediente(null);
        }

        return error.name === 'AbortError';
      }
    }

    options.forEach((input) => {
      input.addEventListener('change', () => {
        refreshCards();
        persistDraftCart('draft');
        focusEmailLookup();
      });
    });

    if (emailInput) {
      emailInput.addEventListener('input', () => {
        persistDraftCart('draft');
        window.clearTimeout(lookupTimer);
        lookupTimer = window.setTimeout(verifyEmail, 550);
      });

      emailInput.addEventListener('blur', () => {
        persistDraftCart('draft');
        window.clearTimeout(lookupTimer);
        verifyEmail();
      });
    }

    if (cartClear) {
      cartClear.addEventListener('click', () => {
        clearBancaCart();
        if (cartResume) cartResume.classList.add('is-hidden');
      });
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitButton = form.querySelector('button[type="submit"]');
      const ok = await verifyEmail();

      if (!ok || !form.reportValidity()) return;

      const selectedPackage = selectedPackageInput();
      persistDraftCart('checkout_preparing');
      trackBancaEvent('bo_activation_requested', {
        ...lookupRouteContext(),
        package_id: selectedPackage ? selectedPackage.value : '',
        selected_case_status: (form.querySelector('input[name="selected_case_status"]') || {}).value || '',
        entry_point: (form.querySelector('input[name="entry_point"]') || {}).value || '',
      });

      setBusy(submitButton, true, 'Preparando activacion...');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: new FormData(form),
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
          const emailError = payload.errors && payload.errors.email && payload.errors.email[0];
          if (emailError && emailInput) {
            emailInput.setCustomValidity(emailError);
            setLookupStatus(emailError, 'error');
            emailInput.reportValidity();
            setBusy(submitButton, false);
            return;
          }

          showAlert(form, payload.message || 'No se pudo preparar la activacion.');
          setBusy(submitButton, false);
          return;
        }

        persistCheckoutCart(payload.checkout);
        renderPaymentStep(payload.checkout);
      } catch (error) {
        showAlert(form, 'No se pudo conectar con el servidor.');
        setBusy(submitButton, false);
      }
    });

    refreshCards();
    restoreCart();
  }

  function paymentItems(items) {
    return (items || [])
      .map((item) => {
        const service = typeof item === 'string' ? { name: item } : item;
        const description = service.description
          ? `<small>${escapeHtml(service.description)}</small>`
          : '';
        const price = service.price !== undefined
          ? `<span>${money(service.price)} EUR</span>`
          : '';

        return `
        <li>
          <i class="fas fa-check"></i>
          <span class="bo-service-line">
            <strong>${escapeHtml(service.name)}</strong>
            ${description}
            ${price}
          </span>
        </li>
      `;
      })
      .join('');
  }

  function maxInstallmentsForInitial(options, initialPercent) {
    const rules = (options.rules || [])
      .map((rule) => ({
        min: Number(rule.min_initial_percent || 0),
        max: Number(rule.max_installments || 1),
      }))
      .filter((rule) => rule.min > 0 && rule.max > 0)
      .sort((a, b) => b.min - a.min);
    const match = rules.find((rule) => initialPercent >= rule.min);

    return Math.max(1, Number(match ? match.max : 1));
  }

  function checkoutQuote(options, period, initialPercent, count) {
    const baseTotal = Number(options.base_total || 0);
    const maxCount = maxInstallmentsForInitial(options, initialPercent);
    const installments = Math.max(1, Math.min(maxCount, Number(count || maxCount)));
    const initialAmount = Math.round((baseTotal * initialPercent / 100) * 100) / 100;
    const remainingAmount = Math.max(0, Math.round((baseTotal - initialAmount) * 100) / 100);
    const surchargePercent = Number(period && period.surcharge_percent ? period.surcharge_percent : 0);
    const surchargeAmount = Math.round((remainingAmount * surchargePercent / 100) * 100) / 100;
    const financedAmount = Math.round((remainingAmount + surchargeAmount) * 100) / 100;
    const installmentAmount = installments > 0 ? Math.round((financedAmount / installments) * 100) / 100 : 0;

    return {
      baseTotal,
      maxCount,
      installments,
      initialPercent,
      initialAmount,
      remainingAmount,
      surchargePercent,
      surchargeAmount,
      financedAmount,
      installmentAmount,
      contractTotal: Math.round((initialAmount + financedAmount) * 100) / 100,
    };
  }

  function checkoutPaymentOptionsHtml(checkout) {
    const options = checkout.payment_options || {};
    const periods = options.periods || [];
    const installmentsEnabled = options.installments_enabled && periods.length > 0;
    const minInitial = Number(options.min_initial_percent || 20);
    const maxInitial = Number(options.max_initial_percent || 99);
    const periodChoices = periods.map((period) => `
      <label class="bo-period-choice">
        <input type="radio" name="checkout_period_choice" value="${escapeHtml(period.slug)}">
        <span>
          <strong>${escapeHtml(period.label)}</strong>
          <small>${Number(period.surcharge_percent || 0).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}% recargo</small>
        </span>
      </label>
    `).join('');

    return `
      <div class="bo-payment-plan bo-checkout-payment-options" id="checkoutPaymentOptions" data-payment-options="${escapeHtml(JSON.stringify(options))}">
        <input type="hidden" name="payment_mode" id="checkoutPaymentMode" value="full">
        <input type="hidden" name="payment_period" id="checkoutPaymentPeriod" value="">
        <input type="hidden" name="initial_percent" id="checkoutInitialPercent" value="${escapeHtml(minInitial)}">

        <label class="bo-pay-choice is-active" data-checkout-payment-choice="full">
          <input type="radio" name="checkout_payment_choice" value="full" checked>
          <span>
            <strong>Activacion completa</strong>
            <small id="checkoutFullPaymentLabel">${escapeHtml(checkout.contract_total_label || checkout.total_label)} EUR ahora</small>
          </span>
        </label>

        ${installmentsEnabled ? `
          <label class="bo-pay-choice" data-checkout-payment-choice="installments">
            <input type="radio" name="checkout_payment_choice" value="installments">
            <span>
              <strong>Activacion con cuotas</strong>
              <small id="checkoutInstallmentPaymentLabel">Define inicial, periodo y cuotas</small>
            </span>
          </label>

          <div class="bo-installment-checkout is-hidden" id="checkoutInstallmentControls">
            <div class="bo-period-choice-grid" id="checkoutPeriodChoices">${periodChoices}</div>
            <label class="bo-field bo-range-field">
              <span>Inicial: <strong id="checkoutInitialPercentLabel">${money(minInitial)}%</strong></span>
              <input type="range" id="checkoutInitialSlider" min="${escapeHtml(minInitial)}" max="${escapeHtml(maxInitial)}" step="1" value="${escapeHtml(minInitial)}">
            </label>
            <label class="bo-field bo-installment-count-field" id="checkoutInstallmentCountField">
              <span>Numero de cuotas</span>
              <select name="installments_count" id="checkoutInstallmentsCount"></select>
            </label>
          </div>
        ` : ''}
      </div>
    `;
  }

  function setupCheckoutPaymentOptions(root) {
    const box = root.querySelector('#checkoutPaymentOptions');
    const form = root.querySelector('#payment-form');
    if (!box || !form) return;

    let options = {};
    try {
      options = JSON.parse(box.dataset.paymentOptions || '{}');
    } catch (error) {
      options = {};
    }

    const choices = Array.from(box.querySelectorAll('[data-checkout-payment-choice]'));
    const modeInput = box.querySelector('#checkoutPaymentMode');
    const periodInput = box.querySelector('#checkoutPaymentPeriod');
    const initialInput = box.querySelector('#checkoutInitialPercent');
    const controls = box.querySelector('#checkoutInstallmentControls');
    const periodRadios = Array.from(box.querySelectorAll('input[name="checkout_period_choice"]'));
    const initialSlider = box.querySelector('#checkoutInitialSlider');
    const initialLabel = box.querySelector('#checkoutInitialPercentLabel');
    const countSelect = box.querySelector('#checkoutInstallmentsCount');
    const fullLabel = box.querySelector('#checkoutFullPaymentLabel');
    const installmentLabel = box.querySelector('#checkoutInstallmentPaymentLabel');
    const totalLabel = root.querySelector('#paymentTotalLabel');
    const totalAmount = root.querySelector('#paymentTotalAmount');
    const breakdown = root.querySelector('[data-payment-breakdown]');
    const submitButton = form.querySelector('#submit-button');
    const periods = options.periods || [];

    function currentMode() {
      return modeInput ? modeInput.value : 'full';
    }

    function selectedPeriod() {
      const selected = periodRadios.find((radio) => radio.checked) || periodRadios[0];
      if (selected && !selected.checked) selected.checked = true;

      return periods.find((period) => period.slug === (selected ? selected.value : '')) || periods[0] || null;
    }

    function setMode(mode) {
      if (mode === 'installments' && (!options.installments_enabled || periods.length === 0)) {
        mode = 'full';
      }

      if (modeInput) modeInput.value = mode;
      choices.forEach((choice) => {
        const active = choice.dataset.checkoutPaymentChoice === mode;
        const radio = choice.querySelector('input[type="radio"]');
        choice.classList.toggle('is-active', active);
        if (radio) radio.checked = active;
      });
      if (controls) controls.classList.toggle('is-hidden', mode !== 'installments');
    }

    function fillCounts(quote) {
      if (!countSelect) return quote.installments;

      const previous = Number(countSelect.value || quote.maxCount);
      const selectedCount = Math.max(1, Math.min(quote.maxCount, previous));
      countSelect.replaceChildren();

      for (let count = 1; count <= quote.maxCount; count++) {
        const countQuote = checkoutQuote(options, selectedPeriod(), quote.initialPercent, count);
        const option = document.createElement('option');
        option.value = String(count);
        option.textContent = `${count} cuotas de ${money(countQuote.installmentAmount)} EUR`;
        option.selected = count === selectedCount;
        countSelect.appendChild(option);
      }

      return selectedCount;
    }

    function renderBreakdown(mode, quote, period) {
      if (!breakdown) return;

      if (mode !== 'installments') {
        const discount = Number(breakdown.dataset.discount || 0);

        if (discount > 0) {
          breakdown.classList.remove('is-hidden');
          breakdown.innerHTML = `
            <span>Subtotal <strong>${escapeHtml(breakdown.dataset.subtotalLabel || money(quote.baseTotal))} EUR</strong></span>
            <span>Descuento <strong>-${escapeHtml(breakdown.dataset.discountLabel || money(discount))} EUR</strong></span>
          `;
        } else {
          breakdown.innerHTML = '';
          breakdown.classList.add('is-hidden');
        }
        return;
      }

      breakdown.classList.remove('is-hidden');
      breakdown.innerHTML = `
        <span>Total base <strong>${money(quote.baseTotal)} EUR</strong></span>
        <span>Inicial hoy (${money(quote.initialPercent)}%) <strong>${money(quote.initialAmount)} EUR</strong></span>
        <span>Saldo financiado <strong>${money(quote.remainingAmount)} EUR</strong></span>
        ${quote.surchargeAmount > 0 ? `<span>Recargo ${money(quote.surchargePercent)}% <strong>${money(quote.surchargeAmount)} EUR</strong></span>` : ''}
        <span>${quote.installments} cuotas ${escapeHtml(period ? period.plural_label : '')} <strong>${money(quote.installmentAmount)} EUR</strong></span>
      `;
    }

    function refresh() {
      const mode = currentMode();
      const baseTotal = Number(options.base_total || 0);
      const period = selectedPeriod();
      const minInitial = Number(options.min_initial_percent || 20);
      const initialPercent = Number(initialSlider ? initialSlider.value : minInitial);
      let quote = checkoutQuote(options, period, initialPercent, countSelect ? countSelect.value : null);
      const count = fillCounts(quote);
      quote = checkoutQuote(options, period, initialPercent, count);

      if (periodInput) periodInput.value = period ? period.slug : '';
      if (initialInput) initialInput.value = String(initialPercent);
      if (initialLabel) initialLabel.textContent = `${money(initialPercent)}%`;
      if (fullLabel) fullLabel.textContent = `${money(baseTotal)} EUR ahora`;
      if (installmentLabel && period) {
        installmentLabel.textContent = `${money(quote.initialAmount)} EUR inicial + ${quote.installments} cuotas ${period.plural_label} de ${money(quote.installmentAmount)} EUR`;
      }

      const amountDueNow = mode === 'installments' ? quote.initialAmount : baseTotal;
      if (totalLabel) totalLabel.textContent = mode === 'installments' ? 'Importe de activacion hoy' : 'Importe de activacion';
      if (totalAmount) totalAmount.textContent = money(amountDueNow);
      if (submitButton) {
        submitButton.innerHTML = `${mode === 'installments' ? 'Completar inicial' : 'Completar activacion'} <i class="fas fa-credit-card"></i>`;
      }
      renderBreakdown(mode, quote, period);
    }

    if (box.dataset.boCheckoutOptionsBound !== '1') {
      box.dataset.boCheckoutOptionsBound = '1';
      choices.forEach((choice) => {
        choice.addEventListener('click', () => {
          setMode(choice.dataset.checkoutPaymentChoice || 'full');
          refresh();
        });
      });
      periodRadios.forEach((radio) => radio.addEventListener('change', refresh));
      if (initialSlider) initialSlider.addEventListener('input', refresh);
      if (countSelect) countSelect.addEventListener('change', refresh);
    }

    if (periodRadios[0] && !periodRadios.some((radio) => radio.checked)) {
      periodRadios[0].checked = true;
    }
    setMode(currentMode());
    refresh();
  }

  function renderPaymentStep(checkout) {
    const main = document.querySelector('main');
    if (!main) return;
    const recommendationReason = checkout.flow && checkout.flow.recommendation
      ? checkout.flow.recommendation.reason
      : '';

    main.className = 'bo-container';
    main.innerHTML = `
      <section class="bo-payment-title">
        <span class="bo-eyebrow"><i class="fas fa-lock"></i> Pago de activacion seguro</span>
        <h1>Formalizar activacion</h1>
        <p>${escapeHtml(checkout.plan_title)}.</p>
        ${recommendationReason ? `<p class="bo-muted-line">${escapeHtml(recommendationReason)}</p>` : ''}
      </section>

      <div class="bo-payment-layout">
        <section class="bo-panel">
          <h2>Servicios profesionales seleccionados</h2>
          <ul class="bo-payment-items">${paymentItems(checkout.items)}</ul>
        </section>

        <aside class="bo-panel">
          ${checkout.stripe_key ? '' : '<div class="bo-alert">No esta configurada la clave publica de Stripe para este servicio.</div>'}
          <div class="bo-payment-breakdown is-hidden" data-payment-breakdown data-subtotal-label="${escapeHtml(checkout.subtotal_label)}" data-discount="${escapeHtml(checkout.discount || 0)}" data-discount-label="${escapeHtml(checkout.discount_label)}"></div>
          <div class="bo-total-label" id="paymentTotalLabel">Importe de activacion</div>
          <div class="bo-total"><span id="paymentTotalAmount">${escapeHtml(checkout.contract_total_label || checkout.total_label)}</span> <small>${escapeHtml(checkout.currency || 'EUR')}</small></div>

          <form id="payment-form" class="bo-form" data-stripe-key="${escapeHtml(checkout.stripe_key)}" data-process-url="${escapeHtml(checkout.process_url)}" data-success-url="${escapeHtml(checkout.thank_you_url)}" data-checkout-token="${escapeHtml(checkout.token)}" data-country="${escapeHtml(checkout.country_slug)}" data-plan="${escapeHtml(checkout.plan_slug || (checkout.flow && checkout.flow.plan_slug) || '')}" data-package-id="${escapeHtml(checkout.package_id)}" data-entry-point="${escapeHtml(checkout.flow && checkout.flow.entry_point)}" data-case-status="${escapeHtml(checkout.flow && checkout.flow.selected_case_status)}">
            ${checkoutPaymentOptionsHtml(checkout)}
            <div class="bo-field-grid">
              <label class="bo-field">
                <span>Nombres</span>
                <input type="text" name="first_name" required>
              </label>
              <label class="bo-field">
                <span>Apellidos</span>
                <input type="text" name="last_name" required>
              </label>
            </div>
            <label class="bo-field">
              <span>Correo electronico</span>
              <input type="email" name="email" value="${escapeHtml(checkout.billing && checkout.billing.email)}" required>
            </label>
            <label class="bo-field">
              <span>Telefono</span>
              <input type="tel" name="phone">
            </label>
            <label class="bo-field">
              <span>Direccion</span>
              <input type="text" name="address_line1" required>
            </label>
            <label class="bo-field">
              <span>Direccion adicional</span>
              <input type="text" name="address_line2">
            </label>
            <div class="bo-field-grid">
              <label class="bo-field">
                <span>Ciudad</span>
                <input type="text" name="city" required>
              </label>
              <label class="bo-field">
                <span>Estado o provincia</span>
                <input type="text" name="state">
              </label>
            </div>
            <div class="bo-field-grid">
              <label class="bo-field">
                <span>Codigo postal</span>
                <input type="text" name="postal_code" required>
              </label>
              <label class="bo-field">
                <span>Pais ISO 2</span>
                <input type="text" name="country" value="VE" maxlength="2" minlength="2" required>
              </label>
            </div>
            <label class="bo-field">
              <span>Tarjeta</span>
              <div id="card-element"></div>
            </label>
            <div class="bo-card-errors" id="card-errors"></div>
            <button class="bo-button bo-button-primary" id="submit-button" type="submit" ${checkout.stripe_key ? '' : 'disabled'}>
              Completar activacion <i class="fas fa-credit-card"></i>
            </button>
          </form>
        </aside>
      </div>
    `;

    window.history.pushState({}, '', checkout.payment_url);
    window.scrollTo({ top: 0, behavior: 'instant' });
    initPaymentForm(document);
  }

  function loadStripeJs() {
    if (window.Stripe) return Promise.resolve();
    if (state.stripeLoading) return state.stripeLoading;

    state.stripeLoading = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });

    return state.stripeLoading;
  }

  async function initPaymentForm(root) {
    const form = root.querySelector('#payment-form');
    if (!form || form.dataset.boPaymentBound === '1') return;
    form.dataset.boPaymentBound = '1';

    const stripeKey = form.dataset.stripeKey;
    const processUrl = form.dataset.processUrl;
    const submitButton = form.querySelector('#submit-button');
    const errorNode = form.querySelector('#card-errors');

    setupCheckoutPaymentOptions(root);
    trackBancaEvent('bo_activation_payment_started', {
      country: form.dataset.country || '',
      plan: form.dataset.plan || '',
      package_id: form.dataset.packageId || '',
      entry_point: form.dataset.entryPoint || '',
      selected_case_status: form.dataset.caseStatus || '',
      checkout_token: form.dataset.checkoutToken || '',
      payment_url: window.location.href,
    });

    if (!stripeKey) return;

    try {
      await loadStripeJs();
    } catch (error) {
      errorNode.textContent = 'No se pudo cargar la pasarela de pago.';
      submitButton.disabled = true;
      return;
    }

    const stripe = window.Stripe(stripeKey);
    const elements = stripe.elements();
    const card = elements.create('card', {
      hidePostalCode: true,
      style: {
        base: {
          fontSize: '16px',
          color: '#093143',
          '::placeholder': { color: '#607783' },
        },
        invalid: { color: '#ac2630' },
      },
    });

    card.mount('#card-element');
    card.on('change', function (event) {
      errorNode.textContent = event.error ? event.error.message : '';
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;

      setBusy(submitButton, true, 'Procesando...');
      errorNode.textContent = '';

      const formData = new FormData(form);
      const billingDetails = {
        name: `${formData.get('first_name')} ${formData.get('last_name')}`.trim(),
        email: formData.get('email'),
        phone: formData.get('phone') || undefined,
        address: {
          line1: formData.get('address_line1'),
          line2: formData.get('address_line2') || undefined,
          city: formData.get('city'),
          state: formData.get('state') || undefined,
          postal_code: formData.get('postal_code'),
          country: String(formData.get('country')).toUpperCase(),
        },
      };

      const result = await stripe.createPaymentMethod({
        type: 'card',
        card: card,
        billing_details: billingDetails,
      });

      if (result.error) {
        errorNode.textContent = result.error.message;
        setBusy(submitButton, false);
        return;
      }

      formData.append('payment_method_id', result.paymentMethod.id);

      try {
        const response = await fetch(processUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: formData,
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
          errorNode.textContent = payload.message || 'No se pudo procesar el pago.';
          setBusy(submitButton, false);
          return;
        }

        renderThankYou(payload.thank_you, payload.redirect_url);
      } catch (error) {
        errorNode.textContent = 'No se pudo conectar con la pasarela de pago.';
        setBusy(submitButton, false);
      }
    });
  }

  function renderThankYou(thankYou, url) {
    const main = document.querySelector('main');
    if (!main) return;

    clearBancaCart();

    const name = thankYou && thankYou.name ? `, ${escapeHtml(thankYou.name)}` : '';
    const paymentPlan = thankYou && thankYou.payment_plan ? thankYou.payment_plan : {};
    const isInstallmentPayment = paymentPlan.mode === 'installments';
    const nextAction = thankYou && thankYou.next_url ? `
      <div class="bo-confirm-actions">
        <a class="bo-button bo-button-primary" href="${escapeHtml(thankYou.next_url)}">
          ${escapeHtml(thankYou.next_label || 'Continuar en la plataforma')} <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    ` : '';
    const installmentSummary = isInstallmentPayment ? `
      <div class="bo-payment-breakdown">
        <span>Total del plan <strong>${money(paymentPlan.contract_total)} ${escapeHtml(thankYou.currency || 'EUR')}</strong></span>
        <span>Inicial recibido <strong>${escapeHtml(thankYou.total_label)} ${escapeHtml(thankYou.currency || 'EUR')}</strong></span>
        <span>${escapeHtml(paymentPlan.installments_count || 0)} cuotas ${escapeHtml(paymentPlan.period_plural_label || 'mensuales')} <strong>${money(paymentPlan.installment_amount)} ${escapeHtml(thankYou.currency || 'EUR')}</strong></span>
      </div>
    ` : '';

    main.className = 'bo-confirm-wrap';
    main.innerHTML = `
      <section class="bo-confirm-card">
        <img class="bo-confirm-logo" src="/img/logo2.png" alt="Sefar Universal">
        <div class="bo-confirm-badge"><i class="fas fa-check-circle"></i> ${isInstallmentPayment ? 'Inicial recibida' : 'Activacion recibida'}</div>
        <h1>Gracias${name}.</h1>
        <p>Tu activacion de Banca Online 2026 fue registrada correctamente. El equipo de Sefar Universal continuara el seguimiento operativo del servicio seleccionado.</p>
        ${installmentSummary}
        <div class="bo-confirm-total">${escapeHtml(thankYou && thankYou.total_label)} ${escapeHtml(thankYou && thankYou.currency ? thankYou.currency : 'EUR')}</div>
        <ul class="bo-confirm-list">${paymentItems(thankYou ? thankYou.items : [])}</ul>
        ${nextAction}
      </section>
    `;

    if (url) window.history.pushState({}, '', url);
    window.scrollTo({ top: 0, behavior: 'instant' });
  }

  function setRevealButtonLabel(button, expanded) {
    const label = expanded
      ? (button.dataset.hideLabel || 'Ocultar')
      : (button.dataset.showLabel || 'Ver todo');
    const icon = document.createElement('i');

    icon.className = expanded ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    icon.setAttribute('aria-hidden', 'true');
    button.replaceChildren(document.createTextNode(`${label} `), icon);
  }

  function initRevealToggles(root) {
    root.querySelectorAll('[data-bo-reveal]').forEach((button) => {
      if (button.dataset.boRevealBound === '1') return;
      button.dataset.boRevealBound = '1';
      setRevealButtonLabel(button, button.getAttribute('aria-expanded') === 'true');

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        const scope = button.closest(button.dataset.boRevealScope || 'section') || root;
        const targets = Array.from(scope.querySelectorAll(button.dataset.boReveal || '.bo-reveal-target'));
        const expanded = button.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !expanded;

        targets.forEach((target) => {
          target.classList.toggle('is-revealed', nextExpanded);
        });

        button.setAttribute('aria-expanded', String(nextExpanded));
        setRevealButtonLabel(button, nextExpanded);

        if (nextExpanded && window.gsap && !prefersReducedMotion()) {
          window.gsap.fromTo(targets, { opacity: 0, y: 6 }, {
            opacity: 1,
            y: 0,
            duration: 0.28,
            stagger: 0.025,
            ease: 'power2.out',
          });
        }
      });
    });
  }

  function animateCurrentView(root) {
    if (!window.gsap || prefersReducedMotion()) return;

    const targets = Array.from(root.querySelectorAll([
      '.bo-intro-main',
      '.bo-switch-panel',
      '.bo-case-panel',
      '.bo-recommendation-band',
      '.bo-strategy-card',
      '.bo-config-head',
      '.bo-rationale-panel',
      '.bo-package-card',
      '.bo-package-summary',
      '.bo-client-panel',
      '.bo-payment-title',
      '.bo-payment-layout',
      '.bo-confirm-card',
    ].join(','))).filter((element) => element.dataset.boAnimated !== '1');

    if (targets.length > 0) {
      targets.forEach((element) => {
        element.dataset.boAnimated = '1';
      });

      window.gsap.fromTo(targets, { opacity: 0, y: 18 }, {
        opacity: 1,
        y: 0,
        duration: 0.62,
        stagger: 0.055,
        ease: 'power3.out',
      });
    }

    const accents = root.querySelectorAll('.bo-case-option.selected .bo-case-dot, .bo-package-card.selected .bo-package-select, .bo-recommendation-band .bo-button');
    if (accents.length > 0) {
      window.gsap.fromTo(accents, { scale: 0.94 }, {
        scale: 1,
        duration: 0.5,
        ease: 'back.out(1.8)',
        overwrite: true,
      });
    }
  }

  function initInteractiveMotion(root) {
    if (prefersReducedMotion()) return;

    root.querySelectorAll('[data-bo-motion-stage]').forEach((stage) => {
      if (stage.dataset.boTiltBound === '1') return;
      stage.dataset.boTiltBound = '1';

      stage.addEventListener('pointermove', (event) => {
        const rect = stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / Math.max(rect.width, 1) - 0.5;
        const y = (event.clientY - rect.top) / Math.max(rect.height, 1) - 0.5;

        stage.style.transform = `perspective(900px) rotateX(${(-y * 5).toFixed(2)}deg) rotateY(${(x * 6).toFixed(2)}deg) translateY(-2px)`;
      });

      stage.addEventListener('pointerleave', () => {
        stage.style.transform = '';
      });
    });
  }

  function init() {
    if (window.location.pathname.startsWith('/banca-online-2026/gracias/')) {
      clearBancaCart();
    }

    initConstellation();
    bindNavigation();
    initRevealToggles(document);
    initMotionStages(document);
    initInteractiveMotion(document);
    initConfigurator(document);
    initPaymentForm(document);
    animateCurrentView(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
