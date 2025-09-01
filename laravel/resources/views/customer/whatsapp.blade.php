@extends('layouts.customer')

@section('title', 'WhatsApp Integration')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">WhatsApp Business Integration</h4>
                    <p class="text-muted mb-0">Connect your WhatsApp Business account to provide AI support via WhatsApp</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Setup Instructions -->
                            <div class="alert alert-info">
                                <h5 class="alert-heading">
                                    <i class="fab fa-whatsapp me-2"></i>WhatsApp Business API Setup
                                </h5>
                                <p class="mb-0">To integrate WhatsApp with your AI agent, you'll need to complete these steps:</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6 class="mb-0">Step 1: Facebook Business Account</h6>
                                        </div>
                                        <div class="card-body">
                                            <ol>
                                                <li>Go to <a href="https://business.facebook.com/" target="_blank" class="text-decoration-none">Facebook Business Manager</a></li>
                                                <li>Create or log into your business account</li>
                                                <li>Navigate to "WhatsApp Business Platform"</li>
                                                <li>Create a new WhatsApp Business Account</li>
                                            </ol>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6 class="mb-0">Step 2: WhatsApp Business API</h6>
                                        </div>
                                        <div class="card-body">
                                            <ol>
                                                <li>In Facebook Developer Console, create a new app</li>
                                                <li>Add "WhatsApp Business Platform" product</li>
                                                <li>Configure webhooks with this URL: 
                                                    <code class="bg-light p-1 rounded">https://ai-chat.support/api/whatsapp/webhook</code>
                                                </li>
                                                <li>Get your Access Token and Phone Number ID</li>
                                            </ol>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h6 class="mb-0">Step 3: Configure Integration</h6>
                                        </div>
                                        <div class="card-body">
                                            <form>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Access Token <span class="text-danger">*</span></label>
                                                        <input type="password" class="form-control" placeholder="Your WhatsApp Access Token">
                                                        <small class="text-muted">Get this from Facebook Developer Console</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Phone Number ID <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" placeholder="Your Phone Number ID">
                                                        <small class="text-muted">WhatsApp Business phone number ID</small>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Verify Token</label>
                                                        <input type="text" class="form-control" value="ai_chat_support_{{ auth()->user()->organization_id ?? 'org' }}" readonly>
                                                        <small class="text-muted">Use this token in webhook verification</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Webhook URL</label>
                                                        <input type="text" class="form-control" value="https://ai-chat.support/api/whatsapp/webhook" readonly>
                                                        <small class="text-muted">Configure this in Facebook Developer Console</small>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-success">
                                                    <i class="fab fa-whatsapp me-2"></i>Save WhatsApp Configuration
                                                </button>
                                                <button type="button" class="btn btn-outline-primary ms-2">
                                                    <i class="fas fa-vial me-2"></i>Test Connection
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fab fa-whatsapp me-2"></i>Integration Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center">
                                        <div class="mb-3">
                                            <i class="fab fa-whatsapp fa-3x text-muted"></i>
                                        </div>
                                        <h6 class="text-muted">Not Connected</h6>
                                        <p class="text-muted small">Complete the setup to start receiving WhatsApp messages</p>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h6>Features</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Auto-respond to WhatsApp messages
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Organization-specific responses
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Rich media support
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            24/7 automated support
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card mt-3 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">Important Notes</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small">
                                        <li class="mb-2">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            WhatsApp Business API approval required
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-info me-2"></i>
                                            Setup can take 1-2 business days
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-dollar-sign text-success me-2"></i>
                                            WhatsApp charges per message
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
