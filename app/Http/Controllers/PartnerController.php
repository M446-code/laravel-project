<?php

namespace App\Http\Controllers;
use App\Models\Partner;
use App\Models\ThirdPartyCost;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    //
    public function getAllPartners()
    {
        $partners = Partner::all();
        return response()->json($partners);
    }

    public function storePartner(Request $request)
    {
        $request->validate([
            'fullname' => 'required',
            'address' => 'required',
        ]);

        Partner::create($request->all());

        return response()->json(['message' => 'Successfully Added Partner'], 201);
    }

    public function destroyPartner($id)
    {
        $partner=Partner::find($id);
        $partner->delete();
        return response()->json(['message' => 'Successfully Delete Partner'], 201);
   }

   public function getAllCosts()
    {
        $costs = ThirdPartyCost::all();
        return response()->json($costs);
    }

    public function storeCost(Request $request)
    {
        ThirdPartyCost::create($request->all());

        return response()->json(['message' => 'Successfully Added Cost'], 201);
    }

    public function destroyCost($id)
    {
        $cost=ThirdPartyCost::find($id);
        $cost->delete();
        return response()->json(['message' => 'Successfully Delete Cost'], 201);
   }

   

}
