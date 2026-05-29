<?php

declare(strict_types=1);

use Arzou\MimeGuard\MimeGuard;
use Arzou\MimeGuard\Rules\MimeValidator;
use Illuminate\Support\Facades\Config;

// ============================================================
// Magic bytes detection (not extension-based)
// ============================================================

describe('MIME detection uses magic bytes, not file extensions', function () {
    it('detects a JPEG regardless of file extension', function () {
        $validator = new MimeValidator;
        $path = createTempFile(hex2bin('FFD8FFE0'), 'fake.png');

        expect($validator->getMimeTypeFromContent($path))->toBe('image/jpeg');
    });

    it('detects a PNG regardless of file extension', function () {
        $validator = new MimeValidator;
        // Full PNG header with IHDR chunk needed for finfo
        $path = createTempFile(hex2bin('89504E470D0A1A0A0000000D49484452000000010000000108060000001F15C489'), 'fake.txt');

        expect($validator->getMimeTypeFromContent($path))->toBe('image/png');
    });

    it('detects a GIF regardless of file extension', function () {
        $validator = new MimeValidator;
        $path = createTempFile('GIF89a', 'fake.doc');

        expect($validator->getMimeTypeFromContent($path))->toBe('image/gif');
    });

    it('detects a PDF regardless of file extension', function () {
        $validator = new MimeValidator;
        $path = createTempFile('%PDF-1.4', 'fake.jpg');

        expect($validator->getMimeTypeFromContent($path))->toBe('application/pdf');
    });

    it('detects a ZIP signature from file content', function () {
        $validator = new MimeValidator;
        // PK signature with enough data — finfo may return zip or octet-stream
        $path = createTempFile(hex2bin('504B0304140000000000') . str_repeat("\0", 100), 'fake.jpg');

        $mime = $validator->getMimeTypeFromContent($path);
        expect(in_array($mime, ['application/zip', 'application/octet-stream']))->toBeTrue();
    });

    it('returns octet-stream for unknown binary content', function () {
        $validator = new MimeValidator;
        $path = createTempFile(hex2bin('DEADBEEF00112233'), 'unknown.bin');

        expect($validator->getMimeTypeFromContent($path))->toBe('application/octet-stream');
    });

    it('throws exception for non-existent file', function () {
        $validator = new MimeValidator;

        expect(fn () => $validator->getMimeTypeFromContent('/nonexistent/path.jpg'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

// ============================================================
// Extension spoofing attacks
// ============================================================

describe('Extension spoofing prevention', function () {
    it('blocks a PHP script disguised as a JPEG', function () {
        Config::set('mime-guard.restricted_by_default', ['text/x-php', 'text/x-c', 'text/plain']);
        Config::set('mime-guard.containers', []);
        Config::set('mime-guard.blueprints', []);

        $guard = new MimeGuard;
        $path = createTempFile('<?php echo "pwned"; ?>', 'evil.jpg');

        expect($guard->isFileAllowed($path))->toBeFalse();
    });

    it('blocks a shell script disguised as an image', function () {
        Config::set('mime-guard.restricted_by_default', ['text/x-shellscript', 'text/plain']);
        Config::set('mime-guard.containers', []);
        Config::set('mime-guard.blueprints', []);

        $guard = new MimeGuard;
        $path = createTempFile("#!/bin/bash\nrm -rf /", 'cute-cat.png');

        expect($guard->isFileAllowed($path))->toBeFalse();
    });

    it('blocks a ZIP disguised as an image', function () {
        // Both zip and octet-stream blocked — finfo may detect either
        Config::set('mime-guard.restricted_by_default', ['application/zip', 'application/octet-stream']);
        Config::set('mime-guard.containers', []);
        Config::set('mime-guard.blueprints', []);

        $guard = new MimeGuard;
        $path = createTempFile(hex2bin('504B0304140000000000'), 'photo.jpg');

        expect($guard->isFileAllowed($path))->toBeFalse();
    });

    it('blocks a PDF disguised as an image', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf']);
        Config::set('mime-guard.containers', []);
        Config::set('mime-guard.blueprints', []);

        $guard = new MimeGuard;
        $path = createTempFile('%PDF-1.4 malicious payload', 'avatar.png');

        expect($guard->isFileAllowed($path))->toBeFalse();
    });
});

// ============================================================
// SVG XSS protection
// ============================================================

describe('SVG XSS protection', function () {
    it('SVG is blocked by default config', function () {
        Config::set('mime-guard.restricted_by_default', ['image/svg+xml']);
        Config::set('mime-guard.containers', []);
        Config::set('mime-guard.blueprints', []);

        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('image/svg+xml'))->toBeFalse();
    });

    it('detects SVG with embedded JavaScript from file content', function () {
        $validator = new MimeValidator;
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert("xss")</script></svg>';
        $path = createTempFile($svg, 'icon.svg');

        // finfo detects SVG content as image/svg+xml
        expect($validator->getMimeTypeFromContent($path))->toBe('image/svg+xml');
    });

    it('SVG can be explicitly allowed in a specific container', function () {
        Config::set('mime-guard.restricted_by_default', ['image/svg+xml']);
        Config::set('mime-guard.containers', [
            'icons' => ['allow' => ['image/svg+xml']],
        ]);
        Config::set('mime-guard.blueprints', []);

        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('image/svg+xml', ['container' => 'icons']))->toBeTrue();
        expect($guard->isMimeTypeAllowed('image/svg+xml', ['container' => 'uploads']))->toBeFalse();
        expect($guard->isMimeTypeAllowed('image/svg+xml'))->toBeFalse();
    });
});

// ============================================================
// Default config security
// ============================================================

describe('Default config blocks dangerous types', function () {
    beforeEach(function () {
        // Load the actual default config
        $config = require __DIR__.'/../../config/mime-guard.php';
        Config::set('mime-guard', $config);
    });

    it('blocks binary/executable by default', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('application/octet-stream'))->toBeFalse();
    });

    it('blocks archives by default', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('application/zip'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('application/x-rar-compressed'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('application/x-7z-compressed'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('application/x-tar'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('application/gzip'))->toBeFalse();
    });

    it('blocks SVG by default (XSS risk)', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('image/svg+xml'))->toBeFalse();
    });

    it('blocks PDF by default (can contain JavaScript)', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('application/pdf'))->toBeFalse();
    });

    it('blocks 3D model formats by default', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('model/stl'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('application/sla'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('model/gltf+json'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('model/gltf-binary'))->toBeFalse();
    });

    it('blocks video by default', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('video/mp4'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('video/webm'))->toBeFalse();
        expect($guard->isMimeTypeAllowed('video/quicktime'))->toBeFalse();
    });

    it('allows safe image types by default', function () {
        $guard = new MimeGuard;

        expect($guard->isMimeTypeAllowed('image/jpeg'))->toBeTrue();
        expect($guard->isMimeTypeAllowed('image/png'))->toBeTrue();
        expect($guard->isMimeTypeAllowed('image/gif'))->toBeTrue();
        expect($guard->isMimeTypeAllowed('image/webp'))->toBeTrue();
    });
});

