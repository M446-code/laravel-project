<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FAQ;


class FAQController extends Controller
{
    public function getAllFAQs()
    {
        $faqs = FAQ::all();
        return response()->json($faqs);
    }

    public function getSingleFAQ($id)
    {
        $faq = FAQ::find($id);
        if (!$faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }
        return response()->json($faq);
    }

    public function addFAQ(Request $request)
    {
        $faq = FAQ::create($request->all());
        return response()->json($faq, 201);
    }

    public function updateFAQ(Request $request, $id)
    {
        $faq = FAQ::find($id);
        if (!$faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }
        $faq->update($request->all());
        return response()->json($faq);
    }

    public function deleteFAQ($id)
    {
        $faq = FAQ::find($id);
        if (!$faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }
        $faq->delete();
        return response()->json(['message' => 'FAQ deleted']);
    }

    // upload file and return file url
    public function uploadFaqFile(Request $request)
    {
        
        // check if file exists
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileUrl = $file->store('uploads/faq', 'public');
            return response()->json(['file_url' => $fileUrl]);
        } else {
            return response()->json(['message' => 'File not found!'], 404);
        }
    }
}
