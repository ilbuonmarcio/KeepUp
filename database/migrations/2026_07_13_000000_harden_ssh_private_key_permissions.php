<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $directory = storage_path('app/private/ssh_private_keys');

        File::ensureDirectoryExists($directory, 0700);
        chmod($directory, 0700);

        foreach (File::files($directory) as $file) {
            if (str_ends_with($file->getFilename(), '.decrypt') || str_starts_with($file->getFilename(), '.keepup-')) {
                File::delete($file->getPathname());

                continue;
            }

            chmod($file->getPathname(), 0600);
        }
    }

    public function down(): void
    {
        // Secure permissions should not be weakened during a rollback.
    }
};
