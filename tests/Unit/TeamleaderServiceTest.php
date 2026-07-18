<?php

namespace Tests\Unit;

use App\Services\TeamleaderService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TeamleaderServiceTest extends TestCase
{
    public function test_it_reads_download_location_from_files_download_response(): void
    {
        $this->assertSame(
            'https://downloads.example.test/passport.pdf',
            $this->extractDownloadLocation([
                'data' => [
                    'location' => 'https://downloads.example.test/passport.pdf',
                ],
            ])
        );
    }

    public function test_it_supports_download_url_fallback(): void
    {
        $this->assertSame(
            'https://downloads.example.test/photo.jpg',
            $this->extractDownloadLocation([
                'data' => [
                    'download_url' => 'https://downloads.example.test/photo.jpg',
                ],
            ])
        );
    }

    private function extractDownloadLocation(array $payload): ?string
    {
        $method = new ReflectionMethod(TeamleaderService::class, 'extractDownloadLocation');
        $method->setAccessible(true);

        return $method->invoke(new TeamleaderService(), $payload);
    }
}
