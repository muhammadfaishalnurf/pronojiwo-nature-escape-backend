<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class PublicSettingsController extends Controller
{
    // GET /api/v1/settings — PUBLIC, untuk Home page
    public function index()
    {
        $settings = Setting::getAllAsArray();
        return response()->json($settings);
    }
}