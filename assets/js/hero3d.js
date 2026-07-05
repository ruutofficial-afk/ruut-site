/* ============================================================
   RUUT — WebGL 3D Hero
   Self-contained ES module. Loads Three.js from CDN (via importmap
   defined in index.html). Renders a procedural premium glass mist
   bottle with drifting fragrance particles, cinematic lighting,
   mouse parallax and scroll-driven motion.

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

  const brand = {
    espresso: new THREE.Color('#2a1c12'),
    coffee: new THREE.Color('#4a3324'),
    beige: new THREE.Color('#e6ded1'),
    gold: new THREE.Color('#c89b6a'),
    mauve: new THREE.Color('#895158'),
    liquid: new THREE.Color('#e0a25c'),
  };

  /* ---------- Renderer ---------- */
  const renderer = new THREE.WebGLRenderer({
    canvas,
    antialias: true,
    alpha: false,
    powerPreference: 'high-performance',
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.2;
  renderer.outputColorSpace = THREE.SRGBColorSpace;

  /* ---------- Scene + gradient backdrop ---------- */
  const scene = new THREE.Scene();
  scene.background = makeGradientTexture();

  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
  camera.position.set(0, 0.2, 9.4);

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

  scene.add(new THREE.AmbientLight(0xffffff, 0.25));

  /* ---------- The bottle ---------- */
  const bottle = new THREE.Group();
  scene.add(bottle);

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
    envMapIntensity: 1.4,
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

  bottle.position.y = -0.3;
  bottle.scale.setScalar(0.82);

  /* ---------- Fragrance mist particles ---------- */
  const isSmall = window.innerWidth < 768;
  const COUNT = isSmall ? 220 : 520;
  const positions = new Float32Array(COUNT * 3);
  const speeds = new Float32Array(COUNT);
  const sway = new Float32Array(COUNT);
  for (let i = 0; i < COUNT; i++) {
    resetParticle(i, true);
  }
  function resetParticle(i, initial) {
    const angle = Math.random() * Math.PI * 2;
    const radius = 0.05 + Math.random() * 0.35;
    positions[i * 3] = Math.cos(angle) * radius;
    positions[i * 3 + 1] = initial ? 1.6 + Math.random() * 3.5 : 1.7 + Math.random() * 0.3;
    positions[i * 3 + 2] = Math.sin(angle) * radius;
    speeds[i] = 0.15 + Math.random() * 0.35;
    sway[i] = Math.random() * Math.PI * 2;
  }
  const mistGeo = new THREE.BufferGeometry();
  mistGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  const mistMat = new THREE.PointsMaterial({
    size: 0.09,
    map: makeSpriteTexture(),
    transparent: true,
    opacity: 0.5,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    color: brand.beige.clone(),
  });
  const mist = new THREE.Points(mistGeo, mistMat);
  scene.add(mist);

  /* ---------- Interaction state ---------- */
  const pointer = { x: 0, y: 0 };
  const target = { x: 0, y: 0 };
  window.addEventListener('pointermove', (e) => {
    target.x = (e.clientX / window.innerWidth) * 2 - 1;
    target.y = (e.clientY / window.innerHeight) * 2 - 1;
  }, { passive: true });

  /* ---------- Resize ---------- */
  function resize() {
    const w = hero.clientWidth || window.innerWidth;
    const h = hero.clientHeight || window.innerHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }
  resize();
  window.addEventListener('resize', resize);

  /* ---------- Reveal overlay once ready ---------- */
  requestAnimationFrame(() => hero.classList.add('hero-3d-ready'));

  /* ---------- Pause when offscreen ---------- */
  let visible = true;
  if ('IntersectionObserver' in window) {
    new IntersectionObserver((entries) => {
      visible = entries[0].isIntersecting;
      if (visible) loop();
    }, { threshold: 0.01 }).observe(hero);
  }

  /* ---------- Scroll handoff ---------- */
  let scrollFactor = 0;
  window.addEventListener('scroll', () => {
    const h = hero.clientHeight || window.innerHeight;
    scrollFactor = Math.min(Math.max(window.scrollY / h, 0), 1);
    if (overlay) overlay.style.opacity = String(1 - scrollFactor * 1.4);
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

    // Damped parallax
    pointer.x += (target.x - pointer.x) * 0.05;
    pointer.y += (target.y - pointer.y) * 0.05;

    // Bottle idle float + subtle turn toward pointer
    bottle.rotation.y = Math.sin(t * 0.25) * 0.35 + pointer.x * 0.4;
    bottle.rotation.x = pointer.y * 0.12;
    bottle.position.y = -0.3 + Math.sin(t * 0.8) * 0.06;

    // Camera parallax + scroll dolly
    camera.position.x += (pointer.x * 0.6 - camera.position.x) * 0.05;
    camera.position.y += (0.2 - pointer.y * 0.4 - camera.position.y) * 0.05;
    camera.position.z = 9.4 + scrollFactor * 2.5;
    camera.lookAt(0, -0.1 + scrollFactor * 0.4, 0);

    // Drift mist upward
    const pos = mistGeo.attributes.position.array;
    for (let i = 0; i < COUNT; i++) {
      pos[i * 3 + 1] += speeds[i] * dt;
      pos[i * 3] += Math.sin(t * 0.6 + sway[i]) * 0.0015;
      if (pos[i * 3 + 1] > 5.2) resetParticle(i, false);
    }
    mistGeo.attributes.position.needsUpdate = true;
    mistMat.opacity = 0.5 * (1 - scrollFactor);

    renderer.render(scene, camera);
    requestAnimationFrame(tick);
  }
  loop();

  /* ---------- Helpers ---------- */
  function makeGradientTexture() {
    const c = document.createElement('canvas');
    c.width = 16;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 256);
    g.addColorStop(0, '#6b4a2c');
    g.addColorStop(0.5, '#402a19');
    g.addColorStop(1, '#1c120a');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 16, 256);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
  }

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
