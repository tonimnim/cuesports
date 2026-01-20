<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Resize;
use Cloudinary\Transformation\Gravity;
use Cloudinary\Transformation\FocusOn;
use Cloudinary\Transformation\Format;
use Cloudinary\Transformation\Quality;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload a profile photo.
     */
    public function uploadProfilePhoto(UploadedFile $file, int $userId): ?string
    {
        return $this->upload($file, "players/{$userId}/profile", [
            'transformation' => [
                'width' => 400,
                'height' => 400,
                'crop' => 'fill',
                'gravity' => 'face',
                'quality' => 'auto:good',
                'format' => 'webp',
            ],
            'eager' => [
                // Thumbnail version
                [
                    'width' => 100,
                    'height' => 100,
                    'crop' => 'fill',
                    'gravity' => 'face',
                    'format' => 'webp',
                ],
            ],
        ]);
    }

    /**
     * Upload a tournament banner.
     */
    public function uploadTournamentBanner(UploadedFile $file, int $tournamentId): ?string
    {
        return $this->upload($file, "tournaments/{$tournamentId}/banner", [
            'transformation' => [
                'width' => 1200,
                'height' => 400,
                'crop' => 'fill',
                'quality' => 'auto:good',
                'format' => 'webp',
            ],
        ]);
    }

    /**
     * Upload an organization logo.
     */
    public function uploadOrganizationLogo(UploadedFile $file, int $organizerId): ?string
    {
        return $this->upload($file, "organizers/{$organizerId}/logo", [
            'transformation' => [
                'width' => 300,
                'height' => 300,
                'crop' => 'fill',
                'quality' => 'auto:good',
                'format' => 'webp',
            ],
        ]);
    }

    /**
     * Generic upload method.
     */
    public function upload(UploadedFile $file, string $folder, array $options = []): ?string
    {
        try {
            $uploadOptions = array_merge([
                'folder' => "cuesports/{$folder}",
                'resource_type' => 'image',
                'overwrite' => true,
                'unique_filename' => true,
            ], $options);

            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );

            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed', [
                'folder' => $folder,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete an image by URL.
     */
    public function delete(string $url): bool
    {
        try {
            // Extract public ID from URL
            $publicId = $this->extractPublicId($url);

            if (!$publicId) {
                return false;
            }

            $this->cloudinary->uploadApi()->destroy($publicId);

            return true;
        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract public ID from Cloudinary URL.
     */
    protected function extractPublicId(string $url): ?string
    {
        // URL format: https://res.cloudinary.com/{cloud}/image/upload/{version}/{public_id}.{format}
        $pattern = '/\/upload\/(?:v\d+\/)?(.+)\.\w+$/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get optimized URL with transformations.
     */
    public function getOptimizedUrl(string $publicId, array $transformations = []): string
    {
        $image = $this->cloudinary->image($publicId);

        if (!empty($transformations)) {
            if (isset($transformations['width']) && isset($transformations['height'])) {
                $image->resize(
                    Resize::fill()
                        ->width($transformations['width'])
                        ->height($transformations['height'])
                        ->gravity(Gravity::focusOn(FocusOn::face()))
                );
            }
        }

        return $image
            ->format(Format::webp())
            ->quality(Quality::auto())
            ->toUrl();
    }

    /**
     * Get thumbnail URL for a profile photo.
     */
    public function getProfileThumbnail(string $url): string
    {
        // Replace dimensions in URL for thumbnail
        return preg_replace(
            '/\/w_\d+,h_\d+/',
            '/w_100,h_100',
            $url
        );
    }
}
