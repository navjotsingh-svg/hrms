<?php

namespace App\Http\Controllers;

class AssetRequestController extends Controller
{
    public function index()
    {
        return view('asset-requests.index');
    }

    public function create()
    {
        return view('asset-requests.apply');
    }

    public function show(int $assetRequest)
    {
        return view('asset-requests.show', ['assetRequestId' => $assetRequest]);
    }
}
