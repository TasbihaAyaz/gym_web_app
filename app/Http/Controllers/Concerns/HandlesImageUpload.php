<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesImageUpload
{
    protected function storeImage(Request $request, string $field, string $directory, ?Model $model = null): ?string
    {
        if (! $request->hasFile($field)) {
            return $model?->{$field};
        }

        /** @var UploadedFile $file */
        $file = $request->file($field);

        if ($model && $model->{$field} && ! str_starts_with((string) $model->{$field}, 'http')) {
            Storage::disk('public')->delete($model->{$field});
        }

        return $file->store($directory, 'public');
    }

    protected function clearImageIfRequested(Request $request, string $field, ?Model $model = null): bool
    {
        if (! $request->boolean('remove_' . $field) || ! $model?->{$field}) {
            return false;
        }

        if (! str_starts_with((string) $model->{$field}, 'http')) {
            Storage::disk('public')->delete($model->{$field});
        }

        return true;
    }
}
