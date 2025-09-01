<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Documents extends Component
{
    use WithFileUploads;

    public $uploadedFiles = [];
    public $file;
    public $isUploading = false;
    public $uploadProgress = 0;

    public function mount()
    {
        $this->loadDocuments();
    }

    public function loadDocuments()
    {
        $organization = Auth::user()->organization;
        if (!$organization) {
            $this->uploadedFiles = collect();
            return;
        }

        // Load existing documents for the organization
        $this->uploadedFiles = collect();
        $orgPath = "organizations/{$organization->id}/documents";
        
        if (Storage::exists($orgPath)) {
            $files = Storage::files($orgPath);
            $this->uploadedFiles = collect($files)->map(function ($file) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::size($file),
                    'created' => Storage::lastModified($file),
                    'type' => pathinfo($file, PATHINFO_EXTENSION)
                ];
            })->sortByDesc('created');
        }
    }

    public function uploadFile()
    {
        $organization = Auth::user()->organization;
        if (!$organization) {
            session()->flash('error', 'No organization assigned to your account.');
            return;
        }

        $this->validate([
            'file' => 'required|file|mimes:pdf,txt,doc,docx,csv|max:10240', // 10MB max
        ]);

        try {
            $this->isUploading = true;
            
            // Store file
            $originalName = $this->file->getClientOriginalName();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $this->file->getClientOriginalExtension();
            $path = $this->file->storeAs("organizations/{$organization->id}/documents", $filename);
            
            // Process with AI Agent
            $aiAgentService = new AiAgentService();
            $content = '';
            
            // Extract text based on file type
            $extension = strtolower($this->file->getClientOriginalExtension());
            $filePath = Storage::path($path);
            
            switch ($extension) {
                case 'txt':
                case 'csv':
                    $content = file_get_contents($filePath);
                    break;
                case 'pdf':
                    // For PDF, we'd need a PDF parser, for now just store filename
                    $content = "PDF Document: " . $originalName;
                    break;
                case 'doc':
                case 'docx':
                    // For DOC/DOCX, we'd need a parser, for now just store filename
                    $content = "Word Document: " . $originalName;
                    break;
            }
            
            if ($content) {
                // Add to vector database
                $embedding = $aiAgentService->embed($content);
                
                if ($embedding) {
                    $aiAgentService->storeInQdrant(
                        $organization->slug,
                        $embedding,
                        [
                            'content' => $content,
                            'source' => 'document_upload',
                            'filename' => $originalName,
                            'org_id' => $organization->id,
                            'uploaded_by' => Auth::id()
                        ]
                    );
                }
            }
            
            $this->reset(['file']);
            $this->loadDocuments();
            $this->isUploading = false;
            
            session()->flash('message', 'Document uploaded and processed successfully!');
            
        } catch (\Exception $e) {
            $this->isUploading = false;
            session()->flash('error', 'Error uploading file: ' . $e->getMessage());
        }
    }

    public function deleteFile($filePath)
    {
        try {
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
                $this->loadDocuments();
                session()->flash('message', 'File deleted successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting file: ' . $e->getMessage());
        }
    }

    public function downloadFile($filePath)
    {
        if (Storage::exists($filePath)) {
            return Storage::download($filePath);
        }
    }

    public function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function render()
    {
        return view('livewire.customer.documents')
            ->layout('layouts.customer')
            ->layoutData(['title' => 'Documents']);
    }
}
