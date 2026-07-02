<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFarmUserRequest;
use App\Http\Requests\UpdateFarmUserRequest;
use App\Models\Farm\FarmUser;

class FarmUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFarmUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(FarmUser $farmUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FarmUser $farmUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFarmUserRequest $request, FarmUser $farmUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FarmUser $farmUser)
    {
        //
    }
}
