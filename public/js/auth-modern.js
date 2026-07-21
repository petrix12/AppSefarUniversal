(function () {
  if (window.__sefarAuthModernReady) {
    return;
  }

  window.__sefarAuthModernReady = true;

  const assets = {
    gsap: '/js/gsap.js',
    three: '/js/three.js',
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

  const animateInterfaceNative = (shell) => {
    shell.classList.add('sefar-auth-native-motion');
    shell.dataset.sefarAuthReducedMotion = prefersReducedMotion() ? 'true' : 'false';

    if (prefersReducedMotion()) {
      shell.dataset.sefarAuthMotion = 'native-reduced';
      return;
    }

    shell.dataset.sefarAuthMotion = 'native';
  };

  const animateInterface = async (shell) => {
    const nativeStartedAt = window.performance ? window.performance.now() : Date.now();

    animateInterfaceNative(shell);

    const gsap = await loadScript(assets.gsap, 'gsap');

    if (!gsap) {
      return;
    }

    if (prefersReducedMotion()) {
      shell.dataset.sefarAuthMotion = 'native-reduced';
      return;
    }

    shell.dataset.sefarAuthMotion = 'gsap';
    shell.classList.add('sefar-auth-gsap-motion');

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

        const nativeHasSettled = ((window.performance ? window.performance.now() : Date.now()) - nativeStartedAt) > 1200;

        if (!nativeHasSettled) {
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

          gsap.from('.sefar-login-text, .sefar-login-field, .sefar-login-options, .sefar-login-actions, .sefar-login-button, .sefar-login-link-button, .sefar-login-register', {
            autoAlpha: 0,
            y: 12,
            duration: 0.46,
            delay: 0.34,
            stagger: 0.045,
            ease: 'power2.out',
            clearProps: 'visibility,opacity,transform',
          });
        }

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
      return () => {};
    }

    shell.dataset.sefarAuthRenderer = 'fallback-tree';

    let frameId = null;
    let ratio = 1;
    let width = 1;
    let height = 1;
    let lastTime = null;
    let tree = null;

    const branchBlueprint = [
      [[0.46, 0.86], [0.45, 0.72], 9],
      [[0.45, 0.72], [0.44, 0.58], 7],
      [[0.44, 0.58], [0.42, 0.43], 5.6],
      [[0.44, 0.58], [0.31, 0.45], 3.8],
      [[0.42, 0.43], [0.25, 0.28], 2.8],
      [[0.42, 0.43], [0.42, 0.24], 2.8],
      [[0.42, 0.43], [0.58, 0.25], 2.8],
      [[0.31, 0.45], [0.18, 0.35], 2.5],
      [[0.31, 0.45], [0.31, 0.28], 2.5],
      [[0.31, 0.45], [0.43, 0.34], 2.2],
      [[0.58, 0.25], [0.55, 0.12], 2.2],
      [[0.58, 0.25], [0.71, 0.17], 2.2],
      [[0.58, 0.25], [0.82, 0.31], 2.2],
    ];
    const nodeBlueprint = [
      [0.18, 0.35, 34],
      [0.31, 0.28, 32],
      [0.43, 0.34, 31],
      [0.25, 0.28, 34],
      [0.42, 0.24, 32],
      [0.55, 0.12, 33],
      [0.71, 0.17, 34],
      [0.82, 0.31, 32],
    ];

    const buildTree = () => {
      const scale = Math.min(width, height) * (width < 720 ? 0.84 : 0.72);
      const originX = width < 720 ? width * 0.5 : width * 0.38;
      const originY = height * 0.52;
      const mapPoint = ([x, y]) => ({
        x: originX + ((x - 0.46) * scale),
        y: originY + ((y - 0.48) * scale),
      });

      const branches = branchBlueprint.map(([from, to, lineWidth], index) => ({
        from: mapPoint(from),
        to: mapPoint(to),
        lineWidth: Math.max(1.2, lineWidth * Math.max(0.62, scale / 760)),
        delay: index * 0.055,
      }));
      const nodes = nodeBlueprint.map(([x, y, radius], index) => {
        const point = mapPoint([x, y]);

        return {
          x: point.x,
          y: point.y,
          radius: Math.max(15, radius * Math.max(0.52, scale / 760)),
          delay: index * 0.09,
        };
      });
      const leavesCount = width < 720 ? 74 : 116;
      const leaves = Array.from({ length: leavesCount }, (_, index) => {
        const angle = (index / leavesCount) * Math.PI * 2;
        const radiusX = scale * (0.25 + (Math.random() * 0.2));
        const radiusY = scale * (0.14 + (Math.random() * 0.1));

        return {
          x: originX + (Math.cos(angle) * radiusX) + ((Math.random() - 0.5) * scale * 0.18),
          y: originY - (scale * 0.2) + (Math.sin(angle) * radiusY) + ((Math.random() - 0.5) * scale * 0.08),
          size: 5 + Math.random() * 10,
          angle: angle + Math.random(),
          speed: 0.6 + Math.random() * 0.9,
          alpha: 0.32 + Math.random() * 0.32,
        };
      });

      return { branches, nodes, leaves };
    };

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
      tree = buildTree();
    };

    const drawPartialBranch = (branch, progress) => {
      const eased = Math.max(0, Math.min(1, progress));
      const x = branch.from.x + ((branch.to.x - branch.from.x) * eased);
      const y = branch.from.y + ((branch.to.y - branch.from.y) * eased);

      context.save();
      context.shadowBlur = 20;
      context.shadowColor = 'rgba(219, 186, 114, 0.62)';
      context.beginPath();
      context.moveTo(branch.from.x, branch.from.y);
      context.lineTo(x, y);
      context.lineWidth = branch.lineWidth * 1.18;
      context.strokeStyle = 'rgba(219, 186, 114, 0.68)';
      context.stroke();
      context.restore();

      context.beginPath();
      context.moveTo(branch.from.x, branch.from.y);
      context.lineTo(x, y);
      context.lineWidth = Math.max(1, branch.lineWidth * 0.48);
      context.strokeStyle = 'rgba(143, 216, 255, 0.34)';
      context.stroke();
    };

    const draw = (elapsed) => {
      if (!tree) {
        return;
      }

      context.clearRect(0, 0, width, height);
      context.lineCap = 'round';
      context.lineJoin = 'round';
      context.globalCompositeOperation = 'source-over';

      tree.leaves.forEach((leaf) => {
        const sway = Math.sin((elapsed * leaf.speed) + leaf.angle) * 5;
        context.save();
        context.translate(leaf.x + sway, leaf.y + (Math.cos(elapsed + leaf.angle) * 2));
        context.rotate(leaf.angle + (sway * 0.018));
        context.beginPath();
        context.ellipse(0, 0, leaf.size * 0.44, leaf.size, 0, 0, Math.PI * 2);
        context.fillStyle = `rgba(143, 216, 255, ${leaf.alpha})`;
        context.fill();
        context.restore();
      });

      tree.branches.forEach((branch) => {
        const progress = prefersReducedMotion()
          ? 1
          : (Math.sin((elapsed * 0.46) - branch.delay) + 1.24) / 2.24;
        drawPartialBranch(branch, progress);
      });

      tree.nodes.forEach((node) => {
        const pulse = prefersReducedMotion()
          ? 1
          : 1 + (Math.sin((elapsed * 1.1) + node.delay) * 0.045);
        context.beginPath();
        context.arc(node.x, node.y, node.radius * pulse, 0, Math.PI * 2);
        context.shadowBlur = 22;
        context.shadowColor = 'rgba(143, 216, 255, 0.54)';
        context.fillStyle = 'rgba(255, 255, 255, 0.08)';
        context.fill();
        context.lineWidth = Math.max(1.2, node.radius * 0.065);
        context.strokeStyle = 'rgba(219, 186, 114, 0.7)';
        context.stroke();
        context.shadowBlur = 0;
        context.beginPath();
        context.arc(node.x, node.y, node.radius * 0.78, 0, Math.PI * 2);
        context.lineWidth = 1.3;
        context.strokeStyle = 'rgba(143, 216, 255, 0.38)';
        context.stroke();
      });
    };

    const render = (timestamp = (window.performance ? window.performance.now() : Date.now())) => {
      const elapsed = timestamp / 1000;

      lastTime = timestamp;
      shell.dataset.sefarAuthFallbackFrames = String(Number(shell.dataset.sefarAuthFallbackFrames || 0) + 1);
      draw(elapsed);
      frameId = window.requestAnimationFrame(render);
    };

    const resume = () => {
      lastTime = null;
    };

    const observer = window.ResizeObserver
      ? new ResizeObserver(() => {
        resize();
        draw((window.performance ? window.performance.now() : Date.now()) / 1000);
      })
      : null;

    if (observer) {
      observer.observe(shell);
    } else {
      window.addEventListener('resize', resize);
    }

    document.addEventListener('visibilitychange', resume);
    window.addEventListener('pageshow', resume);

    const cleanup = () => {
      if (frameId) {
        window.cancelAnimationFrame(frameId);
        frameId = null;
      }

      if (observer) {
        observer.disconnect();
      } else {
        window.removeEventListener('resize', resize);
      }

      document.removeEventListener('visibilitychange', resume);
      window.removeEventListener('pageshow', resume);
    };

    window.addEventListener('beforeunload', cleanup, { once: true });

    resize();
    render();

    return cleanup;
  };

  const setupThreeScene = async (shell, canvas, stopFallback, fallbackCanvas) => {
    try {
      const THREE = window.THREE || await withTimeout(import(assets.three));

      if (!THREE || prefersReducedMotion()) {
        canvas.remove();
        return;
      }

      if (!window.THREE) {
        window.THREE = THREE;
      }

      stopFallback();
      fallbackCanvas.style.display = 'none';
      shell.dataset.sefarAuthRenderer = 'three-tree';

      const gsap = window.gsap || await loadScript(assets.gsap, 'gsap');
      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 80);
      const renderer = new THREE.WebGLRenderer({
        canvas,
        alpha: true,
        antialias: true,
        powerPreference: 'high-performance',
      });
      if (THREE.SRGBColorSpace) {
        renderer.outputColorSpace = THREE.SRGBColorSpace;
      }
      const revealGroup = new THREE.Group();
      const treeGroup = new THREE.Group();
      const branchGroup = new THREE.Group();
      const nodeGroup = new THREE.Group();
      const leafGroup = new THREE.Group();
      const sparkGroup = new THREE.Group();
      const clock = new THREE.Clock();
      let frameId = null;
      let running = true;
      let targetX = 0;
      let targetY = 0;

      const branchSegments = [
        [[0, -2.35, 0], [-0.08, -1.35, 0.05]],
        [[-0.08, -1.35, 0.05], [-0.12, -0.45, 0.02]],
        [[-0.12, -0.45, 0.02], [-0.95, 0.15, -0.04]],
        [[-0.12, -0.45, 0.02], [0.02, 0.62, 0.02]],
        [[-0.12, -0.45, 0.02], [0.86, 0.04, -0.06]],
        [[-0.95, 0.15, -0.04], [-1.56, 0.74, 0.02]],
        [[-0.95, 0.15, -0.04], [-0.96, 1.12, 0.05]],
        [[-0.95, 0.15, -0.04], [-0.25, 0.88, -0.02]],
        [[0.02, 0.62, 0.02], [-0.2, 1.62, 0.08]],
        [[0.02, 0.62, 0.02], [0.7, 1.54, -0.02]],
        [[0.86, 0.04, -0.06], [1.3, 0.94, 0.06]],
        [[0.86, 0.04, -0.06], [1.82, 0.58, -0.03]],
      ];
      const nodePositions = [
        [-1.56, 0.74, 0.05],
        [-0.96, 1.12, 0.08],
        [-0.25, 0.88, 0.02],
        [-0.2, 1.62, 0.08],
        [0.7, 1.54, -0.02],
        [1.3, 0.94, 0.06],
        [1.82, 0.58, -0.03],
      ];
      const branchPositions = new Float32Array(branchSegments.length * 6);

      branchSegments.forEach(([from, to], index) => {
        branchPositions.set(from, index * 6);
        branchPositions.set(to, (index * 6) + 3);
      });

      const branchGeometry = new THREE.BufferGeometry();
      branchGeometry.setAttribute('position', new THREE.BufferAttribute(branchPositions, 3));
      const branchMaterial = new THREE.LineBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.78,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const branchTubeMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.64,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const trunkTubeMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.5,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const branchAuraMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.22,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const trunkAuraMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.24,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const branchLines = new THREE.LineSegments(branchGeometry, branchMaterial);
      branchGroup.add(branchLines);

      const tubeMeshes = branchSegments.map(([from, to], index) => {
        const curve = new THREE.CatmullRomCurve3([
          new THREE.Vector3(from[0], from[1], from[2]),
          new THREE.Vector3(to[0], to[1], to[2]),
        ]);
        const tube = new THREE.Mesh(
          new THREE.TubeGeometry(curve, 20, index < 2 ? 0.044 : 0.024, 8, false),
          index < 2 ? trunkTubeMaterial : branchTubeMaterial
        );

        branchGroup.add(tube);

        return tube;
      });
      const auraTubeMeshes = branchSegments.map(([from, to], index) => {
        const curve = new THREE.CatmullRomCurve3([
          new THREE.Vector3(from[0], from[1], from[2]),
          new THREE.Vector3(to[0], to[1], to[2]),
        ]);
        const auraTube = new THREE.Mesh(
          new THREE.TubeGeometry(curve, 20, index < 2 ? 0.12 : 0.065, 10, false),
          index < 2 ? trunkAuraMaterial : branchAuraMaterial
        );

        branchGroup.add(auraTube);

        return auraTube;
      });

      const trunkGeometry = new THREE.BufferGeometry();
      trunkGeometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array([
        -0.16, -2.38, 0.03,
        -0.02, -1.3, 0.08,
        0.12, -2.38, -0.03,
        -0.02, -1.3, 0.08,
        -0.02, -1.3, 0.08,
        0.02, -0.46, 0.02,
      ]), 3));
      const trunk = new THREE.LineSegments(
        trunkGeometry,
        new THREE.LineBasicMaterial({
          color: 0x8fd8ff,
          transparent: true,
          opacity: 0.58,
          depthWrite: false,
          depthTest: false,
          blending: THREE.AdditiveBlending,
        })
      );
      branchGroup.add(trunk);

      const nodeHaloGeometry = new THREE.RingGeometry(0.22, 0.34, 64);
      const nodeGeometry = new THREE.RingGeometry(0.18, 0.22, 64);
      const nodeInnerGeometry = new THREE.RingGeometry(0.115, 0.132, 52);
      const nodeFillGeometry = new THREE.CircleGeometry(0.18, 64);
      const nodeCoreGeometry = new THREE.CircleGeometry(0.045, 32);
      const nodeFillMaterial = new THREE.MeshBasicMaterial({
        color: 0xffffff,
        transparent: true,
        opacity: 0.12,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
      });
      const nodeMaterial = new THREE.MeshBasicMaterial({
        color: 0xffffff,
        transparent: true,
        opacity: 0.78,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const nodeAccentMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.76,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const nodeHaloMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.22,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const nodeHaloAccentMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.26,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const nodeCoreMaterial = new THREE.MeshBasicMaterial({
        color: 0xffffff,
        transparent: true,
        opacity: 0.66,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const nodeHaloMeshes = [];
      const nodeCoreMeshes = [];
      const nodeMeshes = nodePositions.map((position, index) => {
        const node = new THREE.Group();
        const halo = new THREE.Mesh(nodeHaloGeometry, index % 2 ? nodeHaloMaterial : nodeHaloAccentMaterial);
        const fill = new THREE.Mesh(nodeFillGeometry, nodeFillMaterial);
        const outer = new THREE.Mesh(nodeGeometry, index % 2 ? nodeMaterial : nodeAccentMaterial);
        const inner = new THREE.Mesh(
          nodeInnerGeometry,
          index % 2 ? nodeAccentMaterial : nodeMaterial
        );
        const core = new THREE.Mesh(nodeCoreGeometry, nodeCoreMaterial);

        node.position.set(position[0], position[1], position[2]);
        node.add(halo, fill, outer, inner, core);
        node.userData.halo = halo;
        node.userData.core = core;
        nodeHaloMeshes.push(halo);
        nodeCoreMeshes.push(core);
        nodeGroup.add(node);

        return node;
      });

      const leafGeometry = new THREE.CircleGeometry(0.035, 10);
      const leafBlueMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.72,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const leafGoldMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.62,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const leafWhiteMaterial = new THREE.MeshBasicMaterial({
        color: 0xffffff,
        transparent: true,
        opacity: 0.26,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: false,
        blending: THREE.AdditiveBlending,
      });
      const leafMaterials = [leafBlueMaterial, leafGoldMaterial, leafBlueMaterial, leafWhiteMaterial];
      const leafCount = 92;
      const leafMeshes = Array.from({ length: leafCount }, (_, index) => {
        const angle = (index / leafCount) * Math.PI * 2;
        const radiusX = 1.72 + (Math.random() * 0.72);
        const radiusY = 0.92 + (Math.random() * 0.42);
        const leaf = new THREE.Mesh(leafGeometry, leafMaterials[index % leafMaterials.length]);
        const baseScale = 0.95 + Math.random() * 1.85;

        leaf.position.set(
          Math.cos(angle) * radiusX,
          0.75 + (Math.sin(angle) * radiusY),
          -0.18 + ((Math.random() - 0.5) * 0.38)
        );
        leaf.rotation.z = angle;
        leaf.scale.setScalar(baseScale);
        leaf.userData.baseScale = baseScale;
        leaf.userData.speed = 0.55 + Math.random() * 1.2;
        leaf.userData.offset = Math.random() * Math.PI * 2;
        leafGroup.add(leaf);

        return leaf;
      });

      const sparkGeometry = new THREE.SphereGeometry(0.034, 8, 8);
      const sparkColors = [0xffffff, 0xdbba72, 0x8fd8ff, 0xffffff, 0xdbba72];
      const flowParticles = Array.from({ length: 38 }, (_, index) => {
        const route = branchSegments[index % branchSegments.length];
        const spark = new THREE.Mesh(
          sparkGeometry,
          new THREE.MeshBasicMaterial({
            color: sparkColors[index % sparkColors.length],
            transparent: true,
            opacity: 0.7,
            depthWrite: false,
            depthTest: false,
            blending: THREE.AdditiveBlending,
          })
        );

        spark.userData.from = new THREE.Vector3(route[0][0], route[0][1], route[0][2]);
        spark.userData.to = new THREE.Vector3(route[1][0], route[1][1], route[1][2]);
        spark.userData.offset = Math.random();
        spark.userData.speed = 0.18 + Math.random() * 0.22;
        spark.userData.baseScale = 0.58 + Math.random() * 1.3;
        spark.scale.setScalar(0.01);
        sparkGroup.add(spark);

        return spark;
      });

      treeGroup.add(branchGroup, leafGroup, nodeGroup, sparkGroup);
      revealGroup.add(treeGroup);
      scene.add(revealGroup);
      camera.position.z = 6.8;

      if (gsap && !prefersReducedMotion()) {
        shell.dataset.sefarAuthTreeMotion = 'gsap';
        revealGroup.scale.set(0.72, 0.72, 0.72);
        revealGroup.rotation.z = -0.035;
        branchLines.material.opacity = 0;
        trunk.material.opacity = 0;
        branchTubeMaterial.opacity = 0;
        trunkTubeMaterial.opacity = 0;
        branchAuraMaterial.opacity = 0;
        trunkAuraMaterial.opacity = 0;
        nodeFillMaterial.opacity = 0;
        nodeMaterial.opacity = 0;
        nodeAccentMaterial.opacity = 0;
        nodeHaloMaterial.opacity = 0;
        nodeHaloAccentMaterial.opacity = 0;
        nodeCoreMaterial.opacity = 0;
        nodeMeshes.forEach((node) => node.scale.setScalar(0.2));
        nodeHaloMeshes.forEach((halo) => halo.scale.setScalar(0.35));
        nodeCoreMeshes.forEach((core) => core.scale.setScalar(0.2));
        leafMeshes.forEach((leaf) => leaf.scale.multiplyScalar(0.2));
        flowParticles.forEach((spark) => {
          spark.userData.reveal = 0;
        });

        gsap.timeline({ defaults: { ease: 'power3.out' } })
          .to(revealGroup.scale, {
            x: 1,
            y: 1,
            z: 1,
            duration: 1.15,
            ease: 'elastic.out(1, 0.62)',
          }, 0)
          .to(revealGroup.rotation, {
            z: 0,
            duration: 1.2,
            ease: 'power4.out',
          }, 0)
          .to([
            branchLines.material,
            trunk.material,
            branchTubeMaterial,
            trunkTubeMaterial,
            branchAuraMaterial,
            trunkAuraMaterial,
            nodeFillMaterial,
            nodeMaterial,
            nodeAccentMaterial,
            nodeHaloMaterial,
            nodeHaloAccentMaterial,
            nodeCoreMaterial,
          ], {
            opacity: (index) => [0.78, 0.58, 0.62, 0.48, 0.24, 0.26, 0.16, 0.88, 0.84, 0.24, 0.28, 0.74][index],
            duration: 1.05,
            stagger: 0.025,
          }, 0.04)
          .to(nodeMeshes.map((node) => node.scale), {
            x: 1,
            y: 1,
            z: 1,
            duration: 0.72,
            stagger: 0.055,
            ease: 'back.out(2.1)',
          }, 0.22)
          .to(nodeHaloMeshes.map((halo) => halo.scale), {
            x: 1.16,
            y: 1.16,
            z: 1.16,
            duration: 0.78,
            stagger: 0.055,
            ease: 'back.out(1.9)',
          }, 0.28)
          .to(nodeCoreMeshes.map((core) => core.scale), {
            x: 1,
            y: 1,
            z: 1,
            duration: 0.58,
            stagger: 0.04,
            ease: 'power2.out',
          }, 0.34)
          .to(leafMeshes.map((leaf) => leaf.scale), {
            x: (index, target) => target.x * 5.15,
            y: (index, target) => target.y * 5.15,
            z: (index, target) => target.z * 5.15,
            duration: 0.82,
            stagger: { amount: 0.42, from: 'random' },
            ease: 'power2.out',
          }, 0.42)
          .to(flowParticles.map((spark) => spark.userData), {
            reveal: 1,
            duration: 0.76,
            stagger: { amount: 0.34, from: 'random' },
            ease: 'power2.out',
          }, 0.64);

        gsap.to(nodeMeshes.map((node) => node.scale), {
          x: 1.06,
          y: 1.06,
          z: 1.06,
          duration: 1.9,
          delay: 1.08,
          stagger: 0.12,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
        });

        gsap.to(nodeHaloMeshes.map((halo) => halo.scale), {
          x: 1.42,
          y: 1.42,
          z: 1.42,
          duration: 1.65,
          delay: 1.04,
          stagger: 0.1,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
        });

        gsap.to(nodeCoreMeshes.map((core) => core.scale), {
          x: 1.55,
          y: 1.55,
          z: 1.55,
          duration: 1.25,
          delay: 1.12,
          stagger: 0.08,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
        });

        gsap.to(revealGroup.rotation, {
          z: 0.016,
          duration: 5.4,
          delay: 1.1,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
        });
      }

      const resize = () => {
        const rect = shell.getBoundingClientRect();
        const width = Math.max(1, Math.floor(rect.width));
        const height = Math.max(1, Math.floor(rect.height));
        const isMobile = width < 720;

        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(width, height, false);
        treeGroup.position.set(isMobile ? 0.12 : -1.28, isMobile ? 1.08 : 0.32, -0.92);
        treeGroup.scale.setScalar(isMobile ? 0.56 : 1.24);
      };

      const render = () => {
        if (!running) {
          return;
        }

        const delta = clock.getDelta();
        const elapsed = clock.getElapsedTime();

        shell.dataset.sefarAuthThreeFrames = String(Number(shell.dataset.sefarAuthThreeFrames || 0) + 1);
        treeGroup.rotation.y += ((targetX * 0.26) - treeGroup.rotation.y) * 0.04;
        treeGroup.rotation.x += ((targetY * 0.15) - treeGroup.rotation.x) * 0.04;
        branchLines.material.opacity = 0.72 + (Math.sin(elapsed * 1.2) * 0.09);
        branchTubeMaterial.opacity = 0.54 + (Math.sin(elapsed * 1.05) * 0.07);
        trunkTubeMaterial.opacity = 0.45 + (Math.sin(elapsed * 1.16) * 0.06);
        branchAuraMaterial.opacity = 0.22 + (Math.sin(elapsed * 1.35) * 0.05);
        trunkAuraMaterial.opacity = 0.24 + (Math.sin(elapsed * 1.18) * 0.05);
        leafBlueMaterial.opacity = 0.66 + (Math.sin(elapsed * 0.85) * 0.07);
        leafGoldMaterial.opacity = 0.56 + (Math.sin(elapsed * 0.9) * 0.06);
        leafWhiteMaterial.opacity = 0.36 + (Math.sin(elapsed * 1.05) * 0.05);
        leafGroup.rotation.z = Math.sin(elapsed * 0.34) * 0.038;
        sparkGroup.rotation.z = Math.sin(elapsed * 0.26) * 0.018;
        leafMeshes.forEach((leaf) => {
          leaf.position.y += Math.sin((elapsed * leaf.userData.speed) + leaf.userData.offset) * delta * 0.025;
          leaf.rotation.z += delta * 0.18 * leaf.userData.speed;
        });
        nodeHaloMeshes.forEach((halo, index) => {
          halo.rotation.z += delta * (index % 2 ? 0.26 : -0.22);
        });
        flowParticles.forEach((spark, index) => {
          const progress = (spark.userData.offset + (elapsed * spark.userData.speed)) % 1;
          const energy = Math.sin(progress * Math.PI);
          const reveal = spark.userData.reveal ?? 1;

          spark.position.lerpVectors(spark.userData.from, spark.userData.to, progress);
          spark.position.z += Math.sin((elapsed * 1.8) + index) * 0.055;
          spark.scale.setScalar(spark.userData.baseScale * (0.28 + (energy * 1.35)) * reveal);
          spark.material.opacity = (0.16 + (energy * 0.82)) * reveal;
        });
        renderer.render(scene, camera);
        frameId = window.requestAnimationFrame(render);
      };

      const dispose = () => {
        running = false;

        if (frameId) {
          window.cancelAnimationFrame(frameId);
        }

        branchGeometry.dispose();
        trunkGeometry.dispose();
        tubeMeshes.forEach((mesh) => mesh.geometry.dispose());
        auraTubeMeshes.forEach((mesh) => mesh.geometry.dispose());
        sparkGeometry.dispose();
        nodeHaloGeometry.dispose();
        nodeGeometry.dispose();
        nodeInnerGeometry.dispose();
        nodeFillGeometry.dispose();
        nodeCoreGeometry.dispose();
        leafGeometry.dispose();
        branchMaterial.dispose();
        branchTubeMaterial.dispose();
        trunkTubeMaterial.dispose();
        branchAuraMaterial.dispose();
        trunkAuraMaterial.dispose();
        trunk.material.dispose();
        nodeFillMaterial.dispose();
        nodeMaterial.dispose();
        nodeAccentMaterial.dispose();
        nodeHaloMaterial.dispose();
        nodeHaloAccentMaterial.dispose();
        nodeCoreMaterial.dispose();
        leafBlueMaterial.dispose();
        leafGoldMaterial.dispose();
        leafWhiteMaterial.dispose();
        flowParticles.forEach((spark) => spark.material.dispose());
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
      shell.dataset.sefarAuthRenderer = shell.dataset.sefarAuthRenderer || 'fallback-tree';
      canvas.remove();
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

    canvas.classList.add('sefar-login-fallback-canvas');

    const threeCanvas = document.createElement('canvas');
    threeCanvas.className = 'sefar-login-canvas sefar-login-three-canvas';
    threeCanvas.setAttribute('aria-hidden', 'true');
    canvas.after(threeCanvas);

    animateInterface(shell);
    const stopFallback = setupCanvasFallback(shell, canvas);
    setupThreeScene(shell, threeCanvas, stopFallback, canvas);
  });
}());
