<?php

declare(strict_types=1);

require_once __DIR__ . '/GalleryRepository.php';
require_once __DIR__ . '/admin/controllers/GalleryAdminController.php';

final class GalleryModule implements ModuleInterface, ModuleComponentProviderInterface, ModuleFrontendScriptProviderInterface
{
    use GalleryAdminController;

    private ?ModuleContext $context = null;
    private ?GalleryRepository $repository = null;
    private ?MediaLibrary $media = null;

    public function boot(ModuleContext $context): void
    {
        $this->context = $context;
        // Ensure the core media schema exists before Gallery creates a foreign
        // key to media.id. This keeps first-run module activation deterministic.
        $this->media = new MediaLibrary();
        $this->repository = new GalleryRepository();
    }

    public function adminMenu(): array
    {
        return [[
            'label' => 'Gallerier',
            'route' => 'gallerier',
            'icon' => 'image',
            'capability' => 'content.manage',
            'key' => 'module:gallery',
        ]];
    }

    public function adminRoutes(): array
    {
        return [[
            'route' => 'gallerier',
            'methods' => ['GET', 'POST'],
            'capability' => 'content.manage',
            'handler' => 'adminGalleries',
            'nav_key' => 'module:gallery',
        ]];
    }

    /** Functional frontend behavior only. CSS belongs to frontend/theme. */
    public function frontendScripts(): array
    {
        return ['gallery-lightbox.js', 'gallery-slider.js'];
    }

    public function componentOptions(): array
    {
        $options = [];
        foreach ($this->repository()->activeGalleries() as $gallery) {
            $id = (int)($gallery['id'] ?? 0);
            if ($id > 0) {
                $options['gallery-' . $id] = 'Galleri · ' . (string)($gallery['name'] ?? ('#' . $id));
            }
        }
        return $options;
    }


    public function renderComponent(string $component, array $modifiers = []): bool
    {
        if (!preg_match('/^gallery-([1-9][0-9]*)$/', $component, $match)) {
            return false;
        }

        $galleryId = (int)$match[1];
        $gallery = $this->repository()->findGallery($galleryId, true);
        if (!$gallery) return true;

        $items = $this->repository()->itemsForGallery($galleryId);
        if (!$items) return true;

        $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

        // New placements own layout; old pages inherit their historical state.
        $mode = (string)($modifiers['gallery_mode'] ?? '');
        $legacyLayout = ($modifiers['gallery_layout'] ?? 'grid') === 'slider' ? 'slider' : 'grid';
        $layout = $mode === 'slider' ? 'slider' : ($mode === 'lightbox' ? 'grid' : $legacyLayout);
        $legacyLightbox = (string)($modifiers['gallery_lightbox'] ?? 'inherit');
        $lightbox = $mode === 'lightbox' ? true : ($mode === 'slider' ? false
            : ($legacyLightbox === 'on' ? true : ($legacyLightbox === 'off' ? false : (int)($gallery['lightbox_enabled'] ?? 0) === 1)));
        $columns = max(2, min(6, (int)($modifiers['gallery_columns'] ?? 3)));
        $fullscreen = (string)($modifiers['gallery_fullscreen'] ?? '1') !== '0';
        $autoplay = $layout === 'slider' && (string)($modifiers['gallery_autoplay'] ?? '0') === '1';
        $interval = max(3, min(10, (int)($modifiers['gallery_interval'] ?? 5)));

        echo '<div class="gallery gallery--' . $layout . ($lightbox ? ' gallery--lightbox' : '') . '"';
        if ($layout !== 'slider') echo ' data-gallery-columns="' . $columns . '"';
        if ($lightbox) echo ' data-gallery-viewer="' . ($fullscreen ? 'fullscreen' : 'contained') . '"';
        if ($layout === 'slider') {
            echo ' role="region" aria-roledescription="carousel" data-gallery-slider' . ($autoplay ? ' data-gallery-autoplay="1" data-gallery-interval="' . $interval . '"' : '');
        } else {
            echo ' role="list"';
        }
        echo ' aria-label="' . $e((string)$gallery['name']) . '"';
        if ($lightbox) {
            foreach (['close' => 'Luk billedvisning', 'previous' => 'Forrige billede',
                'next' => 'Næste billede', 'title' => 'Billedvisning'] as $key => $label) {
                echo ' data-gallery-label-' . $key . '="' . $e(t('gallery:' . $label)) . '"';
            }
        }
        echo '>';
        if ($lightbox) {
            // The frontend viewer clones Core-owned icons from inert markup.
            // No duplicated SVG path definitions or frontend CSS in this module.
            echo '<template data-gallery-viewer-icons>';
            foreach (['previous' => 'chevron', 'next' => 'chevron', 'close' => 'close'] as $direction => $icon) {
                echo '<span data-gallery-icon="' . $direction . '">' . AdminIcons::render($icon) . '</span>';
            }
            echo '</template>';
        }
        foreach ($items as $item) {
            $path = $this->media()->publicUrl((string)($item['file_path'] ?? ''));
            if ($path === '') continue;
            $alt = (string)($item['alt_text'] ?? '');
            $width = max(0, (int)($item['width'] ?? 0));
            $height = max(0, (int)($item['height'] ?? 0));
            $dimensions = ($width > 0 && $height > 0) ? ' width="' . $width . '" height="' . $height . '"' : '';
            $srcset = ImageVariants::srcset($path, $width, $height);
            $responsive = $srcset !== '' ? ' srcset="' . $e($srcset) . '" sizes="100vw"' : '';
            $preview = ImageVariants::preferred($path, $layout === 'slider' ? 'large' : 'medium');
            echo '<figure class="gallery__item" role="' . ($layout === 'slider' ? 'group' : 'listitem') . '"' . ($layout === 'slider' ? ' aria-roledescription="slide"' : '') . '>'; 
            if ($lightbox) {
                // Real local anchor retains a working full-image fallback if JS is disabled.
                echo '<a class="gallery__link" href="' . $e($path) . '" data-gallery-lightbox-image aria-label="'
                    . $e(t('gallery:Åbn billede') . ($alt !== '' ? ': ' . $alt : '')) . '">';
            }
            echo '<img src="' . $e($preview) . '" alt="' . $e($alt) . '" loading="lazy" decoding="async"' . $dimensions . $responsive . '>';
            if ($lightbox) echo '</a>';
            echo '</figure>';
        }
        if ($layout === 'slider') {
            echo '<div class="gallery__controls" data-gallery-controls hidden>';
            if ($autoplay) echo '<button type="button" class="gallery__play" data-gallery-play aria-label="' . $e(t('gallery:Pause slideshow')) . '" data-gallery-play-label="' . $e(t('gallery:Play slideshow')) . '" data-gallery-pause-label="' . $e(t('gallery:Pause slideshow')) . '" aria-pressed="true"><span class="gallery__play-icon gallery__play-icon--pause" data-gallery-pause-icon>' . AdminIcons::render('pause') . '</span><span class="gallery__play-icon" data-gallery-resume-icon hidden>' . AdminIcons::render('play') . '</span></button>';
            echo '<button type="button" class="gallery__previous" data-gallery-previous aria-label="' . $e(t('gallery:Forrige billede')) . '">' . AdminIcons::render('chevron') . '</button>';
            echo '<span class="gallery__counter" data-gallery-counter role="status" aria-live="polite"></span>';
            echo '<button type="button" class="gallery__next" data-gallery-next aria-label="' . $e(t('gallery:Næste billede')) . '">' . AdminIcons::render('chevron') . '</button>';
            echo '</div>';
        }
        echo '</div>';
        return true;
    }

