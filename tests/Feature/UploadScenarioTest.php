<?php

declare(strict_types=1);

use Arzou\MimeGuard\MimeGuard;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->mimeGuard = new MimeGuard;

    // Reset config for each test
    Config::set('mime-guard.restricted_by_default', []);
    Config::set('mime-guard.containers', []);
    Config::set('mime-guard.blueprints', []);
});

// ============================================================
// Global Restrictions
// ============================================================

describe('Global restrictions', function () {
    it('allows all types when no restrictions are set', function () {
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg'))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf'))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4'))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip'))->toBeTrue();
    });

    it('blocks a single globally restricted type', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip']);

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg'))->toBeTrue();
    });

    it('blocks multiple globally restricted types', function () {
        Config::set('mime-guard.restricted_by_default', [
            'application/zip',
            'image/svg+xml',
            'video/mp4',
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg'))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf'))->toBeTrue();
    });

    it('blocks types matching a wildcard restriction', function () {
        Config::set('mime-guard.restricted_by_default', ['video/*']);

        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('video/webm'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('video/quicktime'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg'))->toBeTrue();
    });

    it('blocks types matching document/* custom wildcard', function () {
        Config::set('mime-guard.restricted_by_default', ['document/*']);

        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('application/msword'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/png'))->toBeTrue();
    });

    it('blocks types matching archive/* custom wildcard', function () {
        Config::set('mime-guard.restricted_by_default', ['archive/*']);

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('application/x-rar-compressed'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('application/x-7z-compressed'))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/png'))->toBeTrue();
    });

    it('returns correct rules with no context', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip', 'video/mp4']);

        $rules = $this->mimeGuard->getRules();

        expect($rules['restricted'])->toContain('application/zip');
        expect($rules['restricted'])->toContain('video/mp4');
        expect($rules['allowed'])->toBeEmpty();
    });
});

// ============================================================
// Container Rules
// ============================================================

describe('Container rules', function () {
    it('allows a globally restricted type in a specific container', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf']);
        Config::set('mime-guard.containers', [
            'documents' => ['allow' => ['application/pdf']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'documents']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf'))->toBeFalse();
    });

    it('does not affect other containers', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf']);
        Config::set('mime-guard.containers', [
            'documents' => ['allow' => ['application/pdf']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'documents']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'images']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'other']))->toBeFalse();
    });

    it('denies types at container level', function () {
        Config::set('mime-guard.restricted_by_default', []);
        Config::set('mime-guard.containers', [
            'images' => ['deny' => ['image/svg+xml']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', ['container' => 'images']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', ['container' => 'images']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml'))->toBeTrue();
    });

    it('allows with wildcard at container level', function () {
        Config::set('mime-guard.restricted_by_default', ['image/jpeg', 'image/png', 'image/gif']);
        Config::set('mime-guard.containers', [
            'gallery' => ['allow' => ['image/*']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', ['container' => 'gallery']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/png', ['container' => 'gallery']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/gif', ['container' => 'gallery']))->toBeTrue();
    });

    it('container with inherit false ignores global restrictions', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip', 'video/mp4', 'image/svg+xml']);
        Config::set('mime-guard.containers', [
            'uploads' => [
                'inherit' => false,
                'deny' => ['application/octet-stream'],
            ],
        ]);

        // Global restrictions ignored
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['container' => 'uploads']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', ['container' => 'uploads']))->toBeTrue();

        // Only container-level deny applies
        expect($this->mimeGuard->isMimeTypeAllowed('application/octet-stream', ['container' => 'uploads']))->toBeFalse();
    });

    it('container allow and deny combined', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf', 'application/zip']);
        Config::set('mime-guard.containers', [
            'mixed' => [
                'allow' => ['application/pdf'],
                'deny' => ['image/svg+xml'],
            ],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'mixed']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['container' => 'mixed']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', ['container' => 'mixed']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', ['container' => 'mixed']))->toBeTrue();
    });

    it('returns resolved rules with container context', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip']);
        Config::set('mime-guard.containers', [
            'docs' => ['allow' => ['application/zip', 'application/pdf']],
        ]);

        $rules = $this->mimeGuard->getRules(['container' => 'docs']);

        expect($rules['allowed'])->toContain('application/zip');
        expect($rules['allowed'])->toContain('application/pdf');
        expect($rules['restricted'])->not->toContain('application/zip');
    });
});

// ============================================================
// Blueprint Rules
// ============================================================

