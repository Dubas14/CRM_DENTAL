<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClinicLogoController extends Controller
{
    public function upload(Request $request, Clinic $clinic)
    {
        $this->assertClinicAccess($request->user(), $clinic->id);

        $validated = $request->validate([
            'logo' => ['required', 'file', 'image', 'max:10240'], // max 10MB before processing
        ]);

        $file = $validated['logo'];

        // Delete old logo if exists
        if ($clinic->logo_url) {
            $oldPath = str_replace('/storage/', '', parse_url($clinic->logo_url, PHP_URL_PATH));
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Process image with Intervention Image if available
        try {
            if (class_exists(\Intervention\Image\ImageManager::class)) {
                $manager = new \Intervention\Image\ImageManager(
                    new \Intervention\Image\Drivers\Gd\Driver()
                );
                $image = $manager->read($file->getRealPath());
                
                // Resize to max width 500px, maintaining aspect ratio
                $image->scale(width: 500);
                
                // Generate filename
                $filename = 'clinic-' . $clinic->id . '-' . time() . '.jpg';
                $path = 'clinic-logos/' . $filename;
                
                // Save as JPEG with 90% quality
                $image->toJpeg(90)->save(storage_path('app/public/' . $path));
            } else {
                // Fallback: save original file if Intervention Image is not available
                $path = $file->store('clinic-logos', 'public');
            }
        } catch (\Exception $e) {
            // Fallback: save original file if image processing fails
            $path = $file->store('clinic-logos', 'public');
        }

        // Update clinic with logo URL
        $clinic->logo_url = Storage::disk('public')->url($path);
        $clinic->save();

        return response()->json([
            'logo_url' => $clinic->logo_url,
        ]);
    }

    public function destroy(Request $request, Clinic $clinic)
    {
        $this->assertClinicAccess($request->user(), $clinic->id);

        if ($clinic->logo_url) {
            // Extract path from URL
            $path = str_replace('/storage/', '', parse_url($clinic->logo_url, PHP_URL_PATH));
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $clinic->logo_url = null;
        $clinic->save();

        return response()->json([
            'message' => 'Logo deleted successfully',
        ]);
    }

    private function assertClinicAccess($user, int $clinicId): void
    {
        if (! $user->isSuperAdmin() && ! $user->hasClinicRole($clinicId, ['clinic_admin'])) {
            abort(403, 'Немає доступу до цієї клініки');
        }
    }
}