    /** @return array{0:array<string,mixed>,1:array<int,int>,2:array<int,array<string,mixed>>,3:array<string,string>} */
    private function normalizeAdminSubmission(array $source): array
    {
        $errors = [];
        $name = $this->limitText(trim((string)($source['name'] ?? '')), 191);
        $description = $this->limitText(trim((string)($source['description'] ?? '')), 2000);
        $status = (string)($source['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        if ($name === '') $errors['name'] = 'Navn skal udfyldes.';

        $rawIds = is_array($source['media_ids'] ?? null) ? $source['media_ids'] : [];
        $mediaIds = [];
        $items = [];
        foreach ($rawIds as $rawId) {
            $mediaId = max(0, (int)$rawId);
            if ($mediaId < 1 || in_array($mediaId, $mediaIds, true)) continue;
            if (count($mediaIds) >= 100) {
                $errors['images'] = 'Et galleri kan højst indeholde 100 billeder.';
                break;
            }
            $media = $this->media()->find($mediaId);
            if (!$media || ($media['media_kind'] ?? '') !== 'image') continue;
            $mediaIds[] = $mediaId;
            $items[] = $media;
        }

        return [[
            'name' => $name,
            'description' => $description,
            'status' => $status,
            // Controller preserves the existing, hidden legacy flag on save.
            'lightbox_enabled' => 1,
        ], $mediaIds, $items, $errors];
    }

    private function repository(): GalleryRepository
    {
        if (!$this->repository instanceof GalleryRepository) {
            $this->media();
            $this->repository = new GalleryRepository();
            }
        return $this->repository;
    }

    private function media(): MediaLibrary
    {
        return $this->media ??= new MediaLibrary();
    }

    private function requireContext(): void
    {
        if (!$this->context instanceof ModuleContext) {
            throw new RuntimeException('Gallery module context is not available.');
        }
    }

    private function limitText(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