describe('Blueprint rules', function () {
    it('allows a globally restricted type in a specific blueprint', function () {
        Config::set('mime-guard.restricted_by_default', ['model/stl']);
        Config::set('mime-guard.blueprints', [
            'products::3d_models' => ['allow' => ['model/stl']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('model/stl', ['blueprint' => 'products::3d_models']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('model/stl'))->toBeFalse();
    });

    it('denies types at blueprint level', function () {
        Config::set('mime-guard.restricted_by_default', []);
        Config::set('mime-guard.blueprints', [
            'pages::article' => ['deny' => ['video/mp4', 'video/webm']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', ['blueprint' => 'pages::article']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('video/webm', ['blueprint' => 'pages::article']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', ['blueprint' => 'pages::article']))->toBeTrue();
    });

    it('blueprint with inherit false starts fresh', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip', 'video/mp4']);
        Config::set('mime-guard.blueprints', [
            'products::download' => [
                'inherit' => false,
                'allow' => ['application/zip'],
            ],
        ]);

        // Global restrictions ignored, only explicit allow
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['blueprint' => 'products::download']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', ['blueprint' => 'products::download']))->toBeTrue();
    });

    it('does not affect other blueprints', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip']);
        Config::set('mime-guard.blueprints', [
            'products::download' => ['allow' => ['application/zip']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['blueprint' => 'products::download']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['blueprint' => 'pages::article']))->toBeFalse();
    });
});

// ============================================================
// Full Hierarchy: Global → Container → Blueprint → Field
// ============================================================

describe('Full hierarchy', function () {
    it('blueprint overrides container rules', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf']);
        Config::set('mime-guard.containers', [
            'assets' => ['deny' => ['image/svg+xml']],
        ]);
        Config::set('mime-guard.blueprints', [
            'pages::hero' => ['allow' => ['image/svg+xml']],
        ]);

        $context = ['container' => 'assets', 'blueprint' => 'pages::hero'];

        // SVG denied by container, but re-allowed by blueprint
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', $context))->toBeTrue();
        // PDF still globally restricted
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', $context))->toBeFalse();
    });

    it('field overrides blueprint rules', function () {
        Config::set('mime-guard.restricted_by_default', []);
        Config::set('mime-guard.blueprints', [
            'products::gallery' => ['allow' => ['image/*']],
        ]);

        $context = [
            'blueprint' => 'products::gallery',
            'field' => ['deny' => ['image/svg+xml']],
        ];

        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/png', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', $context))->toBeFalse();
    });

    it('field with inherit false ignores all parent rules', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip', 'video/mp4']);
        Config::set('mime-guard.containers', [
            'assets' => ['deny' => ['image/svg+xml']],
        ]);
        Config::set('mime-guard.blueprints', [
            'pages::content' => ['deny' => ['application/pdf']],
        ]);

        $context = [
            'container' => 'assets',
            'blueprint' => 'pages::content',
            'field' => [
                'inherit' => false,
                'allow' => ['image/jpeg', 'image/png'],
                'deny' => ['image/gif'],
            ],
        ];

        // All parent restrictions ignored
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', $context))->toBeTrue();

        // Only field-level rules apply
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/png', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/gif', $context))->toBeFalse();
    });

    it('four-level cascade: global restricted, container allows, blueprint denies, field allows', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf']);
        Config::set('mime-guard.containers', [
            'docs' => ['allow' => ['application/pdf']],
        ]);
        Config::set('mime-guard.blueprints', [
            'pages::secure' => ['deny' => ['application/pdf']],
        ]);

        // Global: PDF blocked
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf'))->toBeFalse();

        // Container: PDF allowed
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'docs']))->toBeTrue();

        // Blueprint: PDF re-blocked
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', [
            'container' => 'docs',
            'blueprint' => 'pages::secure',
        ]))->toBeFalse();

        // Field: PDF re-allowed
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', [
            'container' => 'docs',
            'blueprint' => 'pages::secure',
            'field' => ['allow' => ['application/pdf']],
        ]))->toBeTrue();
    });

    it('container + blueprint combined without field', function () {
        Config::set('mime-guard.restricted_by_default', ['video/mp4', 'video/webm', 'video/quicktime']);
        Config::set('mime-guard.containers', [
            'media' => ['allow' => ['video/mp4']],
        ]);
        Config::set('mime-guard.blueprints', [
            'pages::video' => ['allow' => ['video/webm']],
        ]);

        $context = ['container' => 'media', 'blueprint' => 'pages::video'];

        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/webm', $context))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/quicktime', $context))->toBeFalse();
    });
});

// ============================================================
// Edge cases
// ============================================================

