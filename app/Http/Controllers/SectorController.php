<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ApiResponse;
use App\Services\GeoService;

class SectorController extends Controller
{


    protected $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }
    public function index()
    {
        $sectors = Sector::all();
        return ApiResponse::success(__('messages.sectors_fetched'), ['sectors' => $sectors]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sector_name' => 'required|string|max:255|unique:sectors,sector_name',
            'areas' => 'nullable|array',
            'polygon' => 'nullable|array',
            'is_active' => 'boolean',
            'delivery_fee' => 'nullable|numeric|min:0', // new field
        ]);


        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $sector = Sector::create([
            'sector_name' => $request->sector_name,
            'areas' => $request->areas,
            'polygon' => $request->polygon,
            'is_active' => $request->is_active ?? true,
            'delivery_fee' => $request->delivery_fee ?? 0, // default 0
        ]);


        return ApiResponse::success(__('messages.sector_created'), ['sector' => $sector], 201);
    }

    public function show($id)
    {
        $sector = Sector::find($id);
        if (!$sector) {
            return ApiResponse::error(__('messages.sector_not_found'), null, 404);
        }
        return ApiResponse::success(__('messages.sector_fetched'), ['sector' => $sector]);
    }

    public function update(Request $request, $id)
    {
        $sector = Sector::find($id);
        if (!$sector) {
            return ApiResponse::error(__('messages.sector_not_found'), null, 404);
        }
        $validator = Validator::make($request->all(), [
            'sector_name' => "sometimes|required|string|max:255|unique:sectors,sector_name,$id,sector_id",
            'areas' => 'nullable|array',
            'polygon' => 'nullable|array',
            'is_active' => 'boolean',
            'delivery_fee' => 'nullable|numeric|min:0',
        ]);


        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $sector->update($request->only(['sector_name', 'areas', 'polygon', 'is_active', 'delivery_fee']));

        return ApiResponse::success(__('messages.sector_updated'), ['sector' => $sector]);
    }

    public function destroy($id)
    {
        $sector = Sector::find($id);
        if (!$sector) {
            return ApiResponse::error(__('messages.sector_not_found'), null, 404);
        }

        try {
            $sector->delete();
            return ApiResponse::success(__('messages.sector_deleted'));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == "23000") {
                return ApiResponse::error(__('messages.cannot_delete_sector_fk'), null, 403);
            }
            return ApiResponse::error(__('messages.failed_to_delete_sector'), $e->getMessage(), 500);
        }
    }

    /**
     * Check if given latitude & longitude are inside any active sector
     */
    public function checkLatLongInSector(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('messages.validation_failed'), $validator->errors(), 422);
        }

        $lat = $request->latitude;
        $lng = $request->longitude;

        $sectors = Sector::where('is_active', true)->get();

        foreach ($sectors as $sector) {
            if (!$sector->polygon) continue;

            $polygon = $sector->polygon; // array of [lat, lng] points
            if (app(GeoService::class)->pointInPolygon($lat, $lng, $polygon)) {
                return ApiResponse::success(__('messages.sector_found'), ['sector' => $sector]);
            }
        }

        return ApiResponse::error(__('messages.no_sector_found'), null, 404);
    }
}
