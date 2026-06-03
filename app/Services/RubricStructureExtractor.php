<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

class RubricStructureExtractor
{
    /**
     * @return array<int, array{criteria: string, max_score: int, weight: float|int}>
     */
    public function extractFromPdf(UploadedFile $pdf): array
    {
        $apiKey = (string) config('services.rubric_llm.api_key', '');
        $responsesEndpoint = (string) config('services.rubric_llm.endpoint');
        $filesEndpoint = (string) config('services.rubric_llm.files_endpoint');
        $model = (string) config('services.rubric_llm.model');

        if ($apiKey === '' || $responsesEndpoint === '' || $filesEndpoint === '' || $model === '') {
            return [];
        }

        $binary = file_get_contents($pdf->getRealPath());

        if (! is_string($binary) || $binary === '') {
            return [];
        }

        $uploadedFileId = null;

        try {
            $uploadedFileId = $this->uploadFile($apiKey, $filesEndpoint, $pdf->getClientOriginalName(), $binary);

            if ($uploadedFileId === null) {
                return [];
            }

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.rubric_llm.connect_timeout', 10))
                ->timeout((int) config('services.rubric_llm.timeout', 45))
                ->retry(2, 500)
                ->post($responsesEndpoint, $this->payload($uploadedFileId, $model))
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        } finally {
            if ($uploadedFileId !== null) {
                $this->deleteFile($apiKey, $filesEndpoint, $uploadedFileId);
            }
        }

        return $this->normalizeStructure($this->extractStructure($response));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $fileId, string $model): array
    {
        return [
            'model' => $model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Extract this rubric PDF into JSON. Return criteria in structure_json with fields: criteria (string), max_score (integer), weight (number). If values are missing, infer sensible defaults from rubric context and keep total weight near 100.',
                        ],
                        [
                            'type' => 'input_file',
                            'file_id' => $fileId,
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'rubric_structure',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'structure_json' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'criteria' => ['type' => 'string'],
                                        'max_score' => ['type' => 'integer'],
                                        'weight' => ['type' => 'number'],
                                    ],
                                    'required' => ['criteria', 'max_score', 'weight'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['structure_json'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }

    private function uploadFile(string $apiKey, string $filesEndpoint, string $filename, string $binary): ?string
    {
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout((int) config('services.rubric_llm.connect_timeout', 10))
            ->timeout((int) config('services.rubric_llm.timeout', 45))
            ->retry(2, 500)
            ->attach('file', $binary, $filename, ['Content-Type' => 'application/pdf'])
            ->post($filesEndpoint, [
                'purpose' => 'user_data',
            ])
            ->throw()
            ->json();

        $id = $response['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function deleteFile(string $apiKey, string $filesEndpoint, string $fileId): void
    {
        try {
            Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.rubric_llm.connect_timeout', 10))
                ->timeout((int) config('services.rubric_llm.timeout', 45))
                ->delete(rtrim($filesEndpoint, '/').'/'.$fileId)
                ->throw();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    private function extractStructure(array $response): array
    {
        if ($this->hasRefusal($response)) {
            return [];
        }

        $fromOutputText = json_decode((string) ($response['output_text'] ?? ''), true);

        if (is_array($fromOutputText) && isset($fromOutputText['structure_json']) && is_array($fromOutputText['structure_json'])) {
            return $fromOutputText['structure_json'];
        }

        $outputItems = $response['output'] ?? [];

        if (is_array($outputItems)) {
            foreach ($outputItems as $item) {
                if (! is_array($item) || ! isset($item['content']) || ! is_array($item['content'])) {
                    continue;
                }

                foreach ($item['content'] as $contentItem) {
                    if (! is_array($contentItem) || ($contentItem['type'] ?? null) !== 'output_text') {
                        continue;
                    }

                    $parsed = json_decode((string) ($contentItem['text'] ?? ''), true);

                    if (is_array($parsed) && isset($parsed['structure_json']) && is_array($parsed['structure_json'])) {
                        return $parsed['structure_json'];
                    }
                }
            }
        }

        $firstJsonString = $this->findJsonString($response);

        if ($firstJsonString === null) {
            return [];
        }

        $decoded = json_decode($firstJsonString, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['structure_json']) && is_array($decoded['structure_json'])) {
            return $decoded['structure_json'];
        }

        return isset($decoded[0]) && is_array($decoded[0]) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function hasRefusal(array $response): bool
    {
        $outputItems = $response['output'] ?? [];

        if (! is_array($outputItems)) {
            return false;
        }

        foreach ($outputItems as $item) {
            if (! is_array($item) || ! isset($item['content']) || ! is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $contentItem) {
                if (is_array($contentItem) && ($contentItem['type'] ?? null) === 'refusal') {
                    return true;
                }
            }
        }

        return false;
    }

    private function findJsonString(mixed $value): ?string
    {
        if (is_string($value)) {
            $candidate = trim($value);

            if (($candidate !== '' && str_starts_with($candidate, '{')) || str_starts_with($candidate, '[')) {
                $decoded = json_decode($candidate, true);

                if (is_array($decoded)) {
                    return $candidate;
                }
            }

            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $item) {
            $result = $this->findJsonString($item);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $structure
     * @return array<int, array{criteria: string, max_score: int, weight: float|int}>
     */
    private function normalizeStructure(array $structure): array
    {
        $normalized = [];

        foreach ($structure as $item) {
            if (! is_array($item)) {
                continue;
            }

            $criteria = trim((string) ($item['criteria'] ?? ''));
            $maxScore = (int) ($item['max_score'] ?? 0);
            $weight = (float) ($item['weight'] ?? 0);

            if ($criteria === '' || $maxScore < 1 || $weight < 0) {
                continue;
            }

            $weight = min($weight, 100);

            $normalized[] = [
                'criteria' => $criteria,
                'max_score' => $maxScore,
                'weight' => $weight,
            ];
        }

        return $this->normalizeWeights($normalized);
    }

    /**
     * @param  array<int, array{criteria: string, max_score: int, weight: float|int}>  $structure
     * @return array<int, array{criteria: string, max_score: int, weight: float|int}>
     */
    private function normalizeWeights(array $structure): array
    {
        if ($structure === []) {
            return $structure;
        }

        $total = array_sum(array_map(fn ($item) => (float) $item['weight'], $structure));
        $count = count($structure);

        if ($total <= 0) {
            $equalWeight = round(100 / $count, 2);
            $remaining = 100.0;

            foreach ($structure as $index => $item) {
                if ($index === $count - 1) {
                    $item['weight'] = round($remaining, 2);
                } else {
                    $item['weight'] = $equalWeight;
                    $remaining -= $equalWeight;
                }

                $structure[$index] = $item;
            }

            return $structure;
        }

        $remaining = 100.0;

        foreach ($structure as $index => $item) {
            if ($index === $count - 1) {
                $item['weight'] = round($remaining, 2);
            } else {
                $weight = round(((float) $item['weight'] / $total) * 100, 2);
                $item['weight'] = $weight;
                $remaining -= $weight;
            }

            $structure[$index] = $item;
        }

        return $structure;
    }
}
