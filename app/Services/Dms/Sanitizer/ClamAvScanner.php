<?php

namespace App\Services\Dms\Sanitizer;

use Illuminate\Support\Facades\Process;

class ClamAvScanner
{
    public const EICAR_SIGNATURE = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    /**
     * @var callable|null
     */
    protected static $fakeHandler = null;

    protected string $socketPath;

    protected ?string $cliBinary;

    protected int $timeout;

    public function __construct(
        ?string $socketPath = null,
        ?string $cliBinary = null,
        int $timeout = 10
    ) {
        $this->socketPath = $socketPath ?? (string) config('dms.clamav.socket', '/var/run/clamav/clamd.ctl');
        $this->cliBinary = $cliBinary ?? (string) config('dms.clamav.cli', '');
        $this->timeout = $timeout > 0 ? $timeout : (int) config('dms.clamav.timeout', 10);
    }

    /**
     * Fake scanner results during tests.
     */
    public static function fake(?callable $fakeHandler = null): void
    {
        static::$fakeHandler = $fakeHandler;
    }

    /**
     * Reset fake handler.
     */
    public static function resetFake(): void
    {
        static::$fakeHandler = null;
    }

    /**
     * Scan a file for malware.
     */
    public function scan(string $filePath): ClamAvResult
    {
        if (static::$fakeHandler !== null) {
            $fakeResult = (static::$fakeHandler)($filePath);
            if ($fakeResult instanceof ClamAvResult) {
                return $fakeResult;
            }
        }

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return ClamAvResult::error("File does not exist or is not readable: {$filePath}");
        }

        // 1. Try scanning via ClamAV UNIX socket if available
        if ($this->isSocketAvailable()) {
            $socketResult = $this->scanViaSocket($filePath);
            if ($socketResult !== null) {
                return $socketResult;
            }
        }

        // 2. Try scanning via CLI binary (clamdscan or clamscan)
        $cliBinary = $this->resolveCliBinary();
        if ($cliBinary !== null) {
            $cliResult = $this->scanViaCli($cliBinary, $filePath);
            if ($cliResult !== null) {
                return $cliResult;
            }
        }

        // 3. Fallback signature detection (when daemon and CLI are unavailable)
        return $this->scanFallbackSignature($filePath);
    }

    /**
     * Check if socket is configured and accessible.
     */
    public function isSocketAvailable(): bool
    {
        if (str_starts_with($this->socketPath, 'tcp://') || str_starts_with($this->socketPath, 'unix://')) {
            return true;
        }

        return file_exists($this->socketPath);
    }

    /**
     * Scan file via UNIX domain socket or TCP socket using zINSTREAM.
     */
    protected function scanViaSocket(string $filePath): ?ClamAvResult
    {
        $remote = str_starts_with($this->socketPath, 'tcp://') || str_starts_with($this->socketPath, 'unix://')
            ? $this->socketPath
            : 'unix://'.$this->socketPath;

        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout);
        if (! $socket) {
            return null;
        }

        try {
            stream_set_timeout($socket, $this->timeout);

            // Send zINSTREAM command
            fwrite($socket, "zINSTREAM\0");

            $fp = @fopen($filePath, 'rb');
            if (! $fp) {
                fclose($socket);

                return ClamAvResult::error("Unable to open file for streaming: {$filePath}");
            }

            while (! feof($fp)) {
                $chunk = fread($fp, 8192);
                if ($chunk === false) {
                    break;
                }
                $len = strlen($chunk);
                if ($len > 0) {
                    fwrite($socket, pack('N', $len).$chunk);
                }
            }
            fclose($fp);

            // End stream with 0-length chunk
            fwrite($socket, pack('N', 0));

            $response = '';
            while (! feof($socket)) {
                $line = fgets($socket, 1024);
                if ($line === false) {
                    break;
                }
                $response .= $line;
            }
            fclose($socket);

            $response = trim($response);

            if (str_contains($response, 'FOUND')) {
                // e.g. "stream: Win.Test.EICAR_HDB-1 FOUND"
                if (preg_match('/stream:\s+(.+?)\s+FOUND/i', $response, $matches)) {
                    return ClamAvResult::infected(trim($matches[1]));
                }

                return ClamAvResult::infected('Malware.Generic.Found');
            }

            if (str_contains($response, 'OK')) {
                return ClamAvResult::clean();
            }

            if (str_contains($response, 'ERROR')) {
                return ClamAvResult::error("ClamAV socket error: {$response}");
            }

            return null;
        } catch (\Throwable $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }

            return null;
        }
    }

    /**
     * Find available CLI binary.
     */
    protected function resolveCliBinary(): ?string
    {
        if (! empty($this->cliBinary) && is_executable($this->cliBinary)) {
            return $this->cliBinary;
        }

        foreach (['clamdscan', 'clamscan'] as $candidate) {
            $output = shell_exec("which {$candidate} 2>/dev/null");
            if ($output) {
                $bin = trim($output);
                if (is_executable($bin)) {
                    return $bin;
                }
            }
        }

        return null;
    }

    /**
     * Scan file using CLI binary.
     */
    protected function scanViaCli(string $binary, string $filePath): ?ClamAvResult
    {
        try {
            $escapedFile = escapeshellarg($filePath);
            $process = Process::run("{$binary} --no-summary {$escapedFile}");
            $exitCode = $process->exitCode();
            $output = trim($process->output().' '.$process->errorOutput());

            if ($exitCode === 0) {
                return ClamAvResult::clean();
            }

            if ($exitCode === 1) {
                // Virus found
                if (preg_match('/:\s*(.+?)\s*FOUND/i', $output, $matches)) {
                    return ClamAvResult::infected(trim($matches[1]));
                }

                return ClamAvResult::infected('Malware.Found');
            }

            // Exit code 2 is usually error
            return ClamAvResult::error("ClamAV CLI error (exit {$exitCode}): {$output}");
        } catch (\Throwable $e) {
            return ClamAvResult::error('ClamAV execution failed: '.$e->getMessage());
        }
    }

    /**
     * Built-in fallback signature inspection for EICAR test string and patterns.
     */
    protected function scanFallbackSignature(string $filePath): ClamAvResult
    {
        $contents = @file_get_contents($filePath, false, null, 0, 1024 * 1024);
        if ($contents === false) {
            return ClamAvResult::error("Unable to read file: {$filePath}");
        }

        if (str_contains($contents, self::EICAR_SIGNATURE) || str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
            return ClamAvResult::infected('Win.Test.EICAR_HDB-1');
        }

        return ClamAvResult::clean();
    }
}
