<?php

namespace App\Services;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    public function upload($file, $folder = 'uploads', $userId = null)
    {
        if (!$file) {
            return null;
        }

        // Generate directory structure (e.g., 2025/01)
        $year = date('Y');
        $month = date('m');
        $directory = $folder."/{$year}/{$month}";

        // Ensure the directory exists
        if (!is_dir(storage_path("app/public/{$directory}"))) {
            mkdir(storage_path("app/public/{$directory}"), 0755, true);
        }

        // Resolve file name conflicts
        $originalName = $file->getClientOriginalName();
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $uniqueFileName = $fileName;

        $counter = 1;
        while (file_exists(storage_path("app/public/{$directory}/{$uniqueFileName}.{$extension}"))) {
            $uniqueFileName = "{$fileName}_{$counter}";
            $counter++;
        }
        $storedFileName = "{$uniqueFileName}.{$extension}";

        // Store the file
        $filePath = $file->storeAs($directory, $storedFileName, 'public');

        // Save metadata to the media table
        $media = Media::create([
            'original_name' => $originalName,
            'file_name' => $storedFileName,
            'file_path' => $filePath,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        return [
            'media_id' => $media->id,
            'file_path' => $filePath,
            'url' => asset("storage/{$filePath}"),
        ];
    }

    public function delete($filePath)
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }

        return false;
    }

}
