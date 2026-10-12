<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Region;

class TerritoireController extends Controller
{
    public function regions() { return Region::orderBy('nom')->get(); }
    public function provinces() { return Province::orderBy('nom')->get(); }
}
