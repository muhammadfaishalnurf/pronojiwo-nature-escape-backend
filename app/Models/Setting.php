<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    // Helper static: ambil semua settings sebagai array key=>value
    public static function getAllAsArray(): array
    {
        return self::pluck('value', 'key')->toArray();
    }

    // Helper static: ambil satu value
    public static function getValue(string $key, string $default = ''): string
    {
        return self::where('key', $key)->value('value') ?? $default;
    }
}