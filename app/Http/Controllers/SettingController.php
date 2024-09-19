<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getAllSettings()
    {
        return Setting::all();
    }

    public function storeSettings(Request $request)
    {
        $request->validate([
            'key' => 'required|unique:settings,key',
            'value' => 'required',
            'created_by' => 'required|exists:users,id',
        ]);

        $setting = Setting::create($request->all());

        return response()->json($setting, 201);
    }

    

    public function updateSettings(Request $request, $id)
    {
        $setting = Setting::find($id);
    
        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }
    
        $request->validate([
            'key' => 'required|unique:settings,key,' . $setting->id,
            'value' => 'required',
            'created_by' => 'required|exists:users,id',
        ]);
    
        $setting->update($request->all());
    
        return response()->json($setting, 200);
    }

    public function destroySettings($id)
    {
        $setting = Setting::findOrFail($id);
        $setting->delete();

        return response()->json(null, 204);
    }
}
