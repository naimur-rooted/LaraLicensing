<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;

/** Canonical manifest JSON, file normalisation, and file mismatch lists. */
final class TamperManifestCanonicalizer
{
    /** @param array<string, mixed> $data */
    public function canonicalJson(array $data): string
    {
        return json_encode($this->sortKeys($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function normalizeFiles(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                throw ValidationException::invalidInput('files must be an array of file entries.');
            }
            $path = trim((string) ($file['path'] ?? ''));
            $sha = strtolower(trim((string) ($file['sha256'] ?? '')));
            if ($path === '' || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
                throw ValidationException::invalidInput('each file needs a path and a 64 hex sha256.');
            }
            if (isset($out[$path])) {
                throw ValidationException::invalidInput('duplicate file path in manifest.');
            }
            $out[$path] = ['path' => $path, 'sha256' => $sha, 'bytes' => (int) ($file['bytes'] ?? 0)];
        }
        ksort($out);

        return array_values($out);
    }

    public function fileMismatches(array $manifest, array $byPath): array
    {
        $mismatches = [];
        $manifestPaths = [];
        foreach ($manifest as $file) {
            $manifestPaths[$file['path']] = true;
            $submitted = $byPath[$file['path']] ?? null;
            if ($submitted === null) {
                $mismatches[] = $file['path'] . ': missing';
            }
            if ($submitted !== null && $submitted['sha256'] !== $file['sha256']) {
                $mismatches[] = $file['path'] . ': hash mismatch';
            }
        }
        foreach ($byPath as $path => $file) {
            if (isset($manifestPaths[$path]) === false) {
                $mismatches[] = $path . ': extra file not in manifest';
            }
        }

        return $mismatches;
    }

    private function sortKeys(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value) ? $this->sortKeys($value) : $value;
        }

        return $data;
    }
}
