<?php

namespace App\Http\Controllers;

class AssetTypeController extends Controller
{
    public function index()
    {
        return view('assets.index');
    }

    public function create()
    {
        return view('assets.create');
    }

    public function edit(int $assetType)
    {
        return view('assets.edit', ['assetTypeId' => $assetType]);
    }
}
