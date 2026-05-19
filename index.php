<?php
declare(strict_types=1);

$game = [
    'map' => [
        [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
        [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
        [1,0,2,2,0,0,3,0,0,0,4,4,0,0,0,1],
        [1,0,2,0,0,0,3,0,0,0,4,0,0,5,0,1],
        [1,0,2,0,0,0,3,0,0,0,4,0,0,5,0,1],
        [1,0,0,0,0,0,3,3,3,0,0,0,0,5,0,1],
        [1,0,0,0,6,0,0,0,0,0,0,7,0,5,0,1],
        [1,0,0,0,6,6,6,0,0,0,0,7,0,0,0,1],
        [1,0,0,0,0,0,6,0,0,0,0,7,7,7,0,1],
        [1,0,0,0,0,0,6,0,0,0,0,0,0,0,0,1],
        [1,0,8,8,8,0,0,0,9,9,9,0,0,0,0,1],
        [1,0,8,0,8,0,0,0,9,0,9,0,0,0,0,1],
        [1,0,8,8,8,0,0,0,9,9,9,0,0,0,0,1],
        [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
        [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
        [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
    ],
    'player' => [
        'x' => 3.5,
        'y' => 3.5,
        'dirX' => -1.0,
        'dirY' => 0.0,
        'planeX' => 0.0,
        'planeY' => 0.66,
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Retro PHP Raycaster</title>
  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    html, body { margin: 0; width: 100%; height: 100%; overflow: hidden; background: #000; }
    canvas { display: block; width: 100vw; height: 100vh; image-rendering: pixelated; }
    .hud {
      position: fixed; left: 12px; top: 12px; z-index: 2;
      color: #d6d6d6; font: 12px/1.4 monospace; text-shadow: 1px 1px 0 #000;
      user-select: none; pointer-events: none;
    }
  </style>
</head>
<body>
  <canvas id="game"></canvas>
  <div class="hud">WASD / Arrows: move + turn</div>
  <script>
    "use strict";

    const GAME = <?php echo json_encode($game, JSON_UNESCAPED_SLASHES); ?>;
    const MAP = GAME.map;
    const MAP_H = MAP.length;
    const MAP_W = MAP[0].length;

    const player = {
      x: GAME.player.x,
      y: GAME.player.y,
      dirX: GAME.player.dirX,
      dirY: GAME.player.dirY,
      planeX: GAME.player.planeX,
      planeY: GAME.player.planeY
    };

    const WALL_COLORS = [
      "#000000", "#cc2222", "#22cc22", "#3355dd", "#d6b02a",
      "#b03ed0", "#1bb8b8", "#ff6b35", "#e0466b", "#64d94f"
    ];

    const canvas = document.getElementById("game");
    const ctx = canvas.getContext("2d", { alpha: false });
    let width = 0, height = 0, halfHeight = 0;

    const keys = Object.create(null);
    const MOVESPEED = 3.0;
    const ROTSPEED = 2.2;
    const MAX_DPR = 2;
    const LARGE_DIST = 1e30;
    const MIN_BRIGHTNESS = 0.28;
    const DISTANCE_FALLOFF = 0.08;
    const SIDE_DARKEN = 0.72;

    function resize() {
      const dpr = Math.max(1, Math.min(MAX_DPR, window.devicePixelRatio || 1));
      const w = Math.max(320, Math.floor(window.innerWidth * dpr));
      const h = Math.max(240, Math.floor(window.innerHeight * dpr));
      if (canvas.width === w && canvas.height === h) return;
      canvas.width = width = w;
      canvas.height = height = h;
      halfHeight = h >> 1;
    }

    function wallAt(x, y) {
      const ix = x | 0;
      const iy = y | 0;
      if (ix < 0 || iy < 0 || ix >= MAP_W || iy >= MAP_H) return 1;
      return MAP[iy][ix];
    }

    function applyMovement(dt) {
      const move = MOVESPEED * dt;
      const rot = ROTSPEED * dt;

      const forward = (keys["KeyW"] || keys["ArrowUp"] ? 1 : 0) - (keys["KeyS"] || keys["ArrowDown"] ? 1 : 0);
      const turn = (keys["KeyD"] || keys["ArrowRight"] ? 1 : 0) - (keys["KeyA"] || keys["ArrowLeft"] ? 1 : 0);

      if (forward !== 0) {
        const nx = player.x + player.dirX * move * forward;
        const ny = player.y + player.dirY * move * forward;
        if (!wallAt(nx, player.y)) player.x = nx;
        if (!wallAt(player.x, ny)) player.y = ny;
      }

      if (turn !== 0) {
        const angle = rot * turn;
        const sin = Math.sin(angle);
        const cos = Math.cos(angle);

        const oldDirX = player.dirX;
        player.dirX = player.dirX * cos - player.dirY * sin;
        player.dirY = oldDirX * sin + player.dirY * cos;

        const oldPlaneX = player.planeX;
        player.planeX = player.planeX * cos - player.planeY * sin;
        player.planeY = oldPlaneX * sin + player.planeY * cos;
      }
    }

    function shade(hex, factor) {
      const num = parseInt(hex.slice(1), 16);
      const r = Math.min(255, Math.max(0, ((num >> 16) & 255) * factor)) | 0;
      const g = Math.min(255, Math.max(0, ((num >> 8) & 255) * factor)) | 0;
      const b = Math.min(255, Math.max(0, (num & 255) * factor)) | 0;
      return `rgb(${r},${g},${b})`;
    }

    function render() {
      ctx.fillStyle = "#202028";
      ctx.fillRect(0, 0, width, halfHeight);
      ctx.fillStyle = "#16120e";
      ctx.fillRect(0, halfHeight, width, halfHeight);

      for (let x = 0; x < width; x++) {
        const cameraX = 2 * x / width - 1;
        const rayDirX = player.dirX + player.planeX * cameraX;
        const rayDirY = player.dirY + player.planeY * cameraX;

        let mapX = player.x | 0;
        let mapY = player.y | 0;

        const deltaDistX = rayDirX === 0 ? LARGE_DIST : Math.abs(1 / rayDirX);
        const deltaDistY = rayDirY === 0 ? LARGE_DIST : Math.abs(1 / rayDirY);

        let sideDistX, sideDistY;
        let stepX, stepY;

        if (rayDirX < 0) {
          stepX = -1;
          sideDistX = (player.x - mapX) * deltaDistX;
        } else {
          stepX = 1;
          sideDistX = (mapX + 1 - player.x) * deltaDistX;
        }

        if (rayDirY < 0) {
          stepY = -1;
          sideDistY = (player.y - mapY) * deltaDistY;
        } else {
          stepY = 1;
          sideDistY = (mapY + 1 - player.y) * deltaDistY;
        }

        let side = 0;
        let wallType = 0;

        while (wallType === 0) {
          if (sideDistX < sideDistY) {
            sideDistX += deltaDistX;
            mapX += stepX;
            side = 0;
          } else {
            sideDistY += deltaDistY;
            mapY += stepY;
            side = 1;
          }

          if (mapX < 0 || mapY < 0 || mapX >= MAP_W || mapY >= MAP_H) {
            wallType = 1;
            break;
          }
          wallType = MAP[mapY][mapX];
        }

        let perpWallDist;
        if (side === 0) {
          perpWallDist = (mapX - player.x + (1 - stepX) * 0.5) / rayDirX;
        } else {
          perpWallDist = (mapY - player.y + (1 - stepY) * 0.5) / rayDirY;
        }
        perpWallDist = Math.max(perpWallDist, 0.0001);

        const lineHeight = (height / perpWallDist) | 0;
        const drawStart = Math.max(0, halfHeight - (lineHeight >> 1));
        const drawEnd = Math.min(height - 1, halfHeight + (lineHeight >> 1));

        const base = WALL_COLORS[wallType] || "#bfbfbf";
        const depthShade = Math.max(MIN_BRIGHTNESS, 1 - perpWallDist * DISTANCE_FALLOFF);
        const sideShade = side === 1 ? SIDE_DARKEN : 1.0;
        ctx.fillStyle = shade(base, depthShade * sideShade);
        ctx.fillRect(x, drawStart, 1, drawEnd - drawStart + 1);
      }
    }

    let last = performance.now();
    function frame(now) {
      const dt = Math.min(0.05, (now - last) / 1000);
      last = now;
      resize();
      applyMovement(dt);
      render();
      requestAnimationFrame(frame);
    }

    window.addEventListener("keydown", (e) => { keys[e.code] = true; }, { passive: true });
    window.addEventListener("keyup", (e) => { keys[e.code] = false; }, { passive: true });
    window.addEventListener("resize", resize, { passive: true });
    resize();
    requestAnimationFrame(frame);
  </script>
</body>
</html>