// ============================================================
// Rejection response
// ============================================================

describe('Upload rejection', function () {
    it('rejectUpload throws HttpResponseException with 422', function () {
        $listener = new \Arzou\MimeGuard\Listeners\AssetSavingListener;

        $reflection = new \ReflectionMethod($listener, 'rejectUpload');
        $reflection->setAccessible(true);

        try {
            $reflection->invoke($listener, 'application/zip');
            $this->fail('Expected HttpResponseException');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $response = $e->getResponse();
            expect($response->getStatusCode())->toBe(422);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('message');
            expect($data)->toHaveKey('errors');
            expect($data['errors'])->toHaveKey('file');
            // Translation may not be loaded, check structure is correct
            expect($data['message'])->toBeString();
            expect($data['errors']['file'])->toBeArray();
            expect($data['errors']['file'][0])->toBeString();
        }
    });
});

// ============================================================
// Logging
// ============================================================

describe('Rejection logging', function () {
    it('logs rejection when logging is enabled', function () {
        Config::set('mime-guard.logging.enabled', true);
        Config::set('mime-guard.logging.channel', 'stack');

        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->with('stack')
            ->once()
            ->andReturnSelf();

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'MIME Guard')
                    && $context['mime_type'] === 'application/zip'
                    && $context['filename'] === 'test.zip';
            });

        $listener = new \Arzou\MimeGuard\Listeners\AssetSavingListener;

        $reflection = new \ReflectionMethod($listener, 'logRejection');
        $reflection->setAccessible(true);
        $reflection->invoke($listener, 'application/zip', 'test.zip', ['container' => 'uploads']);
    });

    it('does not log when logging is disabled', function () {
        Config::set('mime-guard.logging.enabled', false);

        \Illuminate\Support\Facades\Log::shouldReceive('channel')->never();

        $listener = new \Arzou\MimeGuard\Listeners\AssetSavingListener;

        $reflection = new \ReflectionMethod($listener, 'logRejection');
        $reflection->setAccessible(true);
        $reflection->invoke($listener, 'application/zip', 'test.zip', ['container' => 'uploads']);
    });
});

// ============================================================
// Helper: create temp file with specific content
// ============================================================

function createTempFile(string $content, string $filename = 'test.bin'): string
{
    $dir = sys_get_temp_dir().'/mime-guard-tests';
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = $dir.'/'.$filename;
    file_put_contents($path, $content);

    // Clean up after test
    register_shutdown_function(function () use ($path) {
        if (file_exists($path)) {
            unlink($path);
        }
    });

    return $path;
}
