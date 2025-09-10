<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
    $organization = Auth::user()->primaryOrganization();
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

    /**
     * Sync document to Qdrant using unified system
     */
    private function syncDocumentToQdrant($organizationSlug, $documentData)
    {
        try {
            $aiService = new AiAgentService();
            
            $items = [
                [
                    'id' => "document_" . md5($documentData['filename'] . $documentData['path']),
                    'title' => $documentData['filename'],
                    'content' => $documentData['content'],
                    'category' => 'document',
                    'metadata' => [
                        'source' => 'document_upload',
                        'filename' => $documentData['filename'],
                        'uploaded_by' => $documentData['uploaded_by'],
                        'file_path' => $documentData['path'],
                        'file_type' => $documentData['file_type'] ?? '',
                        'uploaded_at' => now()->toISOString(),
                    ]
                ]
            ];
            
            $result = $aiService->storeDataToQdrant($organizationSlug, 'documents', $items);
            
            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('>>> Customer Documents sync successful', [
                    'organization_slug' => $organizationSlug,
                    'filename' => $documentData['filename'],
                    'result' => $result
                ]);
                return true;
            } else {
                Log::warning('>>> Customer Documents sync failed', [
                    'organization_slug' => $organizationSlug,
                    'filename' => $documentData['filename'],
                    'result' => $result
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('>>> Customer Documents sync error', [
                'organization_slug' => $organizationSlug,
                'filename' => $documentData['filename'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function uploadFile()
    {
    $organization = Auth::user()->primaryOrganization();
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
                // Sync to Qdrant using unified system
                $documentData = [
                    'filename' => $originalName,
                    'content' => $content,
                    'path' => $path,
                    'uploaded_by' => Auth::id(),
                    'file_type' => $extension
                ];
                
                $this->syncDocumentToQdrant($organization->slug, $documentData);
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
                $organization = Auth::user()->primaryOrganization();
                $filename = basename($filePath);
                
                // Delete from storage
                Storage::delete($filePath);
                
                // Delete from Qdrant using unified system
                if ($organization) {
                    $ai = new AiAgentService();
                    $documentId = "document_" . md5($filename . $filePath);
                    $ai->deleteDataFromQdrant($organization->slug, 'documents', $documentId);
                    
                    Log::info(">>> Customer Documents deleted from Qdrant", [
                        'organization_slug' => $organization->slug,
                        'filename' => $filename,
                        'deleted_id' => $documentId
                    ]);
                }
                
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
