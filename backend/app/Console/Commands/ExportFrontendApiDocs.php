<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('api:docs {--output= : Existing output directory, defaults to backend/docs}')]
#[Description('Export OpenAPI and the frontend endpoint/schema reference without reading application records')]
class ExportFrontendApiDocs extends Command
{
    public function handle(): int
    {
        $directory = $this->option('output') ?? base_path('docs');
        if (! is_dir($directory)) {
            $this->error('Output must be an existing directory.');

            return self::INVALID;
        }
        $path = $directory.DIRECTORY_SEPARATOR.'openapi.json';
        if ($this->call('scramble:export', ['--path' => $path]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        $spec = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        File::put($directory.DIRECTORY_SEPARATOR.'API_REFERENCE.md', $this->render($spec));
        $this->info('OpenAPI and API_REFERENCE.md exported to '.$directory);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $spec */
    private function render(array $spec): string
    {
        $lines = ['# Referensi endpoint ERP Tungku', '',
            'Dihasilkan oleh `php artisan api:docs` dari route, FormRequest dan Resource backend. Lihat [panduan integrasi](FRONTEND_API.md) dan [OpenAPI lengkap](openapi.json).', '',
            'Prefix semua path di bawah: `/api`. `PUT` dan `PATCH` resource memakai validasi update yang sama; PATCH belum tentu menerima body parsial. Field optional bisa tidak hadir; nullable berarti nilainya boleh null. Semua contoh dan schema bersifat struktural, bukan data akun nyata.', '',
            '| Method | Path | Operasi |', '| --- | --- | --- |'];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'], true)) {
                    continue;
                }
                $operation['operationId'] ??= $method.'.'.Str::slug($path);
                $lines[] = '| '.strtoupper($method).' | `'.$path.'` | ['.$operation['operationId'].'](#'.Str::slug($operation['operationId']).') |';
            }
        }
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'], true)) {
                    continue;
                }
                $operation['operationId'] ??= $method.'.'.Str::slug($path);
                $lines[] = '\n<a id="'.Str::slug($operation['operationId']).'"></a>';
                $lines[] = '\n## '.$operation['operationId'].'\n';
                $lines[] = '`'.strtoupper($method).' /api'.$path.'`';
                $lines[] = '\n'.($operation['description'] ?? $operation['summary'] ?? '');
                $lines[] = '\nAutentikasi: '.(empty($operation['security']) ? 'tanpa session pengguna; webhook tetap memerlukan verifikasi provider.' : 'session Sanctum, dengan policy ownership/role endpoint.');
                if (! empty($operation['parameters'])) {
                    $lines[] = '\n| Parameter | Lokasi | Wajib | Tipe / aturan |';
                    $lines[] = '| --- | --- | --- | --- |';
                    foreach ($operation['parameters'] as $parameter) {
                        $lines[] = '| `'.$parameter['name'].'` | '.$parameter['in'].' | '.(! empty($parameter['required']) ? 'ya' : 'tidak').' | '.$this->describe($parameter['schema'] ?? []).' |';
                    }
                }
                foreach ($operation['requestBody']['content'] ?? [] as $media => $body) {
                    $lines[] = '\nBody `'.$media.'`: '.$this->describe($body['schema'] ?? []);
                    if (isset($body['schema']['properties'])) {
                        $lines[] = $this->fields($body['schema']);
                    }
                }
                $lines[] = '\n| Status | Response |';
                $lines[] = '| --- | --- |';
                foreach ($operation['responses'] ?? [] as $status => $response) {
                    if (isset($response['$ref'])) {
                        $parts = explode('/', $response['$ref']);
                        $response = $spec['components']['responses'][end($parts)] ?? $response;
                    }
                    $schema = $response['content']['application/json']['schema'] ?? [];
                    $lines[] = '| '.$status.' | '.$this->describe($schema).' '.$this->escape($response['description'] ?? '').' |';
                }
            }
        }
        $lines[] = '\n# Schema request dan response\n';
        foreach ($spec['components']['schemas'] ?? [] as $name => $schema) {
            $lines[] = '\n## '.$name.'\n';
            $lines[] = $this->fields($schema);
        }

        return str_replace('\n', "\n", implode("\n", $lines))."\n";
    }

    /** @param array<string, mixed> $schema */
    private function fields(array $schema): string
    {
        if (! isset($schema['properties'])) {
            return $this->describe($schema);
        }
        $lines = ['| Field | Wajib | Tipe / aturan |', '| --- | --- | --- |'];
        foreach ($schema['properties'] as $name => $property) {
            $lines[] = '| `'.$name.'` | '.(in_array($name, $schema['required'] ?? [], true) ? 'ya' : 'tidak').' | '.$this->describe($property).' |';
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $schema */
    private function describe(array $schema): string
    {
        if (isset($schema['$ref'])) {
            $name = basename($schema['$ref']);

            return '['.$name.'](#'.Str::slug($name).')';
        }
        if (isset($schema['properties'])) {
            $fields = [];
            foreach ($schema['properties'] as $name => $property) {
                $fields[] = $name.(in_array($name, $schema['required'] ?? [], true) ? '' : '?').': '.$this->describe($property);
            }

            return '{ '.implode('; ', $fields).' }'.(in_array('null', (array) ($schema['type'] ?? []), true) ? ' / null' : '');
        }
        foreach (['anyOf', 'oneOf', 'allOf'] as $combination) {
            if (isset($schema[$combination])) {
                return implode(' / ', array_map(fn (array $item): string => $this->describe($item), $schema[$combination]));
            }
        }
        $description = implode(' / ', (array) ($schema['type'] ?? ['any']));
        if (isset($schema['items'])) {
            $description .= '&lt;'.$this->describe($schema['items']).'&gt;';
        }
        foreach (['enum', 'format', 'minimum', 'maximum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'pattern'] as $constraint) {
            if (array_key_exists($constraint, $schema)) {
                $description .= '; '.$constraint.'='.$this->escape(json_encode($schema[$constraint], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
        }

        return $description;
    }

    private function escape(string $value): string
    {
        return str_replace(["\r", "\n", '|'], ['', ' ', '&#124;'], $value);
    }
}
