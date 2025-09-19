<?php

namespace App\Livewire\Customer;

use App\Models\CustomerReview;
use App\Models\Organization;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class ReviewForm extends Component
{
    public $organizationId;
    public $organization;
    public $rating = 0;
    public $title = '';
    public $review = '';
    public $submitted = false;
    public $existingReview = null;

    protected function rules()
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:200',
            'review' => 'nullable|string|max:1000',
        ];
    }

    protected $messages = [
        'rating.required' => 'Please select a star rating.',
        'rating.min' => 'Please select at least 1 star.',
        'rating.max' => 'Rating cannot exceed 5 stars.',
        'title.max' => 'Title cannot exceed 200 characters.',
        'review.max' => 'Review cannot exceed 1000 characters.',
    ];

    public function mount($organizationId = null)
    {
        if (!Auth::check()) {
            abort(403, 'You must be logged in to submit a review.');
        }

        if ($organizationId) {
            $this->organizationId = $organizationId;
            $this->organization = Organization::findOrFail($organizationId);
        } else {
            // If no organization ID provided, use the first available organization
            $this->organization = Organization::first();
            if (!$this->organization) {
                abort(404, 'No organization found.');
            }
            $this->organizationId = $this->organization->id;
        }
        
        // Check if user already has a review for this organization
        $this->existingReview = CustomerReview::where('user_id', Auth::id())
            ->where('organization_id', $this->organizationId)
            ->first();

        if ($this->existingReview) {
            $this->rating = $this->existingReview->rating;
            $this->title = $this->existingReview->title;
            $this->review = $this->existingReview->review;
        }
    }

    public function setRating($rating)
    {
        $this->rating = $rating;
        $this->validateOnly('rating');
    }

    public function submitReview()
    {
        if (!Auth::check()) {
            session()->flash('error', 'You must be logged in to submit a review.');
            return;
        }

        if (!$this->organizationId) {
            session()->flash('error', 'Organization not found. Please try again.');
            return;
        }

        $this->validate();

        try {
            $data = [
                'user_id' => Auth::id(),
                'organization_id' => $this->organizationId,
                'rating' => $this->rating,
                'title' => $this->title,
                'review' => $this->review,
                'status' => 'pending',
            ];

            if ($this->existingReview) {
                // Update existing review
                $this->existingReview->update($data);
                session()->flash('message', 'Your review has been updated successfully! It will be reviewed by our admin team.');
            } else {
                // Create new review
                CustomerReview::create($data);
                session()->flash('message', 'Thank you for your review! It will be reviewed by our admin team before being published.');
            }

            $this->submitted = true;
            
            // Optionally clear form or redirect
            $this->dispatch('reviewSubmitted');

        } catch (\Exception $e) {
            session()->flash('error', 'There was an error submitting your review. Please try again.');
            \Log::error('Review submission error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'organization_id' => $this->organizationId,
                'rating' => $this->rating,
                'exception' => $e->getTraceAsString()
            ]);
        }
    }

    public function resetForm()
    {
        $this->rating = 0;
        $this->title = '';
        $this->review = '';
        $this->submitted = false;
        $this->existingReview = null;
    }

    public function render()
    {
        return view('livewire.customer.review-form');
    }
}
