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

        // Generate directory structure (e.g., 2025/02)
        $year = date('Y');
        $month = date('m');
        $directory = "{$folder}/{$year}/{$month}";

        // Ensure the directory exists inside "public"
        if (!is_dir(public_path($directory))) {
            mkdir(public_path($directory), 0755, true);
        }

        // Resolve file name conflicts
        $originalName = $file->getClientOriginalName();
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // **Sanitize File Name (Remove Special Characters, Replace Spaces, Lowercase)**
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);
        $fileName = preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace(' ', '_', $fileName)); // Remove unwanted characters
        $fileName = strtolower($fileName); // Convert to lowercase

        // Resolve file name conflicts
        $uniqueFileName = $fileName;
        
        $counter = 1;
        while (file_exists(public_path("{$directory}/{$uniqueFileName}.{$extension}"))) {
            $uniqueFileName = "{$fileName}_{$counter}";
            $counter++;
        }
        $storedFileName = "{$uniqueFileName}.{$extension}";

        // Move file to "public/uploads/..."
        $filePath = "{$directory}/{$storedFileName}";
        $file->move(public_path($directory), $storedFileName);

        // $fileSize = $file->getSize(); // Get size before moving the file
        // Save metadata to the media table
        $media = Media::create([
            'original_name' => $originalName,
            'file_name' => $storedFileName,
            'file_path' => $filePath,
            'mime_type' => $file->getClientMimeType(),
            // 'file_size' => $fileSize,
            'uploaded_by' => $userId,
        ]);

        return [
            'media_id' => $media->id,
            'file_path' => $filePath,
            'url' => asset($filePath),
        ];
    }

    public function delete($filePath,$mediaID)
    {
        if ($filePath && file_exists(public_path($filePath))) {
            $media = Media::find($mediaID);
            if ($media){
                $media->delete(); // Remove media record from database
            }
            return unlink(public_path($filePath));
        }

        return false;
    }
}