describe('Edge cases', function () {
    it('unknown MIME type is allowed when not restricted', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip']);

        expect($this->mimeGuard->isMimeTypeAllowed('application/x-custom-format'))->toBeTrue();
    });

    it('empty restricted and empty allowed means everything is allowed', function () {
        expect($this->mimeGuard->isMimeTypeAllowed('anything/at-all'))->toBeTrue();
    });

    it('non-existent container falls back to global rules', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip']);

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['container' => 'nonexistent']))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', ['container' => 'nonexistent']))->toBeTrue();
    });

    it('non-existent blueprint falls back to global rules', function () {
        Config::set('mime-guard.restricted_by_default', ['application/zip']);

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['blueprint' => 'nonexistent::bp']))->toBeFalse();
    });

    it('multiple containers with different rules', function () {
        Config::set('mime-guard.restricted_by_default', ['application/pdf', 'application/zip']);
        Config::set('mime-guard.containers', [
            'documents' => ['allow' => ['application/pdf']],
            'downloads' => ['allow' => ['application/zip']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'documents']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['container' => 'documents']))->toBeFalse();

        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', ['container' => 'downloads']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', ['container' => 'downloads']))->toBeFalse();
    });

    it('wildcard deny overrides individual allow', function () {
        Config::set('mime-guard.restricted_by_default', []);
        Config::set('mime-guard.containers', [
            'strict' => [
                'allow' => ['image/jpeg'],
                'deny' => ['image/*'],
            ],
        ]);

        // deny is applied after allow, so wildcard deny wins
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', ['container' => 'strict']))->toBeFalse();
    });

    it('application/octet-stream can be allowed per container', function () {
        Config::set('mime-guard.restricted_by_default', ['application/octet-stream']);
        Config::set('mime-guard.containers', [
            '3d_assets' => ['allow' => ['application/octet-stream']],
        ]);

        expect($this->mimeGuard->isMimeTypeAllowed('application/octet-stream', ['container' => '3d_assets']))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/octet-stream'))->toBeFalse();
    });
});

// ============================================================
// Real-world scenarios
// ============================================================

describe('Real-world scenarios', function () {
    it('image-only gallery: blocks everything except images', function () {
        Config::set('mime-guard.restricted_by_default', [
            'application/octet-stream', 'application/zip', 'application/pdf',
            'video/*', 'image/svg+xml',
        ]);
        Config::set('mime-guard.containers', [
            'gallery' => ['allow' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif']],
        ]);

        $ctx = ['container' => 'gallery'];
        expect($this->mimeGuard->isMimeTypeAllowed('image/jpeg', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/png', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/webp', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', $ctx))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', $ctx))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', $ctx))->toBeFalse();
    });

    it('3D product page: allows STL and GLB files', function () {
        Config::set('mime-guard.restricted_by_default', [
            'application/octet-stream', 'model/stl', 'application/sla',
            'model/gltf-binary',
        ]);
        Config::set('mime-guard.blueprints', [
            'products::3d_viewer' => [
                'allow' => ['model/stl', 'application/sla', 'model/gltf-binary'],
            ],
        ]);

        $ctx = ['blueprint' => 'products::3d_viewer'];
        expect($this->mimeGuard->isMimeTypeAllowed('model/stl', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/sla', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('model/gltf-binary', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/octet-stream', $ctx))->toBeFalse();
    });

    it('document portal: PDF allowed, archives blocked', function () {
        Config::set('mime-guard.restricted_by_default', ['archive/*', 'application/pdf']);
        Config::set('mime-guard.containers', [
            'portal' => ['allow' => ['application/pdf', 'document/*']],
        ]);

        $ctx = ['container' => 'portal'];
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/msword', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', $ctx))->toBeFalse();
        expect($this->mimeGuard->isMimeTypeAllowed('application/x-rar-compressed', $ctx))->toBeFalse();
    });

    it('open container with inherit false: allows everything except explicit denies', function () {
        Config::set('mime-guard.restricted_by_default', [
            'application/zip', 'video/mp4', 'image/svg+xml', 'application/pdf',
        ]);
        Config::set('mime-guard.containers', [
            'open' => [
                'inherit' => false,
                'deny' => ['application/octet-stream'],
            ],
        ]);

        $ctx = ['container' => 'open'];
        expect($this->mimeGuard->isMimeTypeAllowed('application/zip', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('video/mp4', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('image/svg+xml', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/pdf', $ctx))->toBeTrue();
        expect($this->mimeGuard->isMimeTypeAllowed('application/octet-stream', $ctx))->toBeFalse();
    });
});
