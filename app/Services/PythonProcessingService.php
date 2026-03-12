<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Exception;
use Illuminate\Support\Facades\Log;

class PythonProcessingService
{
    /**
     * Get the Python executable path from environment or default.
     *
     * @return string
     */
    protected function getPythonPath(): string
    {
        return env('PYTHON_PATH', 'python');
    }

    /**
     * Validate file path to prevent directory traversal attacks.
     *
     * @param string $path
     * @return string
     * @throws Exception
     */
    protected function validatePath(string $path): string
    {
        // Ensure the file path is within the storage directory
        $realStoragePath = realpath(storage_path('app/public'));
        $realFilePath = realpath($path);
        
        if (!$realFilePath) {
            throw new Exception('Invalid file path: file does not exist');
        }
        
        if (strpos($realFilePath, $realStoragePath) !== 0) {
            throw new Exception('Invalid file path: path must be within storage directory');
        }
        
        return $realFilePath;
    }

    /**
     * Run a Python script with a payload and return decoded JSON result.
     *
     * @param string $script
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function process(string $script, array $payload): array
    {
        // Sanitize script name
        $script = basename($script);
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.py$/', $script)) {
            throw new Exception('Invalid script name');
        }

        $scriptPath = base_path("python/$script");
        
        // Validate script exists
        if (!file_exists($scriptPath)) {
            throw new Exception("Python script not found: $script");
        }

        // Validate file paths in payload
        if (isset($payload['file_path'])) {
            $payload['file_path'] = $this->validatePath($payload['file_path']);
        }

        try {
            $t0 = microtime(true);
            $pythonPath = $this->getPythonPath();
            $encodedPayload = json_encode($payload);

            Log::info('Python process start', [
                'script' => $script,
                'payload_keys' => array_keys($payload),
            ]);

            $process = new Process([
                $pythonPath,
                $scriptPath,
                $encodedPayload,
            ]);

            // Phase 1: tighten timeout to 60 seconds for stability
            $process->setTimeout(60);
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if (! $process->isSuccessful()) {
                $decodedError = null;
                if (!empty($output)) {
                    $decodedError = json_decode($output, true);
                }

                $errorPayload = [
                    'status' => 'error',
                    'message' => 'Python script failed',
                    'details' => $errorOutput ?: $output,
                    'script' => $script,
                    'exit_code' => $process->getExitCode(),
                ];

                // Prefer structured error coming from Python, if present
                if (is_array($decodedError) && ($decodedError['success'] ?? null) === false) {
                    $errorPayload['details'] = $decodedError['error'] ?? $errorPayload['details'];
                    $errorPayload['error_type'] = $decodedError['error_type'] ?? null;
                }

                Log::error('Python process failed', [
                    'script' => $script,
                    'stdout' => $output,
                    'stderr' => $errorOutput,
                    'exit_code' => $process->getExitCode(),
                    'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                ]);

                throw new Exception(json_encode($errorPayload));
            }

            // Remove any surrounding quotes if present
            $output = trim($output, '"');
            $output = stripcslashes($output);

            $decoded = json_decode($output, true);

            if ($decoded === null) {
                Log::error('JSON decode failed', [
                    'script' => $script,
                    'output' => $output,
                    'stderr' => $errorOutput,
                    'json_error' => json_last_error_msg()
                ]);
                throw new Exception('JSON decode failed: ' . json_last_error_msg() . (empty($errorOutput) ? '' : ' | Python error: ' . $errorOutput));
            }

            // Check if Python script returned an error
            if (isset($decoded['success']) && $decoded['success'] === false) {
                $errorPayload = [
                    'status' => 'error',
                    'message' => 'Python script failed',
                    'details' => $decoded['error'] ?? null,
                    'script' => $script,
                ];
                throw new Exception(json_encode($errorPayload));
            }

            Log::info('Python process success', [
                'script' => $script,
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);

            return $decoded;
        } catch (Exception $e) {
            Log::error('Python processing exception', [
                'script' => $script,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
