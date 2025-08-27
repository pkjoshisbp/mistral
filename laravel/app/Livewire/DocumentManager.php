<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentManager extends Component
{
    use WithFileUploads;

    public $organizations;
    public $selectedOrgId;
    public $uploadedFiles = [];
    public $file;
    public $isUploading = false;
    public $uploadProgress = 0;

    public function mount()
    {
        $this->organizations = Organization::all();
        $this->loadDocuments();
    }

    public function updatedSelectedOrgId()
    {
        $this->loadDocuments();
    }

    public function loadDocuments()
    {
        if ($this->selectedOrgId) {
            // Load existing documents for the organization
            $this->uploadedFiles = collect();
            $orgPath = "organizations/{$this->selectedOrgId}/documents";
            
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
                });
            }
        }
    }

    public function upload()
    {
        $this->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,txt,csv,xlsx,xls', // 10MB max
        ]);

        if (!$this->selectedOrgId) {
            session()->flash('error', 'Please select an organization first.');
            return;
        }

        $this->isUploading = true;

        try {
            $organization = Organization::find($this->selectedOrgId);
            $fileName = time() . '_' . $this->file->getClientOriginalName();
            $filePath = "organizations/{$this->selectedOrgId}/documents/{$fileName}";
            
            // Store the file
            $path = $this->file->storeAs("organizations/{$this->selectedOrgId}/documents", $fileName);
            
            // Process the file content and add to vector database
            $this->processFileForVector($path, $organization);
            
            $this->loadDocuments();
            $this->reset(['file']);
            
            session()->flash('message', 'Document uploaded and processed successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Upload failed: ' . $e->getMessage());
        }

        $this->isUploading = false;
    }

    private function processFileForVector($filePath, $organization)
    {
        $fullPath = Storage::path($filePath);
        $fileName = basename($filePath);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        $content = '';
        
        // Extract content based on file type
        switch (strtolower($extension)) {
            case 'txt':
                $content = file_get_contents($fullPath);
                // Enhance FAQ format detection and processing
                if (stripos($content, 'faq') !== false || 
                    (stripos($content, 'Q:') !== false && stripos($content, 'A:') !== false)) {
                    $content = "FAQ Document:\n\n" . $content;
                }
                break;
            case 'csv':
                $content = $this->processCsvFile($fullPath);
                break;
            case 'pdf':
                // For PDF, you might need a PDF parser library
                $content = "PDF file: {$fileName} (PDF parsing not implemented yet)";
                break;
            default:
                $content = "Document: {$fileName}";
        }

        // Add to Qdrant vector database
        if (!empty($content)) {
            $aiService = new AiAgentService();
            $aiService->addDocument("org_{$organization->id}", [
                'content' => $content,
                'file_name' => $fileName,
                'file_type' => $extension,
                'category' => 'document'
            ]);
        }
    }

    private function processCsvFile($filePath)
    {
        $content = '';
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            $content .= "CSV Data:\n";
            
            // Check if this looks like an FAQ CSV
            $isFrequentlyAskedQuestions = in_array('question', array_map('strtolower', $headers)) && 
                          in_array('answer', array_map('strtolower', $headers));
            
            if ($isFrequentlyAskedQuestions) {
                $content .= "FAQ Content:\n\n";
                $questionIndex = array_search('question', array_map('strtolower', $headers));
                $answerIndex = array_search('answer', array_map('strtolower', $headers));
                $categoryIndex = array_search('category', array_map('strtolower', $headers));
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $question = $data[$questionIndex] ?? '';
                    $answer = $data[$answerIndex] ?? '';
                    $category = isset($categoryIndex) ? $data[$categoryIndex] ?? '' : '';
                    
                    if (!empty($question) && !empty($answer)) {
                        if ($category) {
                            $content .= "Category: {$category}\n";
                        }
                        $content .= "Q: {$question}\n";
                        $content .= "A: {$answer}\n\n";
                    }
                }
            } else {
                // Regular CSV processing
                $content .= "Headers: " . implode(', ', $headers) . "\n\n";
                $rowCount = 0;
                while (($data = fgetcsv($handle)) !== FALSE && $rowCount < 100) {
                    $content .= implode(' | ', $data) . "\n";
                    $rowCount++;
                }
            }
            fclose($handle);
        }
        return $content;
    }

    public function deleteFile($filePath)
    {
        try {
            Storage::delete($filePath);
            $this->loadDocuments();
            session()->flash('message', 'File deleted successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    public function getFileIcon($type)
    {
        $icons = [
            'pdf' => 'pdf',
            'doc' => 'word',
            'docx' => 'word', 
            'txt' => 'alt',
            'csv' => 'csv',
            'xls' => 'excel',
            'xlsx' => 'excel'
        ];
        return $icons[$type] ?? 'alt';
    }

    public function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
    }

    public function render()
    {
        return view('livewire.document-manager');
    }
}
