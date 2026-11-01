<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MapTileController extends Controller
{
    /**
     * Minimum allowed zoom level.
     */
    public const MIN_ZOOM = 5;

    /**
     * Maximum allowed zoom level.
     */
    public const MAX_ZOOM = 18;

    /**
     * Egypt bounding coordinates envelope (with border buffer).
     */
    public const EGYPT_LAT_MIN = 21.0;

    public const EGYPT_LAT_MAX = 32.5;

    public const EGYPT_LON_MIN = 24.0;

    public const EGYPT_LON_MAX = 37.5;

    /**
     * Handle incoming map tile request.
     */
    public function show(...$args): Response|BinaryFileResponse
    {
        return $this->resolveAndHandle($args);
    }

    /**
     * Handle single action invoke.
     */
    public function __invoke(...$args): Response|BinaryFileResponse
    {
        return $this->resolveAndHandle($args);
    }

    /**
     * Resolve variable arguments and handle tile delivery.
     *
     * @param  array<int, mixed>  $args
     */
    protected function resolveAndHandle(array $args): Response|BinaryFileResponse
    {
        if (isset($args[0]) && $args[0] instanceof Request) {
            array_shift($args);
        }

        $z = $args[0] ?? null;
        $x = $args[1] ?? null;
        $y = $args[2] ?? null;

        return $this->handleTileRequest($z, $x, $y);
    }

    /**
     * Process tile retrieval, validation, caching, and serving.
     */
    protected function handleTileRequest(mixed $z, mixed $x, mixed $y): Response|BinaryFileResponse
    {
        if (is_string($y)) {
            $y = preg_replace('/\.png$/i', '', $y);
        }

        if (! is_numeric($z) || ! is_numeric($x) || ! is_numeric($y)) {
            abort(400, 'Invalid tile coordinates.');
        }

        $z = (int) $z;
        $x = (int) $x;
        $y = (int) $y;

        if ($z < self::MIN_ZOOM || $z > self::MAX_ZOOM) {
            abort(400, 'Zoom level must be between 5 and 18.');
        }

        if (! $this->isTileInEgypt($z, $x, $y)) {
            abort(400, 'Tile coordinates outside of Egypt range.');
        }

        // 1. Check local storage cache in storage/app/tiles/
        $cacheFullPath = storage_path("app/tiles/{$z}/{$x}/{$y}.png");

        if (file_exists($cacheFullPath)) {
            return response(file_get_contents($cacheFullPath), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        // 2. Fetch from upstream OpenStreetMap tile server
        $tileServer = rtrim(config('services.osm.tile_server', 'https://tile.openstreetmap.org'), '/');
        $userAgent = config('services.osm.user_agent', 'AA-Egypt-TileProxy/1.0 (info@aa-egypt.org)');

        try {
            $response = Http::withHeaders([
                'User-Agent' => $userAgent,
            ])->timeout(10)->get("{$tileServer}/{$z}/{$x}/{$y}.png");
        } catch (\Throwable $e) {
            abort(502, 'Failed to fetch map tile: '.$e->getMessage());
        }

        if (! $response->successful()) {
            abort($response->status() >= 400 ? $response->status() : 502, 'Map tile not found on upstream server.');
        }

        $imageContent = $response->body();

        // 3. Cache locally in storage/app/tiles/{z}/{x}/{y}.png
        $cacheDir = dirname($cacheFullPath);
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFullPath, $imageContent);

        return response($imageContent, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Check if tile coordinates intersect the Egyptian geographical bounding box.
     */
    public function isTileInEgypt(int $z, int $x, int $y): bool
    {
        $maxCoord = (1 << $z) - 1;
        if ($x < 0 || $x > $maxCoord || $y < 0 || $y > $maxCoord) {
            return false;
        }

        $n = 1 << $z;
        $tileLonMin = ($x / $n) * 360.0 - 180.0;
        $tileLonMax = (($x + 1) / $n) * 360.0 - 180.0;

        $latRadMax = M_PI * (1.0 - 2.0 * $y / $n);
        $latRadMin = M_PI * (1.0 - 2.0 * ($y + 1) / $n);

        $tileLatMax = rad2deg(atan(sinh($latRadMax)));
        $tileLatMin = rad2deg(atan(sinh($latRadMin)));

        // Verify bounding box intersection with Egypt coordinates
        if ($tileLonMax < self::EGYPT_LON_MIN || $tileLonMin > self::EGYPT_LON_MAX) {
            return false;
        }

        if ($tileLatMax < self::EGYPT_LAT_MIN || $tileLatMin > self::EGYPT_LAT_MAX) {
            return false;
        }

        return true;
    }
}
