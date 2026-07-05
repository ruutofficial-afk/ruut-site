/* ============================================================
   RUUT — WebGL 3D Scroll Journey
   Self-contained ES module. Loads Three.js from CDN (via importmap
   defined in index.html). Renders a procedural premium glass mist
   bottle on a FIXED, transparent, full-page canvas. As the user
   scrolls, the bottle travels between keyframes anchored to page
   sections — drifting left/right, turning and scaling while the
   content changes underneath it.

   IMPORTANT: This is purely additive. It never touches the
   WooCommerce cart / product logic in app.js. If WebGL is missing
   or the user prefers reduced motion, we bail out gracefully and
   the existing image slideshow remains as the background.
   ============================================================ */

import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const canvas = document.getElementById('hero3dCanvas');
const hero = document.getElementById('home');
const overlay = document.getElementById('hero3dOverlay');

const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function supportsWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
  } catch (e) {
    return false;
  }
}

/* Graceful bail-out: keep the existing image slideshow visible. */
if (!canvas || !hero || prefersReduced || !supportsWebGL()) {
  if (hero) hero.classList.add('hero-fallback');
} else {
  boot();
}

function boot() {
  hero.classList.add('hero-3d-active');
  document.body.classList.add('has-3d-journey');

  const brand = {
    espresso: new THREE.Color('#2a1c12'),
    coffee: new THREE.Color('#4a3324'),
    beige: new THREE.Color('#e6ded1'),
    gold: new THREE.Color('#c89b6a'),
    mauve: new THREE.Color('#895158'),
    liquid: new THREE.Color('#e0a25c'),
  };

  /* ---------- Renderer (transparent, full page) ---------- */
  const renderer = new THREE.WebGLRenderer({
    canvas,
    antialias: true,
    alpha: true,
    powerPreference: 'high-performance',
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setClearColor(0x000000, 0);
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.2;
  renderer.outputColorSpace = THREE.SRGBColorSpace;

  const scene = new THREE.Scene();

  const CAM_Z = 9.4;
  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
  camera.position.set(0, 0.2, CAM_Z);

  /* Environment for realistic glass reflections/refractions */
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

  /* ---------- Lighting ---------- */
  const key = new THREE.DirectionalLight(0xfff1e0, 2.4);
  key.position.set(3, 4, 5);
  scene.add(key);

  const rim = new THREE.DirectionalLight(brand.gold.clone(), 2.2);
  rim.position.set(-4, 1.5, -3);
  scene.add(rim);

  const fill = new THREE.DirectionalLight(brand.mauve.clone(), 0.8);
  fill.position.set(-2, -3, 4);
  scene.add(fill);

  scene.add(new THREE.AmbientLight(0xffffff, 0.3));

  /* ---------- Rig: everything that travels on scroll ---------- */
  const rig = new THREE.Group();
  scene.add(rig);

  const bottle = new THREE.Group();
  rig.add(bottle);

  // Lathe profile (x = radius, y = height) for an elegant flacon
  const profile = [
    [0.02, -1.55],
    [0.72, -1.55],
    [0.82, -1.45],
    [0.84, -0.2],
    [0.84, 0.55],
    [0.8, 0.78],
    [0.5, 1.05],
    [0.34, 1.2],
    [0.32, 1.28],
    [0.32, 1.62],
    [0.35, 1.66],
  ].map((p) => new THREE.Vector2(p[0], p[1]));

  const glassGeo = new THREE.LatheGeometry(profile, 96);
  glassGeo.computeVertexNormals();

  const glassMat = new THREE.MeshPhysicalMaterial({
    color: 0xffffff,
    metalness: 0,
    roughness: 0.06,
    transmission: 1,
    thickness: 1.4,
    ior: 1.5,
    clearcoat: 1,
    clearcoatRoughness: 0.08,
    envMapIntensity: 1.6,
    attenuationColor: brand.beige.clone(),
    attenuationDistance: 4,
  });
  const glass = new THREE.Mesh(glassGeo, glassMat);
  bottle.add(glass);

  // Tinted liquid inside (slightly smaller lathe, partially filled)
  const liquidProfile = [
    [0.02, -1.45],
    [0.74, -1.45],
    [0.76, -1.4],
    [0.76, 0.15],
    [0.7, 0.35],
    [0.02, 0.4],
  ].map((p) => new THREE.Vector2(p[0], p[1]));
  const liquidGeo = new THREE.LatheGeometry(liquidProfile, 96);
  const liquidMat = new THREE.MeshPhysicalMaterial({
    color: brand.liquid.clone(),
    metalness: 0,
    roughness: 0.15,
    transmission: 0.9,
    thickness: 1.2,
    ior: 1.33,
    attenuationColor: brand.liquid.clone(),
    attenuationDistance: 2.4,
    envMapIntensity: 1,
  });
  const liquid = new THREE.Mesh(liquidGeo, liquidMat);
  bottle.add(liquid);

  // Champagne-gold collar
  const collarGeo = new THREE.CylinderGeometry(0.36, 0.36, 0.18, 64);
  const goldMat = new THREE.MeshStandardMaterial({
    color: brand.gold.clone(),
    metalness: 1,
    roughness: 0.28,
    envMapIntensity: 1.2,
  });
  const collar = new THREE.Mesh(collarGeo, goldMat);
  collar.position.y = 1.66;
  bottle.add(collar);

  // Cap (matte wood-warm)
  const capGeo = new THREE.CylinderGeometry(0.4, 0.38, 0.62, 64);
  const capMat = new THREE.MeshStandardMaterial({
    color: brand.coffee.clone(),
    metalness: 0.2,
    roughness: 0.55,
    envMapIntensity: 0.8,
  });
  const cap = new THREE.Mesh(capGeo, capMat);
  cap.position.y = 2.06;
  bottle.add(cap);

  // Fine gold ring at cap base
  const ringGeo = new THREE.TorusGeometry(0.4, 0.02, 16, 64);
  const ring = new THREE.Mesh(ringGeo, goldMat);
  ring.rotation.x = Math.PI / 2;
  ring.position.y = 1.77;
  bottle.add(ring);

  bottle.scale.setScalar(0.82);

  /* ---------- Fragrance mist particles (travel with the rig) ---------- */
  const isSmall = window.innerWidth < 768;
  const COUNT = isSmall ? 180 : 420;
  const positions = new Float32Array(COUNT * 3);
  const speeds = new Float32Array(COUNT);
  const sway = new Float32Array(COUNT);
  for (let i = 0; i < COUNT; i++) {
    resetParticle(i, true);
  }
  function resetParticle(i, initial) {
    const angle = Math.random() * Math.PI * 2;
    const radius = 0.05 + Math.random() * 0.3;
    positions[i * 3] = Math.cos(angle) * radius;
    positions[i * 3 + 1] = initial ? 1.4 + Math.random() * 2.6 : 1.45 + Math.random() * 0.25;
    positions[i * 3 + 2] = Math.sin(angle) * radius;
    speeds[i] = 0.15 + Math.random() * 0.35;
    sway[i] = Math.random() * Math.PI * 2;
  }
  const mistGeo = new THREE.BufferGeometry();
  mistGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  const mistMat = new THREE.PointsMaterial({
    size: 0.08,
    map: makeSpriteTexture(),
    transparent: true,
    opacity: 0.45,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    color: brand.beige.clone(),
  });
  const mist = new THREE.Points(mistGeo, mistMat);
  rig.add(mist);

  /* ============================================================
     SCROLL CHOREOGRAPHY
     Each keyframe anchors the bottle to a page section. `xf` is a
     fraction of the visible world width (-0.5 .. 0.5). The bottle
     travels, turns and rescales between keyframes as you scroll.
     ============================================================ */
  const KEYFRAME_DEFS = [
    { sel: '#home',              xf: 0,      y: -0.3, scale: 1,    rotY: 0 },
    { sel: '#collection',        xf: 0.33,   y: -0.2, scale: 0.62, rotY: 1.4 },
    { sel: '#bestsellers',       xf: -0.37,  y: -0.1, scale: 0.5,  rotY: 2.8 },
    { sel: '.quote-callout',     xf: -0.36,  y: -0.2, scale: 0.58, rotY: 4.2 },
    { sel: '.categories-section',xf: 0.36,   y: -0.15,scale: 0.55, rotY: 5.6 },
    { sel: '.testimonials',      xf: -0.36,  y: -0.15,scale: 0.5,  rotY: 7.0 },
    { sel: '#contact',           xf: 0,      y: -0.4, scale: 0.0,  rotY: 8.4 },
  ];

  let keyframes = [];
  function worldWidth() {
    const h = 2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * CAM_Z;
    return h * camera.aspect;
  }
  function buildKeyframes() {
    const ww = worldWidth();
    const mobile = window.innerWidth < 768;
    keyframes = [];
    for (const def of KEYFRAME_DEFS) {
      const el = document.querySelector(def.sel);
      if (!el || el.offsetParent === null) continue; // skip hidden sections
      const rect = el.getBoundingClientRect();
      const centerY = rect.top + window.scrollY + rect.height / 2;
      keyframes.push({
        pos: centerY,
        x: def.xf * ww * (mobile ? 0.55 : 1),
        y: def.y,
        scale: def.scale * (mobile ? 0.72 : 1),
        rotY: def.rotY,
      });
    }
    keyframes.sort((a, b) => a.pos - b.pos);
  }

  function smoothstep(t) {
    return t * t * (3 - 2 * t);
  }

  /* Current (damped) and target rig state */
  const state = { x: 0, y: -0.3, scale: 1, rotY: 0 };
  const goal = { x: 0, y: -0.3, scale: 1, rotY: 0 };

  function updateGoal() {
    if (!keyframes.length) return;
    const focus = window.scrollY + window.innerHeight / 2;
    let a = keyframes[0];
    let b = keyframes[0];
    for (let i = 0; i < keyframes.length; i++) {
      if (keyframes[i].pos <= focus) {
        a = keyframes[i];
        b = keyframes[Math.min(i + 1, keyframes.length - 1)];
      }
    }
    if (focus < keyframes[0].pos) {
      a = b = keyframes[0];
    }
    const span = Math.max(b.pos - a.pos, 1);
    const t = smoothstep(Math.min(Math.max((focus - a.pos) / span, 0), 1));
    goal.x = a.x + (b.x - a.x) * t;
    goal.y = a.y + (b.y - a.y) * t;
    goal.scale = a.scale + (b.scale - a.scale) * t;
    goal.rotY = a.rotY + (b.rotY - a.rotY) * t;
  }

  /* ---------- Interaction state ---------- */
  const pointer = { x: 0, y: 0 };
  const target = { x: 0, y: 0 };
  window.addEventListener('pointermove', (e) => {
    target.x = (e.clientX / window.innerWidth) * 2 - 1;
    target.y = (e.clientY / window.innerHeight) * 2 - 1;
  }, { passive: true });

  /* ---------- Resize ---------- */
  function resize() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    buildKeyframes();
  }
  resize();
  window.addEventListener('resize', resize);

  /* Section positions shift as lazy content/products load in. */
  setTimeout(buildKeyframes, 1500);
  setTimeout(buildKeyframes, 4000);
  if ('ResizeObserver' in window) {
    new ResizeObserver(() => buildKeyframes()).observe(document.body);
  }

  /* ---------- Reveal overlay once ready ---------- */
  requestAnimationFrame(() => hero.classList.add('hero-3d-ready'));

  /* ---------- Pause when tab hidden ---------- */
  let visible = !document.hidden;
  document.addEventListener('visibilitychange', () => {
    visible = !document.hidden;
    if (visible) loop();
  });

  /* ---------- Hero overlay fade on scroll ---------- */
  let heroFade = 0;
  window.addEventListener('scroll', () => {
    const h = hero.clientHeight || window.innerHeight;
    heroFade = Math.min(Math.max(window.scrollY / h, 0), 1);
    if (overlay) overlay.style.opacity = String(1 - heroFade * 1.4);
  }, { passive: true });

  /* ---------- Animation loop ---------- */
  const clock = new THREE.Clock();
  let running = false;
  function loop() {
    if (running) return;
    running = true;
    requestAnimationFrame(tick);
  }
  function tick() {
    running = false;
    if (!visible) return;
    const t = clock.getElapsedTime();
    const dt = Math.min(clock.getDelta(), 0.05);

    // Damped pointer parallax
    pointer.x += (target.x - pointer.x) * 0.05;
    pointer.y += (target.y - pointer.y) * 0.05;

    // Scroll choreography: ease the rig toward its goal
    updateGoal();
    state.x += (goal.x - state.x) * 0.07;
    state.y += (goal.y - state.y) * 0.07;
    state.scale += (goal.scale - state.scale) * 0.07;
    state.rotY += (goal.rotY - state.rotY) * 0.07;

    rig.position.x = state.x;
    rig.position.y = state.y + Math.sin(t * 0.8) * 0.06;
    rig.scale.setScalar(Math.max(state.scale, 0.001));
    rig.rotation.y = state.rotY;

    // Bottle idle sway + subtle turn toward pointer
    bottle.rotation.y = Math.sin(t * 0.25) * 0.3 + pointer.x * 0.35;
    bottle.rotation.x = pointer.y * 0.1;

    // Camera parallax
    camera.position.x += (pointer.x * 0.5 - camera.position.x) * 0.05;
    camera.position.y += (0.2 - pointer.y * 0.35 - camera.position.y) * 0.05;
    camera.lookAt(0, 0, 0);

    // Drift mist upward
    const pos = mistGeo.attributes.position.array;
    for (let i = 0; i < COUNT; i++) {
      pos[i * 3 + 1] += speeds[i] * dt;
      pos[i * 3] += Math.sin(t * 0.6 + sway[i]) * 0.0015;
      if (pos[i * 3 + 1] > 4.2) resetParticle(i, false);
    }
    mistGeo.attributes.position.needsUpdate = true;
    mistMat.opacity = 0.45 * Math.min(state.scale + 0.2, 1);

    renderer.render(scene, camera);
    requestAnimationFrame(tick);
  }
  loop();

  /* ---------- Helpers ---------- */
  function makeSpriteTexture() {
    const c = document.createElement('canvas');
    c.width = c.height = 64;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
    g.addColorStop(0, 'rgba(255,255,255,1)');
    g.addColorStop(0.3, 'rgba(255,244,230,0.6)');
    g.addColorStop(1, 'rgba(255,244,230,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 64, 64);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
  }
}
