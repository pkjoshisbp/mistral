<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-film"></i> Video Generation</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Video Generation</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-lg-7">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clapperboard mr-2"></i>Storyboard Builder</h3>
                        </div>
                        <div class="card-body">

                            {{-- ── Paste-and-parse panel ── --}}
                            <div class="card card-outline card-secondary mb-3">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center" style="cursor:pointer"
                                    wire:click="$set('showPastePanel', {{ $showPastePanel ? 'false' : 'true' }})">
                                    <span><i class="fas fa-paste mr-1"></i> <strong>Paste full storyboard script</strong>
                                        <small class="text-muted ml-2">— paste &amp; auto-fill all scenes at once</small>
                                    </span>
                                    <i class="fas fa-chevron-{{ $showPastePanel ? 'up' : 'down' }} text-muted"></i>
                                </div>
                                @if($showPastePanel)
                                <div class="card-body pt-2 pb-3">
                                    <p class="text-muted mb-2" style="font-size:0.85rem">
                                        Copy the entire contents of <code>video-generation.txt</code> and paste below.
                                        All scenes, image URLs, prompts, voiceovers and durations will be auto-populated.
                                    </p>
                                    <textarea wire:model.defer="storyboardText"
                                        rows="8" class="form-control mb-2"
                                        style="font-size:0.8rem;font-family:monospace"
                                        placeholder="Paste the full storyboard script here…&#10;&#10;Video: AI Chat Support — Promo&#10;Output quality: hd | Aspect ratio: 16:9&#10;&#10;──────&#10;Scene 1 — Hook (10 sec)&#10;──────&#10;Images (one per line):&#10;https://...&#10;…"></textarea>
                                    <div class="d-flex align-items-center" style="gap:8px">
                                        <button type="button" class="btn btn-primary" wire:click="parseStoryboard">
                                            <i class="fas fa-magic mr-1"></i> Parse &amp; Load All Scenes
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('storyboardText','')">
                                            Clear
                                        </button>
                                        <small class="text-muted">Title, org and language still need to be set manually below.</small>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="alert alert-info">
                                Build up to a 3-minute stitched video. Choose a <strong>Visual mode</strong> per scene:
                                <em>Static</em> uses your reference image directly,
                                <em>Text → AI</em> lets AnimateDiff generate from your prompt,
                                <em>Image → AI</em> animates your image, and
                                <em>Text + Image → AI</em> uses both as conditioning.
                                Voice-over is synthesised for all modes.
                            </div>

                            <form wire:submit.prevent="submit">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Organization *</label>
                                        <select wire:model="selectedOrganization" class="form-control">
                                            <option value="">Select organization</option>
                                            @foreach($this->organizations as $organization)
                                                <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedOrganization') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Video title *</label>
                                        <input type="text" wire:model.defer="title" class="form-control" placeholder="Clinic intro, Product teaser, FAQ explainer">
                                        @error('title') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Language</label>
                                        <select wire:model="language" class="form-control">
                                            <option value="en">English</option>
                                            <option value="hi">Hindi</option>
                                            <option value="de">German</option>
                                            <option value="fr">French</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label>Speaker / Voice <small class="text-muted">(Microsoft Edge TTS Neural)</small></label>
                                        <select wire:model.defer="speaker" class="form-control">
                                            <option value="">Default (en-IN-NeerjaExpressiveNeural)</option>
                                            <optgroup label="🇮🇳 Indian English (en-IN)">
                                                <option value="en-IN-NeerjaExpressiveNeural">Neerja Expressive – Female ★</option>
                                                <option value="en-IN-NeerjaNeural">Neerja – Female</option>
                                                <option value="en-IN-PrabhatNeural">Prabhat – Male</option>
                                            </optgroup>
                                            <optgroup label="🇮🇳 Hindi (hi-IN)">
                                                <option value="hi-IN-SwaraNeural">Swara – Female</option>
                                                <option value="hi-IN-MadhurNeural">Madhur – Male</option>
                                            </optgroup>
                                            <optgroup label="🇮🇳 Tamil (ta-IN)">
                                                <option value="ta-IN-PallaviNeural">Pallavi – Female</option>
                                                <option value="ta-IN-ValluvarNeural">Valluvar – Male</option>
                                            </optgroup>
                                            <optgroup label="🇮🇳 Telugu (te-IN)">
                                                <option value="te-IN-ShrutiNeural">Shruti – Female</option>
                                                <option value="te-IN-MohanNeural">Mohan – Male</option>
                                            </optgroup>
                                            <optgroup label="🇮🇳 Kannada / Malayalam / Marathi / Bengali / Gujarati">
                                                <option value="kn-IN-SapnaNeural">Sapna – Kannada Female</option>
                                                <option value="kn-IN-GaganNeural">Gagan – Kannada Male</option>
                                                <option value="ml-IN-SobhanaNeural">Sobhana – Malayalam Female</option>
                                                <option value="ml-IN-MidhunNeural">Midhun – Malayalam Male</option>
                                                <option value="mr-IN-AarohiNeural">Aarohi – Marathi Female</option>
                                                <option value="mr-IN-ManoharNeural">Manohar – Marathi Male</option>
                                                <option value="bn-IN-TanishaaNeural">Tanishaa – Bengali Female</option>
                                                <option value="bn-IN-BashkarNeural">Bashkar – Bengali Male</option>
                                                <option value="gu-IN-DhwaniNeural">Dhwani – Gujarati Female</option>
                                                <option value="gu-IN-NiranjanNeural">Niranjan – Gujarati Male</option>
                                            </optgroup>
                                            <optgroup label="🇺🇸 English US">
                                                <option value="en-US-AvaNeural">Ava – Female</option>
                                                <option value="en-US-EmmaNeural">Emma – Female</option>
                                                <option value="en-US-JennyNeural">Jenny – Female</option>
                                                <option value="en-US-AriaNeural">Aria – Female</option>
                                                <option value="en-US-AndrewNeural">Andrew – Male</option>
                                                <option value="en-US-BrianNeural">Brian – Male</option>
                                                <option value="en-US-GuyNeural">Guy – Male</option>
                                            </optgroup>
                                            <optgroup label="🇬🇧 English UK">
                                                <option value="en-GB-SoniaNeural">Sonia – Female</option>
                                                <option value="en-GB-LibbyNeural">Libby – Female</option>
                                                <option value="en-GB-RyanNeural">Ryan – Male</option>
                                                <option value="en-GB-ThomasNeural">Thomas – Male</option>
                                            </optgroup>
                                            <optgroup label="🇦🇺 English AU">
                                                <option value="en-AU-NatashaNeural">Natasha – Female</option>
                                                <option value="en-AU-WilliamMultilingualNeural">William – Male</option>
                                            </optgroup>
                                        </select>
                                        <small class="text-muted">322 voices available. Use markup in voiceover text: <code>**bold**</code>, <code>[rate:slow]text[/rate]</code>, <code>[pause:500]</code>, <code>[pitch:high]text[/pitch]</code>, <code>[volume:soft]text[/volume]</code></small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Aspect ratio</label>
                                        <select wire:model="aspectRatio" class="form-control">
                                            <option value="16:9">16:9 Landscape</option>
                                            <option value="9:16">9:16 Portrait</option>
                                            <option value="1:1">1:1 Square</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Output quality</label>
                                        <select wire:model="outputQuality" class="form-control">
                                            <option value="standard">Standard &mdash; 512px (fast)</option>
                                            <option value="hd">HD 720p &mdash; RealESRGAN &times;4</option>
                                            <option value="fullhd">Full HD 1080p &mdash; RealESRGAN &times;4</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label>Global style prompt</label>
                                        <textarea wire:model.defer="globalPrompt" rows="3" class="form-control" placeholder="Modern healthcare promo, clean typography, soft transitions, premium brand tone"></textarea>
                                        @error('globalPrompt') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>

                                    {{-- ── Avatar Presenter ──────────────────────────────────────── --}}
                                    <div class="col-12 mb-3">
                                        <div class="card card-outline card-info mb-0">
                                            <div class="card-header d-flex justify-content-between align-items-center py-2"
                                                 data-card-widget="collapse" style="cursor:pointer">
                                                <h3 class="card-title mb-0" style="font-size:14px">
                                                    <i class="fas fa-user-circle mr-2 text-info"></i>
                                                    Avatar Presenter
                                                    <small class="text-muted ml-2">(optional talking-head overlay on every scene)</small>
                                                </h3>
                                                <i class="fas fa-angle-down"></i>
                                            </div>
                                            <div class="card-body">
                                                @php
                                                $avatarCatalog = [
                                                    ['id'=>'f1','name'=>'Priya (F)','thumb'=>'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'f2','name'=>'Neha (F)', 'thumb'=>'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'f3','name'=>'Emma (F)', 'thumb'=>'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'f4','name'=>'Sara (F)', 'thumb'=>'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'m1','name'=>'Arjun (M)','thumb'=>'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'m2','name'=>'Raj (M)',  'thumb'=>'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'m3','name'=>'James (M)','thumb'=>'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'m4','name'=>'Marcus (M)','thumb'=>'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80&h=80&fit=crop&crop=top'],
                                                    ['id'=>'custom','name'=>'Custom','thumb'=>null],
                                                ];
                                                @endphp

                                                {{-- Select avatar --}}
                                                <label class="d-block mb-2"><strong>Select Avatar</strong></label>
                                                <div class="d-flex flex-wrap mb-3" style="gap:8px">
                                                    {{-- None tile --}}
                                                    <div wire:click="$set('avatarId','')"
                                                         class="border rounded text-center"
                                                         style="width:78px;cursor:pointer;padding:6px;{{ $avatarId === '' ? 'border-color:#007bff!important;box-shadow:0 0 0 2px #007bff' : 'border-color:#dee2e6' }}">
                                                        <div style="width:100%;height:58px;background:#f4f6f9;display:flex;align-items:center;justify-content:center;border-radius:4px">
                                                            <i class="fas fa-ban fa-lg text-muted"></i>
                                                        </div>
                                                        <div style="font-size:10px;margin-top:3px;color:{{ $avatarId === '' ? '#007bff' : '#888' }};{{ $avatarId === '' ? 'font-weight:bold' : '' }}">None</div>
                                                    </div>
                                                    {{-- Avatar tiles --}}
                                                    @foreach($avatarCatalog as $av)
                                                    <div wire:click="$set('avatarId','{{ $av['id'] }}')"
                                                         class="border rounded text-center"
                                                         style="width:78px;cursor:pointer;padding:4px;{{ $avatarId === $av['id'] ? 'border-color:#007bff!important;box-shadow:0 0 0 2px #007bff' : 'border-color:#dee2e6' }}">
                                                        @if($av['thumb'])
                                                            <img src="{{ $av['thumb'] }}" alt="{{ $av['name'] }}"
                                                                 style="width:100%;height:58px;object-fit:cover;border-radius:4px">
                                                        @else
                                                            <div style="width:100%;height:58px;background:#e9ecef;display:flex;align-items:center;justify-content:center;border-radius:4px">
                                                                <i class="fas fa-user fa-2x text-muted"></i>
                                                            </div>
                                                        @endif
                                                        <div style="font-size:9px;margin-top:3px;color:{{ $avatarId === $av['id'] ? '#007bff' : '#666' }};{{ $avatarId === $av['id'] ? 'font-weight:bold' : '' }}">{{ $av['name'] }}</div>
                                                    </div>
                                                    @endforeach
                                                </div>

                                                {{-- Custom URL --}}
                                                @if($avatarId === 'custom')
                                                <div class="mb-3">
                                                    <label>Custom Avatar Image URL</label>
                                                    <input wire:model.defer="avatarCustomUrl" type="url" class="form-control"
                                                           placeholder="https://example.com/portrait.jpg  (min 400×400, clear face)">
                                                    @error('avatarCustomUrl') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>
                                                @endif

                                                @if($avatarId !== '')
                                                <div class="row">
                                                    {{-- Position --}}
                                                    <div class="col-md-4 mb-3">
                                                        <label><strong>Position on Frame</strong></label>
                                                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;max-width:150px">
                                                            @php $positions = [['top-left','↖'],['',''],['top-right','↗'],['center-left','◀'],['bottom-center','⬇'],['center-right','▶'],['bottom-left','↙'],['',''],['bottom-right','↘']]; @endphp
                                                            @foreach($positions as [$pos,$icon])
                                                                @if($pos)
                                                                <button type="button" wire:click="$set('avatarPosition','{{ $pos }}')"
                                                                    class="btn btn-xs {{ $avatarPosition === $pos ? 'btn-primary' : 'btn-default' }}"
                                                                    title="{{ $pos }}">{{ $icon }}</button>
                                                                @else
                                                                <div></div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                        <small class="text-muted">{{ str_replace('-',' ',ucwords($avatarPosition,'-')) }}</small>
                                                    </div>

                                                    {{-- Size --}}
                                                    <div class="col-md-4 mb-3">
                                                        <label><strong>Size</strong></label>
                                                        <div class="btn-group btn-group-sm d-block mb-1">
                                                            @foreach(['small'=>'Small','medium'=>'Medium','large'=>'Large'] as $sz=>$lbl)
                                                            <button type="button" wire:click="$set('avatarSize','{{ $sz }}')"
                                                                class="btn {{ $avatarSize === $sz ? 'btn-primary' : 'btn-default' }}">{{ $lbl }}</button>
                                                            @endforeach
                                                        </div>
                                                        <small class="text-muted">Small ~22% &bull; Medium ~29% &bull; Large ~38% of frame</small>
                                                    </div>

                                                    {{-- Shape --}}
                                                    <div class="col-md-4 mb-3">
                                                        <label><strong>Shape</strong></label>
                                                        <div class="btn-group btn-group-sm d-block">
                                                            <button type="button" wire:click="$set('avatarShape','circle')"
                                                                class="btn {{ $avatarShape === 'circle' ? 'btn-primary' : 'btn-default' }}">&#9679; Circle</button>
                                                            <button type="button" wire:click="$set('avatarShape','rounded')"
                                                                class="btn {{ $avatarShape === 'rounded' ? 'btn-primary' : 'btn-default' }}">&#9632; Rounded</button>
                                                            <button type="button" wire:click="$set('avatarShape','rectangle')"
                                                                class="btn {{ $avatarShape === 'rectangle' ? 'btn-primary' : 'btn-default' }}">&#9644; Rect</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="alert alert-info py-2 mb-0" style="font-size:12px">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    <strong>Lip Sync Mode:</strong>
                                                    <span class="badge badge-{{ $lipsyncMode === 'off' ? 'secondary' : 'primary' }}">{{ strtoupper($lipsyncMode) }}</span>
                                                    &nbsp;|&nbsp;
                                                    <strong>Remote API:</strong>
                                                    <span class="badge badge-{{ $lipsyncEnabled ? 'success' : 'secondary' }}">{{ $lipsyncEnabled ? 'Enabled' : 'Disabled' }}</span>
                                                    @if($lipsyncUrl)
                                                        &nbsp;|&nbsp;<small class="text-muted">{{ $lipsyncUrl }}</small>
                                                    @endif
                                                    <br>
                                                    <small class="text-muted">
                                                        Modes: <code>local</code> (CPU fallback), <code>remote</code>, <code>auto</code>, <code>off</code>.
                                                    </small>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">Scenes</h4>
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addScene">
                                        <i class="fas fa-plus"></i> Add Scene
                                    </button>
                                </div>

                                @foreach($scenes as $index => $scene)
                                    <div class="card card-outline card-secondary mb-3">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong>{{ $scene['title'] ?: 'Scene ' . ($index + 1) }}</strong>
                                                <button type="button" class="btn btn-xs btn-outline-danger" wire:click="removeScene({{ $index }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label>Scene title</label>
                                                    <input type="text" wire:model.defer="scenes.{{ $index }}.title" class="form-control" placeholder="Opening shot">
                                                    @error('scenes.' . $index . '.title') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label>Duration (sec)</label>
                                                    <input type="number" wire:model.defer="scenes.{{ $index }}.duration_seconds" class="form-control" min="4" max="45">
                                                    @error('scenes.' . $index . '.duration_seconds') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label>Visual mode</label>
                                                    <select wire:model="scenes.{{ $index }}.input_mode" class="form-control form-control-sm">
                                                        <option value="static">🖼 Static (image/text card)</option>
                                                        <option value="text">✏️ Text → AI (AnimateDiff)</option>
                                                        <option value="image">🎨 Image → AI (AnimateDiff)</option>
                                                        <option value="both">✨ Text + Image → AI</option>
                                                    </select>
                                                    @error('scenes.' . $index . '.input_mode') <small class="text-danger">{{ $message }}</small> @enderror
                                                    <div class="mt-2">
                                                        <label class="mb-0" style="font-size:0.82rem">Motion mode</label>
                                                        <select wire:model="scenes.{{ $index }}.video_mode" class="form-control form-control-sm">
                                                            <option value="animate">🎬 Animate (AI motion)</option>
                                                            <option value="preserve">🖼️ Preserve (Ken Burns — keeps screenshots readable)</option>
                                                        </select>
                                                        @error('scenes.' . $index . '.video_mode') <small class="text-danger">{{ $message }}</small> @enderror
                                                    </div>
                                                </div>

                                                {{-- AI mode notice --}}
                                                @if(in_array($scene['input_mode'] ?? 'static', ['text','image','both']))
                                                    @if(($scene['video_mode'] ?? 'animate') === 'preserve')
                                                        <div class="col-12">
                                                            <div class="alert alert-info py-1 px-2 mb-2" style="font-size:0.82rem">
                                                                <i class="fas fa-image"></i>
                                                                <strong>Preserve mode</strong> — Ken Burns pan/zoom will be used. Screenshots and UI images will remain pixel-perfect (no AI hallucination).
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="col-12">
                                                            <div class="alert alert-warning py-1 px-2 mb-2" style="font-size:0.82rem">
                                                                <i class="fas fa-magic"></i>
                                                                <strong>AI generation</strong> — ComfyUI + AnimateDiff will render this scene (~30–90 s per clip).
                                                                Falls back to static if Vast.ai/ComfyUI is offline.
                                                                <em>Use <strong>Preserve</strong> mode for screenshots with text.</em>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif

                                                {{-- Reference images: file upload + URL list --}}
                                                @if(in_array($scene['input_mode'] ?? 'static', ['static','image','both']))
                                                    <div class="col-md-6 mb-3">
                                                        <label>
                                                            Image URLs
                                                            <small class="text-muted">(one per line — used in sequence)</small>
                                                            @if(in_array($scene['input_mode'] ?? 'static', ['image','both']))
                                                                <span class="badge badge-info ml-1">AI conditioning</span>
                                                            @endif
                                                        </label>
                                                        <textarea wire:model.defer="scenes.{{ $index }}.reference_image_urls_text"
                                                            rows="4" class="form-control" style="font-size:0.78rem"
                                                            placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg&#10;https://example.com/image3.jpg"></textarea>
                                                        @error('scenes.' . $index . '.reference_image_urls_text') <small class="text-danger">{{ $message }}</small> @enderror
                                                        <small class="text-muted">
                                                            Each URL becomes a separate sub-clip (duration split equally).
                                                            @if(in_array($scene['input_mode'] ?? 'static', ['image','both']))
                                                                Each image is used as the AnimateDiff starting frame.
                                                            @endif
                                                        </small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label>Upload image <small class="text-muted">(optional, added to URL list)</small></label>
                                                        <input type="file" wire:model="referenceImages.{{ $index }}" class="form-control" accept="image/*">
                                                        @error('referenceImages.' . $index) <small class="text-danger">{{ $message }}</small> @enderror
                                                        <small class="text-muted">Uploaded file is prepended to the image sequence.</small>
                                                    </div>
                                                @endif

                                                {{-- Scene prompt: visible for text/both/static --}}
                                                @if(in_array($scene['input_mode'] ?? 'static', ['static','text','both']))
                                                    <div class="{{ in_array($scene['input_mode'] ?? 'static', ['image']) ? 'col-md-12' : 'col-md-6' }} mb-3">
                                                        <label>Scene prompt
                                                            @if(in_array($scene['input_mode'] ?? 'static', ['text','both']))
                                                                <span class="badge badge-primary ml-1">AI prompt</span>
                                                            @endif
                                                        </label>
                                                        <textarea wire:model.defer="scenes.{{ $index }}.prompt" rows="3" class="form-control"
                                                            placeholder="{{ in_array($scene['input_mode'] ?? 'static', ['text','both']) ? 'modern SaaS marketing video, presenter explaining AI platform, cinematic lighting' : 'Doctor explaining the importance of annual blood tests in a bright clinic' }}"></textarea>
                                                        @error('scenes.' . $index . '.prompt') <small class="text-danger">{{ $message }}</small> @enderror
                                                    </div>
                                                @endif

                                                <div class="col-12 mb-3">
                                                    <label>Voice-over text</label>
                                                    <textarea wire:model.defer="scenes.{{ $index }}.voiceover_text" rows="3" class="form-control" placeholder="Welcome to our diagnostic clinic. We offer fast, accurate, and affordable screening packages."></textarea>
                                                    @error('scenes.' . $index . '.voiceover_text') <small class="text-danger">{{ $message }}</small> @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary" wire:click="resetComposer">
                                        Reset Builder
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Video Job
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5" wire:poll.10000ms="pollProcessingJobs">
                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clock mr-2"></i>Recent Jobs</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Video</th>
                                            <th>Status</th>
                                            <th>Duration</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->jobs as $job)
                                            <tr>
                                                <td>
                                                    <div class="font-weight-bold">{{ $job->title }}</div>
                                                    <small class="text-muted">{{ $job->organization?->name }} &middot; {{ $job->created_at?->diffForHumans() }}</small>
                                                    @if($job->output_video_url)
                                                        <div class="mt-1 d-flex align-items-center" style="gap:4px">
                                                            <button type="button"
                                                                class="btn btn-xs {{ $previewJobId === $job->id ? 'btn-success' : 'btn-outline-success' }}"
                                                                wire:click="setPreview({{ $previewJobId === $job->id ? 'null' : $job->id }})">
                                                                <i class="fas fa-play"></i> {{ $previewJobId === $job->id ? 'Hide' : 'Preview' }}
                                                            </button>
                                                            <a href="{{ $job->output_video_url }}" download
                                                                class="btn btn-xs btn-outline-primary">
                                                                <i class="fas fa-download"></i> Download
                                                            </a>
                                                            <a href="{{ $job->output_video_url }}" target="_blank"
                                                                class="btn btn-xs btn-outline-secondary">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                            <button type="button"
                                                                class="btn btn-xs btn-outline-danger"
                                                                wire:click="deleteJob({{ $job->id }})"
                                                                onclick="return confirm('Delete this video and free up disk space? This cannot be undone.')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $statusClass = match($job->status) {
                                                            'completed' => 'success',
                                                            'failed'    => 'danger',
                                                            'processing'=> 'warning',
                                                            default     => 'secondary',
                                                        };
                                                        $br       = $job->backend_response ?? [];
                                                        $progress = (int) ($br['progress'] ?? 0);
                                                        $scTotal  = (int) ($br['scenes_total'] ?? 0);
                                                        $scDone   = (int) ($br['current_scene'] ?? 0);
                                                        $scTitle  = (string) ($br['current_scene_title'] ?? '');
                                                        $startedAt = $br['started_at'] ?? null;
                                                    @endphp
                                                    <span class="badge bg-{{ $statusClass }}">{{ ucfirst($job->status) }}</span>

                                                    @if($job->status === 'processing')
                                                        <div class="mt-1" style="min-width:140px">
                                                            <div class="progress" style="height:8px;border-radius:4px">
                                                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                                                                    style="width:{{ $progress }}%"></div>
                                                            </div>
                                                            <small class="text-muted d-block mt-1" style="font-size:0.75rem">
                                                                {{ $progress }}%
                                                                @if($scTotal > 0)
                                                                    &mdash; Scene {{ $scDone }} / {{ $scTotal }}
                                                                @endif
                                                            </small>
                                                            @if($scTitle)
                                                                <small class="text-info d-block" style="font-size:0.72rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                                                    title="{{ $scTitle }}">{{ $scTitle }}</small>
                                                            @endif
                                                            @if($startedAt)
                                                                <small class="text-muted d-block" style="font-size:0.72rem">
                                                                    Started {{ \Carbon\Carbon::parse($startedAt)->diffForHumans() }}
                                                                </small>
                                                            @endif
                                                        </div>
                                                    @elseif($job->status === 'failed' && $job->error_message)
                                                        <div><small class="text-danger">{{ \Illuminate\Support\Str::limit($job->error_message, 80) }}</small></div>
                                                    @endif
                                                </td>
                                                <td>{{ $job->target_duration_seconds }}s</td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-xs btn-outline-primary" wire:click="refreshJobStatus({{ $job->id }})" title="Refresh status">
                                                        <i class="fas fa-sync"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-xs btn-outline-danger ml-1"
                                                        wire:click="deleteJob({{ $job->id }})"
                                                        onclick="return confirm('Delete this job record{{ $job->output_video_url ? ' and video file' : '' }}? This cannot be undone.')"
                                                        title="Delete job">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            @if($previewJobId === $job->id && $job->output_video_url)
                                            <tr>
                                                <td colspan="4" class="p-0">
                                                    <div class="bg-dark p-2">
                                                        <video controls autoplay style="width:100%;max-height:240px;border-radius:4px">
                                                            <source src="{{ $job->output_video_url }}" type="video/mp4">
                                                            Your browser does not support HTML5 video.
                                                        </video>
                                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                                            <small class="text-muted">{{ $job->output_video_url }}</small>
                                                            <a href="{{ $job->output_video_url }}" download class="btn btn-sm btn-success">
                                                                <i class="fas fa-download mr-1"></i>Download MP4
                                                            </a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endif
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">No video jobs submitted yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card card-outline card-info">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title"><i class="fas fa-server mr-2"></i>Stack Status</h3>
                            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="refreshComfyuiStatus">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body p-2">
                            @php
                                $comfyOk = !empty($comfyuiStatus['available']);
                                $statusIcon = $comfyOk ? 'check-circle text-success' : 'times-circle text-danger';
                            @endphp
                            <table class="table table-sm table-borderless mb-0" style="font-size:0.82rem">
                                <tbody>
                                    <tr>
                                        <td><i class="fas fa-{{ $statusIcon }}"></i> ComfyUI</td>
                                        <td class="text-muted">
                                            {{ $comfyOk ? 'Online — ' . ($comfyuiStatus['url'] ?? 'n/a') : 'Offline / not reachable' }}
                                        </td>
                                    </tr>
                                    @if($comfyOk)
                                        <tr>
                                            <td><i class="fas fa-check-circle text-success"></i> SD checkpoint</td>
                                            <td class="text-muted">{{ $comfyuiStatus['checkpoint'] ?? 'n/a' }}</td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-check-circle text-success"></i> Motion model</td>
                                            <td class="text-muted">{{ $comfyuiStatus['motion_model'] ?? 'n/a' }}</td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-check-circle text-success"></i> Upscale model</td>
                                            <td class="text-muted">{{ $comfyuiStatus['upscale_model'] ?? 'n/a' }}</td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-info-circle text-info"></i> Base output</td>
                                            <td class="text-muted">{{ $comfyuiStatus['frames'] ?? 16 }} frames &middot; {{ $comfyuiStatus['render_fps'] ?? 8 }} fps &middot; {{ $comfyuiStatus['base_width'] ?? 512 }}&times;{{ $comfyuiStatus['base_height'] ?? 512 }}px</td>
                                        </tr>
                                    @endif
                                    <tr><td><i class="fas fa-check-circle text-success"></i> TTS voice</td><td class="text-muted">XTTS / Indic (18082–18083)</td></tr>
                                    <tr><td><i class="fas fa-check-circle text-success"></i> FFmpeg stitch</td><td class="text-muted">Local v4.4</td></tr>
                                    <tr><td><i class="fas fa-check-circle text-success"></i> Ollama LLM</td><td class="text-muted">Vast.ai :11435</td></tr>
                                </tbody>
                            </table>
                            <hr class="my-2">
                            <ul class="mb-0 pl-3" style="font-size:0.8rem">
                                <li><strong>Static</strong> — loops your image or shows a text card instantly.</li>
                                <li><strong>Text → AI</strong> — AnimateDiff generates visuals from your prompt (~30–90 s/scene).</li>
                                <li><strong>Image → AI</strong> — AnimateDiff animates your reference photo.</li>
                                <li><strong>Text + Image → AI</strong> — Uses both as conditioning for richer output.</li>
                                <li>AI scenes gracefully fall back to Static if ComfyUI is offline.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
