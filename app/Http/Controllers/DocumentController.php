<?php

namespace App\Http\Controllers;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function getAllDocuments()
    {
        $documents = Document::orderBy('position', 'asc')->get();
        
        return response()->json($documents);
    }

    public function getDocumentById($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        return response()->json($document);
    }


   

    public function storeDocuments(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required|exists:users,id',
            'files_title' => 'required|string',
            'files_description' => 'required|string',
            'user_role' => 'required|string',
            // 'file_url' => 'required|string',
        ]);        

        // Create the document with the validated data and file URL.
        $document = Document::create([
            'user_id' => $request->user_id,
            'title' => $request->files_title,
            'body' => $request->files_description,
            'file_url' => $request->file_url,
            'user_role' => $request->user_role,
            'position' => $request->position ?? 0,
        ]);

        return response()->json($document, 201);
    }


    // public function updateDocuments(Request $request, $id)
    // {
    //     $this->validate($request, [
    //         'user_id' => 'required|exists:users,id',
    //         'files_title' => 'required|string',
    //         'files_description' => 'required|string',
           
    //     ]);

    //     $document = Document::findOrFail($id);

    //     // If a new file is provided, update it and delete the old file.
    //     if ($request->hasFile('file')) {
    //         $newFile = $request->file('file');
    //         $newFileUrl = $newFile->store('uploads', 'public');
            
    //         // Delete the old file.
    //         Storage::disk('public')->delete($document->file_url);

    //         $document->update([
    //             'user_id' => $request->user_id,
    //             'title' => $request->files_title,
    //             'body' => $request->files_description,
    //             'file_url' => $newFileUrl,
    //             'position' => $request->position ?? 0,
    //         ]);
    //     } else {
    //         // No new file provided, update the document with existing file.
    //         $document->update([
    //             'user_id' => $request->user_id,
    //             'title' => $request->files_title,
    //             'body' => $request->files_description,
    //             'position' => $request->position ?? 0,
                
    //         ]);
    //     }

    //     return response()->json($document);
    // }
    public function updateDocuments(Request $request, $id)
{
    $this->validate($request, [
        'user_id' => 'required|exists:users,id',
        'files_title' => 'required|string',
        'files_description' => 'required|string',
    ]);

    $document = Document::findOrFail($id);

    // Check if sales_rep_dash is being updated to 1
    $salesRepDashUpdated = $request->has('sales_rep_dash') && $request->sales_rep_dash == 1;

    // If a new file is provided, update it and delete the old file.
    if ($request->hasFile('file')) {
        $newFile = $request->file('file');
        $newFileUrl = $newFile->store('uploads', 'public');

        // Delete the old file.
        Storage::disk('public')->delete($document->file_url);

        // Update the current document
        $document->update([
            'user_id' => $request->user_id,
            'title' => $request->files_title,
            'body' => $request->files_description,
            'file_url' => $newFileUrl,
            'user_role' => $request->user_role,
            'status' => $request->status,
            'sales_rep_dash' => $request->sales_rep_dash,
            'position' => $request->position ?? 0,
        ]);

        // If sales_rep_dash is updated to 1, update other documents
        if ($salesRepDashUpdated) {
            Document::where('id', '!=', $document->id)
                ->update(['sales_rep_dash' => 0]);
        }
    } else {
        // No new file provided, update the document with existing file.
        $document->update([
            'user_id' => $request->user_id,
            'title' => $request->files_title,
            'body' => $request->files_description,
            'position' => $request->position ?? 0,
            'user_role' => $request->user_role,
            'status' => $request->status,
            'sales_rep_dash' => $request->sales_rep_dash,
            'file_url' => $request->file_url,
        ]);

        // If sales_rep_dash is updated to 1, update other documents
        if ($salesRepDashUpdated) {
            Document::where('id', '!=', $document->id)
                ->update(['sales_rep_dash' => 0]);
        }
    }

    return response()->json($document);
}


    public function destroyDocuments($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        // Delete the associated file from the 'uploads' directory in the 'public' disk.
        Storage::disk('public')->delete($document->file_url);

        // Delete the document from the database.
        $document->delete();

        return response()->json(['message' => 'Document deleted']);
    }

    public function updatePosition(Request $request)
    {  

        $documents = $request->documents;

        foreach ($documents as $document) {
            Document::where('id', $document['id'])->update(['position' => $document['position']]);
        }

        return response()->json(['message' => 'Position updated!']);
    }

    // upload file and return file url
    public function uploadDocumentFile(Request $request)
    {
        // $this->validate($request, [
        //     'file' => 'required|file|mimes:jpg,png,pdf|max:20480',
        // ]);

        // $file = $request->file('file');
        // $fileUrl = $file->store('uploads/documents', 'public');

        // return response()->json(['file_url' => $fileUrl]);

        // check if file exists
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileUrl = $file->store('uploads/documents', 'public');
            return response()->json(['file_url' => $fileUrl]);
        } else {
            return response()->json(['message' => 'File not found!'], 404);
        }
    }


}
