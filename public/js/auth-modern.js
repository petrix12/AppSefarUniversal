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
      const leaves = Array.from({ length: width < 720 ? 42 : 72 }, (_, index) => {
        const angle = (index / (width < 720 ? 42 : 72)) * Math.PI * 2;
        const radiusX = scale * (0.25 + (Math.random() * 0.2));
        const radiusY = scale * (0.14 + (Math.random() * 0.1));

        return {
          x: originX + (Math.cos(angle) * radiusX) + ((Math.random() - 0.5) * scale * 0.18),
          y: originY - (scale * 0.2) + (Math.sin(angle) * radiusY) + ((Math.random() - 0.5) * scale * 0.08),
          size: 4 + Math.random() * 7,
          angle: angle + Math.random(),
          speed: 0.6 + Math.random() * 0.9,
          alpha: 0.22 + Math.random() * 0.24,
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

      context.beginPath();
      context.moveTo(branch.from.x, branch.from.y);
      context.lineTo(x, y);
      context.lineWidth = branch.lineWidth;
      context.strokeStyle = 'rgba(219, 186, 114, 0.48)';
      context.stroke();

      context.beginPath();
      context.moveTo(branch.from.x, branch.from.y);
      context.lineTo(x, y);
      context.lineWidth = Math.max(1, branch.lineWidth * 0.36);
      context.strokeStyle = 'rgba(143, 216, 255, 0.2)';
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
        context.fillStyle = 'rgba(255, 255, 255, 0.045)';
        context.fill();
        context.lineWidth = Math.max(1.2, node.radius * 0.065);
        context.strokeStyle = 'rgba(219, 186, 114, 0.46)';
        context.stroke();
        context.beginPath();
        context.arc(node.x, node.y, node.radius * 0.78, 0, Math.PI * 2);
        context.lineWidth = 1;
        context.strokeStyle = 'rgba(143, 216, 255, 0.2)';
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
        powerPreference: 'low-power',
      });
      const treeGroup = new THREE.Group();
      const branchGroup = new THREE.Group();
      const nodeGroup = new THREE.Group();
      const leafGroup = new THREE.Group();
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
        opacity: 0.58,
        depthWrite: false,
      });
      const branchTubeMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.42,
        depthWrite: false,
      });
      const trunkTubeMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.28,
        depthWrite: false,
      });
      const branchLines = new THREE.LineSegments(branchGeometry, branchMaterial);
      branchGroup.add(branchLines);

      const tubeMeshes = branchSegments.map(([from, to], index) => {
        const curve = new THREE.CatmullRomCurve3([
          new THREE.Vector3(from[0], from[1], from[2]),
          new THREE.Vector3(to[0], to[1], to[2]),
        ]);
        const tube = new THREE.Mesh(
          new THREE.TubeGeometry(curve, 18, index < 2 ? 0.026 : 0.014, 7, false),
          index < 2 ? trunkTubeMaterial : branchTubeMaterial
        );

        branchGroup.add(tube);

        return tube;
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
          opacity: 0.32,
          depthWrite: false,
        })
      );
      branchGroup.add(trunk);

      const nodeGeometry = new THREE.RingGeometry(0.19, 0.207, 52);
      const nodeFillGeometry = new THREE.CircleGeometry(0.18, 52);
      const nodeFillMaterial = new THREE.MeshBasicMaterial({
        color: 0xffffff,
        transparent: true,
        opacity: 0.08,
        side: THREE.DoubleSide,
        depthWrite: false,
      });
      const nodeMaterial = new THREE.MeshBasicMaterial({
        color: 0xffffff,
        transparent: true,
        opacity: 0.72,
        side: THREE.DoubleSide,
        depthWrite: false,
      });
      const nodeAccentMaterial = new THREE.MeshBasicMaterial({
        color: 0xdbba72,
        transparent: true,
        opacity: 0.58,
        side: THREE.DoubleSide,
        depthWrite: false,
      });
      const nodeMeshes = nodePositions.map((position, index) => {
        const node = new THREE.Group();
        const fill = new THREE.Mesh(nodeFillGeometry, nodeFillMaterial);
        const outer = new THREE.Mesh(nodeGeometry, index % 2 ? nodeMaterial : nodeAccentMaterial);
        const inner = new THREE.Mesh(
          new THREE.RingGeometry(0.13, 0.136, 44),
          index % 2 ? nodeAccentMaterial : nodeMaterial
        );

        node.position.set(position[0], position[1], position[2]);
        node.add(fill, outer, inner);
        nodeGroup.add(node);

        return node;
      });

      const leafGeometry = new THREE.CircleGeometry(0.035, 10);
      const leafMaterial = new THREE.MeshBasicMaterial({
        color: 0x8fd8ff,
        transparent: true,
        opacity: 0.5,
        side: THREE.DoubleSide,
        depthWrite: false,
      });
      const leafMeshes = Array.from({ length: 64 }, (_, index) => {
        const angle = (index / 64) * Math.PI * 2;
        const radiusX = 1.7 + (Math.random() * 0.55);
        const radiusY = 0.9 + (Math.random() * 0.32);
        const leaf = new THREE.Mesh(leafGeometry, leafMaterial);

        leaf.position.set(
          Math.cos(angle) * radiusX,
          0.75 + (Math.sin(angle) * radiusY),
          -0.18 + ((Math.random() - 0.5) * 0.38)
        );
        leaf.rotation.z = angle;
        leaf.scale.setScalar(0.75 + Math.random() * 1.3);
        leaf.userData.speed = 0.55 + Math.random() * 1.2;
        leaf.userData.offset = Math.random() * Math.PI * 2;
        leafGroup.add(leaf);

        return leaf;
      });

      treeGroup.add(branchGroup, leafGroup, nodeGroup);
      scene.add(treeGroup);
      camera.position.z = 6.8;

      if (gsap && !prefersReducedMotion()) {
        shell.dataset.sefarAuthTreeMotion = 'gsap';
        treeGroup.scale.set(0.9, 0.9, 0.9);
        branchLines.material.opacity = 0;
        trunk.material.opacity = 0;
        branchTubeMaterial.opacity = 0;
        trunkTubeMaterial.opacity = 0;
        nodeMeshes.forEach((node) => node.scale.setScalar(0.2));
        leafMeshes.forEach((leaf) => leaf.scale.multiplyScalar(0.2));

        gsap.timeline({ defaults: { ease: 'power3.out' } })
          .to(treeGroup.scale, { x: 1, y: 1, z: 1, duration: 1.2 }, 0)
          .to([branchLines.material, trunk.material, branchTubeMaterial, trunkTubeMaterial], { opacity: (index) => [0.58, 0.32, 0.42, 0.28][index], duration: 1 }, 0.05)
          .to(nodeMeshes.map((node) => node.scale), {
            x: 1,
            y: 1,
            z: 1,
            duration: 0.72,
            stagger: 0.055,
            ease: 'back.out(1.7)',
          }, 0.28)
          .to(leafMeshes.map((leaf) => leaf.scale), {
            x: (index, target) => target.x * 5,
            y: (index, target) => target.y * 5,
            z: (index, target) => target.z * 5,
            duration: 0.75,
            stagger: { amount: 0.34, from: 'random' },
            ease: 'power2.out',
          }, 0.44);

        gsap.to(nodeMeshes.map((node) => node.scale), {
          x: 1.06,
          y: 1.06,
          z: 1.06,
          duration: 1.9,
          stagger: 0.12,
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
        treeGroup.position.set(isMobile ? 0.06 : -1.25, isMobile ? 0.98 : -0.12, -0.92);
        treeGroup.scale.setScalar(isMobile ? 0.52 : 1.18);
      };

      const render = () => {
        if (!running) {
          return;
        }

        const delta = clock.getDelta();
        const elapsed = clock.getElapsedTime();

        shell.dataset.sefarAuthThreeFrames = String(Number(shell.dataset.sefarAuthThreeFrames || 0) + 1);
        treeGroup.rotation.y += ((targetX * 0.13) - treeGroup.rotation.y) * 0.035;
        treeGroup.rotation.x += ((targetY * 0.08) - treeGroup.rotation.x) * 0.035;
        branchLines.material.opacity = 0.52 + (Math.sin(elapsed * 1.2) * 0.06);
        branchTubeMaterial.opacity = 0.38 + (Math.sin(elapsed * 1.05) * 0.04);
        leafGroup.rotation.z = Math.sin(elapsed * 0.32) * 0.026;
        leafMeshes.forEach((leaf) => {
          leaf.position.y += Math.sin((elapsed * leaf.userData.speed) + leaf.userData.offset) * delta * 0.025;
          leaf.rotation.z += delta * 0.18 * leaf.userData.speed;
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
        nodeGeometry.dispose();
        nodeFillGeometry.dispose();
        leafGeometry.dispose();
        branchMaterial.dispose();
        branchTubeMaterial.dispose();
        trunkTubeMaterial.dispose();
        trunk.material.dispose();
        nodeFillMaterial.dispose();
        nodeMaterial.dispose();
        nodeAccentMaterial.dispose();
        leafMaterial.dispose();
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
