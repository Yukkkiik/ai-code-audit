<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SourceType;
use App\Exceptions\InvalidRepositoryUrlException;
use App\Services\Ingestion\GitUrlValidator;
use App\Services\Ingestion\ValidatedGitUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StoreAuditRequest extends FormRequest
{
    private ?ValidatedGitUrl $validatedGitUrl = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::enum(SourceType::class)],
            'file' => ['exclude_unless:source_type,zip', 'required', 'file', 'extensions:zip', $this->zipSignature()],
            'repository_url' => ['exclude_unless:source_type,git', 'required', 'string', 'max:2048'],
        ];
    }

    /**
     * Kebijakan URL Git (allowlist + anti-SSRF) dijalankan setelah aturan dasar lolos,
     * dan hasil normalisasinya disimpan agar DNS tidak di-resolve dua kali.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('source_type') !== SourceType::Git->value || $validator->errors()->hasAny(['source_type', 'repository_url'])) {
                return;
            }

            try {
                $this->validatedGitUrl = $this->container->make(GitUrlValidator::class)->validate((string) $this->input('repository_url'));
            } catch (InvalidRepositoryUrlException $e) {
                $validator->errors()->add('repository_url', $e->getMessage());
            }
        }];
    }

    public function sourceType(): SourceType
    {
        return SourceType::from((string) $this->validated('source_type'));
    }

    public function gitRepository(): ValidatedGitUrl
    {
        return $this->validatedGitUrl ?? throw new LogicException('URL Git belum divalidasi.');
    }

    /**
     * Berkas di atas batas ukuran dijawab 413 (API_CONTRACT.md), termasuk yang ditolak oleh batas PHP.
     */
    protected function prepareForValidation(): void
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            return;
        }

        $rejectedByPhp = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

        if ($rejectedByPhp || $file->getSize() > (int) config('audit.max_repository_bytes')) {
            throw new HttpException(413);
        }
    }

    /**
     * Memeriksa magic bytes ZIP ("PK\x03\x04", atau "PK\x05\x06" untuk arsip kosong),
     * bukan hanya ekstensi nama file.
     */
    private function zipSignature(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile || ! $value->isValid() || ! is_readable((string) $value->getRealPath())) {
                return;
            }

            $handle = fopen((string) $value->getRealPath(), 'rb');
            $signature = (string) fread($handle, 4);
            fclose($handle);

            if (! in_array($signature, ["PK\x03\x04", "PK\x05\x06"], true)) {
                $fail('Berkas bukan arsip ZIP yang valid.');
            }
        };
    }
}
